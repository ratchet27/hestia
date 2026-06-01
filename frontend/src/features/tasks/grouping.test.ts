import { describe, expect, it } from "vitest";
import { createChoreResponse, createTaskResponse } from "@/test/mocks/data";
import { groupChores, groupTasks } from "./grouping";

function isoDaysFromToday(days: number): string {
  const d = new Date();
  d.setUTCHours(0, 0, 0, 0);
  d.setUTCDate(d.getUTCDate() + days);
  return d.toISOString();
}

describe("groupChores", () => {
  it("splits overdue from upcoming by next_due_at", () => {
    const overdue = createChoreResponse({
      id: "c1",
      next_due_at: isoDaysFromToday(-1),
    });
    const today = createChoreResponse({
      id: "c2",
      next_due_at: isoDaysFromToday(0),
    });
    const later = createChoreResponse({
      id: "c3",
      next_due_at: isoDaysFromToday(3),
    });

    const result = groupChores([overdue, today, later]);

    expect(result.overdue.map((c) => c.id)).toEqual(["c1"]);
    expect(result.upcoming.map((c) => c.id)).toEqual(["c2", "c3"]);
  });
});

describe("groupTasks", () => {
  it("splits overdue, active, and completed", () => {
    const overdue = createTaskResponse({
      id: "t1",
      due_date: isoDaysFromToday(-2),
      done: false,
    });
    const active = createTaskResponse({
      id: "t2",
      due_date: isoDaysFromToday(2),
      done: false,
    });
    const noDue = createTaskResponse({ id: "t3", due_date: null, done: false });
    const done = createTaskResponse({ id: "t4", done: true });

    const result = groupTasks([overdue, active, noDue], [done]);

    expect(result.overdue.map((t) => t.id)).toEqual(["t1"]);
    expect(result.active.map((t) => t.id)).toEqual(["t2", "t3"]);
    expect(result.completed.map((t) => t.id)).toEqual(["t4"]);
  });

  it("treats a task due today as active, not overdue", () => {
    const dueToday = createTaskResponse({
      id: "t1",
      due_date: isoDaysFromToday(0),
      done: false,
    });

    const result = groupTasks([dueToday], []);

    expect(result.overdue).toEqual([]);
    expect(result.active.map((t) => t.id)).toEqual(["t1"]);
  });
});
