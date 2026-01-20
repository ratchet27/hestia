import { HttpResponse, http } from "msw";
import { describe, expect, it } from "vitest";
import {
  createExpiringEntry,
  createLocation,
  createStockEntry,
  wrapResponse,
} from "@/test/mocks/data";
import { server } from "@/test/mocks/server";
import { render, screen, waitFor } from "@/test/utils";
import { StockPage } from "./StockPage";

describe("StockPage", () => {
  it("loads and displays stock entries", async () => {
    server.use(
      http.get("*/api/internal/v1/stocks/entries", () =>
        HttpResponse.json(
          wrapResponse([
            createStockEntry({ product: { name: "Молоко" } }),
            createStockEntry({ product: { name: "Хлеб" } }),
          ]),
        ),
      ),
    );

    render(<StockPage />);

    expect(await screen.findByText("Молоко")).toBeInTheDocument();
    expect(screen.getByText("Хлеб")).toBeInTheDocument();
  });

  it("filters by location when tab clicked", async () => {
    const fridgeId = "fridge-123";
    const pantryId = "pantry-456";

    server.use(
      http.get("*/api/internal/v1/stocks/entries", () =>
        HttpResponse.json(
          wrapResponse([
            createStockEntry({
              product: { name: "Молоко" },
              location: { id: fridgeId, name: "Холодильник" },
            }),
            createStockEntry({
              product: { name: "Крупа" },
              location: { id: pantryId, name: "Кладовка" },
            }),
          ]),
        ),
      ),
      http.get("*/api/internal/v1/locations", () =>
        HttpResponse.json(
          wrapResponse([
            createLocation({ id: fridgeId, name: "Холодильник" }),
            createLocation({ id: pantryId, name: "Кладовка" }),
          ]),
        ),
      ),
    );

    const { user } = render(<StockPage />);

    // Wait for data to load
    await screen.findByText("Молоко");
    expect(screen.getByText("Крупа")).toBeInTheDocument();

    // Click location tab
    await user.click(screen.getByRole("button", { name: /Холодильник/ }));

    // Should show only Молоко, not Крупа
    expect(screen.getByText("Молоко")).toBeInTheDocument();
    expect(screen.queryByText("Крупа")).not.toBeInTheDocument();
  });

  it("filters by search term", async () => {
    server.use(
      http.get("*/api/internal/v1/stocks/entries", () =>
        HttpResponse.json(
          wrapResponse([
            createStockEntry({ product: { name: "Молоко" } }),
            createStockEntry({ product: { name: "Хлеб" } }),
          ]),
        ),
      ),
    );

    const { user } = render(<StockPage />);

    // Wait for data to load
    await screen.findByText("Молоко");
    expect(screen.getByText("Хлеб")).toBeInTheDocument();

    // Type in search
    const searchInput = screen.getByPlaceholderText("Поиск по названию...");
    await user.type(searchInput, "Мол");

    // Should show only Молоко
    expect(screen.getByText("Молоко")).toBeInTheDocument();
    expect(screen.queryByText("Хлеб")).not.toBeInTheDocument();
  });

  it("shows expiring items in attention section", async () => {
    server.use(
      http.get("*/api/internal/v1/stocks/expiring", () =>
        HttpResponse.json(
          wrapResponse([
            createExpiringEntry({
              product: { name: "Йогурт" },
              days_until_expiry: 1,
            }),
            createExpiringEntry({
              product: { name: "Сметана" },
              days_until_expiry: -1,
            }),
          ]),
        ),
      ),
    );

    render(<StockPage />);

    // Attention section should show expiring items
    expect(await screen.findByText("Йогурт")).toBeInTheDocument();
    expect(screen.getByText("Сметана")).toBeInTheDocument();
  });

  it("shows loading state while fetching entries", async () => {
    // Use a delayed response
    server.use(
      http.get("*/api/internal/v1/stocks/entries", async () => {
        await new Promise((resolve) => setTimeout(resolve, 100));
        return HttpResponse.json(wrapResponse([]));
      }),
    );

    render(<StockPage />);

    // Check for loading indicator
    expect(screen.getByText("Загрузка...")).toBeInTheDocument();

    // Wait for loading to finish
    await waitFor(() => {
      expect(screen.queryByText("Загрузка...")).not.toBeInTheDocument();
    });
  });

  it("displays total count in tabs", async () => {
    server.use(
      http.get("*/api/internal/v1/stocks/entries", () =>
        HttpResponse.json(
          wrapResponse([
            createStockEntry({ product: { name: "Item1" } }),
            createStockEntry({ product: { name: "Item2" } }),
            createStockEntry({ product: { name: "Item3" } }),
          ]),
        ),
      ),
    );

    render(<StockPage />);

    // Wait for entries to load (visible in table)
    await screen.findByText("Item1");

    // Should show count of 3 in "Все" tab
    expect(screen.getByText("3")).toBeInTheDocument();
  });
});
