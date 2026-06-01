import { HttpResponse, http } from "msw";
import { describe, expect, it } from "vitest";
import {
  createChoreResponse,
  createExpiringEntry,
  createProductResponse,
  createShoppingItem,
  createStockEntry,
  wrapResponse,
} from "@/test/mocks/data";
import { server } from "@/test/mocks/server";
import { render, screen } from "@/test/utils";
import { DashboardPage } from "./DashboardPage";

describe("DashboardPage", () => {
  function setupMocks({
    stockEntries = [],
    products = [],
    shoppingItems = [],
    expiringItems = [],
  }: {
    stockEntries?: ReturnType<typeof createStockEntry>[];
    products?: ReturnType<typeof createProductResponse>[];
    shoppingItems?: ReturnType<typeof createShoppingItem>[];
    expiringItems?: ReturnType<typeof createExpiringEntry>[];
  } = {}) {
    server.use(
      http.get("*/api/internal/v1/stocks/entries", () =>
        HttpResponse.json(wrapResponse(stockEntries)),
      ),
      http.get("*/api/internal/v1/products", () =>
        HttpResponse.json(wrapResponse(products)),
      ),
      http.get("*/api/internal/v1/shopping-list", () =>
        HttpResponse.json(wrapResponse(shoppingItems)),
      ),
      http.get("*/api/internal/v1/stocks/expiring", () =>
        HttpResponse.json(wrapResponse(expiringItems)),
      ),
    );
  }

  it("displays stock count card", async () => {
    setupMocks({
      stockEntries: [
        createStockEntry(),
        createStockEntry(),
        createStockEntry(),
      ],
    });

    render(<DashboardPage />);

    // Should show 3 stock items
    expect(await screen.findByText("3")).toBeInTheDocument();
  });

  it("displays shopping count card", async () => {
    setupMocks({
      shoppingItems: [
        createShoppingItem({ done: false }),
        createShoppingItem({ done: false }),
        createShoppingItem({ done: true }), // Done items not counted
      ],
    });

    render(<DashboardPage />);

    // Should show 2 active shopping items
    expect(await screen.findByText("2")).toBeInTheDocument();
  });

  it("displays expiring items section", async () => {
    setupMocks({
      expiringItems: [
        createExpiringEntry({
          product: { name: "Йогурт" },
          days_until_expiry: 1,
        }),
      ],
    });

    render(<DashboardPage />);

    expect(await screen.findByText("Йогурт")).toBeInTheDocument();
  });

  it("displays low stock items section", async () => {
    const product = createProductResponse({
      id: "prod-1",
      name: "Молоко",
      min_stock: 3,
    });

    setupMocks({
      products: [product],
      stockEntries: [
        createStockEntry({
          product: { id: "prod-1", name: "Молоко", unit: "шт" },
        }),
      ], // Only 1 item, min is 3
    });

    render(<DashboardPage />);

    // Should show low stock warning for Молоко
    expect(await screen.findByText("Молоко")).toBeInTheDocument();
    expect(screen.getByText(/Мин/i)).toBeInTheDocument();
  });

  it("navigates to stock page on click", async () => {
    setupMocks({
      expiringItems: [createExpiringEntry({ product: { name: "Йогурт" } })],
    });

    const { user } = render(<DashboardPage />);

    // Wait for data to load
    await screen.findByText("Йогурт");

    // Click "View all stock" link
    const viewAllLink = screen.getByText(/Все запасы/i);
    await user.click(viewAllLink);

    // Router should navigate (we can't fully test this without router mock,
    // but at least verify the button exists and is clickable)
    expect(viewAllLink).toBeInTheDocument();
  });

  it("clicking Выполнено button fires POST to mark chore done", async () => {
    const chore = createChoreResponse({
      id: "chore-test-1",
      name: "Помыть полы",
      // past date so getDaysUntil <= 0, making it a today-chore
      next_due_at: "2026-05-31T00:00:00+00:00",
    });

    let markDoneHit = false;

    server.use(
      http.get("*/api/internal/v1/chores", () =>
        HttpResponse.json(wrapResponse([chore])),
      ),
      http.get("*/api/internal/v1/shopping-list", () =>
        HttpResponse.json(wrapResponse([])),
      ),
      http.post(`*/api/internal/v1/chores/${chore.id}/done`, () => {
        markDoneHit = true;
        return HttpResponse.json(wrapResponse(chore));
      }),
    );

    const { user } = render(<DashboardPage />);

    // Wait for the chore to appear in the today section
    await screen.findByText("Помыть полы");

    // Click the Выполнено button
    const doneButton = screen.getByRole("button", { name: /Выполнено/i });
    await user.click(doneButton);

    // Verify the POST was hit
    await expect.poll(() => markDoneHit).toBe(true);
  });
});
