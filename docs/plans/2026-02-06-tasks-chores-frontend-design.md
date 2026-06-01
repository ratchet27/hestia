# Tasks & Chores Frontend Implementation Design

## Overview

Replace the mock data–driven Tasks page with a real API-backed implementation using the same patterns as the Products feature: Orval-generated API client, TanStack Query hooks, React Hook Form modals, and full i18n support.

## Decisions

- **Layout**: Keep existing 2-column grid (chores left, tasks right)
- **Forms**: Modal dialogs for create/edit (consistent with ProductsPage)
- **Priority display**: Colored badge/pill on task cards
- **Schedule labels**: Human-readable Russian text (e.g., "Каждые 3 дн.", "Каждый понедельник")
- **Assignee**: Shown on chore cards
- **Completed tasks**: Collapsible section at bottom of tasks column
- **Delete**: Available only in edit modal (not on cards)
- **Edit trigger**: Click card to open edit modal
- **i18n**: Both Russian and English translations
- **Tests**: Unit + integration tests with Vitest/RTL/MSW

## Architecture

### Data Layer

**API Client** (auto-generated via Orval):
- Run `bun run generate-api` to generate types and fetch functions for `/tasks` and `/chores` endpoints
- Generated types: `TaskResponse`, `ChoreResponse`, `CreateTaskRequest`, `UpdateTaskRequest`, `CreateChoreRequest`, `UpdateChoreRequest`
- Generated functions: CRUD operations for both resources

**Query Hooks** (`src/api/queries/tasks.ts`):
- `useTasks(status: 'active' | 'completed')` — list tasks, returns `TaskResponse[]`
- `useCreateTask()` — mutation, invalidates task list
- `useUpdateTask()` — mutation, invalidates task list
- `useDeleteTask()` — mutation, invalidates task list
- `useToggleTaskDone(id)` — mutation for `PATCH /tasks/{id}/done`

**Query Hooks** (`src/api/queries/chores.ts`):
- `useChores()` — list all chores ordered by nextDueAt
- `useCreateChore()` — mutation, invalidates chore list
- `useUpdateChore()` — mutation, invalidates chore list
- `useDeleteChore()` — mutation, invalidates chore list
- `useMarkChoreDone(id)` — mutation for `POST /chores/{id}/done`

**Query Keys** (added to `src/api/queries/keys.ts`):
- `tasks.all`, `tasks.list(status)`, `tasks.detail(id)`
- `chores.all`, `chores.list()`, `chores.detail(id)`

### Component Structure

All components in `src/features/tasks/`:

| Component | Purpose |
|-----------|---------|
| `TasksPage.tsx` | Main page: 2-column grid, state for modals, data fetching |
| `components/ChoreCard.tsx` | Chore card: name, schedule label, days until due, assignee, "Done" button. Color-coded border (red=overdue, amber=today). Click to edit. |
| `components/ChoreForm.tsx` | Modal form: name, schedule type dropdown, schedule value (contextual), assignee. Delete button in edit mode. |
| `components/TaskCard.tsx` | Task card: checkbox, name, due date, priority pill. Click to edit. |
| `components/TaskForm.tsx` | Modal form: name, due date, priority. Delete button in edit mode. |

### UI Details

**Task priority badges:**
- Low: `bg-green-100 text-green-700` — "Низкий" / "Low"
- Medium: `bg-amber-100 text-amber-700` — "Средний" / "Medium"
- High: `bg-red-100 text-red-700` — "Высокий" / "High"

**Chore card colors:**
- Overdue (`days < 0`): `border-red-300 bg-red-50`
- Due today (`days === 0`): `border-amber-300 bg-amber-50`
- Upcoming: `border-stone-200` (default)

**Schedule label format (Russian / English):**
- Interval: "Каждые N дн." / "Every N days"
- Fixed weekly: "Каждый <weekday>" / "Every <weekday>"
- Fixed monthly: "Каждое N-е число" / "Every Nth of the month"

**ChoreForm schedule value input adapts to schedule type:**
- Interval → number input (1–365), label "дней"
- Fixed weekly → dropdown: Пн, Вт, Ср, Чт, Пт, Сб, Вс (1–7)
- Fixed monthly → number input (1–31)

**Assignee** on chore cards: small `text-stone-500` label below schedule text.

### i18n

Add keys under `tasks` namespace to both `ru.json` and `en.json`:

```
tasks.title, tasks.subtitle
tasks.chores.title, tasks.chores.add, tasks.chores.done
tasks.chores.overdue, tasks.chores.today, tasks.chores.daysLeft
tasks.chores.schedule.interval, tasks.chores.schedule.fixedWeekly, tasks.chores.schedule.fixedMonthly
tasks.chores.form.*
tasks.tasks.title, tasks.tasks.add, tasks.tasks.completed
tasks.tasks.priority.low, tasks.tasks.priority.medium, tasks.tasks.priority.high
tasks.tasks.form.*
tasks.form.save, tasks.form.cancel, tasks.form.delete, tasks.form.deleteConfirm
tasks.weekdays.mon–sun
```

### Testing

| Test file | Scope |
|-----------|-------|
| `TasksPage.test.tsx` | Page renders, loading states, columns, API integration |
| `TaskForm.test.tsx` | Validation, create/edit modes, delete |
| `ChoreForm.test.tsx` | Validation, schedule switching, create/edit |

MSW handlers for all task/chore endpoints in `src/test/mocks/handlers.ts`.

### Cleanup

Remove from `data/`:
- `TasksContext`, `ChoresContext` from `context.tsx`
- `useTasks`, `useChores` from `hooks.ts`
- `Task`, `Chore` interfaces from `types.ts`
- Mock task/chore data from `mocks.ts`

Keep: `RecipesContext`, `useAuth`, `useRecipes`, utility functions (`formatDate`, `getDaysUntil`).
