import { HttpResponse, http } from "msw";
import { describe, expect, it } from "vitest";
import { server } from "@/test/mocks/server";
import { render, screen, waitFor } from "@/test/utils";
import { RecipesPage } from "./RecipesPage";

const recipe = {
  id: "01890000-0000-7000-8000-000000000001",
  name: "Паста",
  instructions: null,
  source_url: null,
  cookable: false,
  created_at: "2026-06-04T00:00:00+00:00",
  ingredients: [
    {
      id: "01890000-0000-7000-8000-000000000010",
      product_id: "01890000-0000-7000-8000-000000000020",
      product_name: "Соус",
      required_count: 2,
      consume_on_cook: true,
      in_stock: 1,
      has_enough: false,
      shortfall: 1,
      product_inactive: false,
    },
  ],
};

const cookableRecipe = {
  id: "01890000-0000-7000-8000-000000000002",
  name: "Омлет",
  instructions: null,
  source_url: null,
  cookable: true,
  created_at: "2026-06-04T00:00:00+00:00",
  ingredients: [
    {
      id: "01890000-0000-7000-8000-000000000011",
      product_id: "01890000-0000-7000-8000-000000000021",
      product_name: "Яйцо",
      required_count: 2,
      consume_on_cook: true,
      in_stock: 3,
      has_enough: true,
      shortfall: 0,
      product_inactive: false,
    },
  ],
};

describe("RecipesPage", () => {
  it("renders a recipe card with ingredient details and disabled Cook button when not cookable", async () => {
    server.use(
      http.get("*/api/internal/v1/recipes", () =>
        HttpResponse.json({ data: [recipe], meta: {} }),
      ),
    );

    render(<RecipesPage />);

    // Recipe name appears
    expect(await screen.findByText("Паста")).toBeInTheDocument();

    // Ingredient name appears
    expect(screen.getByText("Соус")).toBeInTheDocument();

    // Stock count "1 / 2" appears
    expect(screen.getByText(/1 \/ 2/)).toBeInTheDocument();

    // Cook button is disabled because not cookable
    const cookButton = screen.getByRole("button", { name: /Приготовить/i });
    expect(cookButton).toBeDisabled();
  });

  it("enables Cook button when recipe is cookable", async () => {
    server.use(
      http.get("*/api/internal/v1/recipes", () =>
        HttpResponse.json({ data: [cookableRecipe], meta: {} }),
      ),
    );

    render(<RecipesPage />);

    expect(await screen.findByText("Омлет")).toBeInTheDocument();

    const cookButton = screen.getByRole("button", { name: /Приготовить/i });
    expect(cookButton).not.toBeDisabled();
  });

  it("calls add-missing endpoint when 'В список покупок' button is clicked", async () => {
    let addMissingCalled = false;

    server.use(
      http.get("*/api/internal/v1/recipes", () =>
        HttpResponse.json({ data: [recipe], meta: {} }),
      ),
      http.post(
        `*/api/internal/v1/recipes/${recipe.id}/add-missing-to-shopping-list`,
        () => {
          addMissingCalled = true;
          return HttpResponse.json({ data: {} });
        },
      ),
    );

    const { user } = render(<RecipesPage />);

    // Wait for recipe to load
    expect(await screen.findByText("Паста")).toBeInTheDocument();

    // Click "В список покупок"
    const addButton = screen.getByRole("button", { name: /В список покупок/i });
    await user.click(addButton);

    await waitFor(() => {
      expect(addMissingCalled).toBe(true);
    });
  });

  it("shows loading state while fetching recipes", () => {
    server.use(
      http.get("*/api/internal/v1/recipes", async () => {
        await new Promise((r) => setTimeout(r, 100));
        return HttpResponse.json({ data: [], meta: {} });
      }),
    );

    render(<RecipesPage />);

    expect(screen.getByText("Загрузка...")).toBeInTheDocument();
  });
});
