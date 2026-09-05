# Hestia — Full Codebase Audit

Date: 2026-09-05. Commit audited: `6560b3e` (master, clean tree). Read-only audit; no source file was modified.

Scope: `backend/src` (151 files, ~8.4k LOC), `backend/tests` (44 files, ~7.1k LOC), `backend/config`, `backend/migrations`, compose/Dockerfile/Caddy, `frontend/src` excluding `frontend/src/api/generated` (~9.2k hand-written LOC; 4.4k generated LOC excluded), CI workflows, `docs/` (~30k lines of Markdown). `vendor/`, `node_modules/`, `frontend/dist`, `config/reference.php` excluded.

---

## 1. Verdict

This is a small, deliberately scoped, well-tooled application whose core is better than average and whose edges are noticeably sloppier than its core.

- **Quality:** the backend domain core (stock FIFO, shopping-list reconciliation, chore recurrence, timezone handling, RFC 7807 error model) is deliberate, documented for *why*, and covered by real tests. All gates are green: rector, mago format/lint/analyze, phpstan level 6, 346 PHPUnit tests, biome, tsc, 162 vitest tests. That is rare for a hobby project.
- **Slop:** roughly 20% backend, 15% frontend, 55% docs. Backend slop is boilerplate (24 near-identical exception classes, 4 byte-identical Create/Update DTO pairs, three response-mapping styles, ~10 dead methods/classes, Flex skeleton residue). Frontend slop is copy-paste (11 modal shells, a dead pre-API data layer, three `getDaysUntil`, half-finished i18n migration). Docs slop is 27k lines of executed agent plans with stale paths and phantom files.
- **Security posture:** the application layer is sound (firewall covers every route, bound parameters everywhere, correct CSRF double-submit, opaque 500s, no secrets in history). The weakness is infra: the shipped compose publishes Postgres and RabbitMQ to the host with default passwords and the prod override does not change that, Messenger uses the PHP serializer, containers run as root. Nothing Critical.
- **Correctness bugs found:** hard-deleting a product with stock returns 500; malformed UUID query params return 500; a shopping item can be created with no name; archiving a product strands its auto shopping item; stock mutations do not refresh the shopping list in the UI.

Would I ship it? Yes, for its stated purpose (one household, self-hosted), after fixing the compose exposure. Would I hire the author? Yes. Rewrite? No. Delete about 15% of it and unify the rest.

---

## 2. Top 10 issues

| # | Sev | Issue | Location | Fix direction |
|---|-----|-------|----------|---------------|
| 1 | High | Postgres and RabbitMQ published to host with default creds; prod override inherits it | `backend/compose.yaml:62-63,78-80`, `compose.prod.yaml` | Remove `ports:` for database/rabbitmq from base compose (or bind 127.0.0.1 in override); make passwords `${VAR:?}` in prod |
| 2 | Medium | Messenger transport uses PHP serializer (`unserialize`) on a broker that #1 exposes | `backend/config/packages/messenger.yaml:5-12` | Set `serializer: messenger.transport.symfony_serializer` on `async` |
| 3 | Medium | Hard-delete of a product with stock entries → FK violation → 500 | `backend/src/Service/ProductService.php:160-171` | Add stock-count guard to `ProductInUseException` path; add test |
| 4 | Medium | `Uuid::fromString` on raw query params → 500 (`InvalidUuidException` exists, unused) | `StockController.php:94-100`, `ProductRepository.php:41` | `#[MapQueryString]` DTOs with `#[Assert\Uuid]` |
| 5 | Medium | Shopping item creatable with no name / nonexistent product silently accepted | `AddShoppingItemRequest.php`, `ShoppingListService.php:111-136` | Require one of `product_id`/`custom_name`; throw `ProductNotFoundException` |
| 6 | Medium | Archiving a product strands its AUTO shopping item forever | `ShoppingListService.php:38-41`, `ProductService.php:152-158` | Treat inactive as deficit 0; dispatch on `softDelete`/active flip |
| 7 | Medium | Stock mutations never invalidate shopping-list cache (5-min stale; page-level hack) | `frontend/src/api/queries/stocks.ts:52,69,92,103`, `ShoppingPage.tsx:28-33` | Invalidate `shoppingList.all` from stock/cook mutations; delete the effect |
| 8 | Medium | `messenger` container is permanently unhealthy (inherits Caddy healthcheck) | `backend/Dockerfile:39`, `compose.yaml:36-47` | Override `healthcheck` for the messenger service |
| 9 | Medium | Stale Renovate rule blocks all Symfony minor/major PRs after the 8.1 upgrade shipped | `renovate.json` "Hold Symfony at 8.0" + composer lockFileMaintenance rule | Delete both rules |
| 10 | Medium | Dead code cluster: `MapProduct`/`MapLocation`, `getStockSummaryForProduct`, `StockSummaryResponse`, `findByUuid`, `findActive`, `findCompletedRecently`, `Common/*Exception`, `data/mocks.ts`, `DataProvider`, 5 unused hooks | see appendix B-S1, B-S2, F-S1, F-S9 | Delete |

---

## 3. Security findings (all verified)

### High

**SEC-1. Database and RabbitMQ published to the host with default credentials, inherited by prod**
- Location: `backend/compose.yaml:12-13, 62-63, 69-70, 78-80`; `backend/compose.prod.yaml:1-11`
- Evidence:
  ```yaml
  DATABASE_URL: postgresql://${POSTGRES_USER:-app}:${POSTGRES_PASSWORD:-!ChangeMe!}@database:5432/...
  MESSENGER_TRANSPORT_DSN: amqp://${RABBITMQ_USER:-hestia}:${RABBITMQ_PASSWORD:-hestia}@rabbitmq:5672/%2f/messages
  ...
      ports:
        - "5432:5432"
  ...
      ports:
        - "5672:5672"
        - "15672:15672"
  ```
  `compose.prod.yaml` overrides only `build.target` and three env vars. README line 203 states the app is "Running in production for one household".
- Why it matters: `docker compose -f compose.yaml -f compose.prod.yaml up` on a VPS binds Postgres and the RabbitMQ AMQP + management UI on 0.0.0.0 with `app/!ChangeMe!` and `hestia/hestia` unless four env vars are set. That is full DB read/write and the ability to publish to the queue the worker consumes (see SEC-2).
- Fix: drop `ports:` for database/rabbitmq from `compose.yaml`; if needed for dev, add them in `compose.override.yaml` bound to `127.0.0.1`. Use `${POSTGRES_PASSWORD:?}` / `${RABBITMQ_PASSWORD:?}` in `compose.prod.yaml`.

### Medium

**SEC-2. Messenger consumes PHP-serialized messages from the broker**
- Location: `backend/config/packages/messenger.yaml:5-12`; `vendor/symfony/messenger/Transport/Serialization/PhpSerializer.php:211`
- Evidence: no `serializer:` key on the `async` transport, so the default PhpSerializer is used; its slow path ends in `unserialize($contents, ['allowed_classes' => true])`. Symfony 8.1 hardens the envelope decode (`allowed_classes => [Envelope::class]` fast path), but the inner message body is still native `unserialize`.
- Why it matters: only exploitable by a party who can publish to the exchange; SEC-1 makes that party "anyone who can reach port 5672". The worker runs as root (SEC-3).
- Fix: `serializer: messenger.transport.symfony_serializer` on the `async` transport (JSON). Keep the broker unexposed regardless.

### Low

**SEC-3. Containers run as root**
- Location: `backend/Dockerfile` (no `USER` directive in any stage); `backend/frankenphp/conf.d/20-app.prod.ini:2` `opcache.preload_user = root`; `ENV COMPOSER_ALLOW_SUPERUSER=1`.
- Why it matters: any code-execution bug yields uid 0 in the container and write access to mounted volumes.
- Fix: add a non-root user in the base stage, chown `/app/var` and `/data`, drop `preload_user`.

**SEC-4. Malformed UUID in query string → 500 + CRITICAL log**
- Location: `backend/src/Controller/Api/Internal/V1/StockController.php:94-100`; `backend/src/Repository/ProductRepository.php:41`
- Evidence:
  ```php
  $locationId = $request->query->get('location');
  $productId = $request->query->get('product');
  $data = $this->stockEntryService->getEntries(
      locationId: $locationId !== null ? Uuid::fromString($locationId) : null,
  ```
  `Uuid::fromString` throws `\InvalidArgumentException`, which `ApiExceptionListener` maps to `INTERNAL_SERVER_ERROR` and logs at `critical`. `App\Exception\Common\InvalidUuidException` (400) exists with zero callers. Route params are protected by `Requirement::UUID_V7`; query params are not.
- Fix: `#[MapQueryString]` DTOs with `#[Assert\Uuid]` (as `ExpiringStockQuery` already does).

**SEC-5. `login_throttling` and client-IP logging have no trusted-proxy configuration**
- Location: `backend/config/packages/security.yaml:32-33`; no `trusted_proxies` in `config/`, `.env*`, `compose*.yaml` (only in generated `reference.php`).
- Why it matters: behind any reverse proxy (typical for HTTPS self-hosting) all clients share the proxy IP; 25 bad attempts lock out the household for a minute and the logged IP is useless. Needs an explicit decision either way.
- Fix: document the supported topology; if proxied, set `framework.trusted_proxies` from env with `trusted_headers` limited to the proxy.

**SEC-6. Swagger UI and OpenAPI JSON served unauthenticated in prod**
- Location: `backend/config/packages/security.yaml:38` `- { path: ^/api/doc, roles: PUBLIC_ACCESS }`; `config/routes.yaml:20-30`.
- Why it matters: full route/DTO/validation enumeration pre-auth. Information disclosure only.
- Fix: require `ROLE_USER` or register the doc routes under `when@dev`.

**SEC-7. Passwords accepted as CLI options**
- Location: `backend/src/Command/CreateUserCommand.php:34,46`; `SetUserPasswordCommand.php:30-35,52`
- Evidence: `->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password (prompted if omitted)')` then `$input->getOption('password') ?? $io->askHidden('Password')`.
- Why it matters: plaintext in `ps`, docker exec audit, shell history. The hidden prompt already exists.
- Fix: remove the option or accept `--password-stdin`.

**SEC-8. No security headers / CSP anywhere**
- Location: `backend/frankenphp/Caddyfile:34-35` (only `Permissions-Policy`); `frontend/index.html` (no meta CSP).
- Why it matters: no dangerous sinks exist today (verified: zero `dangerouslySetInnerHTML`, `innerHTML`, `<Trans>`, `javascript:`, user-controlled `href`), so this is defence-in-depth against a future regression and clickjacking.
- Fix: `header` block in Caddy: CSP `script-src 'self'`, `frame-ancestors 'none'`, `nosniff`, `Referrer-Policy`.

**SEC-9. Documented single-origin deployment is not produced by the committed build**
- Location: `backend/Dockerfile:88` (copies backend only); `Caddyfile:27` `root /app/public` (no SPA); `frontend/src/api/client.ts:1` `BASE_URL = import.meta.env.VITE_API_BASE_URL || "https://localhost"`; `nelmio_cors.yaml:6` `allow_headers: ['Content-Type', 'Authorization']` (no `X-CSRF-Token`); `.env:40` CORS regex admits `http://`.
- Why it matters: the SameSite=Lax cookie + non-HttpOnly XSRF cookie design depends on same-origin. Nothing builds `frontend/dist` into the image, so a real deploy pushes toward cross-origin, where CORS rejects the CSRF header and the tempting fix is `SameSite=None`.
- Fix: add a frontend build stage and copy `dist` under `/app/public` with SPA fallback; default `VITE_API_BASE_URL` to relative.

**SEC-10. Unused `ajv` dev dependency carries 4 high `fast-uri` advisories**
- Location: `frontend/package.json:32` `"ajv": "^8.20.0"`; `bun audit` output: `fast-uri >=3.1.3 <3.1.6 … 4 vulnerabilities (4 high)`.
- Why it matters: `ajv` has zero imports in `frontend/src` or any config (left over from the removed ESLint setup). It never reaches the bundle, so the runtime exposure is nil; the audit exit code is the real cost. Downgraded from the subagent's High for that reason.
- Fix: remove `ajv`, run `bun install`, confirm `bun audit` is clean.

### Nit

**SEC-11. CSRF token not bound to session or rotated** — `CsrfDoubleSubmitSubscriber.php:47,63-76`. Cookie==header check, pre-existing cookie survives login/logout. Classic naive double-submit; only defeatable by a cookie-setting attacker (sibling subdomain, plain-HTTP MitM). Negligible for one host. Rotate on login/logout if touching this code anyway.

**SEC-12. Telegram test endpoint echoes raw `\Throwable` messages** — `TelegramController.php:67-68`. Verified that Symfony's `TelegramTransport` wraps HttpClient errors as `'Could not reach the remote Telegram server.'` and the DSN exception masks the token, so nothing leaks today. Map to fixed error codes to keep it that way.

**SEC-13. Two workflows keep checkout credentials; actions pinned by tag** — `backend-code-quality.yaml:22-23`, `frontend-code-quality.yaml:22-23` lack `persist-credentials: false` (the CI workflows have it). Permissions are already `contents: read`. Add the flag; consider SHA pins.

**SEC-14. LIKE metacharacters unescaped in product name filter** — `ProductRepository.php:37` `'%' . $filters['name'] . '%'`. Bound parameter, so no injection; `%`/`_` act as wildcards. Harmless here.

**SEC-15. `min_stock` / `default_expiry_days` have no upper bound; 32-bit column** — `CreateProductRequest.php:25-29`, `ProductForm.tsx:240-263`. Value above 2^31 → 500 instead of 422. Add `Assert\Range`.

### Checked and found fine
Firewall coverage of all 58 routes; json_login JSON-only (form-encoded login CSRF impossible); logout POST-only; `hash_equals` on CSRF; session cookie defaults (HttpOnly, Lax, secure auto); password hashing `auto`; `#[\SensitiveParameter]`; no secrets in git history (`git log --all -p` searched for tokens); `.env.local`/`.env.dev` gitignored and in `.dockerignore`; empty `APP_SECRET` fails at boot; `composer audit` (run in the php container) reports no advisories; all repositories use QueryBuilder with bound parameters; no native SQL, `exec`, `unserialize` in `src/`; `RequestLogListener` logs no headers/bodies; `RedactSecretsProcessor` masks Telegram tokens in message/context/extra; `ExpirySummaryBuilder` HTML-escapes before `parseMode('HTML')`; `ApiExceptionListener` returns opaque 500s; frontend has no token storage, no open redirect (fixed `/login` target with loop guards), route guard waits on `/auth/me`; `frontend/dist` untracked; lockfiles present and consistent (`bun install --frozen-lockfile` clean); Caddy hides `*.php`, admin API not published.

---

## 4. Slop and quality findings

### Backend — estimated slop ratio ~20%

The domain core is deliberate. Slop concentrates in: exception classes, request DTO pairs, response-mapping styles, dead methods, skeleton residue, "what" comments and setter tests. Removing the identified items cuts roughly 1,200-1,500 of 8,400 src lines and ~500 of 7,000 test lines with no behaviour change.

Key items (full list in Appendix, B-S*):
- Dead code: `ObjectMapper/Transform/MapProduct.php`, `MapLocation.php` (registered in `services.yaml:30-36`, zero `#[Map(transform:)]` references; a whole PR #90 was spent suppressing lint on them); `StockEntryService::getStockSummaryForProduct` (docblock claims "used by ProductController", zero callers) plus `StockSummaryResponse`; `ShoppingListItemRepository::findByUuid` (rename of `find`); `TaskRepository::findActive`/`findCompletedRecently`; `Exception/Common/InvalidUuidException`, `ValidationException`; `templates/base.html.twig` (Flex hello-world).
- 24 exception subclasses with drifting details: `code: 404` vs `Response::HTTP_NOT_FOUND`, `extraData: ['id' => …]` vs `['recipe_id' => …]`, `final` on some, three add a `message` key duplicating `title`. `INSUFFICIENT_STOCK` is 400 while `RECIPE_NOT_COOKABLE` (same failure class) is 409.
- 4 byte-identical Create/Update DTO pairs (Chore, Task, Category, Location; verified with `diff`), including a 25-line `#[Assert\Callback]` duplicated in the Chore pair. `RecipeService` already uses a single `SaveRecipeRequest`.
- Three response-mapping mechanisms (ObjectMapper / `fromEntity` / `toResponse()` in `CategoryController:106`, `LocationController:106`, `RecipeService:186`), plus property casing split (`createdAt` vs `created_at`) and mapping direction split (`#[Map(target:)]` on 5 entities vs `#[Map(source:)]` on `ProductResponse`). AGENTS.md already names this as "not yet converted".
- Duplicated resolve-category/location block in `ProductService` create/update (22 lines ×2); seed data duplicated between `SeedCommand.php:22-40` and `Story/AppStory.php:15-32`.
- ~25 comments/docblocks that restate the next line (`// Dispatch stock change event`, `/** Delete a stock entry. */`).
- `DateTimeImmutableNormalizer` reimplements Symfony's default RFC 3339 output.
- Setter round-trip tests (`UserTest::testSettersRoundTrip`, `TaskTest:87-117`, `ProductBriefResponseTest`) and mock-assertion tests that duplicate functional coverage; `CategoryServiceTest`/`LocationServiceTest` are ~430-line textual copies.
- Stale text: `AuthController.php:69` "added in a later task" (shipped); `ApiExceptionListener.php:66-69` comment about `MapQueryString` is wrong on two counts (a query DTO exists; default is 404 not 400); `security.yaml:13` `dev` firewall for a profiler that is not installed; `cache.yaml` is 100% comments.
- 5 migrations with empty `getDescription()`; a create+drop pair for the abandoned `stocks`/`stock_movements` design in the chain.

### Frontend — estimated slop ratio ~15%

Hand-written code is mostly purposeful (StockPage `ModalState` union, RecipeForm error mapping, `queryClient` error policy, strict test harness). Slop is in duplication and a dead legacy layer.

Key items (full list in Appendix, F-S*):
- Dead pre-API data layer: `data/context.tsx:18-22` `DataProvider` returns `<>{children}</>` yet is wrapped in `main.tsx` and `test/utils.tsx` and documented in AGENTS.md; `data/mocks.ts` (94 lines, zero importers); `data/types.ts` `StockEntry` + `locations` (only `mocks.ts` uses them); `data/types.ts:44` `getExpiryStatus` (dead; the live one is `stock/utils/expiryStatus.ts`).
- Three `getDaysUntil`: `data/types.ts:33`, `ChoreCard.tsx:10` (byte-identical body), `expiryStatus.ts` variant. `DashboardPage.tsx:8-12` imports from both modules.
- Modal shell copy-pasted 11 times across 7 files (`grep "fixed inset-0"` = 11); only `EditItemModal` handles Escape/backdrop; none set `role="dialog"`. Page header block rendered three times in `ProductsPage` (loading/error/ready) and `TasksPage`.
- Half-finished i18n: Cyrillic literal counts `ProductForm.tsx` 24, `ProductsPage.tsx` 21, `ShoppingPage.tsx` 6, `App.tsx:22`, `queryClient.ts:28`. Same event toasts `"Товар создан"` in one file and `t("products.created")` in another. 6 orphaned translation keys.
- Two ways to call the API inside `api/queries`: `tasks.ts`, `chores.ts` (4 raw `apiFetch` each) and `products.ts:29` hand-roll URLs and envelope interfaces while generated `getApiTasksList` etc. exist. AGENTS.md says the client is never hand-written.
- Unreachable `if (status === 201) … else throw` guards after `apiFetch` already throws (`products.ts:57-62`, `recipes.ts:26-31`, `stocks.ts:44-49`, `shoppingList.ts:29-34`) with 11 `data!` assertions; other modules do no check at all.
- 5 unused hooks (`useProduct`, `useDeleteProduct`, `useUpdateStockEntry`, `useDeleteStockEntry`, `useDeleteRecipe`), 3 unused query keys, unused `errorHandlers` in `test/mocks/handlers.ts:33-55` (which also encode the wrong 422 shape).
- `TaskForm`/`ChoreForm` share 55 identical lines of submit/delete-confirm; `handleDone` and `handleThrow` in `StockPage.tsx:111-134` are identical with a "For now" comment; unowned TODO in `Navigation.tsx:6` referring to a removed component.
- `vitest.config.ts:27` excludes `src/api/` from coverage with comment "generated by orval" — `client.ts`, `queryClient.ts`, `queries/*` are hand-written.
- Vacuous tests: `TaskForm.test.tsx:64-67` asserts `getAllByText("Удалить").length >= 1` after clicking Удалить (true before the click); `DashboardPage.test.tsx:111-128` titled "navigates…" asserts only that the button exists; positional `buttons[1]!` selectors.

### Docs — estimated slop ratio ~55%

`README.md`, the three top-level design docs, `docs/superpowers/specs/*`, and `docs/reviews/2026-06-05-backend-architecture/` are genuinely useful. The bulk of the folder is not:
- 27 executed step-by-step agent plans (~27.4k of ~30.5k Markdown lines) opening with `> **For Claude:** REQUIRED SUB-SKILL…`, containing 58 occurrences of the stale path `projects/hestia/` (repo is at `projects/personal/hestia`), and referencing ~20 source files that do not exist (e.g. `StockMovement.php`, `StockService.php`). Three docs describe the `Stock`/`StockMovement` model that was replaced two days later.
- `docs/references/frontenddraft.jsx` (1,312 lines) and `stock_page.jsx` (701 lines): orphaned pre-API prototypes with numeric ids and inline mock data, linked from nowhere.
- Stale status metadata: `docs/plans/2026-06-02-auth-design.md:4` "pending implementation plan", `docs/superpowers/specs/2026-06-04-recipes-design.md:4` "pre-implementation" (both shipped); `home_erp_specification.md:303` says Settings controls are placeholders (Locations/Categories CRUD and Telegram test are live); `features_overview.md` v1 must-ship list contradicts the spec on reminders, weekly summary, mobile UI.
- `docs/plans/2026-01-22-frontend-tests-design.md:24-25` lists LoginPage/TasksPage as excluded placeholders; both have real tests.
- `docs/plans/2026-02-06-tasks-chores-manual-test-plan.md:3` contains a dev credential (`pavel` / `password`) and 60 unchecked boxes all covered by automated tests.
- `README.md:15-25` screenshot placeholder comment linking three nonexistent PNGs; `docs/.gitkeep` in a 60-file directory.

### Infra / config

- `renovate.json` still holds Symfony at 8.0 and disables composer lockFileMaintenance "while symfony is held at 8.0"; `composer.json` is on `8.1.*`. Both rules now silently block Symfony updates. (Medium)
- `messenger` service inherits the image `HEALTHCHECK` (`curl http://localhost:2019/metrics`) but runs `bin/console messenger:consume`, so it is permanently unhealthy (`docker inspect` confirms `curl: (7) Failed to connect to localhost port 2019`). CI sidesteps it via a profile in `compose.ci.yaml`; prod `up --wait` would not. (Medium)
- `compose.prod.yaml:9-11` sets `MERCURE_PUBLISHER_JWT_KEY`/`MERCURE_SUBSCRIBER_JWT_KEY` from `CADDY_MERCURE_JWT_SECRET`; no mercure package in `composer.lock`, no `mercure` directive in the Caddyfile. A secret must be provisioned for nothing. (Low)
- `@types/bun: "latest"` — the only floating dependency in `package.json`. `infection/infection: ">=0.33.2"` — no upper bound. (Low)
- `.github/linters/zizmor.yaml` exists but `VALIDATE_GITHUB_ACTIONS_ZIZMOR: false`. (Low)
- Doctrine schema drift: `doctrine:schema:update --dump-sql` wants `DROP INDEX shopping_list_product_unique`. Root cause verified: `Version20260121080355.php:26` creates a **partial** unique index (`WHERE product_id IS NOT NULL`) that Doctrine mapping cannot express, so the DB and mapping will never agree. Not a missing migration, but `doctrine:schema:validate` will always fail; CI has that step disabled with `if: false` (skeleton leftover, `backend-ci.yaml:47-58`). (Low)
- `package.json:2` `"module": "index.ts"` points to a nonexistent file. (Nit)
- No coverage threshold in `vitest.config.ts` or `phpunit.dist.xml`; Infection is local-only by documented choice. (Low)

---

## 5. Architecture

### Actual shape

**Backend:** Controller → Service → Repository → Entity, cleanly. Zero `flush`/`persist` in controllers; zero `HttpFoundation` imports in services/repositories/entities. Controllers are `#[MapRequestPayload]` DTO in, `$this->json(['data' => …])` out. Business rules live in services, with three rich entities (`Chore`, `ShoppingListItem`, `Task::setDone`) and the rest anemic. One `flush()` per use-case; `wrapInTransaction` only in `RecipeService::update` and `::cook`. Cross-feature coupling is a single in-process message: `StockChangedMessage(productId)` dispatched from Stock/Product/Recipe services, handled **synchronously** (only `SendDailyExpirySummary` is routed to `async`) by `StockChangedHandler` → `ShoppingListService::handleStockChange`, which re-queries the live count. The only real async path is Scheduler → `SendDailyExpirySummary` → AMQP → Telegram. Time goes through `AppTimezone` + `HouseholdCalendar` except `ChoreService::now()`, which rebuilds the same thing.

```
HTTP ─> Controller ─DTO─> Service ─> Repository(QB) ─> Postgres
                            │ flush()
                            ├─ dispatch StockChangedMessage ─(sync bus)─> StockChangedHandler ─> ShoppingListService ─> flush()
                            └─ Response DTO (Map | fromEntity | built in service/controller)
Scheduler(MainSchedule, household tz) ─> SendDailyExpirySummary ─(AMQP)─> Handler ─> Telegram
```

**Frontend:** `QueryClientProvider > BrowserRouter > AuthProvider > DataProvider(no-op) > App`. Seven flat routes behind `ProtectedRoute`. One data layer: TanStack Query hooks in `api/queries/*` over Orval fetchers via the `apiFetch` mutator (double `.data.data` unwrap). `AuthProvider` is a hand-rolled `useEffect` fetch outside TanStack. Query keys centralised in `keys.ts` but inconsistent (`["tasks", status]` and `["tasks", id]` can collide; `products.detail` is `["product", id]`, outside `products.all`). Invalidation is per-domain `X.all`. Global error toast policy in `queryClient.ts`. Pages are the composition root for queries, mutations, modal state and toasts (`TasksPage.tsx` 373 LOC, 3 queries, 8 mutations, 4 modal booleans, 4 inline modal shells).

### Prior review (2026-06-05) status

C1, C2, W1-W5, M2, D1, D2 are fixed and verified in code. M1 (three mapping styles) is partially fixed: the policy is written in AGENTS.md, three sites remain unconverted and are listed there. Residuals: `ChoreService::now()` duplicates the C1 fix; controllers still own `toResponse()` + per-row `usageCount()`.

### Top 3 structural risks

1. **DB constraint violations only translated for Category/Location; elsewhere they are 500s from valid-looking input.** `ApiExceptionListener.php:77-81` maps every non-`ApiException`/non-HTTP throwable to an opaque 500. Reachable: hard-delete with stock (FK, no `ON DELETE`), duplicate `product_id` within a recipe payload (`recipe_ingredient_unique`), duplicate strings within `barcodes[]`, malformed UUID query params. Each produces a CRITICAL log and a generic toast.
2. **Read-side N+1 on every aggregate endpoint.** `StockEntryService::getStockSummary` (`:231-238`) is `1 + 2N` queries (`find` + `getLocationBreakdown` per product) and feeds the dashboard. `RecipeService::toResponse` does one COUNT per ingredient per recipe (a TODO acknowledges it). Category/location lists COUNT per row from the controller. Shopping and stock-entry list repositories never `JOIN … addSelect`, so `product`/`location` lazy-load per row. Fine at 20 products; the shape recurs in every new feature.
3. **The stock ↔ shopping-list invariant holds only on happy paths.** `consumeStock` (`StockEntryService.php:116-129`) is count → select → remove → flush with no lock or transaction; Doctrine's zero-row DELETE is silent, so two concurrent consumes of the last unit both report success. Deactivating a product neither dispatches reconciliation nor lets `handleStockChange` remove the auto item (it returns early on inactive), leaving phantom items. The sync `StockChangedHandler` swallows all `\Throwable` including EM-closing DB errors, with a comment written for the old async design.

### What to change first

Guard the four 500 paths (risk 1) with existing `ApiException` types and add the missing tests. Then collapse the aggregate queries (risk 2) to single grouped queries. Then wrap `consumeStock` in a transaction with a pessimistic lock and make inactive products reconcile to deficit 0 (risk 3).

---

## 6. Clean code — codebase-wide patterns to fix

1. **One lookup-or-404 idiom.** `?? throw new XNotFoundException($id)` (as `CategoryService:47`) everywhere; drop the 5-line `if` form and `ProductRepository::exists()`.
2. **One exception template.** Generic `NotFound(type, id)` / `Conflict(type, extra)` or a single style enforced by a factory: `Response::HTTP_*` constants, snake_case `extraData` keys, no `message` duplicating `title`, `final` on all. Settle `INSUFFICIENT_STOCK` vs `RECIPE_NOT_COOKABLE` on one status.
3. **One mapping direction and one mapping mechanism per case.** `#[Map(source: Entity::class)]` on the DTO (as `ProductResponse` does), remove `App\Response` imports from entities, convert the three `toResponse()` leftovers, camelCase DTO properties and let the name converter work.
4. **Merge identical Create/Update DTOs** into `Save*Request` (as Recipe does) unless fields genuinely differ.
5. **Move complexity suppressions from class scope to method scope** (`StockEntryService`, `ProductService`, `ShoppingListService`, `RecipeService`); configure `mixed-return-statement` off for `src/Repository` in `mago.toml` instead of 22 inline ignores.
6. **Foundry factories out of `src/`** into `tests/Factory` (the commented config in `zenstruck_foundry.yaml` already anticipates it); default `minStock => 0`, `defaultExpiryDays => null` so tests opt in to deficits.
7. **Frontend primitives:** one `Modal({title,onClose,children})` with Escape/backdrop/`role="dialog"`, one `PageHeader`, one `FormActions`. Render the header once and switch only the body.
8. **Generated client only.** Replace the 9 raw `apiFetch` calls in `tasks.ts`/`chores.ts`/`products.ts` with generated functions; delete the local envelope interfaces and `ManagedItem`; type form enums with generated `TaskPriority`/`ScheduleType` and remove the 4 casts.
9. **Query keys:** adopt `[...all, "list", params]` / `[...all, "detail", id]` everywhere; invalidate cross-domain effects (stock → shopping list) in the mutation hooks, not in page effects. Move success toasts into `onSuccess`.
10. **One date helper module** (`lib/dates.ts`) for `getDaysUntil`, `formatDate`, locale-aware short date; delete the two copies and hard-coded `ru-RU`.
11. **Finish or drop i18n.** Either migrate Products/Shopping/App/queryClient to `t()` and add plural forms for the 7 `{{count}}` keys, or remove the language switcher.
12. **Test assertions by role/name**, not by text count or positional index; use `vi.setSystemTime` in the four date-dependent suites.

---

## 7. What is good (do not break during cleanup)

- **Layering discipline is real**, not aspirational: no persistence in controllers, no HTTP in services, one flush per use-case, `wrapInTransaction` exactly where multi-step writes need it, post-commit dispatch documented in `RecipeService::cook`.
- **The C2 fix is right**: the message carries only an id, the handler re-queries, the bus is sync by omission, and `consumeAcrossLocations` documents why it does not dispatch.
- **`HouseholdCalendar` + `AppTimezone`** with the stale-tzdata fallback and tests that pin the UTC-vs-Almaty boundary.
- **DB constraint as the single authority for uniqueness**, translated to 409 in one place (`flushOrNameTaken`). Correct under concurrency, with a docblock explaining why.
- **Uniform RFC 7807 error model**: every domain error is an `ApiException`, `type` codes unique, 4xx not logged, `MapRequestPayload` validation unwrapped to one shape. Frontend normalises `errors[]→violations[]` once, in `client.ts:114-122`, with a comment pointing at the PHP class.
- **Strict tooling for a hobby project**: PHP 8.4, `strict_types`, phpstan 6 on src+tests, mago analyzer, Rector, Infection at 90% MSI with a documented scope, `failOnDeprecation`; Biome + `tsc` strict with `noUncheckedIndexedAccess`; zero `any`, zero `@ts-ignore`.
- **Frontend test harness is honest**: `console.error` throws, MSW `onUnhandledRequest: "error"`, no snapshots, `RecipeForm.test.tsx` mocks the real 422 body.
- **Logging design**: `RedactSecretsProcessor`, `RequestContextProcessor`, uid correlation, `RequestLogListener` logging only method/path/status/duration, with precise non-obvious reasoning in docblocks.
- **Optimistic delete with rollback** in `useDeleteShoppingItem`; `StockPage` `ModalState` discriminated union.
- **The prior architecture review document** is a model: stable IDs, `file:line` evidence, a "deliberately NOT recommended" section, and every item traceable to code today.
- **Scope discipline**: no CQRS, no multi-tenancy, no plugin system, and the docs say why.

---

## 8. Suggested order of work

**Now (half a day):**
1. Remove `ports:` for database/rabbitmq from `compose.yaml`; require passwords in `compose.prod.yaml`; set JSON serializer on the `async` transport; override the messenger healthcheck. Closes SEC-1, SEC-2, INF-2.
2. Delete the two stale Renovate rules. Remove `ajv`. Pin `@types/bun`.
3. Guard hard-delete with a stock count; add `Assert\Unique` on recipe ingredients and barcodes; `MapQueryString` DTOs for `location`/`product`/`category_id`; require `product_id` or `custom_name` on shopping items. Each with one functional test. Closes the four 500 paths and the nameless-item bug.

**Next (one to two days):**
4. Inactive product → reconcile to deficit 0 and dispatch on `softDelete`/active flip. Invalidate `shoppingList.all` from stock and cook mutations; delete the `ShoppingPage` effect.
5. Delete verified dead code (Appendix B-S1, B-S2, F-S1, F-S9, `base.html.twig`, `cache.yaml`, `dev` firewall) and the docs slop (executed plans, `docs/references/*.jsx`, manual test plan). Fix stale status lines and the spec's Settings line.
6. Collapse the aggregate queries: grouped location breakdown, bulk `IN` stock count for recipes, `addSelect` joins in list repositories, single grouped `usageCount`.

**Later (as touched):**
7. Exception template + `Save*Request` merges + mapping direction unification (Section 6, items 2-4).
8. Frontend `Modal`/`PageHeader`/`FormActions` primitives; finish i18n for Products/Shopping; generated client for tasks/chores; query-key normalisation.
9. `consumeStock` transaction + pessimistic lock; `HouseholdCalendar::now()` into `ChoreService`; Foundry factories to `tests/`.
10. Security headers in Caddy; frontend build stage in the Dockerfile so the documented single-origin deployment is real; non-root container user; trusted-proxies decision.

Reasoning: items 1-3 are cheap, close every High/Medium security item and every reachable 500; 4-6 remove the bugs users can hit and the dead weight that misleads the next reader; the rest is consistency work best done opportunistically per module.

---

## 9. Appendix

### A. Verified findings — full list

Format: ID · Severity · Area · Category · Location · Evidence · Why · Fix.

#### Security (see Section 3 for SEC-1 … SEC-15 in full)

#### Backend — slop

**B-S1 · Medium · backend · slop · Dead ObjectMapper transforms**
`backend/src/ObjectMapper/Transform/MapProduct.php`, `MapLocation.php`, `backend/config/services.yaml:30-36`. Evidence: `grep -rn "MapProduct\|MapLocation" src config` returns only the class files and their two service definitions; the only `#[Map(transform:)]` in `src/` is `MapCollection` in `ProductResponse`. Why: two classes, two DI entries, four lint suppressions and PR #90 support code nothing calls. Fix: delete both and the service entries.

**B-S2 · Medium · backend · slop · Dead public methods with misleading docblocks**
`StockEntryService.php:313-348` (`getStockSummaryForProduct`, docblock "used by ProductController"), `Response/Stock/StockSummaryResponse.php`, `ShoppingListItemRepository.php:86-92` (`findByUuid` = `find`), `TaskRepository.php:24-49` (`findActive`, `findCompletedRecently`), `Exception/Common/InvalidUuidException.php`, `ValidationException.php`. Evidence: grep across `src` and `tests` finds zero callers for each (verified). Why: `getStockSummaryForProduct` also duplicates the location-breakdown mapping and an in-PHP `MIN(bestBefore)` loop that would be wrong to copy. Fix: delete all; check why `find-unused-definitions` does not flag public methods.

**B-S3 · Medium · backend · slop · 24 near-identical exception subclasses with inconsistent details**
`Exception/ShoppingList/ShoppingListItemNotFoundException.php:18` `code: 404`; `Exception/Task/TaskNotFoundException.php:19` `code: Response::HTTP_NOT_FOUND`; `Exception/Recipe/RecipeNotFoundException.php:20` `extraData: ['recipe_id' => …]` vs `TaskNotFoundException.php:20` `['id' => …]`; 5 classes not `final`; `ProductInUseException`, `RecipeNotCookableException`, `InsufficientStockException` add a `'message'` key. Why: copy-paste without a template; every feature adds two more. Fix: generic `NotFound`/`Conflict` or one enforced style.

**B-S4 · Medium · backend · slop · Three entity→response mechanisms**
`CategoryController.php:106`, `LocationController.php:106`, `RecipeService.php:186` (`toResponse()`); five `fromEntity` DTOs in `Response/Stock`, `Response/ShoppingList`; ObjectMapper for Product/Chore/Task/Barcode/User. Casing split `createdAt` vs `created_at`. Why: nobody reading one module can predict the next. Fix: per AGENTS.md policy, convert the three leftovers; normalise casing.

**B-S5 · Low · backend · slop · Three lookup-or-404 idioms**
`CategoryService.php:47` `?? throw`; `ProductService.php:55-60` 5-line `if`; `BarcodeService.php:32-36` bespoke `exists()`. Fix: standardise on `?? throw`.

**B-S6 · Low · backend · slop · Duplicated category/location resolution in ProductService**
`ProductService.php:66-76` and `:109-119` — 22 identical lines; class carries `cyclomatic-complexity` and `kan-defect` suppressions instead of an `applyRequest()` helper. Fix: extract `resolveCategory()`, `resolveLocation()`, `applyRequest()`.

**B-S7 · Low · backend · slop · Identical Create*/Update* DTOs**
`Request/CreateChoreRequest.php` vs `UpdateChoreRequest.php`, `CreateTaskRequest.php` vs `UpdateTaskRequest.php` (verified byte-identical after class-name substitution), plus Category and Location pairs. The Chore pair duplicates a 25-line `#[Assert\Callback]`. Fix: `Save*Request` as Recipe does.

**B-S8 · Low · backend · slop · Seed data duplicated**
`Command/SeedCommand.php:22-40` and `Story/AppStory.php:15-32` both define `CATEGORIES`/`LOCATIONS`. Fix: one constant source.

**B-S9 · Low · backend · slop · Comments that restate the next line**
~25 sites: `StockEntryService.php:71,92,133,213,333`, `ShoppingListService.php:302,339,354`, `ProductService.php:176,180,191`, `StockEntryRepository.php:66,82,96,114`. E.g. `// Dispatch stock change event` above `$this->messageBus->dispatch(new StockChangedMessage(...))`. Fix: delete.

**B-S10 · Low · backend · slop · Stale placeholder text and skeleton cruft**
`AuthController.php:69` "The CsrfDoubleSubmitSubscriber (added in a later task)…"; `templates/base.html.twig` (Flex hello-world, zero references); `config/packages/cache.yaml` (100% comments); `security.yaml:13` `dev` firewall for `_profiler|_wdt` with no profiler bundle installed; `ApiExceptionListener.php:66-69` comment wrong on `MapQueryString` (a query DTO exists; default status is 404 not 400). Fix: fix/delete.

**B-S11 · Low · backend · slop · Redundant `DateTimeImmutableNormalizer`**
`Serializer/DateTimeImmutableNormalizer.php:9-26` emits `ATOM`, identical to Symfony's default `DateTimeNormalizer` RFC 3339 output. Fix: remove and rerun the functional suite.

**B-S12 · Low · tests · slop · Setter and mock-assertion tests**
`tests/Unit/Entity/UserTest.php:31-39` `testSettersRoundTrip`; `TaskTest.php:87-117`; `ProductBriefResponseTest.php:15-25`; `CategoryServiceTest.php:23-37`, `LocationServiceTest.php:40-55` assert `persist`/`flush` were called on a mock, duplicating functional coverage; the two service test files are ~430-line textual copies. Fix: delete setter tests; dedupe after service unification.

**B-S13 · Nit · backend · slop · Unused inverse-side collection management**
`Entity/Category.php:62-82`, `Location.php:62-82` `addProduct/removeProduct/getProducts`; `Chore.php:116-131` `setNextDueAt/setLastDoneAt` (tests/Foundry only); `Recipe.php:114-118` `removeIngredient`; `Product::setActive`, `Barcode::getProductId`. Fix: drop.

**B-S14 · Low · backend · slop · Inconsistent `now()` sources**
`Entity/Recipe.php:48`, `StockEntry.php:42`, `ShoppingListItem` use `new DatePoint()`; `Product`, `Barcode`, `Chore`, `Task`, `User` use `new \DateTimeImmutable()` (6 files); `ChoreService.php:100-103` `now()` rebuilds `HouseholdCalendar`. Fix: `DatePoint` everywhere; `HouseholdCalendar::now()` into `ChoreService`.

**B-S15 · Nit · backend · slop · Mechanical oddities**
`ProductController.php:155-166` duplicated 204 return; `StockController.php:386` refetches the just-updated entity; `StockEntryService.php:76` `(int)` cast on an int; `CsrfDoubleSubmitSubscriber.php:74` FQCN despite `use Cookie`; `ProductService.php:164` `$this->em->getRepository(RecipeIngredient::class)` bypasses the repository pattern. Fix: tidy.

**B-S16 · Low · backend · slop · 22 identical `@mago-ignore analysis:mixed-return-statement` in repositories**
Verified count: 22 of 59 suppressions. Fix: configure the rule off for `src/Repository` in `mago.toml`.

**B-S17 · Nit · backend · slop · Migration churn**
`Version20260118145001.php` creates `stocks`/`stock_movements`, `Version20260118204136.php` drops them 6h later; 5 migrations with `getDescription(): ''` and the generator header. Fix: fill descriptions; squash the pair while pre-1.0.

#### Backend — clean code / architecture

**B-A1 · Medium · backend · architecture · Hard-delete of a product with stock → 500**
`ProductService.php:160-171` guards only recipes; `Entity/StockEntry.php:25-26` `JoinColumn(nullable: false)` with no `onDelete`; `Version20260118201611.php:27` FK `NOT DEFERRABLE`; `Product.php:64-68` cascades only barcodes. `ApiExceptionListener` has no DBAL mapping (verified). `testDeleteProductHardDelete` (`ProductControllerTest.php:596`) uses a product without stock. Downgraded from the subagent's High: reachable via API, no data loss, frontend `useDeleteProduct` is unused. Fix: stock-count guard → `ProductInUseException`; test.

**B-A2 · Medium · backend · quality · Duplicates inside one payload hit DB unique indexes → 500**
`Request/SaveRecipeRequest.php:162-164` (`Assert\Count(min:1)`, `Assert\Valid`, no `Unique`) vs `recipe_ingredient_unique`; `ProductService.php:192-206` `syncBarcodes` checks DB and existing codes but not the new array against itself vs `UNIQ_…barcode`. Fix: `#[Assert\Unique]` on both; `array_unique` in `syncBarcodes`.

**B-A3 · Medium · backend · quality · Shopping item with no name / unknown product silently accepted**
`Request/AddShoppingItemRequest.php:11-23` (both `product_id` and `custom_name` nullable, no cross-field constraint); `ShoppingListService.php:111-136` `find()` result may be null and falls through to `setProduct(null)`, `setCustomName(null)`. Verified. Fix: `AtLeastOneOf`/callback; throw `ProductNotFoundException`.

**B-A4 · Medium · backend · architecture · Deactivating a product strands its AUTO item**
`ShoppingListService.php:38-41` returns early when `!isActive()` before `removeAutoItem`; `ProductService.php:152-158` `softDelete` never dispatches; `:144-147` `updateProduct` dispatches only on `minStock` change. `testNoActionForInactiveProduct` codifies it. Fix: inactive → deficit 0; dispatch on active flip.

**B-A5 · Medium · backend · quality · N+1 on aggregate endpoints**
`StockEntryService.php:231-238` (`find` + `getLocationBreakdown` per product → `1+2N`); `RecipeService.php:44-47,191-195` (COUNT per ingredient, TODO acknowledges); `CategoryController.php:106-113` (`usageCount` per row); `ShoppingListItemRepository.php:59-68`, `StockEntryRepository.php:118-129` (no `JOIN … addSelect`). Fix: grouped queries, `IN` bulk counts, `addSelect` joins.

**B-A6 · Low · backend · architecture · `consumeStock` is check-then-act with no lock**
`StockEntryService.php:116-129`: `countByProductAndLocation` → `findForFifoConsumption` → `remove` → `flush`, no transaction, no lock; zero-row DELETE is silent. Only `RecipeService` uses `wrapInTransaction` (verified). Fix: `wrapInTransaction` + `PESSIMISTIC_WRITE`; assert deleted count.

**B-A7 · Low · backend · architecture · Entities import presentation DTOs via `#[Map(target:)]`**
`Entity/Chore.php:9,20`, `Task.php`, `Category.php:8,22`, `Location.php`, `Barcode.php` vs `Response/Product/ProductResponse.php:85` `#[Map(source: Product::class)]`. Fix: DTO-side `source` everywhere.

**B-A8 · Low · backend · quality · Same failure class mapped to different status codes**
`InsufficientStockException` `code: 400`; `RecipeNotCookableException` `Response::HTTP_CONFLICT`. Fix: pick 409 for both.

**B-A9 · Low · backend · architecture · Sync reconciliation handler swallows all `\Throwable`**
`MessageHandler/StockChangedHandler.php:23-32`; comment "self-corrects on the next stock change" is false for the inactive case (B-A4); a DB error closes the EM mid-request while the client gets 200. Fix: narrow the catch to constraint violations; make auto-item writes idempotent.

**B-A10 · Low · backend · clean-code · Class-scope complexity suppressions**
`StockEntryService.php:32-33`, `ProductService.php:28-29`, `ShoppingListService.php:19`, `RecipeService.php:26`: `cyclomatic-complexity`/`kan-defect`/`halstead` at class scope exempt every future method. No method currently exceeds 47 LOC. Fix: move to method scope.

**B-A11 · Low · tests · architecture · Foundry factories and story in `src/`, container-discovered in prod**
`src/Factory/*.php` (11), `src/Story/AppStory.php`; `services.yaml:20-21` `App\: resource: '../src/'`; `zenstruck_foundry.yaml:10-13` has the `tests/Factory` config commented out. `zenstruck/foundry` is dev-only. Fix: move to `tests/Factory`.

**B-A12 · Low · tests · quality · Random factory defaults make stock tests seed-sensitive**
`src/Factory/ProductFactory.php:23-28` `minStock => numberBetween(0,10)`, `defaultExpiryDays => optional(0.3)`; `ChoreControllerTest.php:167` real clock `+30 days`; `TaskTest.php:62` `usleep(1000)`. Fix: `minStock => 0`, `defaultExpiryDays => null`; pin dates.

**B-A13 · Nit · backend · clean-code · Envelope `['data'=>…,'meta'=>['total'=>count(…)]]` hand-rolled ~20 times**
e.g. `StockController.php:60-63,102-105,149-152`. Fix: tiny helper.

#### Frontend — slop

**F-S1 · Medium · frontend · slop · Dead pre-API data layer** (downgraded from High: dead code, not a defect)
`data/context.tsx:18-22` `DataProvider` returns `<>{children}</>`, wrapped in `main.tsx:17` and `test/utils.tsx:38`; `data/mocks.ts:1-94` zero importers (verified); `data/types.ts:9-24` `StockEntry`, `locations` used only by `mocks.ts`; `data/types.ts:44` `getExpiryStatus` dead. Fix: delete; rename module to `auth/`.

**F-S2 · Medium · frontend · slop · Three `getDaysUntil`/expiry implementations** (downgraded from High)
`data/types.ts:33-52`, `ChoreCard.tsx:10-18` (byte-identical body, verified), `stock/utils/expiryStatus.ts:5-11`; `DashboardPage.tsx:8-12` imports from both. Fix: `lib/dates.ts`.

**F-S3 · Medium · frontend · slop · Modal shell ×11, page header ×11, input class ×21**
`grep "fixed inset-0"` = 11 across `CreateProductModal.tsx`, `RecipeForm.tsx`, `EditItemModal.tsx`, `AddStockModal.tsx`, `TasksPage.tsx` (×4), `ScanModal.tsx`, `ProductsPage.tsx` (×2). Only `EditItemModal.tsx:49-50` handles Escape (verified). Fix: `Modal` + `PageHeader`.

**F-S4 · Medium · frontend · slop · Header rendered three times for loading/error/ready**
`ProductsPage.tsx:81-113`, `TasksPage.tsx:137-165`. Fix: render header once.

**F-S5 · Medium · frontend · slop · Hardcoded Russian in an i18n'd app**
Cyrillic literal counts (verified): `ProductForm.tsx` 24, `ProductsPage.tsx` 21, `ShoppingPage.tsx` 6, `EditItemModal.tsx` 5, `ShoppingListItem.tsx` 3, `ProductSearchInput.tsx` 3, `App.tsx:22` "Загрузка...", `queryClient.ts:28` `toast.error("Произошла ошибка")`. `ProductForm` mixes `t("barcodes.*")` with literals. Fix: finish or drop.

**F-S6 · Low · frontend · slop · Six orphaned translation keys**
`common.delete`, `common.search`, `expiry.expired`, `scan.createProduct`, `scan.notFound`, `tasks.form.deleteConfirm` in `en.json`/`ru.json`. Fix: delete or add a key-usage check.

**F-S7 · Medium · frontend · slop · Two ways to call the API inside `api/queries`**
`tasks.ts:26,38,60,88`, `chores.ts:26,38,60,88`, `products.ts:29` raw `apiFetch<…>` with hand-written envelope interfaces; generated `getApiTasksList` etc. exist. Fix: use generated.

**F-S8 · Low · frontend · slop · Unreachable status guards after `apiFetch`**
`products.ts:57-62`, `recipes.ts:26-31`, `stocks.ts:44-49`, `shoppingList.ts:29-34` `if (status===201) return data!; throw new Error(...)` — `apiFetch` already throws on non-OK; `categories.ts`, `locations.ts`, `telegram.ts` do no check. 11 `data!` assertions. Fix: uniform `response.data.data`.

**F-S9 · Low · frontend · slop · Unused hooks, keys, fixtures**
`useProduct`, `useDeleteProduct` (`products.ts:39-51,96-104`), `useUpdateStockEntry`, `useDeleteStockEntry` (`stocks.ts:74-106`), `useDeleteRecipe` (`recipes.ts:61-71`); `queryKeys.tasks.detail`, `chores.detail`, `recipes.detail`; `errorHandlers` in `test/mocks/handlers.ts:33-55` (also encodes the wrong 422 shape). Fix: delete.

**F-S10 · Low · frontend · slop · Ingredients normalisation ×3, form defaults ×2**
`RecipesPage.tsx:42-44`, `RecipeForm.tsx:44-48,63-65` `Array.isArray(...) ? ... : Object.values(... ?? {})`. Fix: one helper.

**F-S11 · Nit · frontend · slop · Defensive checks on non-nullable generated types**
`ProductsPage.tsx:182-188` `product.barcodes && Array.isArray(product.barcodes) && product.barcodes.length > 0 && product.barcodes[0] && …`; `ProductForm.tsx:46`; `AddStockModal.tsx:43,55,65`; `TaskCard.tsx:71`. Fix: trust the types.

**F-S12 · Low · frontend · slop · `TaskForm`/`ChoreForm` share 55 identical lines**
`TaskForm.tsx:104-159`, `ChoreForm.tsx:165-220`; both wrap `onSubmit` in a no-op `onFormSubmit`. Fix: `FormActions`.

**F-S13 · Nit · frontend · slop · `handleDone` and `handleThrow` identical with "For now" comment**
`StockPage.tsx:111-134`. Fix: one handler or implement the distinction.

**F-S14 · Nit · frontend · slop · `ManagedItem` redeclares generated shape**
`ManagedList.tsx:4-8`. Fix: `Pick<LocationResponse,…>`.

**F-S15 · Low · tests · slop · Vacuous and positional assertions**
`DashboardPage.test.tsx:111-128` ("navigates…" asserts button exists); `TaskForm.test.tsx:64-67` `length >= 1` (verified: true before click); `TaskCard.test.tsx:76-87` `buttons[1]!`; `TasksPage.test.tsx:100-102` `addButtons[1]`; `ProductsPage.test.tsx:76` `querySelector(".animate-pulse")`; `TaskCard.test.tsx:18-34` three tests differing only in priority. Fix: role/name queries, `it.each`, `MemoryRouter` probe.

**F-S16 · Low · frontend · slop · Coverage excludes hand-written code by comment mistake**
`vitest.config.ts:27` `'src/api/', // generated by orval` (verified). Fix: exclude `src/api/generated/` only.

**F-S17 · Nit · frontend · slop · Restating comments and unowned TODO**
`ProductsPage.tsx:81` "Show skeleton only on initial load" above `if (productsLoading)`; `Navigation.tsx:6` TODO about a date/time display that does not exist. Fix: delete.

#### Frontend — clean code / architecture

**F-A1 · Medium · frontend · architecture · Stock mutations never invalidate the shopping list** (downgraded from High: page-level workaround exists)
`stocks.ts:52,69,92,103` invalidate only `stocks.all` (verified); `recipes.ts:84-85` cook invalidates recipes+stocks only; `staleTime` 5 min (`queryClient.ts:41`); `ShoppingPage.tsx:28-33` `// Refetch on every page visit` effect papers over it. Backend `StockChangedHandler` writes AUTO items on every stock change. Fix: invalidate `shoppingList.all` in the hooks; delete the effect.

**F-A2 · Medium · frontend · clean-code · `data/types.ts` mixes duplicate `User`, dead types, date utils**
`User` duplicates generated `UserResponse`; see F-S1/F-S2. Fix: use `UserResponse`; `lib/dates.ts`.

**F-A3 · Medium · frontend · architecture · Page owns four modal booleans plus eight handlers**
`TasksPage.tsx:49-132` (373 LOC). `StockPage.tsx:26-30` already shows the `ModalState` union pattern. Fix: `ChoresPanel`/`TasksPanel`; toasts into `onSuccess`.

**F-A4 · Low · frontend · quality · `useEffect` syncing props into form state**
`AddStockModal.tsx:52-65` redundant with `defaultValues:41-46`; `EditItemModal.tsx:22-27` clobbers unsaved edits on re-render. Fix: key the modal; move rule to `onChange`.

**F-A5 · Low · frontend · clean-code · Enum form fields typed `string`, cast at call site**
`TasksPage.tsx:58,71,93,110` `as "low"|"medium"|"high"`, `as "interval"|…`; `TaskForm.tsx:18`, `ChoreForm.tsx:17`. Generated `TaskPriority`/`ScheduleType` exist. Fix: use them.

**F-A6 · Low · frontend · clean-code · `CreateProductRequest` cast to `UpdateProductRequest`**
`ProductsPage.tsx:70`. Fix: mode prop or generic form.

**F-A7 · Low · frontend · quality · Count interpolations without plural forms; `{{days}}` misnamed**
`expiry.inDays`, `expiry.daysAgo`, `recipes.missingIngredients`, `tasks.chores.overdue`, `tasks.chores.daysLeft`, `tasks.chores.schedule.interval`, `stock.showAll` single-form; `stock.nextExpiry` uses `{{days}}`; `AttentionSection.nextExpiryDays` never passed. Fix: `_one/_few/_many/_other`; wire or delete.

**F-A8 · Low · frontend · quality · Date formatting hard-codes `ru-RU`**
`expiryStatus.ts:33`, `data/types.ts:30`, `TaskCard.tsx:25`. Fix: locale from `i18n.resolvedLanguage`.

**F-A9 · Low · frontend · quality · Quantity rendered as literal `1` in three places**
`StockRow.tsx:38`, `AttentionCard.tsx:37`, `DashboardPage.tsx:122`; undocumented one-entry-one-unit invariant. Fix: helper with comment.

**F-A10 · Low · frontend · architecture · Query key shapes inconsistent; one collision**
`keys.ts:45-49` `["tasks", status]` vs `["tasks", id]` (verified); `products.detail` is `["product", id]` outside `products.all`, forcing `products.ts:89` extra invalidation. Fix: `recipes` style everywhere.

**F-A11 · Low · frontend · architecture · `AuthProvider` reimplements a query outside TanStack**
`data/context.tsx:34-…` manual cancel flag, loading, error swallow. Fix: `useQuery(["auth","me"])`.

**F-A12 · Nit · frontend · quality · Tab/filter state in `useState` instead of URL**
`StockPage.tsx:34`, `ProductsPage.tsx:21-22`. Fix: `useSearchParams`.

**F-A13 · Low · frontend · quality · `ManagedList` rename can fire twice (Enter + blur)**
`ManagedList.tsx:37-41,58-59` (verified: no in-flight guard). Fix: pending flag or blur-only submit.

**F-A14 · Nit · frontend · quality · `ShoppingListItem` un-cleared `setTimeout(onDelete, 300)`**
`ShoppingListItem.tsx:22`. Fix: call immediately; optimistic update handles feedback.

**F-A15 · Nit · frontend · clean-code · `React.` used as ambient global in 47 places**
e.g. `App.tsx:16`. Fix: `import type { ReactElement }`.

**F-A16 · Low · tests · quality · Default MSW handlers cover half the endpoints; error fixtures wrong shape**
`test/mocks/handlers.ts:32-55` `violations[].propertyPath` vs real `errors[].property`. Fix: delete or fix; add default list handlers.

**F-A17 · Low · tests · quality · Wall-clock-dependent tests**
`ChoreCard.test.tsx:57` UTC arithmetic vs local-midnight `getDaysUntil`; `grouping.test.ts:8-13`; `TasksPage.test.tsx:122-128`; no `vi.setSystemTime` anywhere. Fix: fixed system time.

**F-A18 · Nit · frontend · quality · `ProductForm` server-error mapping is an effect on `submitError`**
`ProductForm.tsx:72` replays toast on reopen; `RecipeForm.tsx:135-145` handles the same in `catch`. Fix: `catch` pattern.

#### Docs

**D-1 · Medium · docs · slop · Executed agent plans kept verbatim** — `docs/plans/*.md`, `docs/superpowers/plans/*.md`, `frontend/docs/plans/*.md`: ~27.4k of ~30.5k lines; 10 files with `For Claude:` boilerplate; 58 occurrences of stale `projects/hestia/` path in 6 files (verified); ~20 phantom file references; three docs for the abandoned `Stock`/`StockMovement` design. Fix: keep `*-design.md`/`specs/`; delete or archive step lists; stamp "Implemented (PR #n)".

**D-2 · Medium · docs · slop · Orphaned JSX prototypes** — `docs/references/frontenddraft.jsx` (1,312 lines), `stock_page.jsx` (701 lines); mock data identical to dead `frontend/src/data/mocks.ts`. Fix: delete.

**D-3 · Low · docs · slop · Stale status lines** — `docs/plans/2026-06-02-auth-design.md:4` "pending implementation plan"; `docs/superpowers/specs/2026-06-04-recipes-design.md:4` "pre-implementation"; `2026-06-04-telegram-notifications-design.md:4`; `2026-06-04-settings-page-design.md:4` (verified for the first two). Fix: update.

**D-4 · Low · docs · slop · Spec says Settings controls are placeholders** — `docs/home_erp_specification.md:303` (verified). Fix: update §15.

**D-5 · Low · docs · slop · `features_overview.md` v1 list contradicts spec** — lines 26,30,35 (reminders, weekly summary, mobile UI) vs `home_erp_specification.md:275,287`, `README.md:205`. Fix: move to Postponed.

**D-6 · Low · docs · slop · Frontend test-design plans describe a codebase that no longer exists** — `docs/plans/2026-01-22-frontend-tests-design.md:24-25` lists LoginPage/TasksPage as excluded placeholders (verified); both have real tests. Overlaps `frontend/docs/plans/2026-01-21-frontend-testing-design.md`. Fix: collapse into AGENTS.md.

**D-7 · Low · docs · slop · Manual test plan with dev credential** — `docs/plans/2026-02-06-tasks-chores-manual-test-plan.md:3` `(login: pavel / password)` (verified); 60 unchecked boxes covered by automated tests. Fix: delete.

**D-8 · Nit · docs · slop · README screenshot placeholder; `docs/.gitkeep`** — `README.md:15-25` links three nonexistent PNGs. Fix: add or drop.

#### Infra / config

**INF-1 · Medium · infra · quality · Stale Renovate rules block Symfony updates** — `renovate.json` "Hold Symfony at 8.0…" (`enabled: false` for symfony minor/major) and composer `lockFileMaintenance` disable; `composer.json` on `8.1.*`, object-mapper in use (verified). Fix: delete both rules.

**INF-2 · Medium · infra · quality · `messenger` container permanently unhealthy** — `Dockerfile:39` `HEALTHCHECK … curl -f http://localhost:2019/metrics` inherited by `compose.yaml:36-47` messenger service running `bin/console messenger:consume`; `docker inspect` health log: `curl: (7) Failed to connect to localhost port 2019`. `compose.ci.yaml` hides it via profile. Fix: override `healthcheck` (e.g. `messenger:stats` or `disable: true`).

**INF-3 · Low · infra · quality · Mercure env vars without Mercure** — `compose.prod.yaml:9-11`; zero `mercure` matches in `composer.json`, `composer.lock`, `Caddyfile` (verified). Fix: remove.

**INF-4 · Low · frontend · quality · `@types/bun: "latest"`** — `package.json:16`. Fix: pin.

**INF-5 · Low · backend · quality · `infection/infection: ">=0.33.2"` unbounded** — `composer.json`. Fix: `^0.33.2`.

**INF-6 · Low · infra · quality · Dead `zizmor.yaml`** — `.github/linters/zizmor.yaml` present, `super-linter.yaml:56` `VALIDATE_GITHUB_ACTIONS_ZIZMOR: false`. Fix: enable or delete.

**INF-7 · Low · backend · quality · Doctrine schema always reports drift** — `doctrine:schema:update --dump-sql` → `DROP INDEX shopping_list_product_unique`; cause: `migrations/Version20260121080355.php:26` partial index `WHERE product_id IS NOT NULL` not expressible in `Entity/ShoppingListItem.php:16-18` mapping. `backend-ci.yaml:55-58` has `doctrine:schema:validate` disabled with `if: false` (skeleton leftover). Fix: document the known diff; enable `schema:validate --skip-sync` in CI (mapping check passes) and remove the three `if: false` skeleton steps.

**INF-8 · Low · tests · quality · No coverage thresholds** — `vitest.config.ts` (no `thresholds`), `phpunit.dist.xml`. Current frontend: 75.5% statements / 77.1% lines. Fix: set near baseline.

**INF-9 · Nit · frontend · slop · `"module": "index.ts"` points to nonexistent file** — `package.json:2`. Fix: remove.

**INF-10 · Nit · backend · slop · `base.html.twig` and skeleton config** — see B-S10.

### B. Dropped or downgraded findings

| Finding (source) | Action | Reason |
|---|---|---|
| Unused `ajv` → 4 high `fast-uri` advisories (dep scan: High; mechanical: High; FE security: Low) | Merged into SEC-10, **Low** | Dev-only, unused, never in the bundle; only the audit exit code is affected |
| Hard-delete FK 500 (BE clean: High) | **Medium** (B-A1) | Reachable but no data loss; frontend never calls hard delete |
| Dead data layer (FE slop: High) | **Medium** (F-S1) | Dead code, not a defect |
| Three `getDaysUntil` (FE slop: High) | **Medium** (F-S2) | Duplication; no divergent behaviour today |
| Stock→shopping invalidation (FE clean: High) | **Medium** (F-A1) | Page-level workaround covers the main route |
| Mercure env vars (dep scan: Medium) | **Low** (INF-3) | Misleading, harmless |
| `@types/bun: latest` (dep scan: Medium) | **Low** (INF-4) | Lockfile pins it in practice |
| Schema drift (mechanical: Medium) | **Low** (INF-7) | Root cause is a Doctrine partial-index limitation, not a missing migration |
| `composer audit` cannot reach packagist (mechanical: Low) | **Dropped** | Transient sandbox network outage. Re-run by the lead after the audit once network returned: `No security vulnerability advisories found.` |
| No CODEOWNERS / commitlint (mechanical: Nit) | **Dropped** | Solo project; not a defect |
| Telegram test endpoint leaks token (my own initial hypothesis) | **Downgraded to Nit** (SEC-12) | Verified in vendor: `TelegramTransport` wraps HttpClient errors with a fixed message; DSN exception masks the token |
| `AuthController.php:237` (BE slop location) | **Corrected to :69** | File is 71 lines |
| `TelegramController.php:50-52` (BE security location) | **Corrected to :67-68** | Line numbers off |
| FE clean "frontend test-design plans match reality" vs FE slop "plans stale" | **Kept FE slop version** (D-6) | Verified lines 24-25 list components that now have tests |

### C. Subagents

| Subagent | Model | Findings returned | Survived verification | Notes |
|---|---|---|---|---|
| Backend slop scan | fable | 18 | 17 (1 merged into B-S10) | 1 line number corrected |
| Frontend slop scan | fable | 28 | 26 (2 merged with FE clean-code duplicates) | 2 downgraded from High |
| Backend security review | fable | 10 | 10 | 1 line number corrected |
| Frontend security review | fable | 5 | 5 | 1 merged into SEC-10 |
| Dependency & config scan | sonnet | 8 | 7 (1 merged into SEC-10) | 2 downgraded; could not run `composer audit` (host PHP 7.4) |
| Backend clean code + architecture | fable | 17 | 17 | 1 downgraded from High |
| Frontend clean code + architecture | fable | 22 | 20 (2 merged with FE slop duplicates) | 1 downgraded from High |
| Mechanical checks | sonnet | 6 | 3 (1 merged, 2 dropped) | All gates green: rector, mago ×3, phpstan, 346 PHPUnit tests / 1365 assertions in 38s, biome, tsc, 162 vitest tests in 4s, build 482 kB. `composer audit` timed out during the run (no egress); lead re-ran it afterwards: clean |

Totals: 114 findings returned, 105 survived after deduplication (7 merged, 2 dropped), 8 downgraded, 3 locations corrected. Every Critical/High and every Medium was opened and confirmed by the lead in the source; Low and Nit were sampled (~40%). Lead-originated findings: INF-1 (Renovate), INF-2 (messenger healthcheck root cause), INF-7 (partial-index root cause).
