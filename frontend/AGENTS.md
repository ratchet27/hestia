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
bun run check     # biome check src && tsc --noEmit
bun run test:run  # vitest run — all tests must pass
```

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
