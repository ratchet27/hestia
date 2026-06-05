# Design — C1: household-timezone day math for stock expiry

**Issue:** [#53](https://github.com/ratchet27/hestia/issues/53) · **Review ref:** `docs/reviews/2026-06-05-backend-architecture/README.md` § C1
**Severity:** Critical · **Effort:** S · **Area:** backend / stock + expiry

## Problem

"Today" is computed two different ways in the backend:

- **Correct path** — `ExpirySummaryBuilder` (Telegram daily summary) reads an injected
  `ClockInterface` and converts to the household zone via `AppTimezone`.
- **Wrong paths** — the SPA-facing stock code computes "today" from the raw system clock,
  which is pinned to **UTC** (`frankenphp/conf.d/10-app.ini` → `date.timezone = UTC`) while
  the household is **+05:00** (`.env` → `APP_TIMEZONE=+05:00`).

Between **00:00–05:00 Asia/Almaty** (i.e. 19:00–24:00 UTC the previous day) UTC is still
"yesterday", so every `days_until_expiry` the API returns is **one day too high**. The SPA
then disagrees with the Telegram summary about the *same* item ("expires tomorrow" vs
"сегодня"). Expiry awareness is the app's core purpose (spec §1), and this is exactly the
bug `docs/plans/2026-01-21-server-authoritative-dates.md` claimed to have eliminated.

## Wrong paths (verified against source)

1. `src/Response/Stock/StockEntryResponse.php:30` — `new \DateTimeImmutable('today')` inside
   the DTO constructor (a hidden clock read; untestable across a day boundary).
2. `src/Service/StockEntryService.php:311` — `new \DateTimeImmutable('today')` in
   `getExpiringEntries` (per-entry delta).
3. `src/Service/StockEntryService.php:76` — `new \DateTimeImmutable()->modify('+N days')`,
   the write-side `best_before` suggestion in `addStock`.
4. **(found during design; not in the issue's evidence list)**
   `src/Repository/StockEntryRepository.php:137` — `findExpiring()` computes its window from a
   raw, *uninjected* `new \DateTimeImmutable()`. Same root-cause tz error on the window
   boundary, and because it is not injected it cannot be controlled by `mockTime` → untestable.
   A repository reading the clock is the ambient-I/O smell ACWA Ch.6/8 argues against.

Correct reference implementation: `src/Service/Telegram/ExpirySummaryBuilder.php:65-77`.

## Approach

Introduce one shared household-calendar helper and route **all** stock "today"/day-delta math
through it. This is the single correct date path the issue and the `server-authoritative-dates`
plan both call for. The day math (`%r%a` on `Y-m-d` strings) is lifted verbatim from the
already-correct `ExpirySummaryBuilder::dayDelta`, so behavior on the correct path is preserved
byte-for-byte; only the three (now four) wrong paths change.

### 1. New unit — `HouseholdCalendar`

`src/Service/Time/HouseholdCalendar.php`, `final readonly`, injects `ClockInterface` +
`AppTimezone` (both already autowire — `ExpirySummaryBuilder` injects them today).

```php
final readonly class HouseholdCalendar
{
    public function __construct(
        private ClockInterface $clock,
        private AppTimezone $tz,
    ) {}

    /** Midnight today in the household zone. */
    public function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone($this->tz->get())->setTime(0, 0);
    }

    /** Signed whole-day difference today -> $date, date-only (negative = past). */
    public function daysUntil(\DateTimeImmutable $date): int
    {
        $a = new \DateTimeImmutable($this->today()->format('Y-m-d'));
        $b = new \DateTimeImmutable($date->format('Y-m-d'));

        return (int) $a->diff($b)->format('%r%a');
    }

    /** Window cutoff date for "expiring within N days" queries. */
    public function expiryCutoff(int $days): \DateTimeImmutable
    {
        return $this->today()->modify(sprintf('+%d days', $days));
    }
}
```

Per the design decision, `daysUntil` reads the clock internally (simplest call sites). Within a
single request the injected clock is fixed, so the repeated reads are deterministic.

### 2. Dedup `ExpirySummaryBuilder` onto the helper

Replace its `ClockInterface` + `AppTimezone` constructor deps with a single
`HouseholdCalendar`; delete its private `today()` and `dayDelta()`; use
`calendar->today()` for the header date and `calendar->daysUntil($bestBefore)` per entry. One
implementation of the day math, no drift. Existing `ExpirySummaryBuilderTest` assertions stay
unchanged and act as a behavior guard (only the constructor wiring in the test updates).

### 3. `StockEntryService` — inject `HouseholdCalendar`, fix the three local paths

- `mapEntryToResponse`: compute `$daysUntilExpiry = $bestBefore !== null ? $calendar->daysUntil($bestBefore) : null`
  and pass it into `StockEntryResponse`.
- `getExpiringEntries`: per-entry `calendar->daysUntil($bestBefore)`; pass
  `calendar->expiryCutoff($days)` to the repository.
- `addStock`: best-before suggestion = `calendar->today()->modify('+N days')`.

### 4. `StockEntryRepository::findExpiring` — stop reading the clock

Signature changes `findExpiring(int $days)` → `findExpiring(\DateTimeImmutable $cutoff)`; the
two clock lines are removed and the bound parameter uses `$cutoff`. The repository becomes a
pure query (no clock, no tz). Both callers compute the cutoff via `HouseholdCalendar`:

- `StockEntryService::getExpiringEntries` (above).
- `SendDailyExpirySummaryHandler` — inject `HouseholdCalendar`, call
  `findExpiring($calendar->expiryCutoff(self::WINDOW_DAYS))`. This is the one file §4 pulls in
  beyond the stock-display surface; accepted because it is the same root-cause bug.

This preserves the selected set: `best_before` is a `DATE_IMMUTABLE` (midnight) and the old
cutoff was `now + N days`; switching to `today()->modify('+N days')` (midnight) selects the same
or a more-correct set, now anchored to the household day.

### 5. `StockEntryResponse` — remove the hidden clock read

Delete `calculateDaysUntilExpiry`; `days_until_expiry` becomes a plain constructor-injected
`?int`. The DTO no longer touches time — testable and mutation-pinnable. There is exactly one
construction site (`StockEntryService::mapEntryToResponse`), so the change is contained.

## Testing

### How the functional boundary test controls "today" (verified)

`StockControllerTest` already uses Symfony's `ClockSensitiveTrait`. Its `static::mockTime($i)`
calls `Clock::set(new MockClock($i))` on the **global** clock, and the autowired
`ClockInterface` service delegates to that global clock. This is already proven in-repo:
`mockTime('2026-01-01 10:00')` drives `new DatePoint()` (entry `createdAt`) in the existing
FIFO-tiebreak test. Once §§3–4 make the expiry path read time **only** through the injected
clock (via `HouseholdCalendar`), `mockTime` controls the API's "today" end-to-end — no new
container wiring.

### Test set

- **`HouseholdCalendarTest`** (unit, new) — pin `MockClock('2026-06-05 22:30:00')`
  (22:30Z = 03:30 on 2026-06-06 in Almaty):
  - `today()->format('Y-m-d') === '2026-06-06'`
  - `daysUntil('2026-06-06') === 0`, `daysUntil('2026-06-05') === -1`, `daysUntil('2026-06-08') === 2`
  - `expiryCutoff(7)->format('Y-m-d') === '2026-06-13'`
  - a mid-day-UTC instant proving no regression away from the boundary.
- **Functional boundary test** (`StockControllerTest`, new) — the end-to-end regression:
  ```php
  static::mockTime(new \DateTimeImmutable('2026-06-05 22:30:00')); // 03:30 on 2026-06-06 Almaty
  $this->createEntry(['product'=>$p,'location'=>$l,'bestBefore'=>new \DateTimeImmutable('2026-06-06')]);
  $data = /* GET /stocks/expiring?days=7 */;
  static::assertSame(0, $data['data'][0]['days_until_expiry']); // buggy UTC path returns 1
  ```
- **`ExpirySummaryBuilderTest`** — update construction to inject `HouseholdCalendar`; assertions
  unchanged. Because the SPA path and Telegram path now share the same helper, "API value
  matches `ExpirySummaryBuilder`" holds by construction.
- **`SendDailyExpirySummaryHandlerTest`** — `findExpiring` mock expectation
  `->with(3)` → `->with(self::isInstanceOf(\DateTimeImmutable::class))`, plus the added
  `HouseholdCalendar` constructor arg when the handler is instantiated.
- **Fix latent fragility:** `testAddStockAutoCalculatesBestBeforeFromProduct` currently expects
  `(server-UTC-now)+14d`. After the fix the API returns `(Almaty-today)+14d`, which diverges
  19:00–24:00 UTC nightly — the test would start flaking. Pin it with `mockTime` and assert the
  Almaty-anchored date.

## Docs to reconcile

- `docs/plans/2026-01-21-server-authoritative-dates.md` — its own sample code uses
  `new \DateTimeImmutable('today')`; annotate it to mandate household-tz "today".
- Re-verify the `@var … guaranteed non-null by findExpiring query` comment at
  `StockEntryService.php:315` after the refactor (still holds — `findExpiring` keeps
  `bestBefore IS NOT NULL`).
- Post a note on issue #53 recording the 4th path (`findExpiring`) found beyond the review's
  listed three; update review doc § C1's evidence list to match.

## Scope guardrails (from the issue)

Do **not**: change the `best_before` storage type; build a broader clock framework beyond this
one helper; touch the frontend (it already consumes server-provided days); modify `AppTimezone`.

## Acceptance criteria

- No `new \DateTimeImmutable('today')` or bare `new \DateTimeImmutable()` used for date math
  remains in `StockEntryService`, `src/Response/Stock/`, or `StockEntryRepository::findExpiring`
  (grep clean).
- `StockEntryResponse` no longer reads the clock.
- Regression: at `2026-06-05T22:30:00Z`, an entry with `best_before = 2026-06-06` returns
  `days_until_expiry == 0` (not `1`), and the API value matches `ExpirySummaryBuilder`.
- `make lint` and `make test` green.

## Verification

```bash
cd /home/pavel/projects/personal/hestia/backend
make lint && make test
grep -rn "DateTimeImmutable('today')" src/Service/StockEntryService.php src/Response/Stock/   # expect: no hits
grep -n "new \\\\DateTimeImmutable()" src/Repository/StockEntryRepository.php                 # expect: no hits in findExpiring
```
