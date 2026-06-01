# Tasks & Chores Branch Completion — Design

**Date:** 2026-06-01
**Branch:** `feature/tasks-chores-frontend` (PR #23, open)
**Goal:** Finish the remaining work on this branch so it is correct and mergeable, matching the original Tasks & Chores design.

## Context

PR #23 delivers the Tasks & Chores frontend on top of the merged backend (PR #21). The core CRUD, schedule logic, components, i18n, and tests are done. A multi-agent review found a small set of must-fix gaps before merge, plus one spec'd-but-unbuilt feature (section grouping). This document defines the completion scope.

The household runs in **Asia/Almaty (UTC+5)**. This matters for chore date math: `next_due_at` is stored at midnight UTC, and the frontend read-path is already correct for an east-of-UTC zone. The real defect is the **write path** — "today/now" for scheduling is computed in server UTC.

## Scope

Six work items. Each is independently testable. All implemented test-first (TDD).

### 1. Timezone-correct scheduling (configured app timezone)

**Problem:** `Chore::initializeNextDueAt()` (`backend/src/Entity/Chore.php:151`) uses `new \DateTimeImmutable('today')` and `ChoreService::markChoreDone` passes `new \DateTimeImmutable()` — both server UTC. A chore completed between 00:00–05:00 Almaty time resolves to the previous UTC calendar day, anchoring `next_due_at` one day early. The same applies to a chore created in those hours.

**Fix (Option A):**
- Add an application timezone: parameter `app.timezone` bound from env `APP_TIMEZONE`, **default `+05:00`** (fixed offset).
  - **Why a fixed offset, not `Asia/Almaty`:** Kazakhstan moved permanently to UTC+5 on 2024-03-01 and observes **no DST**, so a fixed `+05:00` is semantically exact. The named zone `Asia/Almaty` resolves to +05 *only* with tzdata ≥ 2024a; if the FrankenPHP container ships **stale tzdata it silently resolves to +06** and schedules every chore a day off. The fixed offset is immune to tzdata version. A named zone may still be supplied via `APP_TIMEZONE` if DST-aware behaviour is ever wanted.
  - **Guard:** a test asserts the configured timezone currently resolves to a `+05:00` offset (for a date after 2024-03-01), so a misconfigured/stale-tzdata environment **fails loudly** instead of scheduling wrong.
- Use Symfony `Symfony\Component\Clock\ClockInterface` for "now" so it is freezable in tests.
- Scheduling derives the **calendar date in the app timezone**, then anchors `next_due_at` at **midnight UTC of that date** — preserving the existing wire contract (`...T00:00:00+00:00`) that the frontend already reads correctly.
- `Chore::calculateNextDueAt` continues to receive a `\DateTimeImmutable`; the caller (`ChoreService`) is responsible for converting "now" to the app-timezone calendar date before passing it. Entity stays persistence-focused; timezone policy lives in the service.

**No schema change. No frontend change** (the read-path is already correct for east-of-UTC).

**Tests:** `ChoreServiceTest` (and/or `ChoreTest`) with a frozen clock at a UTC/Almaty day boundary, e.g. `2026-06-01T21:00:00Z` = `2026-06-02 02:00` Almaty, asserting `next_due_at` lands on the Almaty calendar day (`2026-06-02` + interval), not the UTC day.

### 2. Month-end overflow + per-type schedule_value validation

**Problem:** `Chore::nextMonthDay` (`backend/src/Entity/Chore.php:186`) relies on PHP date overflow: `fixed_monthly` day 31 done on Jan 31 lands on Mar 3, skipping February. `schedule_value` is only validated as `1..365` regardless of type (`CreateChoreRequest`/`UpdateChoreRequest`), so out-of-range weekly/monthly values silently corrupt scheduling.

**Fix:**
- `nextMonthDay`: clamp the target day to the month's last valid day — `min($targetDay, (int) $nextMonth->format('t'))` — so day 29–31 falls on the last day of short months instead of overflowing.
- Per-type validation via `#[Assert\Callback]` on `CreateChoreRequest` and `UpdateChoreRequest`:
  - `interval`: 1–365
  - `fixed_weekly`: 1–7
  - `fixed_monthly`: 1–28
  (Matches the original design's Validation Rules.)
- `frontend/src/features/tasks/components/ChoreForm.tsx`: monthly day input `max` → 28 (currently 31).

**Tests:** entity data-provider cases for Jan-31→Feb and "done on the 31st" repeat; controller test asserting 422 for out-of-range `schedule_value` per type.

### 3. Dashboard "mark done" button is dead

**Problem:** `frontend/src/features/dashboard/DashboardPage.tsx:212` renders a "Выполнено" button with no `onClick`.

**Fix:** wire it to `useMarkChoreDone()` (hook already exists in `api/queries/chores.ts`).

**Tests:** `DashboardPage.test.tsx` — clicking the button fires the mark-done mutation.

### 4. Nested interactive elements

**Problem:** `TaskCard.tsx` and `ChoreCard.tsx` render the whole card as a `<button>` containing the action `<button>` — invalid HTML; the test suite already prints the warning.

**Fix:**
- Outer card → `<div role="button" tabIndex={0}>` with `onClick` and `onKeyDown` handling **both Enter and Space**; inner action stays a real `<button>` with `stopPropagation`.
- `frontend/src/test/setup.ts`: fail tests on `console.error` so invalid-DOM regressions are caught going forward.

**Tests:** existing `TaskCard`/`ChoreCard` tests stay green with zero console errors; keyboard activation (Enter/Space) opens the card.

### 5. Overdue / Active / Completed sectioning

**Problem:** the original design (Frontend Display Rules) specifies both columns split into Overdue (red) / Active-Due / Completed (faded) sections. The build renders flat lists with only per-card red borders.

**Fix:**
- **Chores column:** Overdue (`next_due_at` < today) → Due/upcoming (sorted by due) → `[+ Add chore]`.
- **Tasks column:** Overdue (`due_date` < today, not done) → Active → Completed (faded, done within 3 days).
- Extract the bucketing into a **pure helper** (e.g. `groupChores`/`groupTasks`) so it is unit-testable and `TasksPage` does not grow further. "Today" is computed consistently with the card date logic (east-of-UTC-correct).
- Small presentational section sub-components/headers inside the tasks feature.

**Tests:** unit tests for the grouping helpers (overdue/active/completed bucketing; null `due_date` handling); `TasksPage.test.tsx` asserts section headers render with the correct items.

### 6. Verification gates

Run and confirm green before claiming completion:
- `cd frontend && bun run check && bun run test:run`
- `cd backend && make lint && make test` (Docker must be up; start it first)

Any red blocks merge.

## Out of Scope (deferred to post-merge cleanup from master)

- Dead-code removal: `data/mocks.ts` unused exports, `useProduct`, dead `TaskRepository::findActive`/`findCompletedRecently`, unused query keys.
- Broad i18n migration (products/shopping/settings/login hardcoded Russian).
- Real authentication, Settings backend, Telegram, Recipes — these are separate epics.
- Backend stock/shopping-list findings from the first review (idempotency, locking, indexes).
- Stale branch pruning.

## Risks

- Doctrine `DATETIME_IMMUTABLE` persistence assumes UTC; the service must construct the UTC-anchored datetime explicitly to avoid double-conversion. Covered by boundary tests.
- Failing tests on `console.error` may surface pre-existing warnings elsewhere; scope the rule to the test setup and fix what it catches within these components only.
