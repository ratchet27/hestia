import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor } from "@testing-library/react";
import { HttpResponse, http } from "msw";
import type { ReactNode } from "react";
import { describe, expect, it, type MockInstance, vi } from "vitest";
import { server } from "@/test/mocks/server";
import { queryKeys } from "./keys";
import { useUpdateProduct } from "./products";
import { useCookRecipe } from "./recipes";
import { useAddStock, useConsumeStock } from "./stocks";

// The backend reconciles AUTO shopping-list items whenever stock changes
// (StockChangedHandler). Every mutation that can move stock or min_stock must
// therefore drop the shopping-list cache, or the Dashboard badge and the
// Shopping page show stale data for staleTime (5 min).

function setup() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });
  const invalidate = vi.spyOn(queryClient, "invalidateQueries");
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  );
  return { invalidate, wrapper };
}

function invalidatedKeys(
  invalidate: MockInstance<QueryClient["invalidateQueries"]>,
) {
  return invalidate.mock.calls.map((call) => call[0]?.queryKey);
}

describe("shopping-list cache invalidation", () => {
  it("useConsumeStock invalidates stocks and the shopping list", async () => {
    server.use(
      http.post("*/api/internal/v1/stocks/consume", () =>
        HttpResponse.json({ data: { consumed: 1, deleted_ids: [] } }),
      ),
    );
    const { invalidate, wrapper } = setup();
    const { result } = renderHook(() => useConsumeStock(), { wrapper });

    await result.current.mutateAsync({
      product_id: "p1",
      location_id: "l1",
      quantity: 1,
    });

    await waitFor(() => {
      expect(invalidatedKeys(invalidate)).toEqual(
        expect.arrayContaining([
          queryKeys.stocks.all,
          queryKeys.shoppingList.all,
        ]),
      );
    });
  });

  it("useAddStock invalidates stocks and the shopping list", async () => {
    server.use(
      http.post("*/api/internal/v1/stocks/add", () =>
        HttpResponse.json(
          { data: { created: 1, entries: [] } },
          { status: 201 },
        ),
      ),
    );
    const { invalidate, wrapper } = setup();
    const { result } = renderHook(() => useAddStock(), { wrapper });

    await result.current.mutateAsync({
      product_id: "p1",
      location_id: "l1",
      quantity: 1,
    });

    await waitFor(() => {
      expect(invalidatedKeys(invalidate)).toEqual(
        expect.arrayContaining([
          queryKeys.stocks.all,
          queryKeys.shoppingList.all,
        ]),
      );
    });
  });

  it("useCookRecipe invalidates recipes, stocks and the shopping list", async () => {
    server.use(
      http.post("*/api/internal/v1/recipes/:id/cook", () =>
        HttpResponse.json({ data: { id: "r1" } }),
      ),
    );
    const { invalidate, wrapper } = setup();
    const { result } = renderHook(() => useCookRecipe(), { wrapper });

    await result.current.mutateAsync("r1");

    await waitFor(() => {
      expect(invalidatedKeys(invalidate)).toEqual(
        expect.arrayContaining([
          queryKeys.recipes.all,
          queryKeys.stocks.all,
          queryKeys.shoppingList.all,
        ]),
      );
    });
  });

  it("useUpdateProduct invalidates the shopping list (min_stock / active can change)", async () => {
    server.use(
      http.put("*/api/internal/v1/products/:id", () =>
        HttpResponse.json({ data: { id: "p1" } }),
      ),
    );
    const { invalidate, wrapper } = setup();
    const { result } = renderHook(() => useUpdateProduct(), { wrapper });

    await result.current.mutateAsync({
      id: "p1",
      data: {
        name: "Milk",
        category_id: "c1",
        default_location_id: "l1",
        min_stock: 2,
        active: false,
      },
    });

    await waitFor(() => {
      expect(invalidatedKeys(invalidate)).toEqual(
        expect.arrayContaining([
          queryKeys.products.all,
          queryKeys.shoppingList.all,
        ]),
      );
    });
  });
});
