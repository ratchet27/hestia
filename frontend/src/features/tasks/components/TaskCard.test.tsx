import { describe, expect, it, vi } from "vitest";
import { createTaskResponse } from "@/test/mocks/data";
import { render, screen } from "@/test/utils";
import { TaskCard } from "./TaskCard";

describe("TaskCard", () => {
  const defaultProps = {
    onToggleDone: vi.fn(),
    onClick: vi.fn(),
  };

  it("renders task name", () => {
    const task = createTaskResponse({ name: "Buy milk" });
    render(<TaskCard {...defaultProps} task={task} />);
    expect(screen.getByText("Buy milk")).toBeInTheDocument();
  });

  it("renders priority badge", () => {
    const task = createTaskResponse({ priority: "high" });
    render(<TaskCard {...defaultProps} task={task} />);
    expect(screen.getByText("Высокий")).toBeInTheDocument();
  });

  it("renders low priority badge", () => {
    const task = createTaskResponse({ priority: "low" });
    render(<TaskCard {...defaultProps} task={task} />);
    expect(screen.getByText("Низкий")).toBeInTheDocument();
  });

  it("renders medium priority badge", () => {
    const task = createTaskResponse({ priority: "medium" });
    render(<TaskCard {...defaultProps} task={task} />);
    expect(screen.getByText("Средний")).toBeInTheDocument();
  });

  it("renders due date when present", () => {
    const task = createTaskResponse({
      due_date: "2026-02-15T00:00:00+00:00",
    });
    render(<TaskCard {...defaultProps} task={task} />);
    expect(screen.getByText(/15/)).toBeInTheDocument();
  });

  it("does not render due date when null", () => {
    const task = createTaskResponse({ due_date: null });
    render(<TaskCard {...defaultProps} task={task} />);
    // Should only show priority badge, no date text
    const badges = screen.getAllByText(/Средний/);
    expect(badges).toHaveLength(1);
  });

  it("applies strikethrough styling when done", () => {
    const task = createTaskResponse({ name: "Done task", done: true });
    render(<TaskCard {...defaultProps} task={task} />);
    const nameElement = screen.getByText("Done task");
    expect(nameElement.className).toContain("line-through");
  });

  it("does not apply strikethrough when not done", () => {
    const task = createTaskResponse({ name: "Active task", done: false });
    render(<TaskCard {...defaultProps} task={task} />);
    const nameElement = screen.getByText("Active task");
    expect(nameElement.className).not.toContain("line-through");
  });

  it("calls onClick when card is clicked", async () => {
    const onClick = vi.fn();
    const task = createTaskResponse({ name: "Clickable task" });
    const { user } = render(
      <TaskCard task={task} onToggleDone={vi.fn()} onClick={onClick} />,
    );
    await user.click(screen.getByText("Clickable task"));
    expect(onClick).toHaveBeenCalledWith(task);
  });

  it("calls onToggleDone when checkbox is clicked", async () => {
    const onToggleDone = vi.fn();
    const task = createTaskResponse({ done: false });
    const { user } = render(
      <TaskCard task={task} onToggleDone={onToggleDone} onClick={vi.fn()} />,
    );
    // The checkbox button is the one with the circle styling
    const buttons = screen.getAllByRole("button");
    // Inner button (checkbox) is the second one
    await user.click(buttons[1]!);
    expect(onToggleDone).toHaveBeenCalledWith(task.id);
  });

  it("does not call onClick when checkbox is clicked", async () => {
    const onClick = vi.fn();
    const onToggleDone = vi.fn();
    const task = createTaskResponse({ done: false });
    const { user } = render(
      <TaskCard task={task} onToggleDone={onToggleDone} onClick={onClick} />,
    );
    const buttons = screen.getAllByRole("button");
    await user.click(buttons[1]!);
    expect(onClick).not.toHaveBeenCalled();
  });
});
