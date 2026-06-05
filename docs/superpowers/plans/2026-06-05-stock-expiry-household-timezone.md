# Stock Expiry Household-Timezone Day Math — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Compute "today" and day-deltas for stock expiry in the household timezone everywhere, eliminating the 00:00–05:00 Almaty off-by-one where the SPA's `days_until_expiry` disagrees with the Telegram summary.

**Architecture:** Introduce one shared `HouseholdCalendar` helper (injected `ClockInterface` + `AppTimezone`) and route all stock "today"/day math through it. Dedup the already-correct `ExpirySummaryBuilder` onto it, fix the four wrong paths (DTO, service display, service `addStock`, repository `findExpiring`), and remove the hidden clock read from the response DTO.

**Tech Stack:** PHP 8.4 / Symfony 8, `symfony/clock` (`ClockInterface`, `MockClock`, `ClockSensitiveTrait`), Doctrine ORM, PHPUnit, Zenstruck Foundry.

**Design ref:** `docs/superpowers/specs/2026-06-05-stock-expiry-household-timezone-design.md` · **Issue:** #53

**Conventions (from CLAUDE.md / AGENTS.md):**
- Backend gate: `cd /home/pavel/projects/personal/hestia/backend && make lint` (rector → mago format → mago lint → mago analyze → phpstan). After `make lint`, **stage explicitly** — never `git add -A` (rector/mago rewrite files).
- Full tests: `cd backend && make test`. Single test: `docker compose exec php bin/phpunit --filter '<name>'`.
- Commits: `git commit -s -m "<type>(<scope>): <desc>"` (GPG auto-signed).
- Branch already created: `fix/stock-expiry-household-timezone`.

---

### Task 1: `HouseholdCalendar` helper

**Files:**
- Create: `backend/src/Service/Time/HouseholdCalendar.php`
- Test: `backend/tests/Unit/Service/Time/HouseholdCalendarTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Unit/Service/Time/HouseholdCalendarTest.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Service\Time;

use App\Service\Time\AppTimezone;
use App\Service\Time\HouseholdCalendar;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class HouseholdCalendarTest extends TestCase
{
    private function calendarAt(string $utc): HouseholdCalendar
    {
        // Clock is UTC; AppTimezone converts to Asia/Almaty (+05) for "today".
        return new HouseholdCalendar(
            new MockClock(new \DateTimeImmutable($utc, new \DateTimeZone('UTC'))),
            new AppTimezone()
        );
    }

    public function testTodayUsesHouseholdTimezoneAcrossTheBoundary(): void
    {
        // 22:30Z == 03:30 on 2026-06-06 in Almaty (+05) -> local "today" is the 6th, not the 5th.
        self::assertSame('2026-06-06', $this->calendarAt('2026-06-05 22:30:00')->today()->format('Y-m-d'));
    }

    public function testDaysUntilIsZeroForLocalTodayInsideTheBoundaryWindow(): void
    {
        $calendar = $this->calendarAt('2026-06-05 22:30:00'); // local today = 2026-06-06

        self::assertSame(0, $calendar->daysUntil(new \DateTimeImmutable('2026-06-06')));
        self::assertSame(-1, $calendar->daysUntil(new \DateTimeImmutable('2026-06-05')));
        self::assertSame(2, $calendar->daysUntil(new \DateTimeImmutable('2026-06-08')));
    }

    public function testExpiryCutoffIsHouseholdTodayPlusDays(): void
    {
        self::assertSame(
            '2026-06-13',
            $this->calendarAt('2026-06-05 22:30:00')->expiryCutoff(7)->format('Y-m-d')
        );
    }

    public function testNoRegressionAwayFromTheBoundary(): void
    {
        // 06:00Z == 11:00 Almaty, same calendar day -> local today = 2026-06-05.
        $calendar = $this->calendarAt('2026-06-05 06:00:00');

        self::assertSame('2026-06-05', $calendar->today()->format('Y-m-d'));
        self::assertSame(0, $calendar->daysUntil(new \DateTimeImmutable('2026-06-05')));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter HouseholdCalendarTest`
Expected: FAIL — `Class "App\Service\Time\HouseholdCalendar" not found`.

- [ ] **Step 3: Write the implementation**

Create `backend/src/Service/Time/HouseholdCalendar.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Service\Time;

use Symfony\Component\Clock\ClockInterface;

/**
 * Single source of truth for "what day is today" and day-deltas in the household timezone.
 *
 * The system clock is pinned to UTC; the household runs at a fixed offset (see AppTimezone).
 * Routing all stock date math through here keeps the SPA and the Telegram summary in agreement.
 */
final readonly class HouseholdCalendar
{
    public function __construct(
        private ClockInterface $clock,
        private AppTimezone $appTimezone
    ) {
    }

    /** Midnight today in the household timezone. */
    public function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone($this->appTimezone->get())->setTime(0, 0);
    }

    /** Signed whole-day difference today -> $date, date-only (negative = already past). */
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

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter HouseholdCalendarTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Lint and commit**

```bash
cd /home/pavel/projects/personal/hestia/backend && make lint
git add src/Service/Time/HouseholdCalendar.php tests/Unit/Service/Time/HouseholdCalendarTest.php
git commit -s -m "feat(time): add HouseholdCalendar for household-tz day math (#53)"
```

---

### Task 2: Dedup `ExpirySummaryBuilder` onto `HouseholdCalendar`

Pure refactor — behavior preserved. The builder drops its private `today()`/`dayDelta()` and its `ClockInterface`/`AppTimezone` deps, taking a single `HouseholdCalendar`. Existing tests stay green as the behavior guard; only their construction wiring changes.

**Files:**
- Modify: `backend/src/Service/Telegram/ExpirySummaryBuilder.php`
- Modify: `backend/tests/Unit/Service/Telegram/ExpirySummaryBuilderTest.php:17-20`
- Modify: `backend/tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php:65-82`

- [ ] **Step 1: Update the test construction sites first (they pin behavior)**

In `tests/Unit/Service/Telegram/ExpirySummaryBuilderTest.php`, replace the `builderAt` helper (and add the import) so it wraps a `HouseholdCalendar`:

```php
use App\Service\Time\HouseholdCalendar;
```

```php
    private function builderAt(string $utc): ExpirySummaryBuilder
    {
        // Clock is UTC; HouseholdCalendar converts to Asia/Almaty (+05) for "today".
        return new ExpirySummaryBuilder(
            new HouseholdCalendar(new MockClock(new \DateTimeImmutable($utc)), new AppTimezone())
        );
    }
```

In `tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php`, add the import and update the `handler()` helper to build the calendar and pass it to the builder (the handler arg is added in Task 3):

```php
use App\Service\Time\HouseholdCalendar;
```

```php
    private function handler(StockEntryRepository $repo, ChatterInterface $chatter): SendDailyExpirySummaryHandler
    {
        $calendar = new HouseholdCalendar(
            new MockClock(new \DateTimeImmutable('2026-06-04 04:00:00')),
            new AppTimezone()
        );
        $builder = new ExpirySummaryBuilder($calendar);

        $this->logHandler = new TestHandler();

        return new SendDailyExpirySummaryHandler(
            $repo,
            $builder,
            new TelegramSender($chatter, new NullLogger()),
            new Logger('app', [$this->logHandler])
        );
    }
```

- [ ] **Step 2: Run the affected tests to verify they fail**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter 'ExpirySummaryBuilderTest|SendDailyExpirySummaryHandlerTest'`
Expected: FAIL — `ExpirySummaryBuilder::__construct()` still expects `ClockInterface, AppTimezone`, not `HouseholdCalendar`.

- [ ] **Step 3: Refactor the builder to delegate**

Replace `backend/src/Service/Telegram/ExpirySummaryBuilder.php` entirely with:

```php
<?php

declare(strict_types = 1);

namespace App\Service\Telegram;

use App\Entity\StockEntry;
use App\Service\Time\HouseholdCalendar;

final readonly class ExpirySummaryBuilder
{
    public function __construct(
        private HouseholdCalendar $calendar
    ) {
    }

    /**
     * @param StockEntry[] $entries entries with bestBefore <= today + window (expired included)
     */
    public function build(array $entries): ?string
    {
        $today = $this->calendar->today();

        $expired = [];
        $soon = [];
        foreach ($entries as $entry) {
            $bestBefore = $entry->getBestBefore();
            if ($bestBefore === null) {
                continue;
            }

            $days = $this->calendar->daysUntil($bestBefore);
            $line = sprintf(
                '• %s (%s) — %s',
                $this->escape($entry->getProduct()->getName()),
                $this->escape($entry->getLocation()->getName()),
                $this->relative($days)
            );

            if ($days < 0) {
                $expired[] = $line;
            } else {
                $soon[] = $line;
            }
        }

        if ($expired === [] && $soon === []) {
            return null;
        }

        $sections = [sprintf('🏠 Гестия — сводка на %s', $today->format('d.m'))];
        if ($expired !== []) {
            $sections[] = "⚠️ Просрочено\n" . implode("\n", $expired);
        }

        if ($soon !== []) {
            $sections[] = "🔔 Скоро истекает\n" . implode("\n", $soon);
        }

        return implode("\n\n", $sections);
    }

    private function relative(int $days): string
    {
        return match (true) {
            $days <= -2 => sprintf('%d дн. назад', -$days),
            $days === -1 => 'вчера',
            $days === 0 => 'сегодня',
            $days === 1 => 'завтра',
            default => sprintf('через %d дн.', $days)
        };
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
```

- [ ] **Step 4: Run the affected tests to verify they pass**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter 'ExpirySummaryBuilderTest|SendDailyExpirySummaryHandlerTest'`
Expected: PASS (behavior unchanged; only wiring moved).

- [ ] **Step 5: Lint and commit**

```bash
cd /home/pavel/projects/personal/hestia/backend && make lint
git add src/Service/Telegram/ExpirySummaryBuilder.php tests/Unit/Service/Telegram/ExpirySummaryBuilderTest.php tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php
git commit -s -m "refactor(telegram): delegate ExpirySummaryBuilder day math to HouseholdCalendar (#53)"
```

---

### Task 3: Migrate `findExpiring` to a household-anchored cutoff

`findExpiring` stops reading the clock and takes a precomputed cutoff date. Both callers compute it via `HouseholdCalendar`: `StockEntryService` (inject the dep) and `SendDailyExpirySummaryHandler` (inject the dep). Per-entry `days_until_expiry` is still the old value at this point — fixed in Task 4. Suite stays green.

**Files:**
- Modify: `backend/src/Repository/StockEntryRepository.php:135-149`
- Modify: `backend/src/Service/StockEntryService.php` (constructor + `getExpiringEntries:308-311`)
- Modify: `backend/src/MessageHandler/SendDailyExpirySummaryHandler.php`
- Modify: `backend/tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php` (mock expectation + handler arg)

- [ ] **Step 1: Update the handler unit test to the new contract (red)**

In `tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php`:

Change both `findExpiring` mock expectations from `->with(3)` to a date matcher:

```php
$repo->expects(self::once())->method('findExpiring')
    ->with(self::isInstanceOf(\DateTimeImmutable::class))->willReturn([$entry]);
```
```php
$repo->expects(self::once())->method('findExpiring')
    ->with(self::isInstanceOf(\DateTimeImmutable::class))->willReturn([]);
```

And pass the `$calendar` into the handler (it is already built in the `handler()` helper from Task 2):

```php
        return new SendDailyExpirySummaryHandler(
            $repo,
            $builder,
            new TelegramSender($chatter, new NullLogger()),
            new Logger('app', [$this->logHandler]),
            $calendar
        );
```

- [ ] **Step 2: Run the handler test to verify it fails**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter SendDailyExpirySummaryHandlerTest`
Expected: FAIL — handler constructor has 4 params (no `$calendar`) and `findExpiring` is still called with `int`.

- [ ] **Step 3: Change the repository signature**

In `backend/src/Repository/StockEntryRepository.php`, replace `findExpiring`:

```php
    /**
     * @return StockEntry[]
     */
    public function findExpiring(\DateTimeImmutable $cutoff): array
    {
        // @mago-ignore analysis:mixed-return-statement
        return $this
            ->createQueryBuilder('e')
            ->where('e.bestBefore IS NOT NULL')
            ->andWhere('e.bestBefore <= :cutoffDate')
            ->setParameter('cutoffDate', $cutoff)
            ->orderBy('e.bestBefore', 'ASC')
            ->getQuery()
            ->getResult();
    }
```

- [ ] **Step 4: Inject `HouseholdCalendar` into `StockEntryService` and pass the cutoff**

In `backend/src/Service/StockEntryService.php`, add the import near the other `App\Service` uses:

```php
use App\Service\Time\HouseholdCalendar;
```

Add the constructor dependency (append to the existing promoted constructor params):

```php
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StockEntryRepository $stockEntryRepository,
        private readonly ProductRepository $productRepository,
        private readonly LocationRepository $locationRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly HouseholdCalendar $householdCalendar
    ) {
    }
```

In `getExpiringEntries`, change only the cutoff line (`:310-311`); leave the per-entry math for Task 4:

```php
        $entries = $this->stockEntryRepository->findExpiring($this->householdCalendar->expiryCutoff($days));
        $today = new \DateTimeImmutable('today');
```

- [ ] **Step 5: Inject `HouseholdCalendar` into the handler and compute the cutoff**

Replace `backend/src/MessageHandler/SendDailyExpirySummaryHandler.php` constructor and `__invoke` head:

```php
use App\Service\Time\HouseholdCalendar;
```

```php
    public function __construct(
        private StockEntryRepository $stockEntryRepository,
        private ExpirySummaryBuilder $builder,
        private TelegramSender $sender,
        private LoggerInterface $logger,
        private HouseholdCalendar $calendar
    ) {
    }

    public function __invoke(SendDailyExpirySummary $message): void
    {
        $entries = $this->stockEntryRepository->findExpiring($this->calendar->expiryCutoff(self::WINDOW_DAYS));
        $summary = $this->builder->build($entries);
```

(Leave the rest of `__invoke` unchanged.)

- [ ] **Step 6: Run handler test + full suite**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter SendDailyExpirySummaryHandlerTest`
Expected: PASS.
Run: `make test`
Expected: PASS (existing `StockControllerTest` expiring tests assert only sign/relative magnitude, so they remain green).

- [ ] **Step 7: Lint and commit**

```bash
cd /home/pavel/projects/personal/hestia/backend && make lint
git add src/Repository/StockEntryRepository.php src/Service/StockEntryService.php src/MessageHandler/SendDailyExpirySummaryHandler.php tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php
git commit -s -m "refactor(stock): anchor findExpiring window to household calendar (#53)"
```

---

### Task 4: Household-tz `days_until_expiry` on the read path + remove DTO clock read

This is the user-visible fix. Move the `days_until_expiry` computation out of `StockEntryResponse` into the service (via the calendar), and switch `getExpiringEntries`'s per-entry math to the calendar. New functional boundary test proves the off-by-one is gone.

**Files:**
- Modify: `backend/src/Response/Stock/StockEntryResponse.php`
- Modify: `backend/src/Service/StockEntryService.php` (`getExpiringEntries:313-335`, `mapEntryToResponse:375-388`)
- Test: `backend/tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php`

- [ ] **Step 1: Write the failing functional boundary test**

Add this test method to `StockControllerTest` (place it in the "Expiring Tests" region, after `testExpiringIncludesAlreadyExpiredItems`):

```php
    /**
     * Regression for C1 (#53): between 00:00–05:00 Almaty the API must report the household day,
     * not UTC. At 22:30Z (= 03:30 on 2026-06-06 Almaty) an item dated 2026-06-06 is "today" (0),
     * not "tomorrow" (1).
     */
    public function testExpiringDaysUntilExpiryUsesHouseholdTimezone(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-05 22:30:00', new \DateTimeZone('UTC')));

        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);
        $this->createEntry([
            'product' => $product,
            'location' => $location,
            'bestBefore' => new \DateTimeImmutable('2026-06-06')
        ]);

        $response = $this->apiGet('/stocks/expiring', ['days' => '7']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
        static::assertSame(0, $data['data'][0]['days_until_expiry']);
    }
```

- [ ] **Step 2: Run it to verify it fails**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter testExpiringDaysUntilExpiryUsesHouseholdTimezone`
Expected: FAIL — current `getExpiringEntries` computes the delta from `new \DateTimeImmutable('today')`, which reads the real system clock (ignores `mockTime`), so `days_until_expiry` is not `0`. (Task 3 already made the entry's *inclusion* deterministic via the mocked cutoff, so the row is present; only the delta is wrong.)

- [ ] **Step 3: Switch `getExpiringEntries` to the calendar**

In `backend/src/Service/StockEntryService.php`, replace the body of `getExpiringEntries` (`:308-336`) so the per-entry delta uses the calendar and the local `$today` / `diff` are gone:

```php
    public function getExpiringEntries(int $days): array
    {
        $entries = $this->stockEntryRepository->findExpiring($this->householdCalendar->expiryCutoff($days));

        return array_map(
            function (StockEntry $entry): ExpiringEntryResponse {
                /** @var \DateTimeImmutable $bestBefore - guaranteed non-null by findExpiring query */
                $bestBefore = $entry->getBestBefore();

                return new ExpiringEntryResponse(
                    id: $entry->getId(),
                    product: new ProductBriefResponse(
                        id: $entry->getProduct()->getId(),
                        name: $entry->getProduct()->getName(),
                        unit: $entry->getProduct()->getUnit()
                    ),
                    location: new LocationResponse(
                        id: $entry->getLocation()->getId(),
                        name: $entry->getLocation()->getName()
                    ),
                    best_before: $bestBefore->format('Y-m-d'),
                    days_until_expiry: $this->householdCalendar->daysUntil($bestBefore)
                );
            },
            $entries
        );
    }
```

Note: the closure changes from `static function (...) use ($today)` to `function (...)` (captures `$this` for the calendar). The `@var … guaranteed non-null by findExpiring query` comment still holds — `findExpiring` keeps `bestBefore IS NOT NULL`.

- [ ] **Step 4: Compute `days_until_expiry` in `mapEntryToResponse` and pass it in**

Replace `mapEntryToResponse` (`:375-388`):

```php
    private function mapEntryToResponse(StockEntry $entry): StockEntryResponse
    {
        $bestBefore = $entry->getBestBefore();

        return new StockEntryResponse(
            id: $entry->getId(),
            product: new ProductBriefResponse(
                id: $entry->getProduct()->getId(),
                name: $entry->getProduct()->getName(),
                unit: $entry->getProduct()->getUnit()
            ),
            location: new LocationResponse(id: $entry->getLocation()->getId(), name: $entry->getLocation()->getName()),
            best_before: $bestBefore?->format('Y-m-d'),
            created_at: $entry->getCreatedAt(),
            days_until_expiry: $bestBefore !== null ? $this->householdCalendar->daysUntil($bestBefore) : null
        );
    }
```

- [ ] **Step 5: Remove the clock read from `StockEntryResponse`**

Replace `backend/src/Response/Stock/StockEntryResponse.php` entirely:

```php
<?php

declare(strict_types = 1);

namespace App\Response\Stock;

use App\Response\Location\LocationResponse;
use Symfony\Component\Uid\Uuid;

final readonly class StockEntryResponse
{
    public function __construct(
        public Uuid $id,
        public ProductBriefResponse $product,
        public LocationResponse $location,
        public ?string $best_before,
        public \DateTimeImmutable $created_at,
        public ?int $days_until_expiry
    ) {
    }
}
```

- [ ] **Step 6: Run the boundary test + full suite**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter testExpiringDaysUntilExpiryUsesHouseholdTimezone`
Expected: PASS (`days_until_expiry == 0`).
Run: `make test`
Expected: PASS.

- [ ] **Step 7: Verify the grep-clean acceptance criterion**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend
grep -rn "DateTimeImmutable('today')" src/Service/StockEntryService.php src/Response/Stock/    # expect: no hits
```
Expected: no output.

- [ ] **Step 8: Lint and commit**

```bash
cd /home/pavel/projects/personal/hestia/backend && make lint
git add src/Response/Stock/StockEntryResponse.php src/Service/StockEntryService.php tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php
git commit -s -m "fix(stock): compute days_until_expiry in household timezone (C1, #53)"
```

---

### Task 5: Household-tz `addStock` suggestion + fix the latent test fragility

`addStock`'s default `best_before` (= product `defaultExpiryDays` from "today") must anchor to the household day. The existing test `testAddStockAutoCalculatesBestBeforeFromProduct` compares against server-UTC `now()+14d`, which will diverge from the new Almaty-anchored value 19:00–24:00 UTC nightly — pin its clock and assert the Almaty date.

**Files:**
- Modify: `backend/src/Service/StockEntryService.php:74-81`
- Modify: `backend/tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php` (`testAddStockAutoCalculatesBestBeforeFromProduct`)

- [ ] **Step 1: Rewrite the test to pin time and assert the household date (red)**

Replace `testAddStockAutoCalculatesBestBeforeFromProduct` in `StockControllerTest`:

```php
    public function testAddStockAutoCalculatesBestBeforeFromProduct(): void
    {
        // 22:30Z == 03:30 on 2026-06-06 Almaty -> "today" is the 6th; +14d = 2026-06-20.
        static::mockTime(new \DateTimeImmutable('2026-06-05 22:30:00', new \DateTimeZone('UTC')));

        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'defaultExpiryDays' => 14
        ]);

        $response = $this->apiPost('/stocks/add', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'quantity' => 1
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertSame('2026-06-20', $data['data']['entries'][0]['best_before']);
    }
```

- [ ] **Step 2: Run it to verify it fails**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter testAddStockAutoCalculatesBestBeforeFromProduct`
Expected: FAIL — `addStock` uses `new \DateTimeImmutable()->modify('+14 days')` (real system clock, UTC), so `best_before` is not `2026-06-20`.

- [ ] **Step 3: Anchor the suggestion to the household calendar**

In `backend/src/Service/StockEntryService.php`, change the `$bestBefore` match arm for `defaultExpiryDays` (`:76-79`):

```php
        $bestBefore = match (true) {
            $request->best_before !== null => new \DateTimeImmutable($request->best_before),
            $product->getDefaultExpiryDays() !== null => $this->householdCalendar->today()->modify(sprintf(
                '+%d days',
                (int) $product->getDefaultExpiryDays()
            )),
            default => null
        };
```

- [ ] **Step 4: Run the test + full suite**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter testAddStockAutoCalculatesBestBeforeFromProduct`
Expected: PASS.
Run: `make test`
Expected: PASS.

- [ ] **Step 5: Verify grep-clean for the repository**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend
grep -n "new \\\\DateTimeImmutable()" src/Repository/StockEntryRepository.php   # expect: no hits in findExpiring
grep -rn "DateTimeImmutable('today')" src/Service/StockEntryService.php          # expect: no hits
```
Expected: no output for either.

- [ ] **Step 6: Lint and commit**

```bash
cd /home/pavel/projects/personal/hestia/backend && make lint
git add src/Service/StockEntryService.php tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php
git commit -s -m "fix(stock): anchor addStock best_before suggestion to household timezone (#53)"
```

---

### Task 6: Reconcile docs and record the 4th path

No code change — close the loop on the issue's "hard-evaluate" items.

**Files:**
- Modify: `docs/plans/2026-01-21-server-authoritative-dates.md`
- Modify: `docs/reviews/2026-06-05-backend-architecture/README.md` (§ C1 evidence list)

- [ ] **Step 1: Annotate the server-authoritative-dates plan**

Open `docs/plans/2026-01-21-server-authoritative-dates.md`, find each sample that uses `new \DateTimeImmutable('today')`, and add an explicit note next to it (do not silently change semantics — annotate). Add, immediately after the first such sample, a callout:

```markdown
> **Correction (2026-06-05, #53):** "today" MUST be resolved in the household timezone, not the
> server zone. Use `HouseholdCalendar::today()` (injected `ClockInterface` + `AppTimezone`),
> never a bare `new \DateTimeImmutable('today')`, which resolves in the UTC server zone and
> reintroduces the very off-by-one this plan set out to eliminate.
```

- [ ] **Step 2: Add the 4th path to the review doc**

In `docs/reviews/2026-06-05-backend-architecture/README.md`, in the § C1 evidence list, add the repository path that the original review missed:

```markdown
- `backend/src/Repository/StockEntryRepository.php:137` — `findExpiring()` computed its window
  from a raw, uninjected `new \DateTimeImmutable()` (same root cause; also untestable). Fixed in
  #53 by passing a household-anchored cutoff date in.
```

- [ ] **Step 3: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add docs/plans/2026-01-21-server-authoritative-dates.md docs/reviews/2026-06-05-backend-architecture/README.md
git commit -s -m "docs(stock): mandate household-tz today; record findExpiring path (#53)"
```

- [ ] **Step 4: Note the 4th path on the issue**

```bash
gh issue comment 53 --body "During implementation we found a 4th wrong path beyond the review's listed three: \`StockEntryRepository::findExpiring\` (\`:137\`) computed its window from a raw uninjected \`new \\DateTimeImmutable()\` — same root-cause tz error on the window boundary, and untestable. Fixed by injecting a household-anchored cutoff (\`HouseholdCalendar::expiryCutoff()\`) computed by the callers."
```

---

### Final verification

- [ ] **Run the full backend gate**

```bash
cd /home/pavel/projects/personal/hestia/backend
make lint && make test
```
Expected: both green.

- [ ] **Confirm acceptance criteria (from the spec)**
  - No `new \DateTimeImmutable('today')` / bare `new \DateTimeImmutable()` for date math in `StockEntryService`, `src/Response/Stock/`, or `StockEntryRepository::findExpiring` (greps from Tasks 4–5 clean).
  - `StockEntryResponse` no longer reads the clock (Task 4).
  - Boundary regression passes: `2026-06-05T22:30:00Z` + `best_before = 2026-06-06` → `days_until_expiry == 0` (Task 4); API value shares the `HouseholdCalendar` with `ExpirySummaryBuilder` (Task 2).

- [ ] **Open the PR** (per CLAUDE.md: `gh auth switch -u ratchet27` first)

```bash
gh pr create --fill --base master --head fix/stock-expiry-household-timezone
```
