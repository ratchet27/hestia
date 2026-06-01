# Tasks & Chores Branch Completion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Finish `feature/tasks-chores-frontend` so chore scheduling is timezone-correct (Asia/Almaty UTC+5), month-end recurrence is safe, `schedule_value` is validated per type, the dashboard mark-done button works, card markup is valid HTML, and tasks/chores render in Overdue/Active/Completed sections — then prove it with green gates.

**Architecture:** Backend introduces an `AppTimezone` resolver (fixed `+05:00` default, upgrades to named `Asia/Almaty` only when that zone genuinely resolves to +05) injected into `ChoreService`, which converts "now" to the app-timezone calendar date before scheduling; the `Chore` entity stays persistence-focused and anchors `next_due_at` at midnight (stored as wall-clock → read back as midnight-UTC of the correct calendar date). Frontend wires the dead dashboard button, fixes nested `<button>` markup, adds a pure grouping helper for sectioning, and a `console.error` test guard.

**Tech Stack:** PHP 8.4 / Symfony 8.0 / Doctrine ORM / symfony/clock / PHPUnit + Foundry; React 19 / TypeScript / TanStack Query / Vitest + Testing Library / react-i18next.

**Spec:** `docs/plans/2026-06-01-tasks-chores-branch-completion-design.md`

**Preconditions:**
- On branch `feature/tasks-chores-frontend`.
- Backend Docker up for backend tests: `cd backend && docker compose up -d` (verify `docker compose ps` shows `php` + `database` healthy).
- `gh auth switch -u ratchet27` before any push (commits are local; no push in this plan).
- Commit format: `git commit -s -m "<type>(tasks): <desc>"`.

---

## Task 1: `AppTimezone` resolver service

**Files:**
- Create: `backend/src/Service/Time/AppTimezone.php`
- Create: `backend/tests/Unit/Service/Time/AppTimezoneTest.php`
- Modify: `backend/config/services.yaml`
- Modify: `backend/.env`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Unit/Service/Time/AppTimezoneTest.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Service\Time;

use App\Service\Time\AppTimezone;
use PHPUnit\Framework\TestCase;

class AppTimezoneTest extends TestCase
{
    private const POST_CHANGE_DATE = '2026-06-01T12:00:00+00:00';

    public function testEffectiveOffsetIsAlwaysPlusFive(): void
    {
        $reference = new \DateTimeImmutable(self::POST_CHANGE_DATE);

        $tz = new AppTimezone('+05:00', 'Asia/Almaty');

        static::assertSame(5 * 3600, $tz->get()->getOffset($reference));
    }

    public function testPrefersNamedZoneWhenItResolvesToPlusFive(): void
    {
        // Etc/GMT-5 is permanently +05:00 regardless of tzdata version.
        $tz = new AppTimezone('+05:00', 'Etc/GMT-5');

        static::assertSame('Etc/GMT-5', $tz->get()->getName());
    }

    public function testFallsBackToFixedOffsetWhenNamedZoneIsNotPlusFive(): void
    {
        // Asia/Bishkek is permanently +06:00 (no DST) -> must NOT be chosen.
        $tz = new AppTimezone('+05:00', 'Asia/Bishkek');

        static::assertSame('+05:00', $tz->get()->getName());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd backend && docker compose exec -T php bin/phpunit tests/Unit/Service/Time/AppTimezoneTest.php`
Expected: FAIL — `Class "App\Service\Time\AppTimezone" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `backend/src/Service/Time/AppTimezone.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Service\Time;

/**
 * Resolves the household's scheduling timezone.
 *
 * Defaults to a fixed offset (always correct, immune to stale tzdata). Upgrades
 * to the preferred named zone only when that zone currently resolves to the same
 * offset as the fixed fallback — so a container with stale tzdata that maps the
 * named zone to the wrong offset transparently falls back to the safe fixed value.
 */
final class AppTimezone
{
    /** A date after Kazakhstan's 2024-03-01 move to permanent UTC+5. */
    private const REFERENCE = '2024-06-01T12:00:00+00:00';

    private readonly \DateTimeZone $timezone;

    public function __construct(
        string $fixedOffset = '+05:00',
        string $preferredZone = 'Asia/Almaty',
    ) {
        $reference = new \DateTimeImmutable(self::REFERENCE);
        $named = new \DateTimeZone($preferredZone);
        $fixed = new \DateTimeZone($fixedOffset);

        $this->timezone = $named->getOffset($reference) === $fixed->getOffset($reference)
            ? $named
            : $fixed;
    }

    public function get(): \DateTimeZone
    {
        return $this->timezone;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd backend && docker compose exec -T php bin/phpunit tests/Unit/Service/Time/AppTimezoneTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Wire configuration**

In `backend/config/services.yaml`, under `parameters:` add:

```yaml
parameters:
    app.timezone: '%env(APP_TIMEZONE)%'
```

And add an explicit service definition (the scalar arg cannot autowire) below the existing explicit definitions:

```yaml
    App\Service\Time\AppTimezone:
        arguments:
            $fixedOffset: '%app.timezone%'
```

In `backend/.env`, add near the other app settings:

```dotenv
###> app ###
APP_TIMEZONE=+05:00
###< app ###
```

- [ ] **Step 6: Verify container boot + full unit suite still green**

Run: `cd backend && docker compose exec -T php bin/console debug:container App\\Service\\Time\\AppTimezone`
Expected: shows the service with no error.
Run: `cd backend && docker compose exec -T php bin/phpunit tests/Unit/Service/Time/AppTimezoneTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add backend/src/Service/Time/AppTimezone.php backend/tests/Unit/Service/Time/AppTimezoneTest.php backend/config/services.yaml backend/.env
git commit -s -m "feat(tasks): add AppTimezone resolver defaulting to +05:00"
```

---

## Task 2: Month-end clamp + `initializeNextDueAt` takes the scheduling instant

**Files:**
- Modify: `backend/src/Entity/Chore.php` (`nextMonthDay` ~186-195, `initializeNextDueAt` ~149-153)
- Modify: `backend/tests/Unit/Entity/ChoreTest.php`

- [ ] **Step 1: Update the failing tests**

In `backend/tests/Unit/Entity/ChoreTest.php`, replace the three "wraps" cases in `fixedMonthlyScheduleProvider()` (the block after the `// Edge case: PHP wraps...` comment, lines ~87-91) with clamped expectations plus a done-on-the-31st case:

```php
        // Days beyond a month's length clamp to the last valid day (no overflow/skip).
        yield 'day 31 clamps to end of February' => ['2026-02-05', 31, '2026-02-28'];
        yield 'day 31 clamps to end of April' => ['2026-04-05', 31, '2026-04-30'];
        yield 'day 30 clamps to end of February' => ['2026-02-05', 30, '2026-02-28'];
        yield 'done on the 31st does not skip February' => ['2026-01-31', 31, '2026-02-28'];
```

Add a new unit test for `initializeNextDueAt` after `testMarkDoneCanBeCalledMultipleTimes()`:

```php
    public function testInitializeNextDueAtUsesGivenInstant(): void
    {
        $chore = $this->createChore(ScheduleType::INTERVAL, 7);

        $chore->initializeNextDueAt(new \DateTimeImmutable('2026-06-02 00:00:00'));

        static::assertSame('2026-06-09', $chore->getNextDueAt()->format('Y-m-d'));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd backend && docker compose exec -T php bin/phpunit tests/Unit/Entity/ChoreTest.php`
Expected: FAIL — monthly cases assert `2026-02-28` but get `2026-03-03`/`2026-05-01`/`2026-03-02`; `testInitializeNextDueAtUsesGivenInstant` fails because `initializeNextDueAt()` takes no argument.

- [ ] **Step 3: Implement the entity changes**

In `backend/src/Entity/Chore.php`, change `initializeNextDueAt` to accept the instant:

```php
    public function initializeNextDueAt(\DateTimeImmutable $now): static
    {
        $this->nextDueAt = $this->calculateNextDueAt($now);
        return $this;
    }
```

Replace `nextMonthDay` with a clamped version:

```php
    private function nextMonthDay(\DateTimeImmutable $from, int $targetDay): \DateTimeImmutable
    {
        $currentDay = (int) $from->format('j');
        $month = $currentDay < $targetDay ? $from : $from->modify('first day of next month');

        $lastDay = (int) $month->format('t');
        $day = min($targetDay, $lastDay);

        return $month->setDate((int) $month->format('Y'), (int) $month->format('m'), $day);
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd backend && docker compose exec -T php bin/phpunit tests/Unit/Entity/ChoreTest.php`
Expected: PASS (all data-provider cases + new init test).

- [ ] **Step 5: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add backend/src/Entity/Chore.php backend/tests/Unit/Entity/ChoreTest.php
git commit -s -m "fix(tasks): clamp month-end recurrence and pass instant to initializeNextDueAt"
```

---

## Task 3: `ChoreService` schedules in the app timezone

**Files:**
- Modify: `backend/src/Service/ChoreService.php`
- Create: `backend/tests/Unit/Service/ChoreServiceTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Unit/Service/ChoreServiceTest.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Service;

use App\Entity\Chore;
use App\Enum\ScheduleType;
use App\Repository\ChoreRepository;
use App\Service\ChoreService;
use App\Service\Time\AppTimezone;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

class ChoreServiceTest extends TestCase
{
    public function testMarkChoreDoneAnchorsToAlmatyCalendarDay(): void
    {
        // 21:00 UTC on Jun 1 == 02:00 Almaty (+05) on Jun 2.
        $clock = new MockClock(new \DateTimeImmutable('2026-06-01 21:00:00', new \DateTimeZone('UTC')));

        $chore = (new Chore())
            ->setName('Test')
            ->setScheduleType(ScheduleType::INTERVAL)
            ->setScheduleValue(7);

        $repository = $this->createMock(ChoreRepository::class);
        $repository->method('find')->willReturn($chore);

        $em = $this->createMock(EntityManagerInterface::class);

        $service = new ChoreService($em, $repository, $clock, new AppTimezone('+05:00', 'Asia/Almaty'));

        $result = $service->markChoreDone(Uuid::v7());

        // Almaty "today" is Jun 2, so +7 days = Jun 9 (NOT Jun 8 from UTC).
        static::assertSame('2026-06-09', $result->getNextDueAt()->format('Y-m-d'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd backend && docker compose exec -T php bin/phpunit tests/Unit/Service/ChoreServiceTest.php`
Expected: FAIL — `ChoreService::__construct()` does not accept a clock/timezone (too few/many arguments).

- [ ] **Step 3: Implement the service changes**

In `backend/src/Service/ChoreService.php`, add imports and constructor dependencies, and route scheduling through an app-timezone "now". Replace the top of the class and the two scheduling call sites:

```php
use App\Service\Time\AppTimezone;
use Symfony\Component\Clock\ClockInterface;
```

```php
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChoreRepository $choreRepository,
        private readonly ClockInterface $clock,
        private readonly AppTimezone $appTimezone
    ) {
    }
```

In `createChore()`, replace `$chore->initializeNextDueAt();` with:

```php
        $chore->initializeNextDueAt($this->now());
```

In `markChoreDone()`, replace `$chore->markDone(new \DateTimeImmutable());` with:

```php
        $chore->markDone($this->now());
```

Add a private helper at the end of the class:

```php
    private function now(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone($this->appTimezone->get());
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd backend && docker compose exec -T php bin/phpunit tests/Unit/Service/ChoreServiceTest.php`
Expected: PASS.

- [ ] **Step 5: Run the chore functional tests (regression)**

Run: `cd backend && docker compose exec -T php bin/phpunit tests/Functional/Controller/ChoreControllerTest.php`
Expected: PASS. If any assertion now expects a UTC-derived date that shifted by the +05 anchoring, update that expectation to the Almaty calendar date and re-run. (Most assertions use relative/`assertNotEquals` checks and should be unaffected.)

- [ ] **Step 6: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add backend/src/Service/ChoreService.php backend/tests/Unit/Service/ChoreServiceTest.php
git commit -s -m "fix(tasks): schedule chores against the configured app timezone"
```

---

## Task 4: Per-type `schedule_value` validation

**Files:**
- Modify: `backend/src/Request/CreateChoreRequest.php`
- Modify: `backend/src/Request/UpdateChoreRequest.php`
- Modify: `backend/tests/Functional/Controller/ChoreControllerTest.php`

- [ ] **Step 1: Write the failing test**

In `backend/tests/Functional/Controller/ChoreControllerTest.php`, add a test asserting out-of-range values are rejected (mirror the existing create-validation test style in this file; the route is `POST /api/internal/v1/chores`):

```php
    public function testCreateChoreRejectsWeekdayOutOfRange(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/internal/v1/chores', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'name' => 'Bad weekly',
            'schedule_type' => 'fixed_weekly',
            'schedule_value' => 8,
        ]));

        static::assertResponseStatusCodeSame(422);
    }

    public function testCreateChoreRejectsMonthDayOutOfRange(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/internal/v1/chores', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'name' => 'Bad monthly',
            'schedule_type' => 'fixed_monthly',
            'schedule_value' => 31,
        ]));

        static::assertResponseStatusCodeSame(422);
    }
```

> Note: confirm the existing tests in this file use `static::createClient()` and the `server:`/`content:` named-arg shape; if they use `KernelBrowser` differently, match the existing pattern in the file.

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd backend && docker compose exec -T php bin/phpunit tests/Functional/Controller/ChoreControllerTest.php`
Expected: FAIL — both return 201 instead of 422 (only the `1..365` range is currently enforced).

- [ ] **Step 3: Implement per-type validation**

In **both** `backend/src/Request/CreateChoreRequest.php` and `backend/src/Request/UpdateChoreRequest.php`:

Add imports:

```php
use Symfony\Component\Validator\Context\ExecutionContextInterface;
```

On the `$schedule_value` property, remove the `#[Assert\Range(min: 1, max: 365)]` line (keep `#[Assert\NotBlank]` and `#[Assert\Positive]`).

Add this method to the class body (after the constructor):

```php
    #[Assert\Callback]
    public function validateScheduleValue(ExecutionContextInterface $context): void
    {
        $max = match ($this->schedule_type) {
            'fixed_weekly' => 7,
            'fixed_monthly' => 28,
            default => 365,
        };

        if ($this->schedule_value < 1 || $this->schedule_value > $max) {
            $context->buildViolation('schedule_value must be between 1 and {{ max }} for schedule_type "{{ type }}".')
                ->setParameter('{{ max }}', (string) $max)
                ->setParameter('{{ type }}', $this->schedule_type)
                ->atPath('schedule_value')
                ->addViolation();
        }
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd backend && docker compose exec -T php bin/phpunit tests/Functional/Controller/ChoreControllerTest.php`
Expected: PASS (new rejection tests + existing tests stay green — valid `interval`/`fixed_weekly`/`fixed_monthly` values within range are unaffected).

- [ ] **Step 5: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add backend/src/Request/CreateChoreRequest.php backend/src/Request/UpdateChoreRequest.php backend/tests/Functional/Controller/ChoreControllerTest.php
git commit -s -m "feat(tasks): validate schedule_value range per schedule type"
```

---

## Task 5: Wire the dashboard mark-done button

**Files:**
- Modify: `frontend/src/features/dashboard/DashboardPage.tsx`
- Modify: `frontend/src/features/dashboard/DashboardPage.test.tsx`

- [ ] **Step 1: Write the failing test**

In `frontend/src/features/dashboard/DashboardPage.test.tsx`, add a test that clicking a today-chore's "done" button fires the mark-done request. Match the file's existing render/MSW setup; add a `POST /api/internal/v1/chores/:id/done` handler and assert it is hit:

```tsx
import { http, HttpResponse } from "msw";
import { server } from "@/test/mocks/server";
import { createChoreResponse, wrapResponse } from "@/test/mocks/data";

it("marks a today chore done from the dashboard", async () => {
  const today = new Date().toISOString();
  const chore = createChoreResponse({ id: "chore-1", name: "Полить цветы", next_due_at: today });
  let doneCalled = false;

  server.use(
    http.get("*/api/internal/v1/chores", () => HttpResponse.json(wrapResponse([chore]))),
    http.post("*/api/internal/v1/chores/chore-1/done", () => {
      doneCalled = true;
      return HttpResponse.json(wrapResponse(chore));
    }),
  );

  renderWithProviders(<DashboardPage />); // use this file's existing render helper

  const doneButton = await screen.findByRole("button", { name: /Выполнено/i });
  await userEvent.click(doneButton);

  await waitFor(() => expect(doneCalled).toBe(true));
});
```

> Adapt `renderWithProviders`, imports, and the `wrapResponse`/`createChoreResponse` signatures to whatever `DashboardPage.test.tsx` and `src/test/mocks/data.ts` already export.

- [ ] **Step 2: Run test to verify it fails**

Run: `cd frontend && bun run test:run src/features/dashboard/DashboardPage.test.tsx`
Expected: FAIL — `doneCalled` stays `false` (button has no handler).

- [ ] **Step 3: Implement the handler**

In `frontend/src/features/dashboard/DashboardPage.tsx`:

Add the hook import alongside `useChores`:

```tsx
import { useChores, useMarkChoreDone } from "../../api/queries/chores";
```

Inside the component, after the other query hooks, add:

```tsx
  const markChoreDone = useMarkChoreDone();
```

Wire the button (currently around line 212) by adding the click handler:

```tsx
                    <button
                      type="button"
                      onClick={() => markChoreDone.mutate(chore.id)}
                      disabled={markChoreDone.isPending}
                      className="px-3 py-1 bg-green-500 text-white rounded-lg text-sm hover:bg-green-600 transition-colors disabled:opacity-50"
                    >
                      {t("dashboard.completed")}
                    </button>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd frontend && bun run test:run src/features/dashboard/DashboardPage.test.tsx`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add frontend/src/features/dashboard/DashboardPage.tsx frontend/src/features/dashboard/DashboardPage.test.tsx
git commit -s -m "fix(tasks): wire dashboard chore done button to mark-done mutation"
```

---

## Task 6: Fix nested `<button>` markup in cards

**Files:**
- Modify: `frontend/src/features/tasks/components/TaskCard.tsx`
- Modify: `frontend/src/features/tasks/components/ChoreCard.tsx`
- Modify: `frontend/src/features/tasks/components/TaskCard.test.tsx`
- Modify: `frontend/src/features/tasks/components/ChoreCard.test.tsx`

- [ ] **Step 1: Write the failing test**

In `frontend/src/features/tasks/components/TaskCard.test.tsx`, add a keyboard-activation test (and keep existing tests):

```tsx
it("opens the card on Space key without nesting buttons", async () => {
  const onClick = vi.fn();
  render(
    <TaskCard
      task={createTaskResponse({ id: "t1", name: "Купить хлеб" })}
      onToggleDone={vi.fn()}
      onClick={onClick}
    />,
  );

  const card = screen.getByRole("button", { name: /Купить хлеб/i });
  card.focus();
  await userEvent.keyboard("[Space]");

  expect(onClick).toHaveBeenCalledTimes(1);
});
```

Add the equivalent test in `ChoreCard.test.tsx` using `createChoreResponse` and the chore name.

- [ ] **Step 2: Run test to verify it fails**

Run: `cd frontend && bun run test:run src/features/tasks/components/TaskCard.test.tsx src/features/tasks/components/ChoreCard.test.tsx`
Expected: FAIL — Space does not trigger `onClick` (only Enter is handled on the current `<button>`).

- [ ] **Step 3: Implement the markup fix**

In `frontend/src/features/tasks/components/TaskCard.tsx`, replace the outer `<button ...>` opening tag (lines ~32-37) and its closing `</button>` (line ~71) with a `div role="button"`:

```tsx
    <div
      role="button"
      tabIndex={0}
      className="bg-white rounded-xl p-4 shadow-sm border border-stone-200 hover:border-amber-400 transition-colors cursor-pointer w-full text-left"
      onClick={() => onClick(task)}
      onKeyDown={(e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          onClick(task);
        }
      }}
    >
```

Change the matching closing `</button>` (the outermost one, line ~71) to `</div>`. The inner toggle `<button>` stays unchanged.

In `frontend/src/features/tasks/components/ChoreCard.tsx`, apply the same change to the outer `<button>` (lines ~63-74) → `<div role="button" tabIndex={0} ...>` keeping its existing `className` template literal and `onClick={() => onClick(chore)}`, with:

```tsx
      onKeyDown={(e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          onClick(chore);
        }
      }}
```

and change its outermost closing `</button>` (line ~96) to `</div>`. The inner mark-done `<button>` stays unchanged.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd frontend && bun run test:run src/features/tasks/components/TaskCard.test.tsx src/features/tasks/components/ChoreCard.test.tsx`
Expected: PASS, with no `<button> cannot be a descendant of <button>` warning in output.

- [ ] **Step 5: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add frontend/src/features/tasks/components/TaskCard.tsx frontend/src/features/tasks/components/ChoreCard.tsx frontend/src/features/tasks/components/TaskCard.test.tsx frontend/src/features/tasks/components/ChoreCard.test.tsx
git commit -s -m "fix(tasks): use role=button cards to avoid nested interactive elements"
```

---

## Task 7: Fail tests on `console.error`

**Files:**
- Modify: `frontend/src/test/setup.ts`

- [ ] **Step 1: Add the guard**

In `frontend/src/test/setup.ts`, add `beforeEach`/`afterEach` from vitest and install a throwing spy. Final file:

```ts
import "@testing-library/jest-dom/vitest";
import { cleanup } from "@testing-library/react";
import { afterAll, afterEach, beforeAll, beforeEach, vi } from "vitest";
import i18n from "@/i18n";
import { server } from "./mocks/server";

let consoleError: ReturnType<typeof vi.spyOn>;

beforeAll(() => {
  server.listen({ onUnhandledRequest: "error" });
  i18n.changeLanguage("ru");
});

beforeEach(() => {
  consoleError = vi.spyOn(console, "error").mockImplementation((...args) => {
    throw new Error(`Unexpected console.error in test: ${args.join(" ")}`);
  });
});

afterEach(() => {
  cleanup();
  server.resetHandlers();
  consoleError.mockRestore();
});

afterAll(() => server.close());
```

- [ ] **Step 2: Run the FULL frontend suite and fix fallout**

Run: `cd frontend && bun run test:run`
Expected: PASS for all files. If a test legitimately exercises an error path that logs via `console.error` (e.g. a deliberate query failure), fix the root cause if it is a real warning; if the log is intentional and benign, scope a local `vi.spyOn(console, "error").mockImplementation(() => {})` within that specific test rather than weakening the global guard. Do not disable the global guard.

- [ ] **Step 3: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add frontend/src/test/setup.ts
git commit -s -m "test(tasks): fail tests on unexpected console.error"
```

---

## Task 8: Align ChoreForm monthly max to 28

**Files:**
- Modify: `frontend/src/features/tasks/components/ChoreForm.tsx` (line ~126)

- [ ] **Step 1: Change the max**

In `frontend/src/features/tasks/components/ChoreForm.tsx`, the numeric input `max` attribute (line ~126):

```tsx
            max={scheduleType === "fixed_monthly" ? 28 : 365}
```

- [ ] **Step 2: Verify the form tests still pass**

Run: `cd frontend && bun run test:run src/features/tasks/components/ChoreForm.test.tsx`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add frontend/src/features/tasks/components/ChoreForm.tsx
git commit -s -m "fix(tasks): cap chore monthly day input at 28 to match backend"
```

---

## Task 9: Grouping helper for sectioning

**Files:**
- Create: `frontend/src/features/tasks/grouping.ts`
- Create: `frontend/src/features/tasks/grouping.test.ts`

- [ ] **Step 1: Write the failing test**

Create `frontend/src/features/tasks/grouping.test.ts`:

```ts
import { describe, expect, it } from "vitest";
import {
  createChoreResponse,
  createTaskResponse,
} from "@/test/mocks/data";
import { groupChores, groupTasks } from "./grouping";

function isoDaysFromToday(days: number): string {
  const d = new Date();
  d.setUTCHours(0, 0, 0, 0);
  d.setUTCDate(d.getUTCDate() + days);
  return d.toISOString();
}

describe("groupChores", () => {
  it("splits overdue from upcoming by next_due_at", () => {
    const overdue = createChoreResponse({ id: "c1", next_due_at: isoDaysFromToday(-1) });
    const today = createChoreResponse({ id: "c2", next_due_at: isoDaysFromToday(0) });
    const later = createChoreResponse({ id: "c3", next_due_at: isoDaysFromToday(3) });

    const result = groupChores([overdue, today, later]);

    expect(result.overdue.map((c) => c.id)).toEqual(["c1"]);
    expect(result.upcoming.map((c) => c.id)).toEqual(["c2", "c3"]);
  });
});

describe("groupTasks", () => {
  it("splits overdue, active, and completed", () => {
    const overdue = createTaskResponse({ id: "t1", due_date: isoDaysFromToday(-2), done: false });
    const active = createTaskResponse({ id: "t2", due_date: isoDaysFromToday(2), done: false });
    const noDue = createTaskResponse({ id: "t3", due_date: null, done: false });
    const done = createTaskResponse({ id: "t4", done: true });

    const result = groupTasks([overdue, active, noDue], [done]);

    expect(result.overdue.map((t) => t.id)).toEqual(["t1"]);
    expect(result.active.map((t) => t.id)).toEqual(["t2", "t3"]);
    expect(result.completed.map((t) => t.id)).toEqual(["t4"]);
  });
});
```

> Confirm `createChoreResponse`/`createTaskResponse` in `src/test/mocks/data.ts` accept `next_due_at`/`due_date`/`done` overrides; if a field name differs, match the factory.

- [ ] **Step 2: Run test to verify it fails**

Run: `cd frontend && bun run test:run src/features/tasks/grouping.test.ts`
Expected: FAIL — `./grouping` module does not exist.

- [ ] **Step 3: Implement the helper**

Create `frontend/src/features/tasks/grouping.ts`:

```ts
import type { ChoreResponse, TaskResponse } from "../../api/generated/models";
import { getDaysUntil } from "../../data/types";

export interface GroupedChores {
  overdue: ChoreResponse[];
  upcoming: ChoreResponse[];
}

export interface GroupedTasks {
  overdue: TaskResponse[];
  active: TaskResponse[];
  completed: TaskResponse[];
}

export function groupChores(chores: ChoreResponse[]): GroupedChores {
  const overdue: ChoreResponse[] = [];
  const upcoming: ChoreResponse[] = [];

  for (const chore of chores) {
    if (getDaysUntil(chore.next_due_at) < 0) {
      overdue.push(chore);
    } else {
      upcoming.push(chore);
    }
  }

  return { overdue, upcoming };
}

export function groupTasks(
  activeTasks: TaskResponse[],
  completedTasks: TaskResponse[],
): GroupedTasks {
  const overdue: TaskResponse[] = [];
  const active: TaskResponse[] = [];

  for (const task of activeTasks) {
    if (task.due_date && getDaysUntil(task.due_date) < 0) {
      overdue.push(task);
    } else {
      active.push(task);
    }
  }

  return { overdue, active, completed: completedTasks };
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd frontend && bun run test:run src/features/tasks/grouping.test.ts`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add frontend/src/features/tasks/grouping.ts frontend/src/features/tasks/grouping.test.ts
git commit -s -m "feat(tasks): add pure grouping helper for overdue/active/completed sections"
```

---

## Task 10: Render sections on TasksPage

**Files:**
- Modify: `frontend/src/features/tasks/TasksPage.tsx`
- Modify: `frontend/src/i18n/locales/en.json`
- Modify: `frontend/src/i18n/locales/ru.json`
- Modify: `frontend/src/features/tasks/TasksPage.test.tsx`

- [ ] **Step 1: Add i18n section keys**

In `frontend/src/i18n/locales/ru.json`, under `tasks.chores` add `"sections": { "overdue": "Просрочено", "upcoming": "Предстоящие" }`, and under `tasks.items` add `"sections": { "overdue": "Просрочено", "active": "Активные" }`. In `frontend/src/i18n/locales/en.json` add the same paths with `"Overdue"`, `"Upcoming"`, `"Overdue"`, `"Active"`. (Keep existing `tasks.items.completed`.)

- [ ] **Step 2: Write the failing test**

In `frontend/src/features/tasks/TasksPage.test.tsx`, add a test asserting an overdue chore renders under an "Просрочено" heading. Use the file's existing render helper and MSW pattern; serve one overdue chore and one upcoming chore:

```tsx
it("renders an Overdue section for overdue chores", async () => {
  const overdue = createChoreResponse({ id: "c1", name: "Пропылесосить", next_due_at: pastIso() });
  const upcoming = createChoreResponse({ id: "c2", name: "Помыть пол", next_due_at: futureIso() });

  server.use(
    http.get("*/api/internal/v1/chores", () =>
      HttpResponse.json(wrapResponse([overdue, upcoming])),
    ),
  );

  renderTasksPage(); // file's existing helper

  expect(await screen.findByText("Просрочено")).toBeInTheDocument();
  expect(screen.getByText("Пропылесосить")).toBeInTheDocument();
});
```

Define `pastIso()`/`futureIso()` inline like the `isoDaysFromToday` helper in Task 9, or import a shared helper if the test file already has one.

- [ ] **Step 3: Run test to verify it fails**

Run: `cd frontend && bun run test:run src/features/tasks/TasksPage.test.tsx`
Expected: FAIL — no "Просрочено" heading is rendered.

- [ ] **Step 4: Implement sectioning**

In `frontend/src/features/tasks/TasksPage.tsx`:

Add the import:

```tsx
import { groupChores, groupTasks } from "./grouping";
```

After the hooks/handlers and before `return`, compute groups:

```tsx
  const choreGroups = groupChores(chores);
  const taskGroups = groupTasks(activeTasks, completedTasks);
```

Replace the chores list block (the `<div className="space-y-3">` containing `chores.map(...)`, lines ~189-201) with overdue + upcoming sections:

```tsx
          <div className="space-y-4">
            {choreGroups.overdue.length > 0 && (
              <div className="space-y-3">
                <h4 className="text-sm font-medium text-red-600">
                  {t("tasks.chores.sections.overdue")}
                </h4>
                {choreGroups.overdue.map((chore) => (
                  <ChoreCard
                    key={chore.id}
                    chore={chore}
                    onMarkDone={handleMarkChoreDone}
                    onClick={setEditingChore}
                  />
                ))}
              </div>
            )}
            <div className="space-y-3">
              {choreGroups.overdue.length > 0 && (
                <h4 className="text-sm font-medium text-stone-500">
                  {t("tasks.chores.sections.upcoming")}
                </h4>
              )}
              {choreGroups.upcoming.map((chore) => (
                <ChoreCard
                  key={chore.id}
                  chore={chore}
                  onMarkDone={handleMarkChoreDone}
                  onClick={setEditingChore}
                />
              ))}
            </div>
            {chores.length === 0 && (
              <p className="text-stone-500 text-sm">{t("common.noItems")}</p>
            )}
          </div>
```

Replace the active-tasks list block (the `<div className="space-y-3">` containing `activeTasks.map(...)`, lines ~218-230) with overdue + active sections:

```tsx
          <div className="space-y-4">
            {taskGroups.overdue.length > 0 && (
              <div className="space-y-3">
                <h4 className="text-sm font-medium text-red-600">
                  {t("tasks.items.sections.overdue")}
                </h4>
                {taskGroups.overdue.map((task) => (
                  <TaskCard
                    key={task.id}
                    task={task}
                    onToggleDone={handleToggleTaskDone}
                    onClick={setEditingTask}
                  />
                ))}
              </div>
            )}
            <div className="space-y-3">
              {taskGroups.overdue.length > 0 && (
                <h4 className="text-sm font-medium text-stone-500">
                  {t("tasks.items.sections.active")}
                </h4>
              )}
              {taskGroups.active.map((task) => (
                <TaskCard
                  key={task.id}
                  task={task}
                  onToggleDone={handleToggleTaskDone}
                  onClick={setEditingTask}
                />
              ))}
            </div>
            {activeTasks.length === 0 && (
              <p className="text-stone-500 text-sm">{t("common.noItems")}</p>
            )}
          </div>
```

Leave the existing "completed" block (lines ~232-248) as-is — it already renders `completedTasks` faded under `tasks.items.completed`.

- [ ] **Step 5: Run test to verify it passes**

Run: `cd frontend && bun run test:run src/features/tasks/TasksPage.test.tsx`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add frontend/src/features/tasks/TasksPage.tsx frontend/src/i18n/locales/en.json frontend/src/i18n/locales/ru.json frontend/src/features/tasks/TasksPage.test.tsx
git commit -s -m "feat(tasks): render overdue/active/completed sections on tasks page"
```

---

## Task 11: Full verification gates

**Files:** none (verification only)

- [ ] **Step 1: Frontend check + tests**

Run: `cd frontend && bun run check && bun run test:run`
Expected: biome + `tsc --noEmit` clean; all test files PASS with no console.error failures.

- [ ] **Step 2: Backend lint (host) + tests (docker)**

Run: `cd backend && make lint`
Expected: mago/rector/phpstan clean (PHPStan level 6, no baseline).
Run: `cd backend && make test`
Expected: full backend suite PASS.

- [ ] **Step 3: Fix any failures**

If either gate is red, fix the root cause and re-run that gate until green. Do not proceed past a red gate.

- [ ] **Step 4: Final confirmation commit (only if fixes were needed)**

```bash
cd /home/pavel/projects/personal/hestia
git add -A
git commit -s -m "chore(tasks): satisfy lint and test gates for branch completion"
```

- [ ] **Step 5: Report status**

Summarize: which gates passed, test counts, and confirm the branch is ready to update PR #23. Do not push or merge unless the user asks.

---

## Self-Review Notes

- **Spec coverage:** §1 timezone → Tasks 1+3; §1 guard test → Task 1; §2 month-end clamp + per-type validation + form max → Tasks 2, 4, 8; §3 dashboard button → Task 5; §4 nested buttons + console.error guard → Tasks 6, 7; §5 sectioning → Tasks 9, 10; §6 gates → Task 11. All covered.
- **Type consistency:** `AppTimezone::get()` used in Tasks 1/3; `groupChores`/`groupTasks` defined in Task 9 and consumed in Task 10 with matching `{overdue, upcoming}` / `{overdue, active, completed}` shapes.
- **Known adaptation points (flagged inline):** exact MSW/render helpers and factory field names in frontend test files, and the `ChoreControllerTest` client-call shape — match the existing patterns in those files rather than assuming.
