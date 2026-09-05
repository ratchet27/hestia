# Telegram Notifications (Daily Expiry Summary) — Design

**Date:** 2026-06-04
**Status:** Implemented (2026-06)
**Scope:** First slice of spec §15 Telegram integration — an outbound-only daily expiry summary.

## Goal

Deliver the "awareness layer" from the spec: a Telegram bot that pushes a **daily summary of
expired / expiring-soon stock** to a shared household chat, so nobody has to open the app to
remember what needs using up. Build the full pipeline (sender + scheduler + config + tests)
around this one summary; weekly-chores and shopping-list summaries become small follow-ons on
the same rails.

## Context

Spec §15 ("Telegram Integration"): **outbound only (v1)**, **no commands**. Notifications:
daily (expired/expiring), weekly (chores), optional (shopping). This design ships the **daily**
one.

Existing infrastructure this reuses:
- **Messenger + RabbitMQ** worker already runs (the `messenger` container) with an `async`
  transport (retry 3×, multiplier 2) and a `failed` transport. Pattern precedent:
  `src/MessageHandler/StockChangedHandler.php`.
- **`StockEntryRepository::findExpiring(int $days)`** already returns expired + expiring entries
  ordered by urgency.
- **`symfony/clock`** (`ClockInterface` + `MockClock`) and `App\Service\Time\AppTimezone`
  (Asia/Almaty +05) are already used by the chores feature — reused for "days until" math and
  the schedule's timezone.

Not yet installed: `symfony/notifier`, `symfony/telegram-notifier`, `symfony/scheduler`.

## Decisions (locked)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Feature scope | **Daily expiry summary only** | Full pipeline, highest-value nag; weekly/shopping are follow-ons. |
| Direction | **Outbound only, no commands** | Spec §15. |
| Recipients | **One shared household chat** (single chat ID) | 2-person household; expiry is household-wide. No per-user storage. |
| Send mechanism | **`symfony/notifier` + telegram bridge** (`ChatterInterface`) | Idiomatic, handles API + formatting, mockable in tests. |
| Scheduling | **`symfony/scheduler`** (rides the messenger worker) | Schedule-as-code, timezone-aware, no new process. |
| Send time | **Configurable via env** (`TELEGRAM_DAILY_SUMMARY_TIME`, default `08:30`), Asia/Almaty, daily | Morning by default; tweakable without a code change. |
| "Expiring soon" window | **3 days** | Actionable "use/buy within a few days" horizon. |
| Empty state | **Send nothing** | A ping then always means action; avoids notification fatigue. |

## Architecture & flow

```
symfony/scheduler  (cron: daily 08:30 Asia/Almaty)
  → dispatches SendDailyExpirySummary  (Messenger, existing worker)
    → SendDailyExpirySummaryHandler
       → ExpirySummaryBuilder   (StockEntryRepository::findExpiring → ?string RU HTML; null if empty)
       → TelegramSender         (wraps ChatterInterface → sends to the configured chat)
```

**When** (schedule) ⊥ **what** (builder) ⊥ **send** (notifier) — each unit testable in isolation.
If the builder returns `null`, the handler returns early and nothing is sent.

## Components

**Create:**
- `src/Message/SendDailyExpirySummary.php` — empty marker message (the "fire" signal).
- `src/MessageHandler/SendDailyExpirySummaryHandler.php` — orchestrates builder → sender; no-op on `null`.
- `src/Service/Telegram/ExpirySummaryBuilder.php` — pure logic: entries → `?string` (RU HTML or `null`). The testable heart; injects `ClockInterface` + `AppTimezone` for "days until".
- `src/Service/Telegram/TelegramSender.php` — thin wrapper over `ChatterInterface` (handlers never touch the notifier directly; trivially mockable).
- `src/Schedule/MainSchedule.php` — `#[AsSchedule]` provider with the daily recurring trigger,
  dispatching `SendDailyExpirySummary`. Reads the send time from `TELEGRAM_DAILY_SUMMARY_TIME`
  (injected `%env(...)%`, `HH:MM`, default `08:30`) and builds a daily trigger at that time in
  Asia/Almaty (`AppTimezone`).
- `config/packages/notifier.yaml` — chatter transport bound to `TELEGRAM_DSN`.

**Modify:**
- `config/packages/messenger.yaml` — route `SendDailyExpirySummary` to `async` (or a dedicated
  scheduler transport per Symfony's scheduler setup).
- `.env` — `TELEGRAM_DSN` placeholder (real value in gitignored `.env.local`).
- `composer.json` — add `symfony/notifier`, `symfony/telegram-notifier`, `symfony/scheduler`.

## The daily message

RU, HTML-formatted, expired section first (most urgent), then expiring-soon. Example:

```
🏠 Гестия — сводка на 04.06

⚠️ Просрочено
• Молоко (Холодильник) — 2 дн. назад
• Сметана (Холодильник) — вчера

🔔 Скоро истекает
• Йогурт (Холодильник) — сегодня
• Хлеб (Кладовая) — через 2 дн.
```

- **Expired** = best-before < today (local). **Expiring-soon** = best-before within the next 3 days.
- Sorted by urgency. Each line: product · location · human-relative date
  (`сегодня` / `вчера` / `завтра` / `N дн. назад` / `через N дн.`).
- App name in copy is **Гестия** (with Г) — never "Хестия".
- Both sections empty → builder returns `null` → nothing sent.

## Config & secrets

- **`TELEGRAM_DSN`** = `telegram://<BOT_TOKEN>@default?channel=<CHAT_ID>` — chat ID baked into the
  DSN, so `TelegramSender` just sends.
- Placeholder committed in `.env`; **real token + chat ID in `.env.local`** (gitignored).
- **`TELEGRAM_DAILY_SUMMARY_TIME`** = daily send time as `HH:MM` (default `08:30`, Asia/Almaty);
  committed in `.env`, overridable in `.env.local`. The schedule reads it; changing it needs no
  code change (just a worker restart).
- Threshold (3 days) lives in code (version-controlled, one place).
- **One-time manual setup** (documented, not code): create the bot via @BotFather → token; create
  the household group, add the bot → chat ID.

## Error handling

- **Send fails / Telegram API down** → `async` transport retries 3× (existing config); persistent
  failure lands in the `failed` transport (not lost silently). Worker keeps running.
- **Bot token / chat ID misconfigured** → `TelegramSender` catches the notifier exception, logs an
  error (monolog), handler exits cleanly — a broken bot never crashes the worker or other messages.
- **Empty summary** → `null` → early return, nothing sent (by design).

## Testing

- **`ExpirySummaryBuilder` (unit — the core):** `MockClock` + Foundry `StockEntryFactory`:
  expired-only, expiring-only, both, nothing→`null`, sort order, date wording
  (`сегодня`/`вчера`/`через N дн.`), and the **Гестия** spelling.
- **`SendDailyExpirySummaryHandler`:** mock builder + mock `TelegramSender` — asserts sender called
  with the built text; asserts sender **not** called when builder returns `null`.
- **`TelegramSender`:** mock `ChatterInterface` — asserts a `ChatMessage` is dispatched (no network).
- **Schedule:** assert the schedule contains the recurring `SendDailyExpirySummary`, built from the
  configured `TELEGRAM_DAILY_SUMMARY_TIME` (no time-travel).

## One-time setup

The bot token and chat ID are **not** in the repo — they live in `backend/.env.local` (gitignored).
Do this once:

1. **Create the bot.** In Telegram, message **@BotFather** → `/newbot` → follow the prompts → copy the
   **bot token** it gives you (looks like `123456789:AA...`).
2. **Create the household chat and get its ID.**
   - Create a Telegram group, add the bot to it, and post any message in the group.
   - Fetch updates: open `https://api.telegram.org/bot<TOKEN>/getUpdates` in a browser (replace
     `<TOKEN>`). Find `"chat":{"id":<CHAT_ID>,...}` — for a group the ID is negative
     (e.g. `-1001234567890`). That's the **chat ID**.
   - (If `getUpdates` is empty, post another message in the group and refresh.)
3. **Put both into `backend/.env.local`** (create the file if absent):
   ```bash
   TELEGRAM_DSN=telegram://<BOT_TOKEN>@default?channel=<CHAT_ID>
   # Optional — override the default 08:30 Asia/Almaty send time:
   # TELEGRAM_DAILY_SUMMARY_TIME=09:00
   ```
4. **Restart the worker** so it picks up the env and consumes the schedule:
   ```bash
   cd backend && docker compose up -d messenger
   ```
   Verify the schedule is live: `docker compose exec -T php bin/console debug:scheduler` should list
   the `main` schedule with `SendDailyExpirySummary` and the next run at your configured time.

The summary fires daily at the configured time and stays silent when nothing is expired/expiring.

## Out of scope (v1 of this feature)

Weekly chores summary · shopping-list summary (both small follow-ons on these rails) · inbound
commands/replies · per-user targeting · delivery guarantees beyond messenger's retry.
