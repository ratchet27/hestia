# Logging Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the noisy, context-free Monolog JSON stream into a clean, enriched, meaningful log stream — kill framework debug noise, enrich every record with correlation/call-site/request metadata, and add the meaningful application events that are currently missing.

**Architecture:** Three layers. (1) *Remove* — drop the dev file handler and silence the `security` + `http_client` channels via Monolog config. (2) *Enrich* — register three built-in Monolog processors (UID, Introspection, Web) as globally-tagged services. (3) *Add* — a `kernel.terminate` listener emitting one access line per API request on a dedicated `http` channel, plus explicit failure/outcome logging in `TelegramSender` and `SendDailyExpirySummaryHandler`. Also surface the `messenger` channel so the worker shows its lifecycle.

**Tech Stack:** Symfony 8, MonologBundle, Monolog 3, PHPUnit, Docker Compose (php/messenger/database/rabbitmq). Backend gate is `make lint` (run on host); tests via `make test`.

**Reference spec:** `docs/superpowers/specs/2026-06-04-logging-redesign-design.md`

---

## File Structure

| File | Action | Responsibility |
|------|--------|----------------|
| `backend/config/services.yaml` | Modify | Register UID / Introspection / Web processors as `monolog.processor`-tagged services |
| `backend/config/packages/monolog.yaml` | Modify | Drop dev file handler; adjust channel exclusions; declare `http` channel |
| `backend/src/EventListener/RequestLogListener.php` | Create | One access line per API request at `kernel.terminate` |
| `backend/tests/Unit/EventListener/RequestLogListenerTest.php` | Create | Unit test for the listener |
| `backend/src/Service/Telegram/TelegramSender.php` | Modify | Log a clean error on delivery failure, then re-throw |
| `backend/tests/Unit/Service/Telegram/TelegramSenderTest.php` | Create | Unit test for failure logging + re-throw |
| `backend/src/MessageHandler/SendDailyExpirySummaryHandler.php` | Modify | Log sent/skipped outcome with expiring count |
| `backend/tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php` | Modify | Cover outcome logging; fix `TelegramSender` construction (new arg) |

Existing patterns to follow:
- Custom processor + pure-unit test: `src/Logger/RedactSecretsProcessor.php`, `tests/Unit/Logger/RedactSecretsProcessorTest.php`.
- `#[AsEventListener]` listener: `src/EventListener/ApiExceptionListener.php`.
- Handler unit test with mocks: `tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php`.

**Note on `make lint`:** it auto-rewrites files. Always `git add <explicit paths>`, never `git add -A`.

---

## Task 1: Register Monolog enrichment processors

**Files:**
- Modify: `backend/config/services.yaml`

These three built-in Monolog processors live in `vendor/`, so they need explicit service definitions. With `_defaults: { autoconfigure: true }`, each is auto-tagged `monolog.processor` (MonologBundle autoconfigures `ProcessorInterface`). `UidProcessor` additionally needs `kernel.reset` so its correlation id regenerates per request under long-lived FrankenPHP workers.

- [ ] **Step 1: Add processor service definitions**

In `backend/config/services.yaml`, after the existing explicit service definitions (below the `App\Service\Time\AppTimezone` block), add:

```yaml
    # --- Logging: enrich every record's `extra` with correlation id, call site,
    # and request metadata. Tagged monolog.processor via autoconfigure. ---
    Monolog\Processor\UidProcessor:
        # FrankenPHP workers are long-lived; regenerate the id per request so logs
        # within one request share a `uid` but distinct requests don't collide.
        tags:
            - { name: kernel.reset, method: reset }

    Monolog\Processor\WebProcessor: ~

    Monolog\Processor\IntrospectionProcessor:
        arguments:
            $level: !php/const Monolog\Level::Debug
            $skipClassesPartials: ['Symfony\\', 'Monolog\\']
```

- [ ] **Step 2: Verify the processors are registered and tagged**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/console debug:container --tag=monolog.processor
```
Expected: the output lists `Monolog\Processor\UidProcessor`, `Monolog\Processor\WebProcessor`, `Monolog\Processor\IntrospectionProcessor` (alongside the existing `App\Logger\RedactSecretsProcessor`).

- [ ] **Step 3: Run the backend lint gate**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend && make lint
```
Expected: passes (no analyzer/phpstan errors).

- [ ] **Step 4: Commit**

```bash
cd /home/pavel/projects/personal/hestia/backend
git add config/services.yaml
git commit -s -m "feat(logging): enrich records with uid/introspection/web processors"
```

---

## Task 2: Reconfigure Monolog handlers and channels

**Files:**
- Modify: `backend/config/packages/monolog.yaml`

Drop the dev file handler (stdout is the single sink; the profiler still holds full DEBUG/SQL in dev). On the dev `console` handler: silence `security` + `http_client`, and stop excluding `messenger` so the worker shows its lifecycle. Declare the new `http` channel (consumed in Task 3). Apply `security`/`http_client` silencing to prod too.

- [ ] **Step 1: Replace the monolog.yaml contents**

Overwrite `backend/config/packages/monolog.yaml` with:

```yaml
monolog:
    channels:
        - deprecation # Deprecations are logged in the dedicated "deprecation" channel when it exists
        - http        # Per-request access line emitted by RequestLogListener

when@dev:
    monolog:
        handlers:
            # Single stdout sink. No file handler: in a container stdout is the
            # canonical sink, and the Symfony profiler still captures full
            # DEBUG/SQL detail in dev independently.
            console:
                type: stream
                path: php://stdout
                level: debug
                formatter: monolog.formatter.json
                # Silenced: framework event/doctrine/console internals, the
                # pre-handler "Matched route" (request), security auth chatter,
                # and http_client request/response (failures are logged in code).
                # messenger is intentionally NOT excluded so the worker shows its lifecycle.
                channels: ["!event", "!doctrine", "!console", "!request", "!security", "!http_client"]

when@test:
    monolog:
        handlers:
            main:
                type: fingers_crossed
                action_level: critical
                handler: nested
                excluded_http_codes: [400, 401, 403, 404, 405, 422]
                channels: ["!event"]
            nested:
                type: "null"

when@prod:
    monolog:
        handlers:
            main:
                type: fingers_crossed
                action_level: error
                handler: nested
                excluded_http_codes: [404, 405]
                channels: ["!deprecation", "!security", "!http_client"]
                buffer_size: 50 # How many messages should be saved? Prevent memory leaks
            nested:
                type: stream
                path: php://stderr
                level: debug
                formatter: monolog.formatter.json
            console:
                type: console
                process_psr_3_messages: false
                channels: ["!event", "!doctrine"]
            deprecation:
                type: stream
                channels: [deprecation]
                path: php://stderr
                formatter: monolog.formatter.json
```

- [ ] **Step 2: Verify the container still builds and the dev file handler is gone**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/console cache:clear --env=dev 2>&1 | tail -3
```
Expected: cache clears with no error (config is valid).

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/console debug:container --env=dev monolog.handler.console >/dev/null && echo "console handler OK"
```
Expected: prints `console handler OK` (and the previously-existing `main` file handler is no longer defined in dev).

- [ ] **Step 3: Run the backend lint gate**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend && make lint
```
Expected: passes.

- [ ] **Step 4: Commit**

```bash
cd /home/pavel/projects/personal/hestia/backend
git add config/packages/monolog.yaml
git commit -s -m "feat(logging): silence noise channels, drop dev file handler, add http channel"
```

---

## Task 3: Request-completion access listener

**Files:**
- Create: `backend/src/EventListener/RequestLogListener.php`
- Test: `backend/tests/Unit/EventListener/RequestLogListenerTest.php`

One INFO line per API request on the `http` channel, with method/path/status/duration. The `http` channel (Task 2) makes Symfony autowire `LoggerInterface $httpLogger` to `monolog.logger.http`.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Unit/EventListener/RequestLogListenerTest.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\RequestLogListener;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class RequestLogListenerTest extends TestCase
{
    public function testLogsApiRequestWithContext(): void
    {
        $handler = new TestHandler();

        $request = Request::create('/api/internal/v1/tasks', 'GET');
        $request->server->set('REQUEST_TIME_FLOAT', microtime(true) - 0.05);

        ( new RequestLogListener(new Logger('http', [$handler])) )(
            $this->event($request, new Response('', 200))
        );

        self::assertTrue($handler->hasInfoThatContains('request handled'));

        [$record] = $handler->getRecords();
        self::assertSame('GET', $record->context['method']);
        self::assertSame('/api/internal/v1/tasks', $record->context['path']);
        self::assertSame(200, $record->context['status']);
        self::assertIsInt($record->context['duration_ms']);
        self::assertGreaterThanOrEqual(0, $record->context['duration_ms']);
    }

    public function testIgnoresNonApiRequest(): void
    {
        $handler = new TestHandler();

        ( new RequestLogListener(new Logger('http', [$handler])) )(
            $this->event(Request::create('/_wdt/abc', 'GET'), new Response())
        );

        self::assertSame([], $handler->getRecords());
    }

    private function event(Request $request, Response $response): TerminateEvent
    {
        return new TerminateEvent($this->createMock(HttpKernelInterface::class), $request, $response);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php vendor/bin/phpunit tests/Unit/EventListener/RequestLogListenerTest.php
```
Expected: FAIL — `Class "App\EventListener\RequestLogListener" not found`.

- [ ] **Step 3: Write the listener**

Create `backend/src/EventListener/RequestLogListener.php`:

```php
<?php

declare(strict_types = 1);

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Emits one access line per API request on the "http" channel — the meaningful
 * replacement for the framework's pre-handler "Matched route" log. Carries the
 * outcome (status) and timing, correlated to the request via the uid processor.
 */
#[AsEventListener(event: KernelEvents::TERMINATE)]
final readonly class RequestLogListener
{
    public function __construct(
        private LoggerInterface $httpLogger
    ) {
    }

    public function __invoke(TerminateEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $startedAt = $request->server->get('REQUEST_TIME_FLOAT');
        $durationMs = is_numeric($startedAt)
            ? (int) round((microtime(true) - (float) $startedAt) * 1000)
            : null;

        $this->httpLogger->info('request handled', [
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'status' => $event->getResponse()->getStatusCode(),
            'duration_ms' => $durationMs
        ]);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php vendor/bin/phpunit tests/Unit/EventListener/RequestLogListenerTest.php
```
Expected: PASS (2 tests).

- [ ] **Step 5: Run the lint gate**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend && make lint
```
Expected: passes.

- [ ] **Step 6: Commit**

```bash
cd /home/pavel/projects/personal/hestia/backend
git add src/EventListener/RequestLogListener.php tests/Unit/EventListener/RequestLogListenerTest.php
git commit -s -m "feat(logging): add per-request access line on http channel"
```

---

## Task 4: Log Telegram delivery failures

**Files:**
- Modify: `backend/src/Service/Telegram/TelegramSender.php`
- Test: `backend/tests/Unit/Service/Telegram/TelegramSenderTest.php`

Silencing `http_client` (Task 2) hid the only trace of a failed Telegram call. Replace it with a clean, enriched error logged at the point of failure, then re-throw so Messenger's retry/`failed` semantics are preserved.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Unit/Service/Telegram/TelegramSenderTest.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Service\Telegram;

use App\Service\Telegram\TelegramSender;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;

final class TelegramSenderTest extends TestCase
{
    public function testLogsAndRethrowsOnFailure(): void
    {
        $handler = new TestHandler();

        $chatter = $this->createMock(ChatterInterface::class);
        $chatter->method('send')->willThrowException(new TransportException('boom'));

        $sender = new TelegramSender($chatter, new Logger('app', [$handler]));

        try {
            $sender->send('hello');
            self::fail('Expected TransportException to propagate');
        } catch (TransportException) {
            // expected — propagation preserves Messenger retry/failed semantics
        }

        self::assertTrue($handler->hasErrorThatContains('Telegram delivery failed'));
    }

    public function testDoesNotLogOnSuccess(): void
    {
        $handler = new TestHandler();

        $chatter = $this->createMock(ChatterInterface::class);
        $chatter->expects(self::once())->method('send')->with(self::isInstanceOf(ChatMessage::class));

        ( new TelegramSender($chatter, new Logger('app', [$handler])) )->send('hello');

        self::assertSame([], $handler->getRecords());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php vendor/bin/phpunit tests/Unit/Service/Telegram/TelegramSenderTest.php
```
Expected: FAIL — `TelegramSender::__construct()` called with 2 args but signature takes 1 (`ArgumentCountError` / type error).

- [ ] **Step 3: Update TelegramSender**

Replace `backend/src/Service/Telegram/TelegramSender.php` with:

```php
<?php

declare(strict_types = 1);

namespace App\Service\Telegram;

use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

final readonly class TelegramSender
{
    public function __construct(
        private ChatterInterface $chatter,
        private LoggerInterface $logger
    ) {
    }

    public function send(string $message): void
    {
        $chatMessage = new ChatMessage($message, new TelegramOptions()->parseMode('HTML'));
        $chatMessage->transport('telegram');

        try {
            $this->chatter->send($chatMessage);
        } catch (\Throwable $exception) {
            // Log a clean, enriched failure line (http_client logs are silenced),
            // then let it propagate so Messenger's async retry (3x) + failed
            // transport still handle delivery.
            $this->logger->error('Telegram delivery failed', [
                'exception' => $exception,
                'length' => mb_strlen($message)
            ]);

            throw $exception;
        }
    }
}
```

- [ ] **Step 4: Fix the existing handler test's TelegramSender construction**

In `backend/tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php`, the `handler()` helper constructs `new TelegramSender($chatter)`. Add the `Psr\Log\NullLogger` import and pass it as the second argument. Add near the other `use` statements:

```php
use Psr\Log\NullLogger;
```

Change the `TelegramSender` construction inside `handler()` from:

```php
        return new SendDailyExpirySummaryHandler($repo, $builder, new TelegramSender($chatter));
```

to:

```php
        return new SendDailyExpirySummaryHandler($repo, $builder, new TelegramSender($chatter, new NullLogger()));
```

(Task 5 revisits this helper again to add the handler's own logger — this step only keeps it compiling.)

- [ ] **Step 5: Run both affected test files to verify they pass**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php vendor/bin/phpunit tests/Unit/Service/Telegram/TelegramSenderTest.php tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php
```
Expected: PASS (all tests in both files).

- [ ] **Step 6: Run the lint gate**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend && make lint
```
Expected: passes.

- [ ] **Step 7: Commit**

```bash
cd /home/pavel/projects/personal/hestia/backend
git add src/Service/Telegram/TelegramSender.php tests/Unit/Service/Telegram/TelegramSenderTest.php tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php
git commit -s -m "feat(logging): log telegram delivery failures before rethrow"
```

---

## Task 5: Log expiry-summary handler outcome

**Files:**
- Modify: `backend/src/MessageHandler/SendDailyExpirySummaryHandler.php`
- Test: `backend/tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php`

Give the worker stream a meaningful line for what the handler did (sent vs skipped, and how many entries were expiring).

- [ ] **Step 1: Update the handler test to assert the outcome logs**

In `backend/tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php`:

Add imports near the other `use` statements:

```php
use Monolog\Handler\TestHandler;
use Monolog\Logger;
```

Add a property at the top of the class body (before `testSendsWhenSummaryNotEmpty`):

```php
    private TestHandler $logHandler;
```

Change the `handler()` helper's return statement from:

```php
        return new SendDailyExpirySummaryHandler($repo, $builder, new TelegramSender($chatter, new NullLogger()));
```

to:

```php
        $this->logHandler = new TestHandler();

        return new SendDailyExpirySummaryHandler(
            $repo,
            $builder,
            new TelegramSender($chatter, new NullLogger()),
            new Logger('app', [$this->logHandler])
        );
```

At the end of `testSendsWhenSummaryNotEmpty()`, add:

```php
        self::assertTrue($this->logHandler->hasInfoThatContains('Daily expiry summary sent'));
```

At the end of `testSendsNothingWhenSummaryIsNull()`, add:

```php
        self::assertTrue($this->logHandler->hasInfoThatContains('Daily expiry summary skipped'));
```

- [ ] **Step 2: Run the test to verify it fails**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php vendor/bin/phpunit tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php
```
Expected: FAIL — `SendDailyExpirySummaryHandler::__construct()` does not accept a 4th argument (type/argument error).

- [ ] **Step 3: Update the handler**

Replace `backend/src/MessageHandler/SendDailyExpirySummaryHandler.php` with:

```php
<?php

declare(strict_types = 1);

namespace App\MessageHandler;

use App\Message\SendDailyExpirySummary;
use App\Repository\StockEntryRepository;
use App\Service\Telegram\ExpirySummaryBuilder;
use App\Service\Telegram\TelegramSender;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendDailyExpirySummaryHandler
{
    private const int WINDOW_DAYS = 3;

    public function __construct(
        private StockEntryRepository $stockEntryRepository,
        private ExpirySummaryBuilder $builder,
        private TelegramSender $sender,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(SendDailyExpirySummary $message): void
    {
        $entries = $this->stockEntryRepository->findExpiring(self::WINDOW_DAYS);
        $summary = $this->builder->build($entries);

        if ($summary === null) {
            $this->logger->info('Daily expiry summary skipped', ['expiring' => count($entries)]);

            return;
        }

        $this->sender->send($summary);
        $this->logger->info('Daily expiry summary sent', ['expiring' => count($entries)]);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php vendor/bin/phpunit tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php
```
Expected: PASS.

- [ ] **Step 5: Run the lint gate**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend && make lint
```
Expected: passes.

- [ ] **Step 6: Commit**

```bash
cd /home/pavel/projects/personal/hestia/backend
git add src/MessageHandler/SendDailyExpirySummaryHandler.php tests/Unit/MessageHandler/SendDailyExpirySummaryHandlerTest.php
git commit -s -m "feat(logging): log daily expiry summary sent/skipped outcome"
```

---

## Task 6: Full verification and manual smoke test

**Files:** none (verification only)

- [ ] **Step 1: Run the full backend test suite**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend && make test
```
Expected: all tests pass.

- [ ] **Step 2: Run the full lint gate one final time**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend && make lint
```
Expected: passes (and reports no unstaged auto-fixes; if it rewrote anything, stage those explicit files and amend the relevant commit).

- [ ] **Step 3: Smoke-test the live dev JSON stream**

Exercise an authenticated GET, a 404, and a 5xx-free flow, then inspect the JSON stdout:

```bash
cd /tmp
# Reuse the existing cookie jar from the session if present; otherwise log in:
#   curl -sk -c cj.txt https://localhost/api/internal/v1/auth/csrf -o /dev/null
#   XSRF=$(grep XSRF-TOKEN cj.txt | awk '{print $7}')
#   curl -sk -b cj.txt -c cj.txt -H "Content-Type: application/json" -H "X-CSRF-Token: $XSRF" \
#     -X POST https://localhost/api/internal/v1/auth/login -d '{"username":"pavel","password":"Test1234!"}'
curl -sk -b cj.txt https://localhost/api/internal/v1/tasks -o /dev/null
curl -sk -b cj.txt https://localhost/api/internal/v1/tasks/00000000-0000-0000-0000-000000000000 -o /dev/null
```

Then:
```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose logs php --since 1m --no-log-prefix 2>&1 | grep -E '^\{' | tail -10
```

Expected:
- A `{"message":"request handled","channel":"http",...}` line per API request, with `context.status` / `context.duration_ms` and `extra.uid` / `extra.url` / `extra.file` populated.
- **No** `security.DEBUG` lines and **no** `http_client` request/response lines.

- [ ] **Step 4: Smoke-test the worker stream**

Run the summary command (dispatches to the worker) and inspect the messenger container:

```bash
cd /home/pavel/projects/personal/hestia/backend
docker compose exec -T php bin/console app:telegram:send-summary
sleep 4
docker compose logs messenger --since 1m --no-log-prefix 2>&1 | grep -E '^\{' | tail -10
```

Expected:
- Messenger lifecycle lines on `channel":"messenger"` (received/handled), and
- A `Daily expiry summary sent` or `Daily expiry summary skipped` line on `channel":"app"` with `extra.uid`.
- **No** `http_client` request/response lines.

- [ ] **Step 5: Confirm git state is clean**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend && git status
```
Expected: clean working tree; all logging commits present in `git log --oneline -6`.

---

## Notes / known trade-offs

- **Prod access lines:** prod uses `fingers_crossed` (flush on error only), so the `http` access line is buffered and only emitted when a request errors. Routine prod access logging is expected to come from Caddy (out of scope). The dev stream shows every API request.
- **WebProcessor in CLI/worker:** `WebProcessor` no-ops when `$_SERVER['REQUEST_URI']` is absent, so worker/CLI records simply omit those `extra` fields — no errors.
- **UID per request:** relies on the `kernel.reset` tag (Task 1) + Symfony's service resetter to regenerate the id between requests in worker mode.
