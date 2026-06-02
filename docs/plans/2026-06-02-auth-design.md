# Authentication v1 — Design

**Date:** 2026-06-02
**Status:** Approved (design) — pending implementation plan
**Scope:** First-class authentication for the Hestia household app (2 users, self-hosted).

## Goal

Replace the current mock auth scaffold with real, secure authentication: a Symfony
session-cookie login backing the React SPA, native username/password only, accounts
provisioned via CLI. Designed so Google/Telegram login can be added later without rework.

## Context — what exists today

**Frontend (≈80% scaffolded, all mock):**
- `src/features/auth/LoginPage.tsx` — form with hardcoded `pavel`/`password` and hardcoded RU error strings.
- `src/data/context.tsx` — `AuthContext` (`user`, `login`, `logout`), hydrated from `localStorage`.
- `src/App.tsx` — `ProtectedRoute` wraps every page; `AuthProvider` wraps the app.
- `src/components/UserProfile.tsx` — user display + logout button already in the sidebar.
- `src/api/client.ts` — `apiFetch` already sets `credentials: "include"` and stubs `getCsrfToken()` + `X-CSRF-Token`.
- `src/data/types.ts` — `User { id: number; name; username; email }` (note: `id` is `number`, backend uses UUID).

**Backend (greenfield for auth, but primed):**
- No `security-bundle`, no `User` entity, all `/api/internal/v1/*` routes open.
- `config/packages/framework.yaml` has `session: true`.
- `config/packages/nelmio_cors.yaml` allows credentials + the `Authorization` header.
- Doctrine entities use UUIDv7 primary keys (`Symfony\Component\Uid\Uuid`).
- PHP ≥ 8.4, Symfony 8.0, Doctrine ORM 3.6. No password-hasher component yet.
- `Chore.assignee` is a free-text string (max 100), not linked to any user.

## Decisions (locked)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Session transport | **Session cookie** (HttpOnly, Secure, SameSite=Lax) | Safest (no token in JS), simplest; frontend + backend already wired. Subdomains (`app.`/`api.` under one parent domain) are same-site, so cookies work; only different registrable domains would force `SameSite=None`, which we avoid. |
| Login method (v1) | **Username + password only** | Smallest, self-contained. Google/Telegram bolt on later as extra buttons on the same session. |
| Provisioning | **CLI console command** | Two fixed users; no public signup = no attack surface. |
| Roles | **Flat** — everyone gets `ROLE_USER` = full access | Two equal partners; no admin/member split (YAGNI). |
| `chore.assignee` | **Stays free-text** | Linking to `User` is a separate optional enhancement. |
| 2FA | **None** | Overkill for two people. |

## Architecture & request flow

Symfony `security-bundle` with a **stateful, session-backed firewall** over the whole API,
authenticating via **`json_login`** (JSON POST, not an HTML form — SPA-friendly).

- `POST /api/internal/v1/auth/login` `{username, password}` → authenticate, start session,
  set `HttpOnly; Secure; SameSite=Lax` cookie, return current user JSON.
- `GET /api/internal/v1/auth/me` → current user JSON, or `401` if no session. Frontend calls
  this once on load to rehydrate.
- `POST /api/internal/v1/auth/logout` → clear session.
- All `/api/internal/v1/*` **except** `auth/login` require authentication via `access_control`.
  Unauthenticated → **`401` JSON** (never an HTML redirect — the SPA owns routing).
- **CSRF**: cookie-based auth needs CSRF defense on state-changing requests. `SameSite=Lax`
  already blocks cross-site cookie POSTs; on top, use Symfony **stateless CSRF token** exposed
  to JS via a readable cookie and returned in `X-CSRF-Token` (the hook `apiFetch` already stubs).
- **Login throttling** (Symfony built-in) and **remember-me** (checkbox already in the UI) on.

## Backend components

- **`User` entity** — UUIDv7 PK; `username` (unique), `email` (unique), `name` (display),
  `password` (hashed), `roles` (default `["ROLE_USER"]`). Implements `UserInterface` +
  `PasswordAuthenticatedUserInterface`. One Doctrine migration.
- **`security.yaml`** — auto password hasher (argon/bcrypt), entity user provider keyed on
  `username`, firewall (`json_login` + `logout` + `remember_me` + `login_throttling`),
  `access_control` protecting the API.
- **Auth controller** — thin: `me` endpoint and login/logout response shaping; Symfony does the auth.
- **CLI commands** — `app:user:create` (prompt username/email/name/password, hash it) and
  `app:user:set-password`. No registration endpoint.

## Frontend wiring

- `AuthContext`: `login()` → real `POST auth/login`; `logout()` → `POST auth/logout`; remove
  `localStorage` fake-user hydration.
- **Bootstrap**: call `GET auth/me` once on load; loading gate while in flight; `200` → set user,
  `401` → logged-out. Replaces localStorage read.
- `LoginPage`: wire to the real mutation; replace hardcoded creds and RU strings with `auth.*`
  i18n keys (`ru.json` / `en.json`).
- `apiFetch`: implement `getCsrfToken()` (read the CSRF cookie) so `X-CSRF-Token` is sent on mutations.
- **Type fix**: `User.id: number` → `string` (UUID); align `User` shape with the `/me` payload
  (prefer Orval-generated type).
- `ProtectedRoute`, `UserProfile`, logout button — already present; work once context is real.

## Data flow

1. App loads → `GET /me` → `401` → render `LoginPage`.
2. Submit → `POST /login` → cookie + user JSON → context populated → redirect to dashboard.
3. Subsequent requests carry the cookie automatically; mutations also send `X-CSRF-Token`.
4. Session expired / cookie missing → API `401` → response interceptor clears context → `/login`.
5. Logout → `POST /logout` → context cleared → `/login`.

## Error handling

- **Bad credentials** → `401` → inline "неверное имя или пароль" (no field-level leak).
- **Throttled** → `429` → "слишком много попыток, попробуйте позже".
- **Unauthenticated API hit** → `401` JSON → global interceptor → redirect to login.
- **CSRF failure** → `403` → generic error + retry (shouldn't happen in normal use).
- **`/me` network error** (backend down) ≠ logged-out → show error state, do **not** bounce to login.

## Testing

**Backend (PHPUnit functional, Foundry `UserFactory`):**
- login success sets session + returns user; login failure → 401.
- protected route without session → 401; with session → 200.
- logout drops the session.
- login throttling triggers after N attempts.
- `app:user:create` creates a user with a hashed password (command test).

**Frontend (Vitest + MSW; handlers for `auth/login|logout|me`):**
- login form submits and populates context; bad creds show error.
- `ProtectedRoute` redirects when `/me` is 401.
- bootstrap hydrates user from `/me`.
- logout clears state.

## Out of scope (v1) — additive follow-ups, no rework

Google / Telegram login (designed-for, not built), 2FA, public registration, roles beyond
`ROLE_USER`, linking `chore.assignee` → `User`.
