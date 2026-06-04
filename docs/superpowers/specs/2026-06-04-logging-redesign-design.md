# Logging redesign — design

**Date:** 2026-06-04
**Scope:** Backend Monolog configuration and application-level logging. Caddy/FrankenPHP runtime logs are explicitly out of scope.

## Problem

The Symfony/Monolog JSON stream is dominated by framework debug noise and carries almost no meaningful application context.

Measured over ~10 minutes of normal authenticated API use (`php` container, JSON stdout):

- **145 JSON lines total; 143 were `security.DEBUG`** ("Checking for authenticator support", "Authenticator does not support the request", "Stored the security token", ~7× per request).
- Only 2 lines were meaningful (`Authenticator successful!`, a remember-me debug).
- `context` and `extra` are empty `{}` on essentially every line.
- `http_client` logs **twice per outbound call at INFO** (request + response), e.g. Telegram `sendMessage`.
- The one useful per-request line, `request.INFO: Matched route "..."`, is excluded from stdout and only lands in `var/log/dev.log`.
- The messenger worker's JSON stream shows **only** `http_client`; its `messenger` channel is excluded.

Application code barely logs: `ApiExceptionListener` logs `critical` only on 5xx; message handlers and services log nothing.

**Goal:** nice, in-place, meaningful JSON-formatted logs.

## Approach

Three layers: remove noise, enrich what remains, add the meaningful events that are currently missing.

### 1. Remove — kill the noise

- **Drop the `main` file handler** (`var/log/dev.log`) in dev. In a container, stdout/stderr is the canonical sink; the file is redundant. The Symfony profiler still captures full DEBUG/SQL detail in dev independently, so debugging capability is not lost.
- **Exclude the `security` channel** from output — pure framework auth internals (143/145 lines).
- **Exclude the `http_client` channel** — silences the 2 INFO lines per outbound call. Failures are handled explicitly in code (see §3).
- **Drop `request` "Matched route"** (exclude `request` channel) — it fires pre-handler with no status/duration/outcome. Replaced by the request-completion line in §3.

### 2. Enrich — processors on `extra`

Registered in `config/services.yaml` with the `monolog.processor` tag so they apply globally (web + worker + CLI, all envs):

- `Monolog\Processor\UidProcessor` → `extra.uid`. Correlates every line within one request. Symfony's service resetter regenerates it per request (incl. FrankenPHP worker mode).
- `Monolog\Processor\IntrospectionProcessor` (skip partials `Symfony\`, `Monolog\`) → `extra.file`, `line`, `class`, `function`. Points at the application call site, not framework internals.
- `App\Logger\RequestContextProcessor` (custom, `RequestStack`-backed) → `extra.url`, `http_method`, `ip`. No-ops cleanly outside an HTTP request (CLI / messenger worker). **Not** Monolog's `WebProcessor`: that binds the `$_SERVER` superglobal once and goes stale under FrankenPHP's long-lived workers (every request logs the first request's URL/method). `RequestStack` is request-scoped, so it stays accurate per request. (This was caught during live verification; see the implementation history.) Uses `getMainRequest()` so all logs in one transaction share a correlating URL; consequently the `terminate`-time access line itself carries no `extra.url` — the request has already been popped — but its `context` already holds method/path.

Git enrichment is intentionally **out of scope** (GitProcessor shells out to `git` at runtime, unreliable in the container image).

### 3. Add — make meaningful events log

- **Request-completion line.** A kernel `TERMINATE` event listener emits one INFO line per request on a dedicated `http` channel: `method`, `path`, `status`, `duration_ms`. This is the justified replacement for "Matched route" — a real access line with outcome and timing, tied to the request `uid`. Duration is measured from `$_SERVER['REQUEST_TIME_FLOAT']` (set by PHP at request start) to terminate, avoiding a separate start-time listener.
- **`TelegramSender` failure log.** Wrap `chatter->send()` in `try/catch`: log `error` `'Telegram delivery failed'` with `{exception, length}`, then **re-throw** to preserve Messenger's async retry (3×) → `failed` transport semantics.
- **`SendDailyExpirySummaryHandler` outcome log.** One INFO line — `expiring=N`, `sent` or `skipped` — so the worker stream shows what the handler actually did.

### 4. Surface messenger lifecycle

Stop excluding the `messenger` channel from the output handler(s). The worker (and the web container when dispatching) then shows messenger's own INFO lifecycle — "Received message", "Message handled by ...", "Sending message" — which is meaningful for a background worker.

### 5. Leave alone

- Caddy/FrankenPHP runtime logs (out of scope).
- `prod` `fingers_crossed` structure — already quiet (buffers, flushes only on error). It gains the global processors **only**. Channel silencing is **dev-only**: in prod the `main` handler buffers every channel and writes nothing on the happy path, so excluding `security`/`http_client` there would strip useful context from the error-flush buffer without reducing any volume. The noise being cut exists solely on the dev `console` handler (level `debug`, writes continuously). Prod `main` channels stay `["!deprecation"]`.
- `test` env (null handler).

## Channel configuration summary

Dev `console` handler (stdout, JSON) excluded channels change from:

```
["!event", "!doctrine", "!console", "!request", "!messenger"]
```

to:

```
["!event", "!doctrine", "!console", "!request", "!security", "!http_client"]
```

(`messenger` removed from exclusions → now surfaced; `security` and `http_client` added → now silenced.)

## Components

| Component | Type | Responsibility |
|-----------|------|----------------|
| `config/packages/monolog.yaml` | config | Remove file handler; adjust channel exclusions per env |
| `config/services.yaml` | config | Register 3 Monolog processors as tagged services |
| Request-completion listener | new class | Emit one access line per request on `http` channel at `kernel.terminate` |
| `TelegramSender` | edit | Log + re-throw on delivery failure |
| `SendDailyExpirySummaryHandler` | edit | Log expiring count + sent/skipped outcome |

## Net effect

A typical authenticated GET goes from ~21 JSON lines (20 `security.DEBUG`) to one meaningful line:

```json
{"message":"request handled","channel":"http","level_name":"INFO",
 "context":{"method":"GET","path":"/api/internal/v1/tasks","status":200,"duration_ms":12},
 "extra":{"uid":"a1b2c3d4","url":"https://localhost/...","ip":"172.18.0.1",
          "http_method":"GET","file":".../TaskController.php","line":42}}
```

## Testing

- Unit test the request-completion listener: given a terminate event, it logs once with the expected context keys and status/duration.
- Unit test `TelegramSender`: on `chatter->send()` throwing, it logs an error and re-throws (exception propagates).
- Unit/functional test `SendDailyExpirySummaryHandler`: logs `sent` when a summary exists, `skipped` when none.
- Manual verification: exercise a GET, a 4xx, a 5xx, and a worker message; confirm the JSON stream is clean, enriched, and correlated by `uid`.

## Out of scope

- Caddy/FrankenPHP log format.
- Git/version enrichment.
- Log shipping / aggregation infrastructure.
- 4xx request-error logging in `ApiExceptionListener` (current 5xx `critical` behavior unchanged).
