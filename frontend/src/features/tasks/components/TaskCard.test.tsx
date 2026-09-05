import { describe, expect, it, vi } from "vitest";
import { createTaskResponse } from "@/test/mocks/data";
import { render, screen, userEvent } from "@/test/utils";
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

  it.each([
    ["high", "Высокий"],
    ["medium", "Средний"],
    ["low", "Низкий"],
  ] as const)("renders the %s priority badge", (priority, label) => {
    const task = createTaskResponse({ priority });
    render(<TaskCard {...defaultProps} task={task} />);
    expect(screen.getByText(label)).toBeInTheDocument();
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
    await user.click(
      screen.getByRole("button", { name: "Отметить выполненной" }),
    );
    expect(onToggleDone).toHaveBeenCalledWith(task.id);
  });

  it("does not call onClick when checkbox is clicked", async () => {
    const onClick = vi.fn();
    const onToggleDone = vi.fn();
    const task = createTaskResponse({ done: false });
    const { user } = render(
      <TaskCard task={task} onToggleDone={onToggleDone} onClick={onClick} />,
    );
    await user.click(
      screen.getByRole("button", { name: "Отметить выполненной" }),
    );
    expect(onClick).not.toHaveBeenCalled();
  });

  it("opens the card on Space key", async () => {
    const onClick = vi.fn();
    render(
      <TaskCard
        task={createTaskResponse({ id: "t1", name: "Купить хлеб" })}
        onToggleDone={vi.fn()}
        onClick={onClick}
      />,
    );
    const card = screen.getByRole("button", { name: /Купить хлеб/i });
    card.focus();
    await userEvent.keyboard("[Space]");
    expect(onClick).toHaveBeenCalledTimes(1);
  });
});
