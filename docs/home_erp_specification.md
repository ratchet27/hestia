# Home ERP – Family Household Management System

> **Scope note:** This document describes the system **as currently built**.
> Designed-but-not-yet-implemented ideas live in §19 Roadmap, not in the
> body above it. Keep it that way — the body is the source of truth for what exists.

## 1. Purpose

This system is a **self-hosted home ERP** for a single household (initially two adults, kids later).  
It is built to reduce mental load, not to be a product or platform.

Primary goals:
- Always know **what we have**
- Know **what is expired / expiring**
- Know **what to buy**
- Reduce waste
- Make shopping and meal prep easier
- Track household tasks & chores
- Send useful notifications (Telegram)
- Stay easy to use, even when tired

Correctness matters.  
Perfection does not.

---

## 2. Core Principles

1. **Stock-first**  
   Inventory truth is the foundation.

2. **Barcode-first**  
   Scanning is the primary input method.

3. **Low friction > full accuracy**  
   Approximate data is acceptable.

4. **Web-first**  
   Desktop UI for management, mobile web for quick actions.

5. **Single household**  
   No multi-tenancy, no per-user stock.

6. **Internal API with clear boundary**  
   All endpoints live under `/api/internal/v1`.

---

## 3. Technology Stack

**Backend**
- Symfony 8.0 on PHP 8.4
- FrankenPHP (Caddy-based application server, serves HTTP/HTTPS directly)
- PostgreSQL 18 (Doctrine ORM)
- RabbitMQ via Symfony Messenger (async) + Symfony Scheduler (cron)
- OpenAPI documented via NelmioApiDocBundle (`/api/doc`, `/api/doc.json`)

**Frontend**
- React 19 single-page app (TypeScript)
- Vite (dev server + build), Bun (runtime / package manager)
- Tailwind CSS, TanStack Query, React Router, React Hook Form
- API client generated from the backend OpenAPI spec with Orval

**Cross-cutting**
- Notifications: Telegram (outbound) via Symfony Notifier
- Languages: Russian (primary), English (secondary), i18next
- Deployment: Docker Compose (home server / VPS)

---

## 4. Hosting & Architecture

The app is two services — a React SPA and the Symfony API — that the browser
must see as a **single origin** so the session cookie is sent on API calls.

### Development
- API (FrankenPHP) serves on `https://localhost` with a self-signed cert.
- The Vite dev server runs on `http://localhost:5173` and **proxies `/api` →
  `https://localhost`**, so the browser treats everything as one origin and the
  `SameSite=Lax` session cookie is preserved.
- CORS is restricted to `localhost` / `127.0.0.1` (`CORS_ALLOW_ORIGIN`).

### Production
- Single deployment behind one origin (the SPA build is served alongside the API).
- A multi-subdomain split (`ui.*` / `api.*`) with a parent-domain cookie is a
  possible future topology — see §19.

---

## 5. Authentication Model

### Web UI (implemented)
- Login + password, submitted as JSON to `POST /api/internal/v1/auth/login`
- Session cookie (HTTP-only, Secure, `SameSite=Lax`)
- CSRF protection via **double-submit cookie**: the API sets an `XSRF-TOKEN`
  cookie; the client echoes it back in the `X-CSRF-Token` header on writes
- Login throttling (max attempts per window)
- "Remember me" supported server-side
- No JWT, no OAuth — no tokens in JavaScript

### Sessions vs machines
Browsers authenticate with the session cookie. There is currently **no
machine/bot credential** (API keys are a roadmap item — §19); Telegram delivery
is outbound only and scheduled tasks run inside the app via Symfony Scheduler /
console commands, so neither calls the HTTP API.

Rule going forward:
> **Sessions for browsers, tokens for machines**

---

## 6. API Structure

All internal endpoints live under:

```
/api/internal/v1/...
```

Examples (actual routes):
- `POST /stocks/add`, `POST /stocks/consume`, `GET /stocks/expiring`
- `GET/POST /shopping-list`, `POST /shopping-list/clear-completed`
- `POST /chores/{uuid}/mark-done`, `POST /tasks/{uuid}/complete`

The API is:
- stateless (session via cookie)
- use-case oriented
- versioned (`v1`)
- documented with OpenAPI
- consumed by the React SPA

---

## 7. Data Model (Simplified)

### Products
- name (unique)
- category (required)
- default location (required)
- default expiry days (optional)
- min stock (default 0)
- unit (default `piece`)
- active flag

### Barcodes
- barcode (unique)
- product

Barcodes resolve a scanned code to a product. Price memory / store association
are roadmap items (§19).

---

## 8. Stock Entries

Stock quantity is modeled as **discrete entries** — there is no `amount` column.
The on-hand quantity of a product in a location is the **number of stock entries**.

Each entry has:
- product
- location
- best_before_date (nullable)
- created_at (when it entered stock)

FIFO order for consumption:
1. earliest best_before
2. then earliest created_at

No:
- opened logic
- tare logic
- freezer logic

---

## 9. Locations

Logical buckets only (Doctrine entity, seeded). Default set:
- Холодильник (Fridge)
- Кладовая (Pantry)
- Ванная (Bathroom)
- Другое (Other)

Used for filtering & clarity.

---

## 10. Categories

Product categories (Doctrine entity, seeded). Default set:
Молочные, Хлеб, Мясо, Крупы, Консервы, Гигиена, Овощи, Фрукты, Напитки.

Used for grouping and color-coding in the UI.

> Stores / shopping-location modeling and price memory are **not built** — see §19.

---

## 11. Stock Operations

### Add
- resolve product (scan barcode or pick existing; create product inline if new)
- suggested expiry shown from the product's default expiry days
- optional edit
- save (creates one or more stock entries)

### Consume
- FIFO automatically (removes the earliest-expiring entries first)

### Correction
- explicit action: edit or delete individual stock entries

---

## 12. Shopping List

Single shared list.

Sources:
- **manual**
- **auto** — added when a product drops below its min stock, and auto-removed
  when it is restocked at or above min (driven by stock-change events)

Item fields: product *or* free-text custom name, amount, note, done flag.

Features:
- mark done
- auto-remove when stocked
- clear completed
- notes

> A `recipe` shopping-list source is **active** — a recipe's "add missing"
> action adds shortfall items as `source = recipe`. See §13 Recipes.

---

## 13. Recipes

Recipes are ingredient sets (product + required count, each with a
`consume on cook` flag) for checking what you can make and restocking for what
you can't.

Features:
- CRUD (name, ingredients)
- **fulfillment check** — required count vs. current stock, per ingredient
- **cook** — allowed only when every ingredient is in stock; consumes stock
  (across locations) for ingredients flagged `consume on cook`, then reconciles
  the shopping list. Blocks with the missing-product list if any ingredient is
  short.
- **add missing to shopping list** — adds each ingredient's shortfall as a
  shopping-list item with `source = recipe` (skips products already listed)

Backend-integrated end to end, with a dedicated Recipes page in the SPA.

---

## 14. Tasks & Chores

### Tasks
- one-off
- name, optional due date
- priority (low / medium / high)
- done flag (with completed-at timestamp)

### Chores
- recurring, with a schedule type:
  - `interval` (every N days)
  - `fixed_weekly` (a given weekday)
  - `fixed_monthly` (a given day of month)
- optional assignee
- tracks last-done and next-due
- "mark done" advances the next-due date (timezone-aware)
- editing a chore's schedule (type or value) recomputes next-due from now (clock restart, timezone-aware); editing only name/assignee leaves next-due unchanged

> Per-chore reminders and product consumption on completion are **not built** — §19.

---

## 15. Telegram Integration

Outbound only.

Implemented:
- **daily** summary of expired / expiring items (Symfony Scheduler →
  Messenger → Telegram, sent at a configurable time in the app timezone)

> Weekly chores summary and shopping-list summary are **not built** — §19.
> No inbound commands.

---

## 16. UI Structure

Single responsive SPA (desktop-first, usable on mobile web).

Pages:
- Dashboard (aggregated widgets: expiring, low stock, today's chores, tasks)
- Stock overview (by location, expiry status, add / consume)
- Products (CRUD, categories)
- Shopping list
- Tasks & Chores
- Recipes (CRUD, fulfillment check, cook, add missing to shopping list)
- Settings (language switch is live; other controls are placeholders — §19)

Telegram acts as a lightweight awareness layer on top of this UI.

---

## 17. Localization

- Russian primary
- English secondary
- translation keys from day one (i18next)
- language switch in Settings, persisted client-side

---

## 18. Testing & Quality

- Backend: PHPUnit (unit + functional controller tests, Zenstruck Foundry
  factories, `ResetDatabase`). Gate: `make lint` (Rector → Mago format/lint/analyze
  → PHPStan) — see `backend/AGENTS.md`.
- Frontend: Vitest + MSW + Testing Library. Gate: `bun run check` + `bun run test:run`
  — see `frontend/AGENTS.md`.

---

## 19. Roadmap

Everything in this section is **designed or desired but not yet built**.

### v2 – Trustworthy
- richer correction UI / undo of stock actions
- chore reminders and optional product consumption on chore completion
- better mobile UX

### v2/v3 – Awareness & data
- **weekly chores** Telegram summary; optional shopping-list Telegram summary
- **stores / shopping locations** + price memory on barcodes (`last_price`,
  shopping location, note) and later price analytics
- product picture
- Settings: profile, Telegram config, locations management, data export/import

### v3 – Platform
- **API keys for machines** (`X-API-Key`) for bots / cron / scripts, revocable
- multi-subdomain hosting (`ui.*` / `api.*`) with a parent-domain session cookie
- meal planning, deeper / inbound Telegram integration
