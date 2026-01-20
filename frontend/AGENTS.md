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
- `bun test` for tests

## Code Quality (REQUIRED)

**You MUST run `bun run check` before claiming any work is complete.**

Use the `frontend-lint` skill for full workflow. Quick reference:

```bash
bun run check   # runs: biome check src && tsc --noEmit
```

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
