import { describe, expect, it, vi } from "vitest";
import { createExpiringEntry } from "@/test/mocks/data";
import { render, screen } from "@/test/utils";
import { AttentionCard } from "./AttentionCard";

describe("AttentionCard", () => {
  it("displays product name and location", () => {
    const entry = createExpiringEntry({
      product: { name: "Молоко" },
      location: { name: "Холодильник" },
    });

    render(<AttentionCard entry={entry} onDone={vi.fn()} onThrow={vi.fn()} />);

    expect(screen.getByText("Молоко")).toBeInTheDocument();
    expect(screen.getByText("Холодильник")).toBeInTheDocument();
  });

  it("displays product unit", () => {
    const entry = createExpiringEntry({
      product: { unit: "л" },
    });

    render(<AttentionCard entry={entry} onDone={vi.fn()} onThrow={vi.fn()} />);

    expect(screen.getByText(/1 л/)).toBeInTheDocument();
  });

  it("displays relative expiry text", () => {
    const entry = createExpiringEntry({
      days_until_expiry: 1,
    });

    render(<AttentionCard entry={entry} onDone={vi.fn()} onThrow={vi.fn()} />);

    expect(screen.getByText("завтра")).toBeInTheDocument();
  });

  it('calls onDone when "Готово" clicked', async () => {
    const onDone = vi.fn();
    const entry = createExpiringEntry();

    const { user } = render(
      <AttentionCard entry={entry} onDone={onDone} onThrow={vi.fn()} />,
    );
    await user.click(screen.getByRole("button", { name: "Готово" }));

    expect(onDone).toHaveBeenCalledWith(entry);
  });

  it('calls onThrow when "Выбросить" clicked', async () => {
    const onThrow = vi.fn();
    const entry = createExpiringEntry();

    const { user } = render(
      <AttentionCard entry={entry} onDone={vi.fn()} onThrow={onThrow} />,
    );
    await user.click(screen.getByRole("button", { name: "Выбросить" }));

    expect(onThrow).toHaveBeenCalledWith(entry);
  });

  it("shows warning icon for expired items", () => {
    const entry = createExpiringEntry({
      days_until_expiry: -1,
    });

    render(<AttentionCard entry={entry} onDone={vi.fn()} onThrow={vi.fn()} />);

    // Expired items show warning emoji
    expect(screen.getByText(/⚠️/)).toBeInTheDocument();
  });

  it("shows alarm icon for items expiring today", () => {
    const entry = createExpiringEntry({
      days_until_expiry: 0,
    });

    render(<AttentionCard entry={entry} onDone={vi.fn()} onThrow={vi.fn()} />);

    // Today items show alarm emoji
    expect(screen.getByText(/⏰/)).toBeInTheDocument();
  });
});
