import { screen, waitFor } from "@testing-library/react";
import { HttpResponse, http } from "msw";
import { describe, expect, it, vi } from "vitest";
import { server } from "@/test/mocks/server";
import { render } from "@/test/utils";
import { RecipeForm } from "./RecipeForm";

const products = [
  { id: "01890000-0000-7000-8000-000000000020", name: "Соус", unit: "piece" },
  { id: "01890000-0000-7000-8000-000000000021", name: "Паста", unit: "piece" },
];

describe("RecipeForm", () => {
  it("creates a recipe with a name and one ingredient", async () => {
    let posted: unknown = null;
    server.use(
      http.get("*/api/internal/v1/products*", () =>
        HttpResponse.json({ data: products, meta: { total: products.length } }),
      ),
      http.post("*/api/internal/v1/recipes", async ({ request }) => {
        posted = await request.json();
        return HttpResponse.json(
          { data: { id: "x", name: "Паста", ingredients: [] } },
          { status: 201 },
        );
      }),
    );
    const onClose = vi.fn();
    const { user } = render(<RecipeForm recipeId={null} onClose={onClose} />);

    await user.type(await screen.findByLabelText(/название/i), "Паста");
    await user.click(
      screen.getByRole("button", { name: /добавить ингредиент/i }),
    );
    const [firstSelect] = await screen.findAllByRole("combobox");
    await user.selectOptions(firstSelect as HTMLElement, products[0]!.id);
    await user.click(screen.getByRole("button", { name: /сохранить/i }));

    await waitFor(() => expect(posted).not.toBeNull());
    expect(onClose).toHaveBeenCalled();
  });
});
