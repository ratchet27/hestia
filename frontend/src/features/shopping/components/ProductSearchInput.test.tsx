import { HttpResponse, http } from "msw";
import { describe, expect, it, vi } from "vitest";
import { createProductResponse, wrapResponse } from "@/test/mocks/data";
import { server } from "@/test/mocks/server";
import { render, screen, waitFor } from "@/test/utils";
import { ProductSearchInput } from "./ProductSearchInput";

describe("ProductSearchInput", () => {
  it("renders input and add button", () => {
    server.use(
      http.get("*/api/internal/v1/products", () =>
        HttpResponse.json(wrapResponse([])),
      ),
    );

    render(<ProductSearchInput onAddProduct={vi.fn()} onAddCustom={vi.fn()} />);

    expect(
      screen.getByPlaceholderText("Добавить товар..."),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Добавить" }),
    ).toBeInTheDocument();
  });

  it("filters products as user types", async () => {
    server.use(
      http.get("*/api/internal/v1/products", () =>
        HttpResponse.json(
          wrapResponse([
            createProductResponse({ name: "Молоко" }),
            createProductResponse({ name: "Масло" }),
            createProductResponse({ name: "Хлеб" }),
          ]),
        ),
      ),
    );

    const { user } = render(
      <ProductSearchInput onAddProduct={vi.fn()} onAddCustom={vi.fn()} />,
    );

    await user.type(screen.getByPlaceholderText("Добавить товар..."), "Мол");

    await waitFor(() => {
      expect(screen.getByText("Молоко")).toBeInTheDocument();
    });
    expect(screen.queryByText("Хлеб")).not.toBeInTheDocument();
  });

  it("selects product from dropdown", async () => {
    const onAddProduct = vi.fn();
    const productId = "product-123";

    server.use(
      http.get("*/api/internal/v1/products", () =>
        HttpResponse.json(
          wrapResponse([
            createProductResponse({ id: productId, name: "Молоко" }),
          ]),
        ),
      ),
    );

    const { user } = render(
      <ProductSearchInput onAddProduct={onAddProduct} onAddCustom={vi.fn()} />,
    );

    await user.type(screen.getByPlaceholderText("Добавить товар..."), "Мол");

    await waitFor(() => {
      expect(screen.getByText("Молоко")).toBeInTheDocument();
    });

    await user.click(screen.getByText("Молоко"));

    expect(onAddProduct).toHaveBeenCalledWith(productId);
  });

  it("adds custom item when no match", async () => {
    const onAddCustom = vi.fn();

    server.use(
      http.get("*/api/internal/v1/products", () =>
        HttpResponse.json(wrapResponse([])),
      ),
    );

    const { user } = render(
      <ProductSearchInput onAddProduct={vi.fn()} onAddCustom={onAddCustom} />,
    );

    await user.type(screen.getByPlaceholderText("Добавить товар..."), "Бананы");
    await user.click(screen.getByRole("button", { name: "Добавить" }));

    expect(onAddCustom).toHaveBeenCalledWith("Бананы");
  });

  it("navigates dropdown with keyboard and selects", async () => {
    const onAddProduct = vi.fn();
    const productId = "product-456";

    server.use(
      http.get("*/api/internal/v1/products", () =>
        HttpResponse.json(
          wrapResponse([
            createProductResponse({ id: "other-id", name: "Молоко" }),
            createProductResponse({ id: productId, name: "Масло" }),
          ]),
        ),
      ),
    );

    const { user } = render(
      <ProductSearchInput onAddProduct={onAddProduct} onAddCustom={vi.fn()} />,
    );

    const input = screen.getByPlaceholderText("Добавить товар...");
    await user.type(input, "М");

    await waitFor(() => {
      expect(screen.getByText("Молоко")).toBeInTheDocument();
    });

    // Navigate down to second item and select
    await user.keyboard("{ArrowDown}");
    await user.keyboard("{Enter}");

    expect(onAddProduct).toHaveBeenCalledWith(productId);
  });

  it("closes dropdown on escape", async () => {
    server.use(
      http.get("*/api/internal/v1/products", () =>
        HttpResponse.json(
          wrapResponse([createProductResponse({ name: "Молоко" })]),
        ),
      ),
    );

    const { user } = render(
      <ProductSearchInput onAddProduct={vi.fn()} onAddCustom={vi.fn()} />,
    );

    const input = screen.getByPlaceholderText("Добавить товар...");
    await user.type(input, "Мол");

    await waitFor(() => {
      expect(screen.getByText("Молоко")).toBeInTheDocument();
    });

    await user.keyboard("{Escape}");

    await waitFor(() => {
      expect(screen.queryByText("Молоко")).not.toBeInTheDocument();
    });
  });
});
