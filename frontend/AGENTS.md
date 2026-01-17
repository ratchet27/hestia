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

## Code Quality

Linting strictness: **5/10** (balanced)

- Enforce consistent formatting and imports
- Require explicit types for function signatures
- Allow reasonable type inference for variables
- No unused variables or imports
- No `any` without justification
- Prefer functional components and hooks
