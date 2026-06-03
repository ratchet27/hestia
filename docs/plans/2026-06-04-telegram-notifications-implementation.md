# Telegram Daily Expiry Summary — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An outbound-only Telegram bot that posts a daily summary of expired / expiring-soon stock to a shared household chat, scheduled at a configurable time.

**Architecture:** `symfony/scheduler` dispatches an empty `SendDailyExpirySummary` message daily (cron from `TELEGRAM_DAILY_SUMMARY_TIME`, Asia/Almaty); it's routed to the existing `async` Messenger transport; `SendDailyExpirySummaryHandler` reads `StockEntryRepository::findExpiring(3)`, hands the entries to `ExpirySummaryBuilder` (pure logic → RU HTML or `null`), and on a non-null result sends via `TelegramSender` (wraps `ChatterInterface`). Failures inherit the existing async retry (3×) → `failed` transport.

**Tech Stack:** PHP 8.4 / Symfony 8.0 · symfony/notifier + symfony/telegram-notifier · symfony/scheduler · symfony/messenger (+ RabbitMQ) · symfony/clock · PHPUnit 13 + Zenstruck Foundry.

**Design reference:** `docs/plans/2026-06-04-telegram-notifications-design.md`

---

## Conventions (read before starting)

- **Backend commands run in Docker:** `cd backend && docker compose exec -T php <cmd>`. Never run `php`/`composer`/`bin/console`/`bin/phpunit` on the host.
- **`make lint` is the gate** (`rector → mago format → mago lint → mago analyze → phpstan`, host mago). Run it before claiming a task done — NOT a subset. mago runs on the HOST.
- Stage files explicitly by path; never `git add -A`. `config/reference.php` is generated + gitignored — ignore it.
- Commits: `git commit -s -m "<type>(telegram): <desc>"`.
- Work on branch `feature/telegram-notifications` (already created).
- App name in user-facing copy is **Гестия** (Cyrillic Г) — never "Хестия".

## File Structure

**Create:**
- `src/Message/SendDailyExpirySummary.php` — empty marker message (the scheduled "fire" signal).
- `src/Service/Telegram/ExpirySummaryBuilder.php` — pure: `StockEntry[]` → `?string` (RU HTML or `null`). Injects `ClockInterface` + `AppTimezone`. The testable heart.
- `src/Service/Telegram/TelegramSender.php` — thin wrapper over `ChatterInterface`.
- `src/MessageHandler/SendDailyExpirySummaryHandler.php` — repo → builder → sender; no-op on `null`.
- `src/Schedule/MainSchedule.php` — `#[AsSchedule('main')]` provider; daily cron from env.
- Tests under `tests/Unit/Service/Telegram/`, `tests/Unit/MessageHandler/`, `tests/Unit/Schedule/`.

**Modify:**
- `config/packages/notifier.yaml` (Flex-created) — bind the `telegram` chatter transport to `TELEGRAM_DSN`.
- `config/packages/messenger.yaml` — route `SendDailyExpirySummary` to `async`.
- `.env` — `TELEGRAM_DSN`, `TELEGRAM_DAILY_SUMMARY_TIME` placeholders.
- `compose.yaml` — the `messenger` worker also consumes `scheduler_main`.
- `composer.json` — add the three Symfony packages + cron-expression (Flex).

---

## Task 1: Install packages + config + env

**Files:** `composer.json`, `config/packages/notifier.yaml`, `config/packages/messenger.yaml`, `.env`, `compose.yaml`

- [ ] **Step 1: Install (in Docker)**

```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php composer require symfony/notifier symfony/telegram-notifier symfony/scheduler dragonmantank/cron-expression
```

Expected: packages added; Flex creates `config/packages/notifier.yaml`. (`dragonmantank/cron-expression` backs the scheduler's cron triggers.)

- [ ] **Step 2: Configure the chatter transport**

Overwrite `config/packages/notifier.yaml` with:

```yaml
framework:
    notifier:
        chatter_transports:
            telegram: '%env(TELEGRAM_DSN)%'
```

- [ ] **Step 3: Route the scheduled message to async**

In `config/packages/messenger.yaml`, add the new message under `routing` (alongside `StockChangedMessage`):

```yaml
        routing:
            'App\Message\StockChangedMessage': async
            'App\Message\SendDailyExpirySummary': async
```

- [ ] **Step 4: Add env placeholders**

Append to `.env`:

```bash
###> symfony/telegram-notifier ###
# Real token + chat id go in .env.local (gitignored). Format:
# telegram://<BOT_TOKEN>@default?channel=<CHAT_ID>
TELEGRAM_DSN=telegram://TOKEN@default?channel=CHATID
###< symfony/telegram-notifier ###

# Daily expiry summary send time (HH:MM, Asia/Almaty)
TELEGRAM_DAILY_SUMMARY_TIME=08:30
```

- [ ] **Step 5: Make the worker consume the scheduler transport**

In `compose.yaml`, change the `messenger` service `command` to also consume `scheduler_main`:

```yaml
    command: bin/console messenger:consume async scheduler_main --time-limit=3600 -vv
```

- [ ] **Step 6: Verify the container boots**

```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/console lint:container
docker compose exec -T php bin/console debug:config framework notifier 2>&1 | head -20
```

Expected: `lint:container` passes; the telegram chatter transport shows up. (The `scheduler_main` transport won't exist until Task 6 adds the schedule — that's fine.)

- [ ] **Step 7: Commit**

```bash
cd /home/pavel/projects/personal/hestia/backend && mago format
git add composer.json composer.lock symfony.lock config/packages/notifier.yaml config/packages/messenger.yaml .env compose.yaml
git commit -s -m "chore(telegram): install notifier/scheduler, configure telegram transport"
```

---

## Task 2: SendDailyExpirySummary message

**Files:** Create `src/Message/SendDailyExpirySummary.php`

- [ ] **Step 1: Create the marker message** (mirrors `src/Message/StockChangedMessage.php`, but empty — it carries no data; it just triggers the handler)

```php
<?php

declare(strict_types = 1);

namespace App\Message;

/**
 * Scheduled trigger for the daily expiry summary. Carries no payload —
 * the handler reads current stock when it runs.
 */
final readonly class SendDailyExpirySummary
{
}
```

- [ ] **Step 2: Lint + commit**

```bash
cd /home/pavel/projects/personal/hestia/backend && make lint
git add src/Message/SendDailyExpirySummary.php
git commit -s -m "feat(telegram): add SendDailyExpirySummary message"
```

Expected: `make lint` clean. (Stage explicitly; ignore `config/reference.php`.)

---

## Task 3: ExpirySummaryBuilder (the core — TDD)

**Files:** Create `src/Service/Telegram/ExpirySummaryBuilder.php`; Test `tests/Unit/Service/Telegram/ExpirySummaryBuilderTest.php`

**Context:** `StockEntry` has `getProduct()->getName()`, `getLocation()->getName()`, `getBestBefore(): ?\DateTimeImmutable` (a DATE). `findExpiring(3)` returns entries with `bestBefore <= now+3d`, including already-expired, ordered by `bestBefore ASC`. The builder splits them by today (local) into **expired** (`bestBefore < today`) and **expiring-soon** (`today <= bestBefore`), formats each line with a relative date, and returns RU HTML — or `null` when there's nothing.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Service/Telegram/ExpirySummaryBuilderTest.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Service\Telegram;

use App\Entity\Location;
use App\Entity\Product;
use App\Entity\StockEntry;
use App\Service\Telegram\ExpirySummaryBuilder;
use App\Service\Time\AppTimezone;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ExpirySummaryBuilderTest extends TestCase
{
    private function builderAt(string $utc): ExpirySummaryBuilder
    {
        // Clock is UTC; AppTimezone converts to Asia/Almaty (+05) for "today".
        return new ExpirySummaryBuilder(new MockClock(new \DateTimeImmutable($utc)), new AppTimezone());
    }

    private function entry(string $product, string $location, string $bestBefore): StockEntry
    {
        return (new StockEntry())
            ->setProduct((new Product())->setName($product))
            ->setLocation((new Location())->setName($location))
            ->setBestBefore(new \DateTimeImmutable($bestBefore));
    }

    public function testReturnsNullWhenNoEntries(): void
    {
        self::assertNull($this->builderAt('2026-06-04 04:00:00')->build([]));
    }

    public function testBuildsBothSectionsWithRelativeDates(): void
    {
        // Local "today" = 2026-06-04 (09:00 Almaty).
        $builder = $this->builderAt('2026-06-04 04:00:00');

        $text = $builder->build([
            $this->entry('Молоко', 'Холодильник', '2026-06-02'),   // 2 дн. назад
            $this->entry('Сметана', 'Холодильник', '2026-06-03'),  // вчера
            $this->entry('Йогурт', 'Холодильник', '2026-06-04'),   // сегодня
            $this->entry('Хлеб', 'Кладовая', '2026-06-06'),        // через 2 дн.
        ]);

        self::assertNotNull($text);
        self::assertStringContainsString('🏠 Гестия — сводка на 04.06', $text);
        self::assertStringContainsString('⚠️ Просрочено', $text);
        self::assertStringContainsString('Молоко (Холодильник) — 2 дн. назад', $text);
        self::assertStringContainsString('Сметана (Холодильник) — вчера', $text);
        self::assertStringContainsString('🔔 Скоро истекает', $text);
        self::assertStringContainsString('Йогурт (Холодильник) — сегодня', $text);
        self::assertStringContainsString('Хлеб (Кладовая) — через 2 дн.', $text);
        // App name is Гестия, never Хестия.
        self::assertStringNotContainsString('Хестия', $text);
    }

    public function testOmitsExpiredSectionWhenNoneExpired(): void
    {
        $builder = $this->builderAt('2026-06-04 04:00:00');

        $text = $builder->build([$this->entry('Йогурт', 'Холодильник', '2026-06-05')]); // завтра

        self::assertNotNull($text);
        self::assertStringNotContainsString('⚠️ Просрочено', $text);
        self::assertStringContainsString('🔔 Скоро истекает', $text);
        self::assertStringContainsString('Йогурт (Холодильник) — завтра', $text);
    }

    public function testEscapesHtmlSpecialCharsInNames(): void
    {
        $builder = $this->builderAt('2026-06-04 04:00:00');

        $text = $builder->build([$this->entry('Сок <Rich & Co>', 'Кухня', '2026-06-04')]);

        self::assertNotNull($text);
        self::assertStringContainsString('Сок &lt;Rich &amp; Co&gt; (Кухня) — сегодня', $text);
    }
}
```

- [ ] **Step 2: Run it — verify it fails**

```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/phpunit tests/Unit/Service/Telegram/ExpirySummaryBuilderTest.php
```

Expected: FAIL (`Class "App\Service\Telegram\ExpirySummaryBuilder" not found`).

- [ ] **Step 3: Implement the builder**

Create `src/Service/Telegram/ExpirySummaryBuilder.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Service\Telegram;

use App\Entity\StockEntry;
use App\Service\Time\AppTimezone;
use Symfony\Component\Clock\ClockInterface;

final readonly class ExpirySummaryBuilder
{
    public function __construct(
        private ClockInterface $clock,
        private AppTimezone $appTimezone
    ) {
    }

    /**
     * @param StockEntry[] $entries entries with bestBefore <= today + window (expired included)
     */
    public function build(array $entries): ?string
    {
        $today = $this->today();

        $expired = [];
        $soon = [];
        foreach ($entries as $entry) {
            $bestBefore = $entry->getBestBefore();
            if ($bestBefore === null) {
                continue;
            }

            $days = $this->dayDelta($today, $bestBefore);
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

    private function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone($this->appTimezone->get());
    }

    /** Signed whole-day difference: bestBefore date minus today's date (negative = past). */
    private function dayDelta(\DateTimeImmutable $today, \DateTimeImmutable $bestBefore): int
    {
        $a = new \DateTimeImmutable($today->format('Y-m-d'));
        $b = new \DateTimeImmutable($bestBefore->format('Y-m-d'));

        return (int) $a->diff($b)->format('%r%a');
    }

    private function relative(int $days): string
    {
        return match (true) {
            $days <= -2 => sprintf('%d дн. назад', -$days),
            $days === -1 => 'вчера',
            $days === 0 => 'сегодня',
            $days === 1 => 'завтра',
            default => sprintf('через %d дн.', $days),
        };
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
```

- [ ] **Step 4: Run it — verify it passes**

```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/phpunit tests/Unit/Service/Telegram/ExpirySummaryBuilderTest.php
```

Expected: PASS (4 tests).

- [ ] **Step 5: Lint + commit**

```bash
cd /home/pavel/projects/personal/hestia/backend && make lint
git add src/Service/Telegram/ExpirySummaryBuilder.php tests/Unit/Service/Telegram/ExpirySummaryBuilderTest.php
git commit -s -m "feat(telegram): expiry summary builder (RU, relative dates, HTML-escaped)"
```

---

## Task 4: TelegramSender (TDD)

**Files:** Create `src/Service/Telegram/TelegramSender.php`; Test `tests/Unit/Service/Telegram/TelegramSenderTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Service/Telegram/TelegramSenderTest.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Service\Telegram;

use App\Service\Telegram\TelegramSender;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

final class TelegramSenderTest extends TestCase
{
    public function testSendsChatMessageWithGivenText(): void
    {
        $chatter = $this->createMock(ChatterInterface::class);
        $chatter->expects(self::once())
            ->method('send')
            ->with(self::callback(static fn(ChatMessage $m): bool => $m->getSubject() === 'hello'));

        new TelegramSender($chatter)->send('hello');
    }
}
```

- [ ] **Step 2: Run it — verify it fails**

```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/phpunit tests/Unit/Service/Telegram/TelegramSenderTest.php
```

Expected: FAIL (class not found).

- [ ] **Step 3: Implement**

Create `src/Service/Telegram/TelegramSender.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Service\Telegram;

use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;

final readonly class TelegramSender
{
    public function __construct(private ChatterInterface $chatter)
    {
    }

    public function send(string $message): void
    {
        $chatMessage = new ChatMessage($message, (new TelegramOptions())->parseMode('HTML'));
        $chatMessage->transport('telegram');

        // Exceptions propagate so Messenger's async retry (3x) + failed transport handle delivery.
        $this->chatter->send($chatMessage);
    }
}
```

- [ ] **Step 4: Run it — verify it passes**

```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/phpunit tests/Unit/Service/Telegram/TelegramSenderTest.php
```

Expected: PASS.

- [ ] **Step 5: Lint + commit**

```bash
cd /home/pavel/projects/personal/hestia/backend && make lint
git add src/Service/Telegram/TelegramSender.php tests/Unit/Service/Telegram/TelegramSenderTest.php
git commit -s -m "feat(telegram): TelegramSender wrapping ChatterInterface"
```

---

## Task 5: SendDailyExpirySummaryHandler (TDD)

**Files:** Create `src/MessageHandler/SendDailyExpirySummaryHandler.php`; Test `tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\SendDailyExpirySummary;
use App\MessageHandler\SendDailyExpirySummaryHandler;
use App\Repository\StockEntryRepository;
use App\Service\Telegram\ExpirySummaryBuilder;
use App\Service\Telegram\TelegramSender;
use PHPUnit\Framework\TestCase;

final class SendDailyExpirySummaryHandlerTest extends TestCase
{
    public function testSendsWhenSummaryNotEmpty(): void
    {
        $repo = $this->createMock(StockEntryRepository::class);
        $repo->method('findExpiring')->with(3)->willReturn([]);

        $builder = $this->createMock(ExpirySummaryBuilder::class);
        $builder->method('build')->willReturn('summary text');

        $sender = $this->createMock(TelegramSender::class);
        $sender->expects(self::once())->method('send')->with('summary text');

        new SendDailyExpirySummaryHandler($repo, $builder, $sender)(new SendDailyExpirySummary());
    }

    public function testSendsNothingWhenSummaryIsNull(): void
    {
        $repo = $this->createMock(StockEntryRepository::class);
        $repo->method('findExpiring')->with(3)->willReturn([]);

        $builder = $this->createMock(ExpirySummaryBuilder::class);
        $builder->method('build')->willReturn(null);

        $sender = $this->createMock(TelegramSender::class);
        $sender->expects(self::never())->method('send');

        new SendDailyExpirySummaryHandler($repo, $builder, $sender)(new SendDailyExpirySummary());
    }
}
```

- [ ] **Step 2: Run it — verify it fails**

```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/phpunit tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php
```

Expected: FAIL (class not found).

- [ ] **Step 3: Implement** (mirrors `src/MessageHandler/StockChangedHandler.php`)

Create `src/MessageHandler/SendDailyExpirySummaryHandler.php`:

```php
<?php

declare(strict_types = 1);

namespace App\MessageHandler;

use App\Message\SendDailyExpirySummary;
use App\Repository\StockEntryRepository;
use App\Service\Telegram\ExpirySummaryBuilder;
use App\Service\Telegram\TelegramSender;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendDailyExpirySummaryHandler
{
    private const int WINDOW_DAYS = 3;

    public function __construct(
        private StockEntryRepository $stockEntryRepository,
        private ExpirySummaryBuilder $builder,
        private TelegramSender $sender
    ) {
    }

    public function __invoke(SendDailyExpirySummary $message): void
    {
        $entries = $this->stockEntryRepository->findExpiring(self::WINDOW_DAYS);
        $summary = $this->builder->build($entries);

        if ($summary === null) {
            return;
        }

        $this->sender->send($summary);
    }
}
```

- [ ] **Step 4: Run it — verify it passes**

```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/phpunit tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php
```

Expected: PASS (2 tests).

- [ ] **Step 5: Lint + commit**

```bash
cd /home/pavel/projects/personal/hestia/backend && make lint
git add src/MessageHandler/SendDailyExpirySummaryHandler.php tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php
git commit -s -m "feat(telegram): handler wiring repo -> builder -> sender"
```

---

## Task 6: MainSchedule (TDD)

**Files:** Create `src/Schedule/MainSchedule.php`; Test `tests/Unit/Schedule/MainScheduleTest.php`

**Context:** `symfony/scheduler` discovers `#[AsSchedule('main')]` providers and creates a `scheduler_main` transport (the worker consumes it — wired in Task 1 Step 5). `RecurringMessage::cron($expr, $message, $timezone)` builds a cron trigger. The provider parses `HH:MM` from `TELEGRAM_DAILY_SUMMARY_TIME` into `m H * * *`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Schedule/MainScheduleTest.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Schedule;

use App\Message\SendDailyExpirySummary;
use App\Schedule\MainSchedule;
use App\Service\Time\AppTimezone;
use PHPUnit\Framework\TestCase;

final class MainScheduleTest extends TestCase
{
    public function testScheduleHasDailySummaryAtConfiguredTime(): void
    {
        $schedule = new MainSchedule('08:30', new AppTimezone())->getSchedule();

        $messages = $schedule->getRecurringMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(SendDailyExpirySummary::class, $messages[0]->getMessage());
        self::assertStringContainsString('30 8 * * *', (string) $messages[0]->getTrigger());
    }
}
```

- [ ] **Step 2: Run it — verify it fails**

```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/phpunit tests/Unit/Schedule/MainScheduleTest.php
```

Expected: FAIL (class not found).

- [ ] **Step 3: Implement**

Create `src/Schedule/MainSchedule.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Schedule;

use App\Message\SendDailyExpirySummary;
use App\Service\Time\AppTimezone;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('main')]
final class MainSchedule implements ScheduleProviderInterface
{
    public function __construct(
        #[Autowire('%env(TELEGRAM_DAILY_SUMMARY_TIME)%')]
        private readonly string $dailySummaryTime,
        private readonly AppTimezone $appTimezone
    ) {
    }

    public function getSchedule(): Schedule
    {
        [$hour, $minute] = array_map('intval', explode(':', $this->dailySummaryTime));
        $cron = sprintf('%d %d * * *', $minute, $hour);

        return new Schedule()->add(
            RecurringMessage::cron($cron, new SendDailyExpirySummary(), $this->appTimezone->get())
        );
    }
}
```

- [ ] **Step 4: Run it — verify it passes**

```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/phpunit tests/Unit/Schedule/MainScheduleTest.php
```

Expected: PASS. If `getRecurringMessages()`/`getTrigger()` names differ in this Symfony version, inspect `Schedule`/`RecurringMessage` and adjust the assertions to read the message + cron expression (the implementation stays the same).

- [ ] **Step 5: Verify the scheduler transport is registered**

```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/console debug:messenger 2>&1 | grep -i schedule || true
docker compose exec -T php bin/console lint:container
```

Expected: `lint:container` passes; a `scheduler_main` transport now exists.

- [ ] **Step 6: Lint + commit**

```bash
cd /home/pavel/projects/personal/hestia/backend && make lint
git add src/Schedule/MainSchedule.php tests/Unit/Schedule/MainScheduleTest.php
git commit -s -m "feat(telegram): daily schedule at configurable time (Asia/Almaty)"
```

---

## Task 7: Full verification + manual setup notes

**Files:** none (verification); optionally `docs/plans/2026-06-04-telegram-notifications-design.md` (append a setup note)

- [ ] **Step 1: Full backend gate**

```bash
cd /home/pavel/projects/personal/hestia/backend && make lint && make test
```

Expected: `make lint` clean; full suite green (existing 224 + new unit tests).

- [ ] **Step 2: Smoke-test the message end-to-end without scheduling**

Trigger the handler once by dispatching the message manually, with real (or test) Telegram creds in `.env.local`:

```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/console messenger:dispatch-message 'App\Message\SendDailyExpirySummary' 2>/dev/null || true
```

If `messenger:dispatch-message` isn't available, write a tiny throwaway console command or use the scheduler dry-run below; then DELETE it. Preferred check: list the schedule:

```bash
docker compose exec -T php bin/console debug:scheduler 2>&1 | head -20
```

Expected: the `main` schedule lists the `SendDailyExpirySummary` with the next run time at your configured time. (Actual Telegram delivery requires real creds in `.env.local` and the worker running with the updated `compose.yaml` command — `docker compose up -d messenger`.)

- [ ] **Step 3: Document the one-time bot setup**

Append a short "## One-time setup" section to the design doc covering: create the bot via **@BotFather** → copy the token; create a Telegram group, add the bot, send a message, then read the chat id (e.g. `https://api.telegram.org/bot<TOKEN>/getUpdates`); put both into `.env.local` as `TELEGRAM_DSN=telegram://<TOKEN>@default?channel=<CHAT_ID>`. Commit:

```bash
cd /home/pavel/projects/personal/hestia && git add docs/plans/2026-06-04-telegram-notifications-design.md
git commit -s -m "docs(telegram): document one-time bot setup"
```

---

## Self-review notes (resolved during planning)

- **Retry semantics:** routing `SendDailyExpirySummary` → `async` (Task 1) means the handler runs in the existing async worker, so a failed Telegram send inherits the async retry (3×) → `failed` transport. `TelegramSender` therefore does **not** swallow exceptions (intentional — messenger owns delivery/retry). This realizes the design's "retries 3× → failed transport" via messenger rather than a try/catch.
- **HTML escaping:** product/location names are user-supplied → `htmlspecialchars` in the builder (Task 3) prevents a stray `<`/`&` from breaking Telegram HTML delivery. Covered by `testEscapesHtmlSpecialCharsInNames`.
- **Timezone:** the builder derives "today" via `ClockInterface` + `AppTimezone` (same pattern as `ChoreService`); the schedule's cron runs in `AppTimezone->get()`. Both reuse the existing +05 resolution.
- **Worker:** the schedule only fires if the worker consumes `scheduler_main` (Task 1 Step 5 updates `compose.yaml`). Without it, nothing is dispatched.
- **Type consistency:** `ExpirySummaryBuilder::build(StockEntry[]): ?string`, `TelegramSender::send(string): void`, handler const `WINDOW_DAYS = 3` (matches `findExpiring(3)`) — consistent across Tasks 3/4/5.
- **Out of scope confirmed absent:** no weekly/shopping summaries, no inbound commands, no per-user targeting.
