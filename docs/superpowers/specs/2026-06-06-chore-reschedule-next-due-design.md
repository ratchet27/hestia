# W2 — Editing a chore schedule must recompute `nextDueAt`

**Issue:** #56 (architecture-review, tech-debt) · **Severity:** Medium · **Effort:** S
**Area:** backend / chores · **Review ref:** `docs/reviews/2026-06-05-backend-architecture/README.md` § W2

## Problem

`ChoreService::updateChore` sets `scheduleType`/`scheduleValue` but never recomputes
`nextDueAt`. Changing a chore's schedule (e.g. "every 14 days" → "every 2 days", or
weekly Mon → Fri) leaves the next-due date stale until the next "mark done". Next-due is
the entire point of a chore (spec §13), so this is incorrect behavior on a core field.

`createChore` (→ `initializeNextDueAt`) and `markChoreDone` (→ `markDone`) both recompute;
only `updateChore` has the gap.

## Product decision

When the schedule (`scheduleType` **or** `scheduleValue`) changes on edit, recompute
`nextDueAt` from **`now`** — a clock restart. "Every 2 days" means due in 2 days from the
edit, regardless of completion history.

- **Anchor = `now`, not `lastDoneAt`.** Chosen over the cadence-preserving
  `lastDoneAt ?? now` alternative because anchoring to a long-past completion can make a
  chore *retroactively, instantly overdue* the moment you edit it (done 90 days ago,
  re-set to "every 2 days" → due 88 days ago). Clock-restart is the more intuitive result
  of an explicit edit and never produces a past-due date.
- **`lastDoneAt` is left untouched.** Completion history is preserved; only the forward
  schedule resets.
- **Editing only name/assignee leaves `nextDueAt` untouched.** Recompute happens *only*
  when the schedule actually changed — otherwise renaming a chore would silently reset its
  due date.

## Design

### 1. Entity — new `Chore::reschedule`

Add to `backend/src/Entity/Chore.php` (alongside `markDone`/`initializeNextDueAt`, which
already own the recurrence math):

```php
public function reschedule(ScheduleType $type, int $value, \DateTimeImmutable $now): static
{
    $this->scheduleType  = $type;
    $this->scheduleValue = $value;
    $this->nextDueAt     = $this->calculateNextDueAt($now);
    return $this;
}
```

Reuses the existing private `calculateNextDueAt`. Deliberately does **not** touch
`lastDoneAt`.

### 2. Service — `ChoreService::updateChore`

Compare the incoming schedule against the **currently persisted** values (read before any
overwrite), and only reschedule on a real change:

```php
public function updateChore(Uuid $id, UpdateChoreRequest $request): Chore
{
    $chore = $this->getChore($id);
    $chore->setName($request->name);
    $chore->setAssignee($request->assignee);

    $newType = ScheduleType::from($request->schedule_type);
    $scheduleChanged = $newType !== $chore->getScheduleType()
        || $request->schedule_value !== $chore->getScheduleValue();

    if ($scheduleChanged) {
        $chore->reschedule($newType, $request->schedule_value, $this->now());
    }

    $this->em->flush();

    return $chore;
}
```

No `else` branch: when the schedule is unchanged, type/value are already correct, so we
don't write them. `now()` already resolves the household timezone
(`ChoreService.php:92-95`).

### 3. Tests

**Entity (`tests/Unit/Entity/ChoreTest.php`):**
- `reschedule` recomputes `nextDueAt` from the given `$now` for INTERVAL, FIXED_WEEKLY,
  FIXED_MONTHLY (including month-length clamping — reuse the existing data-provider style).
- `reschedule` leaves `lastDoneAt` unchanged (set a `lastDoneAt`, reschedule, assert it
  is untouched).

**Service (`tests/Unit/Service/ChoreServiceTest.php`):**
- Editing the interval recomputes `nextDueAt` anchored to "now" (use `MockClock`,
  household-tz-anchored, matching the existing `markChoreDone` test).
- Editing only name/assignee (same schedule) leaves `nextDueAt` unchanged.
- A chore last done long ago, edited to a short interval, is due **from now** (not
  retroactively overdue) — pins the anchor decision.

## Scope

- **In scope:** `Chore::reschedule`; the `updateChore` guard; the tests above; reconcile
  spec §13 / the hard-evaluate items below.
- **Out of scope / do NOT:** a manual "next-due override" feature; changes to
  `create`/`markDone`; chore reminders (roadmap §18); the cadence-preserving anchor
  variants (rejected above); any API contract change.

## Hard-evaluate (don't trust, verify)

- The clamping comment in `Chore::nextMonthDay` (`:194-196`) still holds when reached via
  `reschedule` (same private method, so it does — confirm).
- No existing chore test asserts that `updateChore` leaves `nextDueAt` unchanged on a
  schedule change (that's the behavior being fixed). Update intentionally if found.
- Spec §13 ("mark done advances the next-due date") — note that editing the schedule also
  recomputes `nextDueAt`, anchored to now.

## Acceptance criteria

- Changing `scheduleType` or `scheduleValue` via update recomputes `nextDueAt` from now.
- Editing only name/assignee leaves `nextDueAt` unchanged.
- The recompute logic lives in the `Chore` entity (`reschedule`), not the service.
- `lastDoneAt` is unaffected by a reschedule.
- `make lint` + `make test` green, with the new unit tests.

## Verification

```bash
cd /home/pavel/projects/personal/hestia/backend
make lint && make test
```
