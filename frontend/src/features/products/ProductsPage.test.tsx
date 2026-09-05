import { HttpResponse, http } from "msw";
import { describe, expect, it } from "vitest";
import {
  createCategory,
  createLocation,
  createProductResponse,
  wrapResponse,
} from "@/test/mocks/data";
import { server } from "@/test/mocks/server";
import { render, screen, waitFor } from "@/test/utils";
import { ProductsPage } from "./ProductsPage";

describe("ProductsPage", () => {
  const categories = [
    createCategory({ id: "cat-1", name: "Молочные" }),
    createCategory({ id: "cat-2", name: "Мясо" }),
  ];

  const locations = [createLocation({ id: "loc-1", name: "Холодильник" })];

  const products = [
    createProductResponse({
      id: "prod-1",
      name: "Молоко",
      category: categories[0],
      default_location: locations[0],
    }),
    createProductResponse({
      id: "prod-2",
      name: "Курица",
      category: categories[1],
      default_location: locations[0],
    }),
  ];

  function setupMocks(productList = products) {
    server.use(
      http.get("*/api/internal/v1/products", () =>
        HttpResponse.json(wrapResponse(productList)),
      ),
      http.get("*/api/internal/v1/categories", () =>
        HttpResponse.json(wrapResponse(categories)),
      ),
      http.get("*/api/internal/v1/locations", () =>
        HttpResponse.json(wrapResponse(locations)),
      ),
    );
  }

  it("loads and displays products", async () => {
    setupMocks();

    render(<ProductsPage />);

    expect(await screen.findByText("Молоко")).toBeInTheDocument();
    expect(screen.getByText("Курица")).toBeInTheDocument();
  });

  it("shows loading skeleton initially", async () => {
    server.use(
      http.get("*/api/internal/v1/products", async () => {
        await new Promise((resolve) => setTimeout(resolve, 100));
        return HttpResponse.json(wrapResponse(products));
      }),
      http.get("*/api/internal/v1/categories", () =>
        HttpResponse.json(wrapResponse(categories)),
      ),
      http.get("*/api/internal/v1/locations", () =>
        HttpResponse.json(wrapResponse(locations)),
      ),
    );

    render(<ProductsPage />);

    expect(screen.getByRole("status")).toHaveAttribute("aria-busy", "true");

    // Wait for products to load
    await screen.findByText("Молоко");
  });

  it("shows error state on API failure", async () => {
    setupMocks();
    server.use(
      http.get("*/api/internal/v1/products", () =>
        HttpResponse.json({ error: "Server error" }, { status: 500 }),
      ),
    );

    render(<ProductsPage />);

    await waitFor(() => {
      expect(
        screen.getByText(/Не удалось загрузить товары/i),
      ).toBeInTheDocument();
    });
  });

  it("filters by search term", async () => {
    setupMocks();

    const { user } = render(<ProductsPage />);

    // Wait for products to load
    await screen.findByText("Молоко");
    expect(screen.getByText("Курица")).toBeInTheDocument();

    // Type in search
    await user.type(screen.getByPlaceholderText(/Поиск по названию/i), "Мол");

    // Should show only Молоко
    expect(screen.getByText("Молоко")).toBeInTheDocument();
    expect(screen.queryByText("Курица")).not.toBeInTheDocument();
  });

  it("filters by category", async () => {
    setupMocks();

    const { user } = render(<ProductsPage />);

    // Wait for products to load
    await screen.findByText("Молоко");
    expect(screen.getByText("Курица")).toBeInTheDocument();

    // Select category filter
    await user.selectOptions(
      screen.getByRole("combobox", { name: "" }),
      "cat-2",
    );

    // Should show only Курица (Мясо category)
    expect(screen.queryByText("Молоко")).not.toBeInTheDocument();
    expect(screen.getByText("Курица")).toBeInTheDocument();
  });

  it("opens add modal when button clicked", async () => {
    setupMocks();

    const { user } = render(<ProductsPage />);

    // Wait for products to load
    await screen.findByText("Молоко");

    // Click add button
    await user.click(screen.getByRole("button", { name: /Новый товар/i }));

    // Modal should open - check for the form inside the modal
    expect(screen.getByLabelText(/Название/i)).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: /Создать/i }),
    ).toBeInTheDocument();
  });
});
