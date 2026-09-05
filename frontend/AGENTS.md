# Frontend Agent Guidelines

## Stack

- React SPA
- Bun (runtime, bundler, package manager)
- Vite (dev server, build)
- TypeScript

## Bun Commands

- `bun install` instead of npm/yarn/pnpm install
- `bun run <script>` instead of npm run
- `bunx <pkg>` instead of npx
- `bun run test:run` for tests (uses vitest, NOT bun's native test runner)

## Code Quality (REQUIRED)

**You MUST run BOTH before claiming any work is complete:**

```bash
bun run check:write  # biome check --write src (auto-fixes format + safe lint) && tsc --noEmit
bun run test:run     # vitest run — all tests must pass
```

`check:write` is the working gate: it fixes formatting/imports/safe-lint **and** type-checks in
one pass (the frontend analogue of backend `make lint`, which also auto-fixes). Don't run a
verify-only check and then format separately — that's two passes for one job. Plain `bun run check`
(no `:write`) is the verify-only, CI-parity command; use it to confirm a clean tree, not to iterate.

Use the `frontend-lint` skill for the full lint workflow.

Linting strictness: **5/10** (balanced)

- Enforce consistent formatting and imports
- Require explicit types for function signatures
- Allow reasonable type inference for variables
- No unused variables or imports
- No `any` without justification
- Prefer functional components and hooks

## API Client Generation (Orval)

To regenerate the API client from backend OpenAPI spec:

```bash
# Backend must be running first (https://localhost/api/doc.json)
NODE_TLS_REJECT_UNAUTHORIZED=0 bun run generate-api
```

**Note:** The `NODE_TLS_REJECT_UNAUTHORIZED=0` is required because the backend uses a self-signed certificate.

## API Client Contract (`src/api/client.ts`)

The generated Orval client and all manual calls go through the `apiFetch` mutator, which
**wraps every response as `{ data: <parsedBody>, status, headers }`**. So for an endpoint whose
JSON body is `{ data: X }` (the standard envelope), the value is at **`result.data.data`** — a
double unwrap. Get this depth wrong and you silently bind the wrong object.

- `apiFetch` sends `credentials: "include"` and attaches the `X-CSRF-Token` header from the
  `XSRF-TOKEN` cookie (`getCsrfToken`). Auth is a session cookie (no token in JS).
- On an unexpected `401` (not `/auth/me` or `/auth/login`) it hard-redirects to `/login`.
- **Hooks in `src/api/queries/` call the generated functions only** (no hand-written
  `apiFetch<Envelope>(url)` calls) and return `unwrap(await generatedCall())`. `unwrap`
  (`src/api/queries/unwrap.ts`) strips the double envelope and narrows the generated
  success|error union; `apiFetch` already threw for non-2xx, so no `if (status === 201)` guards.
- Query keys come from `src/api/queries/keys.ts`; every key extends its resource's `all` root,
  so `invalidateQueries({ queryKey: x.all })` always covers the whole resource.
- Dates: `src/lib/dates.ts` (`getDaysUntil`, locale-aware `formatShortDate`). Do not
  hand-roll `toLocaleDateString("ru-RU", …)` or day arithmetic in components.
- Tests that depend on "today" pin the clock: `vi.useFakeTimers({ toFake: ["Date"] })` +
  `vi.setSystemTime(...)`. Faking only `Date` keeps `waitFor`/`userEvent` timers real.

## Auth & dev server

Auth is a same-origin session cookie (`SameSite=Lax`). In dev the frontend is `http://localhost:5173`
and the API is `https://localhost` — **different "sites" under schemeful same-site**, which drops the
cookie. `vite.config.ts` proxies `/api` → `https://localhost` so the browser sees one origin; set
`VITE_API_BASE_URL` to the dev-server origin in a (gitignored) `.env.local`. Don't "fix" this by
loosening the cookie to `SameSite=None`.

## Testing

Vitest + MSW + Testing Library. Non-obvious infra (in `src/test/`):

- **`setup.ts` makes `console.error` throw** — any unexpected console error (incl. React act
  warnings, state-after-unmount) fails the test. Write effects/guards accordingly.
- **MSW runs with `onUnhandledRequest: "error"`** — every endpoint a component touches needs a
  handler. Default handlers live in `mocks/handlers.ts` (incl. a default `GET /auth/me → 401`);
  override per-test with `server.use(...)`.
- **`render` from `@/test/utils`** already wraps the app providers (i18n, QueryClient,
  `AuthProvider`, `DataProvider`, Router) — render the component directly, don't re-wrap.
- App default language is **Russian** (`react-i18next`); assert on the RU strings tests expect.
