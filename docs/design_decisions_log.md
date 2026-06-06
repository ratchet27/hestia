# Design Decisions Log – Home ERP

This document records *why* the original Grocy-based plan was changed and how the current specification emerged.

It exists to keep the project coherent over time.

---

## 1. Initial state

The project started as a full Grocy rewrite based on deep analysis of:
- Grocy source code
- Grocy UI
- Grocy data model
- Grocy edge cases

The result was technically correct but too complex for real daily use.

---

## 2. Reality check

The system is for:
- one household
- two people
- daily use
- stress reduction

Not for:
- scale
- plugins
- public API users
- extensibility

This forced a shift from completeness to usability.

---

## 3. Key decisions

### 3.1 Stock-first, not meal-first

Inventory truth is harder and more valuable than meal planning.
Meals are built *from* stock, not the other way around.

---

### 3.2 Barcode-first input

Typing is friction.
Scanning is natural.
Local barcode DB is mandatory (Kazakhstan reality).

---

### 3.3 Simplified locations

Locations are logical buckets, not physical modeling.
Freezer logic was dropped intentionally.

---

### 3.4 Dropped tare/open/freezer logic

These features add complexity without improving daily life.
They were removed to keep the system usable.

---

### 3.5 Web app only

A native mobile app would increase maintenance burden.
Mobile web + Telegram is sufficient.

---

### 3.6 Telegram as awareness layer

Telegram is used for:
- reminders
- summaries
- notifications

Not for control or editing.

---

### 3.7 Localization early

Russian is the daily language.
I18n added from day one to avoid painful refactors.

---

### 3.8 Authentication choice

Sessions for browser users.
API keys for bots.
No JWT for web.
No OAuth.

---

### 3.9 API boundary decision

All endpoints live under `/api/internal/v1`.
This makes future external APIs possible without refactors.

---

## 4. Hosting decisions

- Single-origin deployment: the SPA build is served alongside the Symfony API
  behind one origin (FrankenPHP/Caddy), so the `SameSite=Lax` session cookie is
  sent on API calls — see spec §4
- CORS kept minimal (restricted to localhost in dev) — see spec §4
- Single reverse proxy (FrankenPHP/Caddy) in front: serves static SPA assets and
  routes dynamic requests to Symfony
- Multi-subdomain split (`ui.*` / `api.*`) with a parent-domain cookie is deferred
  to the roadmap — see spec §19

_History: originally planned as two subdomains (`ui` + `api`) with a shared cookie
domain; superseded by the single-origin model above._

---

## 5. Guiding rule going forward

> If a feature makes daily use harder, it is wrong — even if it is architecturally beautiful.

This rule overrides all others.

