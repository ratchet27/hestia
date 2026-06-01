import type { ChoreResponse, TaskResponse } from "../../api/generated/models";
import { getDaysUntil } from "../../data/types";

export interface GroupedChores {
  overdue: ChoreResponse[];
  upcoming: ChoreResponse[];
}

export interface GroupedTasks {
  overdue: TaskResponse[];
  active: TaskResponse[];
  completed: TaskResponse[];
}

export function groupChores(chores: ChoreResponse[]): GroupedChores {
  const overdue: ChoreResponse[] = [];
  const upcoming: ChoreResponse[] = [];

  for (const chore of chores) {
    if (getDaysUntil(chore.next_due_at) < 0) {
      overdue.push(chore);
    } else {
      upcoming.push(chore);
    }
  }

  return { overdue, upcoming };
}

export function groupTasks(
  activeTasks: TaskResponse[],
  completedTasks: TaskResponse[],
): GroupedTasks {
  const overdue: TaskResponse[] = [];
  const active: TaskResponse[] = [];

  for (const task of activeTasks) {
    if (task.due_date && getDaysUntil(task.due_date) < 0) {
      overdue.push(task);
    } else {
      active.push(task);
    }
  }

  return { overdue, active, completed: completedTasks };
}
