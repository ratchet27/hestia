# Tasks & Chores Feature Design

**Date:** 2026-02-05
**Status:** Approved

## Overview

Add backend support for the Tasks feature, which consists of two separate concepts:
- **Chores**: Recurring household duties with flexible scheduling
- **Tasks**: One-off to-do items with optional deadlines and priorities

The frontend UI already exists with mock data. This design covers the backend implementation and API integration.

## Data Model

### Chore Entity

```
id: int (PK)
name: string (required, max 255)
schedule_type: enum [interval, fixed_weekly, fixed_monthly]
schedule_value: int
  - interval: number of days (1-365)
  - fixed_weekly: weekday (1=Mon, 7=Sun)
  - fixed_monthly: day of month (1-28)
assignee: string|null (max 100, simple name like "Pavel")
last_done_at: datetime|null
next_due_at: datetime
created_at: datetime
```

**Schedule Examples:**

| Chore | schedule_type | schedule_value | Behavior |
|-------|---------------|----------------|----------|
| Cleaning | interval | 7 | Done Thu → next due in 7 days (slides) |
| Trash day | fixed_weekly | 1 | Always Monday |
| Rent | fixed_monthly | 21 | Always 21st of month |
| Gym | interval | 2 | Every 2 days (slides) |

### Task Entity

```
id: int (PK)
name: string (required, max 255)
due_date: date|null (optional deadline)
priority: enum [low, medium, high] (default: medium)
done: boolean (default: false)
done_at: datetime|null
created_at: datetime
```

## API Design

Base path: `/api/internal/v1/`

### Chores Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /chores | List all chores |
| POST | /chores | Create chore |
| GET | /chores/{id} | Get single chore |
| PUT | /chores/{id} | Update chore |
| DELETE | /chores/{id} | Delete chore |
| POST | /chores/{id}/done | Mark done (recalculates next_due_at) |

### Tasks Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /tasks | List tasks (filter by status) |
| POST | /tasks | Create task |
| GET | /tasks/{id} | Get single task |
| PUT | /tasks/{id} | Update task |
| DELETE | /tasks/{id} | Delete task |
| PATCH | /tasks/{id}/done | Toggle done status |

**Query params for GET /tasks:**
- `?status=active` (default) - not done, includes overdue
- `?status=completed` - done within last 3 days
- `?status=all` - everything

## Next Due Calculation Logic

When a chore is marked done, `next_due_at` is calculated based on schedule type:

### Interval (sliding)
```
next_due_at = now + interval_days
```

### Fixed Weekly
```
next_due_at = next occurrence of weekday after today
```
Example: fixed_weekly=1 (Monday), done Tue → next due following Monday

### Fixed Monthly
```
next_due_at = schedule_value day of next month (or current if not passed)
```
Example: fixed_monthly=21, done on 15th → due 21st this month
Example: fixed_monthly=21, done on 25th → due 21st next month

### Edge Cases
- Day 29-31 for fixed_monthly: cap at 28 in UI to avoid Feb issues
- If `last_done_at` is null (new chore): `next_due_at` = today

## Frontend Display Rules

### Sections (both columns)
1. **Overdue** (red): items with due date < today, not done
2. **Active/Due**: upcoming items, sorted by due date
3. **Completed** (faded): done within last 3 days

### Task Priority Display
- High: red indicator/badge
- Medium: no indicator (default)
- Low: gray/muted text

### Layout
```
┌─────────────────────────────┬───────────────────────────┐
│  Chores                     │  Tasks                    │
├─────────────────────────────┼───────────────────────────┤
│  [Overdue - red]            │  [Overdue - red]          │
│  • Clean bathroom (2d late) │  • Call plumber (1d late) │
│                             │                           │
│  [Due today/upcoming]       │  [Active]                 │
│  • Trash day - today        │  • Fix lamp [high]        │
│  • Laundry - in 3 days      │  • Buy gift [medium]      │
│                             │                           │
│  [+ Add chore]              │  [Completed - faded]      │
│                             │  • ✓ Send email           │
│                             │  [+ Add task]             │
└─────────────────────────────┴───────────────────────────┘
```

## Validation Rules

### Chore
- `name`: required, max 255 chars
- `schedule_type`: required, one of [interval, fixed_weekly, fixed_monthly]
- `schedule_value`: required
  - interval: 1-365
  - fixed_weekly: 1-7
  - fixed_monthly: 1-28
- `assignee`: optional, max 100 chars

### Task
- `name`: required, max 255 chars
- `due_date`: optional, valid date (can be past)
- `priority`: one of [low, medium, high], defaults to medium

## Out of Scope (v2)

- Telegram notifications for overdue items
- Chore pause/resume functionality
- Completion history tracking
- Task-to-chore conversion
- User accounts / authentication per person

## Decisions Summary

| Aspect | Decision |
|--------|----------|
| Entities | Chores + Tasks, separate |
| Chore schedule | Hybrid: interval (sliding) OR fixed (weekly/monthly) |
| Chore assignee | Simple name string |
| Chore history | No history, just last_done_at |
| Task priority | High/Medium/Low |
| Completed tasks | Hidden after 3 days |
| Overdue display | Separate section at top |
| API language | English enums, frontend translates |
