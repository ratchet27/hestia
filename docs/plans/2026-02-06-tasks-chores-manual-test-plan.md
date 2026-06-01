# Tasks & Chores — Manual Test Plan

**URL:** `http://localhost:5173/tasks` (login: `pavel` / `password`)

---

## 1. Page Load

- [ ] Navigate to `/tasks` — page shows "Задачи и дела" title
- [ ] Two-column layout: "Регулярные дела" (left), "Разовые задачи" (right)
- [ ] Both columns have "+ Добавить" links
- [ ] Empty columns show "Нет записей"

---

## 2. Create Task

- [ ] Click "+ Добавить" in the tasks column — modal "Новая задача" opens
- [ ] Submit with empty name — shows "Название обязательно"
- [ ] Fill name "Купить молоко", set priority "Высокий", pick a due date
- [ ] Click "Создать" — modal closes, toast appears, task card shows in list
- [ ] Card shows name, red "Высокий" badge, and formatted due date
- [ ] Click "Отмена" in a new modal — closes without creating

---

## 3. Toggle Task Done

- [ ] Click the circle checkbox on a task — toast appears, task moves to "Выполнено" section
- [ ] Completed task has strikethrough name, green checkmark, faded appearance
- [ ] Click the checkmark on a completed task — moves back to active list

---

## 4. Edit Task

- [ ] Click a task card (not the checkbox) — modal "Редактировать задачу" opens
- [ ] Form is prefilled with current values
- [ ] Change name and priority, click "Сохранить" — card updates
- [ ] Click "Отмена" — closes without saving

---

## 5. Delete Task

- [ ] Open edit modal, click "Удалить" — confirmation appears
- [ ] Click "Отмена" in confirmation — returns to form
- [ ] Click "Удалить" again and confirm — task removed from list

---

## 6. Create Chore — Interval

- [ ] Click "+ Добавить" in chores column — modal "Новое дело" opens
- [ ] Default schedule type is "Каждые N дней", value defaults to 7
- [ ] Fill name "Пылесосить", click "Создать" — card shows "Каждые 7 дн."

## 7. Create Chore — Fixed Weekly

- [ ] Open create chore modal, select schedule type "День недели"
- [ ] Value field changes to weekday dropdown (понедельник–воскресенье)
- [ ] Select "среда", fill name, create — card shows "Каждый среда"

## 8. Create Chore — Fixed Monthly

- [ ] Select schedule type "День месяца"
- [ ] Value field is number input (max 31)
- [ ] Enter 15, fill name, create — card shows schedule with "15"

---

## 9. Chore Card Display

- [ ] Upcoming chore: white card, shows "Через N дн."
- [ ] Overdue chore: red-tinted card, shows "Просрочено на N дн."
- [ ] Chore with assignee: small gray name below schedule text

---

## 10. Mark Chore Done

- [ ] Click green "Выполнено" button on a chore — toast appears, next_due_at recalculates
- [ ] Clicking "Выполнено" does NOT open the edit modal

---

## 11. Edit Chore

- [ ] Click chore card body — modal "Редактировать дело" opens with prefilled values
- [ ] Change name, click "Сохранить" — card updates

---

## 12. Delete Chore

- [ ] Open edit modal, click "Удалить", confirm — chore removed from list

---

## 13. Dashboard

- [ ] Navigate to `/` — dashboard shows "Дела на сегодня" with chores due today
- [ ] "Ближайшие задачи" section shows up to 3 active tasks with due dates
- [ ] Top stats bar shows count of today's chores
