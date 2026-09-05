import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { createChoreResponse } from "@/test/mocks/data";
import { render, screen, userEvent } from "@/test/utils";
import { ChoreCard } from "./ChoreCard";

describe("ChoreCard", () => {
  // Local noon on a fixed day: day arithmetic must not depend on when CI runs.
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ["Date"] });
    vi.setSystemTime(new Date(2026, 2, 10, 12, 0));
  });
  afterEach(() => vi.useRealTimers());

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
    expect(screen.getByText(/Каждые 5 дней/)).toBeInTheDocument();
  });

  it("uses the singular form for a daily interval", () => {
    const chore = createChoreResponse({
      schedule_type: "interval",
      schedule_value: 1,
    });
    render(<ChoreCard {...defaultProps} chore={chore} />);
    expect(screen.getByText(/Каждый день/)).toBeInTheDocument();
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
    expect(screen.getByText(/Через 5 дней/)).toBeInTheDocument();
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

  it("opens the card on Space key", async () => {
    const onClick = vi.fn();
    const chore = createChoreResponse({ id: "c1", name: "Пылесосить полы" });
    render(<ChoreCard chore={chore} onMarkDone={vi.fn()} onClick={onClick} />);
    const card = screen.getByRole("button", { name: /Пылесосить полы/i });
    card.focus();
    await userEvent.keyboard("[Space]");
    expect(onClick).toHaveBeenCalledTimes(1);
  });
});
