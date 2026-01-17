# Home ERP – Family Household Management System

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

- Backend: Symfony 7.4 (PHP 8.3)
- Database: PostgreSQL
- Frontend: React SPA
- Deployment: Docker (home server / VPS)
- Reverse proxy: nginx / traefik
- Notifications: Telegram Bot
- Languages: Russian (primary), English (secondary)
- UI: i18n-ready from day one

---

## 4. Hosting & Architecture

### Domains

```
ui.erp.local   → React SPA
api.erp.local  → Symfony API
```

### Cookie scope

Session cookies are scoped to the parent domain:

```
Domain=.erp.local
```

This allows:
- session-based auth across subdomains
- no tokens in JS
- no JWT for browser
- clean CORS

---

## 5. Authentication Model

### Web UI
- Login + password
- Session cookie (HTTP-only, Secure, SameSite=Lax)
- CSRF protected
- No JWT
- No OAuth

### Bots / Scripts (Telegram, cron, tools)
- API keys
- Header: `X-API-Key: <key>`
- Revocable

Rule:
> **Sessions for browsers, tokens for machines**

---

## 6. API Structure

All internal endpoints:

```
/api/internal/v1/...
```

Examples:
- `/stock/add`
- `/stock/consume`
- `/shopping-list/add`
- `/chores/execute`

API is:
- stateless
- use-case oriented
- versioned
- clean but not public
- reusable by React, Telegram, scripts

---

## 7. Data Model (Simplified)

### Products
- name
- category
- default expiry days
- min stock (optional)
- default location
- active
- picture (optional)

### Barcodes
- barcode
- product_id
- last_price
- shopping_location
- note

Local DB first (Kazakhstan reality).

---

## 8. Stock Entries

Every purchase = one entry.

Fields:
- product_id
- amount
- best_before_date (nullable)
- purchased_date
- location
- note

FIFO order:
1. earliest expiry
2. earliest purchase date

No:
- opened logic
- tare logic
- freezer logic

---

## 9. Locations

Logical buckets only:
- Fridge
- Pantry
- Bathroom
- Other

Used for filtering & clarity.

---

## 10. Shopping Locations (Stores)

Examples:
- Magnum
- Small Shop
- Delivery App

Used for:
- price memory
- later analytics
- convenience

---

## 11. Stock Operations

### Add
- scan barcode
- resolve product
- suggested expiry shown
- optional edit
- save

### Consume
- quick +/- buttons
- FIFO automatically

### Correction
- explicit action
- logged
- expected to be used

---

## 12. Shopping List

Single shared list.

Sources:
- manual
- below min stock
- recipe missing items

Features:
- mark done
- auto-remove when stocked
- notes

---

## 13. Recipes (Minimal)

Purpose:
- fulfillment check
- shopping list generation
- stock consumption

No nesting, no calories, no pricing v1.

---

## 14. Tasks & Chores

### Tasks
- one-off
- due date
- done flag

### Chores
- daily / weekly / monthly
- optional reminder
- optional product consumption

---

## 15. Telegram Integration

Outbound only (v1).

Notifications:
- daily: expired / expiring
- weekly: chores
- optional: shopping summary

No commands yet.

---

## 16. UI Structure

### Desktop
- dashboard
- stock overview
- products
- recipes
- chores/tasks
- settings

### Mobile Web
- scan & add
- shopping list
- quick consume
- stock lookup

### Telegram
- awareness layer

---

## 17. Localization

- Russian primary
- English secondary
- translation keys from day one
- no runtime complexity

---

## 18. Roadmap

### v1 – Daily usable
- products + barcodes
- stock add/consume
- expired/due soon
- shopping list
- tasks & chores
- Telegram notifications
- auth + sessions

### v2 – Trustworthy
- correction UI
- undo stock actions
- better mobile UX
- recipe fulfillment

### v3 – Nice to have
- meal planning
- analytics
- price trends
- deeper Telegram integration

