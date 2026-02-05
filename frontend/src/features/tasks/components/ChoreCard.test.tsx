import { describe, expect, it, vi } from "vitest";
import { createChoreResponse } from "@/test/mocks/data";
import { render, screen } from "@/test/utils";
import { ChoreCard } from "./ChoreCard";

describe("ChoreCard", () => {
  const defaultProps = {
    onMarkDone: vi.fn(),
    onClick: vi.fn(),
  };

  it("renders chore name", () => {
    const chore = createChoreResponse({ name: "Пылесосить" });
    render(<ChoreCard {...defaultProps} chore={chore} />);
    expect(screen.getByText("Пылесосить")).toBeInTheDocument();
  });

  it("renders assignee when present", () => {
    const chore = createChoreResponse({ assignee: "Pavel" });
    render(<ChoreCard {...defaultProps} chore={chore} />);
    expect(screen.getByText("Pavel")).toBeInTheDocument();
  });

  it("does not render assignee when null", () => {
    const chore = createChoreResponse({ assignee: null });
    render(<ChoreCard {...defaultProps} chore={chore} />);
    expect(screen.queryByText("Pavel")).not.toBeInTheDocument();
  });

  it("shows interval schedule label", () => {
    const chore = createChoreResponse({
      schedule_type: "interval",
      schedule_value: 5,
    });
    render(<ChoreCard {...defaultProps} chore={chore} />);
    expect(screen.getByText(/Каждые 5 дн\./)).toBeInTheDocument();
  });

  it("shows fixed_weekly schedule label", () => {
    const chore = createChoreResponse({
      schedule_type: "fixed_weekly",
      schedule_value: 1,
    });
    render(<ChoreCard {...defaultProps} chore={chore} />);
    expect(screen.getByText(/понедельник/)).toBeInTheDocument();
  });

  it("shows fixed_monthly schedule label", () => {
    const chore = createChoreResponse({
      schedule_type: "fixed_monthly",
      schedule_value: 15,
    });
    render(<ChoreCard {...defaultProps} chore={chore} />);
    expect(screen.getByText(/15/)).toBeInTheDocument();
  });

  it("shows overdue styling when past due", () => {
    const chore = createChoreResponse({
      next_due_at: new Date(Date.now() - 86400000 * 2).toISOString(),
    });
    render(<ChoreCard {...defaultProps} chore={chore} />);
    expect(screen.getByText(/Просрочено/)).toBeInTheDocument();
  });

  it("shows today label when due today", () => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const chore = createChoreResponse({
      next_due_at: today.toISOString(),
    });
    render(<ChoreCard {...defaultProps} chore={chore} />);
    expect(screen.getByText(/Сегодня/)).toBeInTheDocument();
  });

  it("shows days left for future chores", () => {
    const chore = createChoreResponse({
      next_due_at: new Date(Date.now() + 86400000 * 5).toISOString(),
    });
    render(<ChoreCard {...defaultProps} chore={chore} />);
    expect(screen.getByText(/Через \d+ дн\./)).toBeInTheDocument();
  });

  it("calls onClick when card is clicked", async () => {
    const onClick = vi.fn();
    const chore = createChoreResponse();
    const { user } = render(
      <ChoreCard chore={chore} onMarkDone={vi.fn()} onClick={onClick} />,
    );
    await user.click(screen.getByText(chore.name));
    expect(onClick).toHaveBeenCalledWith(chore);
  });

  it("calls onMarkDone when done button is clicked", async () => {
    const onMarkDone = vi.fn();
    const chore = createChoreResponse();
    const { user } = render(
      <ChoreCard chore={chore} onMarkDone={onMarkDone} onClick={vi.fn()} />,
    );
    await user.click(screen.getByText("Выполнено"));
    expect(onMarkDone).toHaveBeenCalledWith(chore.id);
  });

  it("does not call onClick when done button is clicked", async () => {
    const onClick = vi.fn();
    const onMarkDone = vi.fn();
    const chore = createChoreResponse();
    const { user } = render(
      <ChoreCard chore={chore} onMarkDone={onMarkDone} onClick={onClick} />,
    );
    await user.click(screen.getByText("Выполнено"));
    expect(onClick).not.toHaveBeenCalled();
  });
});
