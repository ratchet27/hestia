import { HttpResponse, http } from "msw";
import { describe, expect, it } from "vitest";
import { createShoppingItem, wrapResponse } from "@/test/mocks/data";
import { server } from "@/test/mocks/server";
import { render, screen, waitFor } from "@/test/utils";
import { ShoppingPage } from "../ShoppingPage";

describe("ShoppingPage", () => {
  it("displays loading state initially", () => {
    server.use(
      http.get("*/api/internal/v1/shopping-list", async () => {
        await new Promise((r) => setTimeout(r, 100));
        return HttpResponse.json(wrapResponse([]));
      }),
    );

    render(<ShoppingPage />);
    expect(screen.getByText("Загрузка...")).toBeInTheDocument();
  });

  it("displays empty state when list is empty", async () => {
    server.use(
      http.get("*/api/internal/v1/shopping-list", () =>
        HttpResponse.json(wrapResponse([])),
      ),
    );

    render(<ShoppingPage />);

    await waitFor(() => {
      expect(
        screen.getByText("Список пуст. Найдите товар выше, чтобы добавить."),
      ).toBeInTheDocument();
    });
  });

  it("adds custom item via search input", async () => {
    let addedItem: unknown = null;

    server.use(
      http.get("*/api/internal/v1/shopping-list", () =>
        HttpResponse.json(wrapResponse([])),
      ),
      http.post("*/api/internal/v1/shopping-list", async ({ request }) => {
        addedItem = await request.json();
        return HttpResponse.json(
          { data: createShoppingItem({ name: "Бананы" }) },
          { status: 201 },
        );
      }),
      http.get("*/api/internal/v1/products", () =>
        HttpResponse.json(wrapResponse([])),
      ),
    );

    const { user } = render(<ShoppingPage />);

    await waitFor(() => {
      expect(
        screen.getByPlaceholderText("Добавить товар..."),
      ).toBeInTheDocument();
    });

    await user.type(screen.getByPlaceholderText("Добавить товар..."), "Бананы");
    await user.click(screen.getByRole("button", { name: "Добавить" }));

    await waitFor(() => {
      expect(addedItem).toEqual({ custom_name: "Бананы", amount: 1 });
    });
  });
});
