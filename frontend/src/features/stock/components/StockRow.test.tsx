import { describe, expect, it, vi } from "vitest";
import { createStockEntry } from "@/test/mocks/data";
import { render, screen } from "@/test/utils";
import { StockRow } from "./StockRow";

describe("StockRow", () => {
  it("renders product name and unit", () => {
    const entry = createStockEntry({
      product: { name: "Молоко", unit: "л" },
    });

    render(
      <table>
        <tbody>
          <StockRow entry={entry} onConsume={vi.fn()} />
        </tbody>
      </table>,
    );

    expect(screen.getByText("Молоко")).toBeInTheDocument();
    expect(screen.getByText("1 л")).toBeInTheDocument();
  });

  it("displays expiry date with relative text", () => {
    const entry = createStockEntry({
      best_before: "2026-01-25",
      days_until_expiry: 3,
    });

    render(
      <table>
        <tbody>
          <StockRow entry={entry} onConsume={vi.fn()} />
        </tbody>
      </table>,
    );

    expect(screen.getByText("через 3 дн.")).toBeInTheDocument();
    expect(screen.getByText(/25 янв\./)).toBeInTheDocument();
  });

  it("shows warning emoji for expired items", () => {
    const entry = createStockEntry({
      best_before: "2026-01-20",
      days_until_expiry: -2,
    });

    render(
      <table>
        <tbody>
          <StockRow entry={entry} onConsume={vi.fn()} />
        </tbody>
      </table>,
    );

    expect(screen.getByText(/⚠️/)).toBeInTheDocument();
  });

  it("shows alarm emoji for items expiring today", () => {
    const entry = createStockEntry({
      best_before: "2026-01-22",
      days_until_expiry: 0,
    });

    render(
      <table>
        <tbody>
          <StockRow entry={entry} onConsume={vi.fn()} />
        </tbody>
      </table>,
    );

    expect(screen.getByText(/⏰/)).toBeInTheDocument();
  });

  it("shows dash when no expiry date", () => {
    const entry = createStockEntry({
      best_before: undefined,
      days_until_expiry: undefined,
    });

    render(
      <table>
        <tbody>
          <StockRow entry={entry} onConsume={vi.fn()} />
        </tbody>
      </table>,
    );

    expect(screen.getByText("—")).toBeInTheDocument();
  });

  it("calls onConsume when checkmark clicked", async () => {
    const onConsume = vi.fn();
    const entry = createStockEntry();

    const { user } = render(
      <table>
        <tbody>
          <StockRow entry={entry} onConsume={onConsume} />
        </tbody>
      </table>,
    );

    await user.click(screen.getByRole("button"));

    expect(onConsume).toHaveBeenCalledWith(entry);
  });
});
