import { describe, expect, it, vi } from "vitest";
import { createShoppingItem } from "@/test/mocks/data";
import { render, screen, waitFor } from "@/test/utils";
import { ShoppingListItem } from "./ShoppingListItem";

describe("ShoppingListItem", () => {
  it("renders item name and amount", () => {
    const item = createShoppingItem({
      name: "Хлеб",
      amount: 2,
    });

    render(
      <ShoppingListItem item={item} onClick={vi.fn()} onDelete={vi.fn()} />,
    );

    expect(screen.getByText("Хлеб")).toBeInTheDocument();
    expect(screen.getByText("2 шт.")).toBeInTheDocument();
  });

  it("shows note when present", () => {
    const item = createShoppingItem({
      note: "Бородинский",
    });

    render(
      <ShoppingListItem item={item} onClick={vi.fn()} onDelete={vi.fn()} />,
    );

    expect(screen.getByText("Бородинский")).toBeInTheDocument();
  });

  it('shows "Авто" badge for auto source', () => {
    const item = createShoppingItem({
      source: "auto",
    });

    render(
      <ShoppingListItem item={item} onClick={vi.fn()} onDelete={vi.fn()} />,
    );

    expect(screen.getByText("Авто")).toBeInTheDocument();
  });

  it("does not show badge for manual source", () => {
    const item = createShoppingItem({
      source: "manual",
    });

    render(
      <ShoppingListItem item={item} onClick={vi.fn()} onDelete={vi.fn()} />,
    );

    expect(screen.queryByText("Авто")).not.toBeInTheDocument();
  });

  it("calls onClick when row clicked", async () => {
    const onClick = vi.fn();
    const item = createShoppingItem();

    const { user } = render(
      <ShoppingListItem item={item} onClick={onClick} onDelete={vi.fn()} />,
    );

    await user.click(screen.getByRole("button", { name: /Хлеб|Молоко/i }));

    expect(onClick).toHaveBeenCalled();
  });

  it("calls onDelete when checkbox clicked", async () => {
    const onDelete = vi.fn();
    const item = createShoppingItem();

    const { user } = render(
      <ShoppingListItem item={item} onClick={vi.fn()} onDelete={onDelete} />,
    );

    await user.click(
      screen.getByRole("button", { name: "Отметить купленным" }),
    );

    await waitFor(
      () => {
        expect(onDelete).toHaveBeenCalled();
      },
      { timeout: 500 },
    );
  });

  it("handles keyboard Enter", async () => {
    const onClick = vi.fn();
    const item = createShoppingItem();

    const { user } = render(
      <ShoppingListItem item={item} onClick={onClick} onDelete={vi.fn()} />,
    );

    const row = screen.getByRole("button", { name: /Молоко/ });
    row.focus();
    await user.keyboard("{Enter}");

    expect(onClick).toHaveBeenCalled();
  });
});
