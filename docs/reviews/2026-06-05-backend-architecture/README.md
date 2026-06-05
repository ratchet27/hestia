# Backend Architecture Review — Hestia (2026-06-05)

> **What this is.** A self-contained record of a backend architecture review of the
> Symfony/PHP backend, run as a three-way debate between (a) our code, (b) a
> reference book on clean architecture, and (c) engineering judgment refereeing
> both. It exists so a future agent (or human) can **cross-check what was found and
> why** without re-running the review or needing the external book.
>
> **Authoritative inputs were our own docs**, not the book and not generic DDD:
> `docs/home_erp_specification.md`, `docs/design_decisions_log.md`,
> `docs/features_overview.md`. Where those disagree with the book, the docs win.
> Where they disagreed with the code, that mismatch is itself a finding (see D1, D2).
>
> **The reference book is NOT vendored in this repo.** Its relevant arguments are
> embedded inline below (with chapter + section citations) precisely so this
> document stands alone.

---

## Verdict (TL;DR)

This reads like **serious, long-lived code, not a throwaway**. Tells: a coherent
RFC-7807 error model with a single global handler; rich entities exactly where
complexity justifies them (`Chore` scheduling, `Task` done/doneAt) and plain ones
where it doesn't; two-level validation; mutation testing; deliberate scope
discipline (no CQRS/ES/multi-tenancy); a thoughtful stale-tzdata guard in
`AppTimezone`; FIFO pushed into an indexed SQL query. The findings below are
**refinements, not a rewrite**.

What holds it back is **inconsistency about where logic lives and how ambient state
(time, async) is handled** — and one of those inconsistencies (C1) is an actual
daily bug.

Top 3 to push it further toward long-lived code:
1. **C1** — fix "today" (one household-calendar helper, used everywhere).
2. **C2** — make min-stock reconciliation synchronous + idempotent (drop the broker from a core path).
3. **W1–W4** — make "where does the logic live?" have a single answer.

---

## How to use this document

- Each finding has a stable **ID** (`C1`, `C2`, `W1`…`W5`, `M1`, `M2`, `D1`, `D2`).
- Each finding records: **Problem**, **Evidence** (`file:line`), **Effect**,
  **Three-way reasoning** (our code / the book / the ruling), and **fix direction**.
- The **executable** version of each finding (concrete steps, acceptance criteria,
  verification commands) lives in its tracked GitHub issue — see the
  [Issue index](#issue-index).
- Before "fixing" anything that looks odd, read
  [Deliberately NOT recommended](#deliberately-not-recommended-scope-defense)
  first — several apparent "smells" are intentional and correct for this project.

---

## Scope of the review

Backend only (`backend/src`, config, migrations). This is a **single-household**
tool (two adults). Per `design_decisions_log.md` and `features_overview.md`, the
following are **out of bounds regardless of how clean they'd be** and were NOT
recommended: CQRS infrastructure, event sourcing, multi-tenancy, generic
plugin/extensibility, external API surface. Runtime and UX complexity must stay
minimal; structural rigor is welcome **only where it protects a real rule**.

Toolchain confirmed in-repo: PHP 8.4 / Symfony, PostgreSQL + Doctrine ORM,
RabbitMQ via Symfony Messenger + Symfony Scheduler, FrankenPHP. Quality gate
`make lint` (Rector → Mago format/lint/analyze → PHPStan); tests PHPUnit + Foundry;
mutation testing via Infection (`infection.json5`).

---

## Method & sources

1. Read the three source-of-truth docs above; derived the real business rules.
2. Read the domain core directly: all 11 entities, `StockEntryService`,
   `ShoppingListService`, `ProductService`, `ChoreService`, `TaskService`, the
   error-handling stack (`ApiException`, `ApiProblem`, `ApiExceptionListener`),
   the event path (`StockChangedMessage`/`StockChangedHandler`/`messenger.yaml`),
   representative controllers, and `infection.json5`.
3. Compared against the reference book (below) and refereed.

**Reference book:** Adel Faizrakhmanov, *Architecture of Complex Web Applications*
(Russian; "ACWA"). Cloned at review time to
`https://github.com/adelf/acwa_book_ru` — **not committed here**. Chapters used:
4 (application layer), 5 (error handling), 6 (validation/value objects),
7 (events), 9 (domain layer). Chapters 10 (CQRS) / 11 (event sourcing) skimmed
only to confirm they are out of scope for us.

---

## The reference book, embedded (so you don't need the repo)

The author is deliberately **anti-dogmatic**: he walks a single example from naive
controller code toward more structure and stops at each step to ask "is this
project worth the cost?" He pre-concedes the "this is overkill" objection in every
chapter. So "the book says X" almost always means "weigh X," not "you must."

**Ch.4 — Application layer.** Extract an application layer (services, or
command+handler) out of controllers for exactly **two** reasons (§*Пара слов в
конце главы*): (1) multiple interfaces to the same actions (web, API, console,
bot), (2) controllers grown tangled with mixed HTTP + business logic. Pass data in
as **DTOs** (decouple from the HTTP request); the service loads the entity by id
itself rather than receiving it from the controller. Caveat: DTOs "aren't always
worth it" for simpler apps.

**Ch.5 — Error handling.** Prefer exceptions over result-codes for clean control
flow. Use **one** `BusinessException` (carrying a safe, user-facing message),
mapped centrally by a **global handler** to HTTP — 4xx shown-not-logged, 5xx
logged-without-leaking-internals. He raises Java-style **checked vs unchecked**
exceptions and then **disowns the idea** (§*Проверяемые и непроверяемые
исключения*: *"lofty matters that will in the end turn out to be useless"*), making
`BusinessException` unchecked. Guidance: **don't proliferate custom exceptions**
until a caller actually needs to distinguish them.

**Ch.6 — Validation / value objects.** **Two-level validation**: cheap
format/required/cross-field checks at the Web/API edge; business + DB-coupled checks
(existence, archived, uniqueness) **inside the service, which must not trust its
caller**. **Value objects** guarantee a value's correctness once — but their
constructor checks are **assertions that earlier validation happened**
(`InvalidArgumentException` ⊂ `LogicException`), **not** the user-facing validation
layer. VOs cost real money (ORM casting) — "is this project worth it?" He warns
against wrapping a primitive when a primitive suffices (the `Distance`-vs-`float`
aside) and **never** suggests wrapping a closed set (an enum).

**Ch.7 — Events.** Keep transactions tiny; defer **heavy** post-actions (sitemap
gen, external APIs) to **queues**; fire **explicit business events after a
successful commit**, with **ids (not entities) as payload** so listeners reload
fresh state. Events earn their keep "when the number of post-actions becomes
large" (§*Пара слов в конце главы*) — for one cheap reaction, the indirection isn't
required. Sharpest warning (§*Использование событий Eloquent*): avoid code that
"works correctly *sometimes, in some special situation*" — e.g. behaving
differently sync vs async.

**Ch.9 — Domain layer.** Rich entities + Data Mapper (Doctrine) are "nearly
obligatory" for genuinely **complex business logic**; **invariants must live in the
code, not on convention** (§*Инварианты сущностей*: *"systems where the code itself
doesn't let you do big stupid things are far more stable…"*). But the cost is
"colossal," Doctrine adds real complexity, and — crucially — *"if the application
is a simple CRUD with very little additional logic, a domain layer will be of
little use"* (§*Пара слов в конце главы*). He even reverses his Ch.5 advice for
rich domains and then concedes that, too, gets messy. Net: reserve rich domain for
the genuinely-hard parts.

---

## Business rules → where they live (the map)

| Rule (per docs) | Where it lives | Verdict |
|---|---|---|
| On-hand qty = count of entries (§8) | `StockEntryRepository::countByProduct*` | ✅ correct (SQL) |
| FIFO: best_before, then created_at (§8) | `StockEntryRepository::findForFifoConsumption` ORDER BY (`:36-38`) + index (`StockEntry.php:18`) | ✅ correct — rightly NOT in an entity |
| Cannot consume more than on-hand (§11) | `StockEntryService::consumeStock:124`, `consumeAcrossLocations:159` | ⚠️ convention-enforced; acceptable at this scale |
| Expiry "days until" / "expiring" (§8; core purpose) | 3 implementations, **2 timezone bases** | ❌ **C1** |
| Below-min → auto item; restock → remove (§12) | `StockChangedMessage` → handler → `ShoppingListService::handleStockChange` | ⚠️ logic right; transport wrong → **C2** |
| Auto item must not clobber a user's manual edit (§12) | `ShoppingListService` (scattered) | ⚠️ **W3** |
| Chore recurrence math (§13) | `Chore` entity `calculateNextDueAt/...` (`:162-203`) | ✅ exemplary (rich entity) |
| Editing a chore's schedule updates next-due (§13) | `ChoreService::updateChore` — **not recomputed** | ⚠️ **W2** |
| Task done ⇔ doneAt set (§13) | `Task::setDone` (`:99-103`) | ✅ correct (in the entity) |
| Product name unique (§7) | `#[UniqueEntity]` + DB + validator in `ProductService` | ✅ correct |
| Category/Location name unique & in-use guard | entity `#[UniqueEntity]`+DB **and** hand-rolled inline in the controller | ⚠️ **W1** |
| Correction audit / undo | not built | ✅ correctly deferred (roadmap §18) |

**Two takeaways.** (1) The team clearly *can* build rich entities — `Chore` and
`Task` put invariants exactly where the book wants them — so the anemic entities
elsewhere are a *choice*, mostly defensible. (2) The genuinely hard rules (FIFO,
counting) live in SQL where they belong (defended below).

---

## Findings

### Critical

#### C1 — Expiry "today" is computed in two timezones; the API answer is wrong ~5h/day
- **Problem.** "Today" is computed two ways. Correct path:
  `ExpirySummaryBuilder::today()` uses `clock->now()->setTimezone(AppTimezone)`
  (household tz). Wrong path: `StockEntryResponse::calculateDaysUntilExpiry` and
  `StockEntryService::getExpiringEntries` use raw `new \DateTimeImmutable('today')`
  (server tz).
- **Evidence.** `frankenphp/conf.d/10-app.ini:2` → `date.timezone = UTC`;
  `.env:48` → `APP_TIMEZONE=+05:00`;
  `src/Response/Stock/StockEntryResponse.php:30`;
  `src/Service/StockEntryService.php:311` and `:76` (write-side suggestion);
  `src/Repository/StockEntryRepository.php:137` (`findExpiring()` — raw, uninjected `new \DateTimeImmutable()`, same root cause, also untestable; fixed in #53);
  correct path: `src/Service/Telegram/ExpirySummaryBuilder.php:65-68,71-77`;
  the rule: `docs/plans/2026-01-21-server-authoritative-dates.md`.
- **Effect.** Between 00:00–05:00 Asia/Almaty, UTC is still "yesterday," so the
  API's `days_until_expiry` is **one day too high**; the SPA disagrees with the
  Telegram summary for the same item. This is the app's core metric (§1) and the
  exact bug the design doc claimed to have eliminated.
- **Reasoning.** *Our code:* one correct path, two wrong ones reading the system
  clock in UTC. *Book (Ch.6/Ch.8):* isolate time so logic is deterministic and
  testable — a Response DTO calling the clock in its constructor is hidden,
  untestable I/O. *Ruling:* book + our own doc are right; the abstraction
  (`AppTimezone`) already exists and just isn't used here.
- **Fix direction.** Introduce one `HouseholdCalendar` (clock + `AppTimezone`)
  exposing `today()`/`daysUntil()`; use it in `StockEntryService`,
  `ExpirySummaryBuilder`, and `addStock`; remove date math from the DTO. → GitHub issue.

#### C2 — Min-stock → shopping-list reconciliation: async on one path, sync on another, off a stale snapshot, through a broker it doesn't need
- **Problem.** The "below-min → add, restock → remove" rule is a real decoupled
  event (good), but (1) it's routed to the **async** RabbitMQ transport for a
  one-query reaction; (2) the *same* method is called **synchronously** from the
  product-edit path; (3) the handler trusts a **quantity snapshot**
  (`message.newQty`) and tests run it via `in-memory://` (≈sync).
- **Evidence.** Dispatch: `src/Service/StockEntryService.php:97,142,171,223`;
  routing: `config/packages/messenger.yaml:14-16` (both → `async`), `:18-23`
  (test `in-memory`); handler: `src/MessageHandler/StockChangedHandler.php`;
  reconciliation: `src/Service/ShoppingListService.php:35,45`; sync call:
  `src/Service/ProductService.php:145-147`.
- **Effect.** After consuming the last unit, the shopping list updates only once a
  worker drains the queue — and **if no consumer is running, it silently never
  updates** (core feature, self-hosted box). Under retry/reorder, a stale `newQty`
  can re-create a phantom deficit. Tests exercise a path that doesn't exist in prod.
- **Reasoning.** *Our code:* good decoupling, wrong transport, duplicated by a sync
  call, validated only on the sync-equivalent path. *Book (Ch.7):* queues are for
  **heavy** post-actions; events pay off when post-actions are **many** (here:
  one); and it explicitly warns against code that "works sometimes" (sync vs
  async). It *does* bless firing after commit (we do). *Ruling:* keep the event
  decoupling, drop the broker hop, make the reaction self-correcting.
- **Fix direction.** Route `StockChangedMessage` to `sync` (keep
  `SendDailyExpirySummary` async — real external I/O); have `handleStockChange`
  **re-query** current count instead of trusting the message. → GitHub issue.

### Worth doing

#### W1 — Category & Location: business logic in the controller, duplicating an invariant already on the entity
- **Problem.** Unlike the 7 other domains, `CategoryController`/`LocationController`
  do CRUD inline (name check, in-use guard, `persist`/`flush`), and
  `assertNameAvailable` re-implements the entity's `#[UniqueEntity]` with a racy
  `findOneBy` — so a concurrent create yields an unmapped 500 instead of a clean 409.
- **Evidence.** `src/Controller/Api/Internal/V1/CategoryController.php:71,97-103,118-131,140,143-148`;
  mirror in `LocationController.php:139-149`; entity rules
  `src/Entity/Category.php:20,28`, `src/Entity/Location.php:20,28`; contrast the
  validator-based path `src/Service/ProductService.php:88-91,133-136`.
- **Effect.** Name-uniqueness now lives in 3 places; one of them is racy; the logic
  is outside the service convention and outside Infection's scope (see W4).
- **Reasoning.** *Our code:* inline + duplicated check. *Book (Ch.4):* by its own
  two-reasons test, tiny CRUD doesn't *require* a service — so the book half-tolerates
  this. *Ruling — against the book here:* the stronger ground is **consistency**;
  every other domain has a service reached from console/scheduler/handlers, so the
  convention is real and these two break it.
- **Fix direction.** Extract minimal `CategoryService`/`LocationService`; enforce
  uniqueness via `validator->validate()` (consume the entity's `#[UniqueEntity]`),
  matching `ProductService`. → GitHub issue.

#### W2 — Editing a chore's schedule leaves `nextDueAt` stale
- **Problem.** `ChoreService::updateChore` sets `scheduleType`/`scheduleValue` but
  never recomputes `nextDueAt`.
- **Evidence.** `src/Service/ChoreService.php:62-73` (no recompute); contrast `:54`
  (`createChore`→`initializeNextDueAt`), `:85` (`markDone`); math in
  `src/Entity/Chore.php:149-203`.
- **Effect.** Change "every 14 days" → "every 2 days" and next-due doesn't move
  until the next mark-done. Next-due is the whole point of a chore (§13).
- **Reasoning.** *Book (Ch.9):* recurrence is a `Chore` invariant; every change that
  affects it must preserve it — and the math already lives in the entity, correctly.
  *Ruling:* one-line gap in an otherwise exemplary rich entity.
- **Fix direction.** Add `Chore::reschedule(type, value, now)` that recomputes
  `nextDueAt` (most natural: from `lastDoneAt ?? now`); service calls it. → GitHub issue.

#### W3 — "User edit converts an auto item to manual" rule is scattered; it belongs on `ShoppingListItem`
- **Problem.** The rule "auto-reconciliation must never overwrite a user-owned item"
  is spread across three service spots; the entity is a pure data bag, so the rule
  can't be enforced in one place.
- **Evidence.** `src/Service/ShoppingListService.php:68` (skip non-AUTO), `:122-124`
  (merge→MANUAL), `:162-168` (edit→MANUAL); anemic `src/Entity/ShoppingListItem.php`.
- **Effect.** A future caller setting `amount` without flipping source would let the
  next stock event silently revert the user's change.
- **Reasoning.** *Book (Ch.9, Инварианты сущностей):* invariants in the object, not
  in caller discipline. *Ruling:* real rule, real failure mode — pull it onto the
  entity, but stop there (do NOT build a rich aggregate; see scope defense).
- **Fix direction.** `ShoppingListItem::reviseAmount(int)` that sets amount and, if
  source was AUTO, marks MANUAL; service calls it. → GitHub issue.

#### W4 — Mutation testing only covers `src/Service`, so the most complex pure logic is uncovered
- **Problem.** Infection is scoped to `src/Service`, missing the `Chore` scheduling
  math (entity) and the Category/Location controller logic (W1).
- **Evidence.** `infection.json5:3-7`; uncovered `src/Entity/Chore.php:162-203`.
  (Good practice noted: reasoned `@infection-ignore-all` at
  `ShoppingListService.php:44`, `StockEntryService.php:232,141`.)
- **Effect.** The intricate month-clamping recurrence math isn't pinned by mutation
  testing.
- **Reasoning.** *Book (Ch.8):* pure logic exists to be cheaply, exhaustively
  verified; Infection is the proof the tests bite. *Ruling:* the boundary smell and
  the coverage gap are the same smell.
- **Fix direction.** Add `src/Entity` to Infection `directories`; W1 pulls
  Category/Location in automatically. → GitHub issue.

#### W5 — `addStock` quantity is unbounded, and one unit = one row
- **Problem.** `AddStockRequest.quantity` has only `#[Assert\Positive]`; with the
  row-per-unit model, `quantity: 100000` is 100k INSERTs (and a 100k-row FIFO later).
- **Evidence.** `src/Request/AddStockRequest.php:20-21`;
  `src/Service/StockEntryService.php:84-91`.
- **Effect.** A fat-finger is a self-inflicted DoS on a home server.
- **Reasoning.** *Book (Ch.6):* cheap sanity checks belong at the edge. *Ruling:*
  low severity, but pointed given the discrete-entry design.
- **Fix direction.** Add a sane `#[Assert\LessThanOrEqual(...)]` cap. → GitHub issue.

### Minor

#### M1 — Entity→response mapping done three inconsistent ways
- **Problem/Evidence.** Symfony ObjectMapper `#[Map]` on some entities; `fromEntity()`
  on `ShoppingItemResponse`; inline in `StockController.php:178-181` and
  `ShoppingListController`. Some services return entities the controller maps (mild
  entity leak to the controller).
- **Effect.** Consistency only — no rule violated. Pick one default.
- **Ruling.** Low priority; unify when convenient. → GitHub issue.

#### M2 — Duplicate exception classes share a `type` string at different HTTP statuses
- **Problem/Evidence.** `Exception/Product/CategoryNotFoundException` (type
  `CATEGORY_NOT_FOUND`, code **400**) vs `Exception/Category/CategoryNotFoundException`
  (same type, code **404**); same pattern for Location. The two situations are
  legitimately different (bad foreign key in a payload vs missing addressed
  resource), so two classes is fine — but the shared `type` blinds an SPA that keys
  off `type`.
- **Ruling.** Give them distinct `type` codes (e.g. `INVALID_CATEGORY_REFERENCE` vs
  `CATEGORY_NOT_FOUND`). Trivial. → GitHub issue.

### Documentation mismatches

#### D1 — Spec describes recipes as unbuilt, but the backend shipped
- **Evidence.** `home_erp_specification.md` §12 ("recipe source not wired"), §15
  ("Recipes page is a placeholder on mock data"), §18 (roadmap) vs a complete
  `src/Service/RecipeService.php` (create/update/`cook` with consumption /
  `addMissingToShoppingList` writing `source = RECIPE`); shipped in commit `c6db660`.
- **Ruling.** Update §12/§15/§18 to reflect the built feature. → GitHub issue.

#### D2 — Hosting topology: decisions log contradicts the (authoritative) spec
- **Evidence.** `design_decisions_log.md §4` ("Two subdomains: ui + api / shared
  cookie domain") vs `home_erp_specification.md §4` (production is single-origin;
  multi-subdomain is a §18 future item).
- **Ruling.** The spec is the "as-built" source of truth; fix/annotate the
  decisions-log entry. → GitHub issue.

---

## Deliberately NOT recommended (scope defense)

**Read this before "fixing" anything that looks like a smell.** The following are
intentional and correct for a two-person household tool; changing them would add
cost without protecting a real rule.

1. **No rich `Stock` aggregate.** Stock is a *count of rows*; the real operations
   are FIFO-order-and-limit and counting — inherently SQL. An in-memory aggregate
   would hydrate every entry to consume one. Book Ch.9's own caveat ("simple CRUD →
   domain layer of little use") applies. Keep FIFO + counting in the repository; keep
   the non-negative guard in the service.
2. **No checked/business-vs-system exception hierarchy.** `ApiException extends
   RuntimeException` + `ApiProblem` + global `ApiExceptionListener` (logs ≥500 only)
   **is** where Ch.5 lands after disowning the checked/unchecked idea. Do not
   "upgrade" to a per-error checked hierarchy — the book walks that back itself.
3. **No CQRS read model / no event sourcing.** Reads return Response DTOs and don't
   leak write-path command objects; that's enough. Forbidden by our scope docs.
4. **No new value objects.** No VO over-application exists today (and that's good);
   enums are correctly native enums, not pseudo-VOs. The simple positivity/uniqueness
   invariants are already covered by `#[Assert\*]` + DB constraints. Book Ch.6's cost
   test says no for this project.
5. **Validation placement already matches Ch.6** (edge format checks on DTOs;
   DB-coupled checks in services). No change needed.

---

## Issue index

Tracked as GitHub issues on `ratchet27/hestia`. Links filled in as each is filed.

| ID | Title | Severity | Effort | Issue |
|----|-------|----------|--------|-------|
| C1 | Expiry "today" computed in two timezones → API off by a day | Critical | S | [#53](https://github.com/ratchet27/hestia/issues/53) |
| C2 | Min-stock reconciliation: async/sync split, stale snapshot, needless broker | Critical | S–M | [#54](https://github.com/ratchet27/hestia/issues/54) |
| W1 | Category/Location logic in controllers; uniqueness duplicates entity | High | S | [#55](https://github.com/ratchet27/hestia/issues/55) |
| W2 | Editing a chore's schedule doesn't recompute next-due | Medium | S | [#56](https://github.com/ratchet27/hestia/issues/56) |
| W3 | Auto→manual shopping-item rule scattered; move onto entity | Medium | S | [#58](https://github.com/ratchet27/hestia/issues/58) |
| W4 | Mutation testing scoped to `src/Service` only | Medium | S | [#57](https://github.com/ratchet27/hestia/issues/57) |
| W5 | `addStock` quantity unbounded (row-per-unit mass insert) | Low | S | [#59](https://github.com/ratchet27/hestia/issues/59) |
| M1 | Entity→response mapping done three inconsistent ways | Minor | S–M | [#60](https://github.com/ratchet27/hestia/issues/60) |
| M2 | Duplicate exception classes share a `type` at different statuses | Minor | S | [#61](https://github.com/ratchet27/hestia/issues/61) |
| D1 | Spec says recipes unbuilt, but backend shipped | Doc | S | [#62](https://github.com/ratchet27/hestia/issues/62) |
| D2 | Hosting topology: decisions log contradicts spec | Doc | S | [#63](https://github.com/ratchet27/hestia/issues/63) |

---

## Appendix — review provenance

- Date: 2026-06-05. Backend branch: `master`.
- Reference book (not vendored): `github.com/adelf/acwa_book_ru`, chapters 4–7, 9.
- This document is the durable record; the executable detail lives in the linked
  GitHub issues. If code drifts from the `file:line` evidence above, re-verify
  before acting — line numbers are a snapshot.
