# Server-Authoritative Date Calculation

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal**: Remove client-side date calculation entirely. Server provides `days_until_expiry` in all stock-related responses, eliminating timezone mismatches.

**Architecture**: Backend calculates `days_until_expiry` for all stock entries. Frontend never calculates days from date strings - only displays what server provides.

**Tech Stack**: Symfony/PHP (backend), React/TypeScript (frontend)

---

## Task 1: Backend - Add `days_until_expiry` to StockEntryResponse

**Files:**
- Modify: `backend/src/Response/Stock/StockEntryResponse.php`

**Step 1: Add the field and calculation**

Add `days_until_expiry` field to the response class. Calculate it from `best_before` relative to server's "today".

```php
public ?int $days_until_expiry;
```

In the constructor or factory method, calculate:
```php
$this->days_until_expiry = $this->calculateDaysUntilExpiry($bestBefore);

private function calculateDaysUntilExpiry(?\DateTimeInterface $bestBefore): ?int
{
    if ($bestBefore === null) {
        return null;
    }

    $today = new \DateTimeImmutable('today');
    $diff = $today->diff($bestBefore);

    return $diff->invert ? -$diff->days : $diff->days;
}
```

> **Correction (2026-06-05, #53):** "today" MUST be resolved in the household timezone, not the
> server zone. Use `HouseholdCalendar::today()` (injected `ClockInterface` + `AppTimezone`),
> never a bare `new \DateTimeImmutable('today')`, which resolves in the UTC server zone and
> reintroduces the very off-by-one this plan set out to eliminate.

**Step 2: Run backend tests**

Run: `cd /home/pavel/projects/hestia/backend && make test`
Expected: All tests pass

**Step 3: Commit**

```bash
git add backend/src/Response/Stock/StockEntryResponse.php
git commit -s -m "feat(backend): add days_until_expiry to StockEntryResponse"
```

---

## Task 2: Frontend - Regenerate API types

**Step 1: Regenerate API types**

Run: `cd /home/pavel/projects/hestia/frontend && NODE_TLS_REJECT_UNAUTHORIZED=0 bun run generate-api`

**Step 2: Verify the new field exists**

Check that `frontend/src/api/generated/models/stockEntryResponse.ts` now includes `days_until_expiry`.

**Step 3: Commit**

```bash
git add frontend/src/api/generated/
git commit -s -m "chore(frontend): regenerate API types with days_until_expiry"
```

---

## Task 3: Frontend - Use server-provided days in StockRow

**Files:**
- Modify: `frontend/src/features/stock/components/StockRow.tsx`

**Step 1: Remove getDaysUntil function**

Delete the entire `getDaysUntil` function (lines 16-30).

**Step 2: Use entry.days_until_expiry**

Replace:
```tsx
const days = getDaysUntil(entry.best_before);
```

With:
```tsx
const days = entry.days_until_expiry ?? Infinity;
```

**Step 3: Run frontend check and tests**

Run: `cd /home/pavel/projects/hestia/frontend && bun run check && bun run test:run`
Expected: All pass

**Step 4: Commit**

```bash
git add frontend/src/features/stock/components/StockRow.tsx
git commit -s -m "refactor(frontend): use server-provided days_until_expiry instead of client calculation"
```

---

## Task 4: Final verification

**Step 1: Run all backend tests**

Run: `cd /home/pavel/projects/hestia/backend && make test`
Expected: All tests pass

**Step 2: Run all frontend tests**

Run: `cd /home/pavel/projects/hestia/frontend && bun run test:run`
Expected: All tests pass

**Step 3: Manual test**

Verify that stock items display the same expiry status in both:
- AttentionCard (uses `days_until_expiry` from expiring endpoint)
- StockTable (now uses `days_until_expiry` from entries endpoint)

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1 | Add days_until_expiry to backend response | `StockEntryResponse.php` |
| 2 | Regenerate frontend API types | `api/generated/` |
| 3 | Use server field in StockRow | `StockRow.tsx` |
| 4 | Final verification | - |

## Rationale

- **Expiry dates are calendar days**, not timestamps
- **Server is single source of truth** for "what day is today"
- **Frontend never calculates** - only displays
- **Eliminates timezone bugs** permanently
