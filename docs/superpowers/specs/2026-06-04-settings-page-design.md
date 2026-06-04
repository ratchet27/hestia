# Settings Page — Design

**Date:** 2026-06-04
**Status:** Approved design, pending implementation plan

## 1. Problem

The Settings page (`frontend/src/features/settings/SettingsPage.tsx`) was scaffolded
from a generic admin mockup. Only the language switcher works; everything else is
inert UI: a Profile form with hardcoded name/email, Telegram notification toggles
for features that don't exist, a "storage locations" list reading a **static mock**
(`data/types`) rather than the real `Location` API, and a Data block
(export / import / clear-all).

Several of those sections don't fit this system — a self-hosted, two-person
household whose guiding rule is *"if a feature makes daily use harder, it is wrong."*
This redesign makes Settings **minimal and honest**: only the things that genuinely
vary per household, all wired to real APIs.

## 2. Scope

**Ships:**
- **Язык** — existing `LanguageSwitcher`, unchanged.
- **Места хранения (Locations)** — real CRUD against the `Location` entity.
- **Категории (Categories)** — real CRUD against the `Category` entity.
- **Telegram** — read-only status + a real "send test message" button.

Sections render in that order. There is **no About/version section** in this rework
(see §8 — deferred); the current hardcoded `Hestia v0.1.0` footer is removed, not replaced.

**Removed:**
- Profile (name / email) — near-zero value for two known users.
- Fake Telegram notification checkboxes (toggled features that aren't built).
- The entire **Данные** block — export / import / clear-all. Backups belong at the
  DB level (`pg_dump`) for a Dockerized Postgres app; in-app JSON export/import is
  scope we don't need, and "clear all data" is a footgun.
- This page's dependency on the static `data/types` `locations` map (other pages'
  usage is out of scope and untouched).

## 3. Approach

**Reuse existing entities.** `Category` and `Location` are already seeded Doctrine
entities with repositories and `GET` endpoints; we add the missing write endpoints
and two small Telegram endpoints, then rewrite `SettingsPage` against the real API.

No new tables, no key-value settings store (Telegram stays `.env`-configured —
YAGNI), no bulk-reassign-on-delete (we use delete-only-when-empty).

## 4. Backend

All endpoints live under `/api/internal/v1`, behind the existing authenticated
firewall + double-submit CSRF, consistent with every other controller.

### 4.1 Usage count = the delete guard

An item is deletable only when nothing references it.

- **Location `usageCount`** = (# products whose `defaultLocation` is it)
  + (# stock entries in it).
- **Category `usageCount`** = (# products in it).

Computed via repository `count` queries. Surfaced on the list response so the UI
can show the number and disable Delete; also enforced server-side.

### 4.2 LocationController (extend)

| Method | Route | Behavior |
|--------|-------|----------|
| GET | `/locations` | list, each item now includes `usageCount` |
| POST | `/locations` | create; `name` non-blank, ≤100, unique |
| PATCH | `/locations/{uuid}` | rename; same validation |
| DELETE | `/locations/{uuid}` | `409` if `usageCount > 0`, else delete (`204`) |

### 4.3 CategoryController (extend)

Identical four operations; `usageCount` = product count in the category.

### 4.4 TelegramController (new)

| Method | Route | Behavior |
|--------|-------|----------|
| GET | `/telegram/status` | `{ configured: bool, dailySummaryTime: "HH:MM" }` |
| POST | `/telegram/test` | real **synchronous** send via existing `TelegramSender`; `200 { ok: true }` or `200 { ok: false, error }` |

- `configured` is derived from the bound `TELEGRAM_DSN` env (non-empty / not the
  unset default). **No secrets are ever returned.**
- `dailySummaryTime` is read from `TELEGRAM_DAILY_SUMMARY_TIME` env (read-only here).
- `/telegram/test` calls `TelegramSender::send(...)` directly inside try/catch so the
  caller gets immediate real-delivery feedback (it does **not** go through the async
  messenger path). The test button always performs a genuine send to the configured chat.

### 4.5 DTOs & errors

- Requests: `CreateLocationRequest{ name }`, `UpdateLocationRequest{ name }`, and the
  Category equivalents — readonly, `#[Assert\NotBlank]` + `#[Assert\Length(max: 100)]`,
  uniqueness via `#[UniqueEntity]`.
- Responses: extend `LocationResponse` / `CategoryResponse` with `usageCount: int`;
  new `TelegramStatusResponse{ configured, dailySummaryTime }`.
- Errors as `ApiProblem` (RFC 7807): `409` for delete-in-use and duplicate name,
  `422`/`400` for invalid payloads — matching existing controllers.

## 5. Frontend (`SettingsPage` rewrite)

Card-per-section, `max-w-2xl`, matching the current visual style (Tailwind).

- **Язык** — keep `LanguageSwitcher`.
- **Места хранения / Категории** — both render a shared **`ManagedList`** component
  (single purpose: manage a list of named entities with usage counts). Per item:
  name + "используется в N"; inline "+ Добавить" text input; inline rename; Delete
  **disabled when `usageCount > 0`** (tooltip explains why), with a confirm on delete.
  Backed by React Query mutations added to `api/queries/locations.ts` and
  `categories.ts` (create / update / delete → invalidate the list).
- **Telegram** — status line (Настроено ✓ / Не настроено), read-only daily-summary
  time, **[Отправить тест]** → `POST /telegram/test`; toast success/error from the
  `{ ok }` response. New `api/queries/telegram.ts` (`useTelegramStatus`,
  `useSendTelegramTest`).

The hardcoded `Hestia v0.1.0` footer is removed (no version shown — see §8).
The Orval client is regenerated after the backend endpoints exist
(`NODE_TLS_REJECT_UNAUTHORIZED=0 bun run generate-api`). All copy via ru/en i18n keys.

## 6. Testing

**Backend (functional):**
- Locations & Categories: create; rename; list includes correct `usageCount`;
  delete when empty → `204`; **delete in-use → `409`**; duplicate name → `409`.
- `GET /telegram/status` returns the documented shape (no secrets).

**No new test for `/telegram/test`'s send path** — already covered by existing
`TelegramSenderTest`, `SendDailyExpirySummaryHandlerTest`,
`ExpirySummaryBuilderTest`, `SendExpirySummaryCommandTest`. The test button is
verified manually (it performs a real send).

**Frontend (Vitest + MSW):**
- `ManagedList`: Delete disabled at `usageCount > 0`, enabled at `0`; add and rename
  happy paths.
- Telegram: status renders; clicking the test button shows the success and error
  toasts (MSW handler stands in for the HTTP response — this exercises UI reaction,
  not the real send). Respect the `console.error`-throws and
  `onUnhandledRequest: "error"` test infra.

## 7. Edge cases

- **Rename to an existing name** → `409` duplicate; toast surfaces it.
- **Stale UI** — if an item gained usage since the list loaded, the server-side
  `409` still prevents an unsafe delete; the UI refetches and re-disables.
- **Deleting the last category/location** — allowed if empty. Products require a
  category and default location, so creating a product simply requires at least one
  of each to exist; user can re-add. Acceptable.

## 8. Deferred & out of scope

**Deferred to a later phase:**
- **About / version display.** No version source exists today (no `package.json` /
  `composer.json` version fields, no git tags). When added, it will be derived from
  the codebases (git commit / tag at build time), and the front/back-drift question
  decided then. Not part of this rework.

**Out of scope (revisit only on concrete need):**
Per-user profiles, in-app Telegram credential editing, editable summary time,
notification on/off preferences, data export/import/backup UI.
