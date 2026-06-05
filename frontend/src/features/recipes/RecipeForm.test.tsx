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

const R_ID = "01890000-0000-7000-8000-000000000099";

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
    expect((posted as { name: string }).name).toBe("Паста");
    expect(
      (posted as { ingredients: { product_id: string }[] }).ingredients[0]
        ?.product_id,
    ).toBe(products[0]!.id);
    expect(onClose).toHaveBeenCalled();
  });

  it("prefills name in edit mode and submits updated name via PUT", async () => {
    const recipe = {
      id: R_ID,
      name: "Старое",
      instructions: null,
      source_url: null,
      ingredients: [
        {
          product_id: products[0]!.id,
          required_count: 1,
          consume_on_cook: true,
        },
      ],
    };

    let putBody: unknown = null;
    server.use(
      http.get("*/api/internal/v1/recipes", () =>
        HttpResponse.json({ data: [recipe], meta: { total: 1 } }),
      ),
      http.get("*/api/internal/v1/products*", () =>
        HttpResponse.json({ data: products, meta: { total: products.length } }),
      ),
      http.put("*/api/internal/v1/recipes/*", async ({ request }) => {
        putBody = await request.json();
        return HttpResponse.json({ data: { ...recipe, name: "Новое" } });
      }),
    );

    const onClose = vi.fn();
    const { user } = render(<RecipeForm recipeId={R_ID} onClose={onClose} />);

    const nameInput = await screen.findByLabelText(/название/i);
    // Wait for the async recipes query to resolve and reset() to fire.
    await waitFor(() =>
      expect((nameInput as HTMLInputElement).value).toBe("Старое"),
    );

    await user.clear(nameInput);
    await user.type(nameInput, "Новое");
    await user.click(screen.getByRole("button", { name: /сохранить/i }));

    await waitFor(() => expect(putBody).not.toBeNull());
    expect((putBody as { name: string }).name).toBe("Новое");
    expect(onClose).toHaveBeenCalled();
  });

  it("shows an error and does not POST when there are no ingredients", async () => {
    let posted: unknown = null;
    server.use(
      http.get("*/api/internal/v1/products*", () =>
        HttpResponse.json({ data: products, meta: { total: products.length } }),
      ),
      http.post("*/api/internal/v1/recipes", async ({ request }) => {
        posted = await request.json();
        return HttpResponse.json(
          { data: { id: "y", name: "Тест", ingredients: [] } },
          { status: 201 },
        );
      }),
    );
    const onClose = vi.fn();
    const { user } = render(<RecipeForm recipeId={null} onClose={onClose} />);

    await user.type(await screen.findByLabelText(/название/i), "Тест");
    // deliberately do NOT add any ingredient
    await user.click(screen.getByRole("button", { name: /сохранить/i }));

    await screen.findByText(/добавьте хотя бы один ингредиент/i);
    expect(posted).toBeNull();
    expect(onClose).not.toHaveBeenCalled();
  });

  it("rejects an invalid source URL client-side without posting", async () => {
    let posted: unknown = null;
    server.use(
      http.get("*/api/internal/v1/products*", () =>
        HttpResponse.json({ data: products, meta: { total: products.length } }),
      ),
      http.post("*/api/internal/v1/recipes", async ({ request }) => {
        posted = await request.json();
        return HttpResponse.json({ data: { id: "z" } }, { status: 201 });
      }),
    );
    const onClose = vi.fn();
    const { user } = render(<RecipeForm recipeId={null} onClose={onClose} />);

    await user.type(await screen.findByLabelText(/название/i), "Тест");
    await user.click(
      screen.getByRole("button", { name: /добавить ингредиент/i }),
    );
    const [firstSelect] = await screen.findAllByRole("combobox");
    await user.selectOptions(firstSelect as HTMLElement, products[0]!.id);
    await user.type(
      screen.getByLabelText(/ссылка на источник/i),
      "www.foo.com",
    );
    await user.click(screen.getByRole("button", { name: /сохранить/i }));

    await screen.findByText(/корректную ссылку/i);
    expect(posted).toBeNull();
    expect(onClose).not.toHaveBeenCalled();
  });

  it("surfaces a backend 422 validation error on the offending field", async () => {
    server.use(
      http.get("*/api/internal/v1/products*", () =>
        HttpResponse.json({ data: products, meta: { total: products.length } }),
      ),
      // Backend rejects with its real shape: errors: [{ property, violation }].
      http.post("*/api/internal/v1/recipes", () =>
        HttpResponse.json(
          {
            title: "Validation failed",
            type: "VALIDATION_ERROR",
            code: 422,
            errors: [
              { property: "source_url", violation: "Сервер отклонил ссылку" },
            ],
          },
          { status: 422 },
        ),
      ),
    );
    const onClose = vi.fn();
    const { user } = render(<RecipeForm recipeId={null} onClose={onClose} />);

    await user.type(await screen.findByLabelText(/название/i), "Тест");
    await user.click(
      screen.getByRole("button", { name: /добавить ингредиент/i }),
    );
    const [firstSelect] = await screen.findAllByRole("combobox");
    await user.selectOptions(firstSelect as HTMLElement, products[0]!.id);
    // A client-valid URL so submission reaches the server.
    await user.type(
      screen.getByLabelText(/ссылка на источник/i),
      "https://example.com",
    );
    await user.click(screen.getByRole("button", { name: /сохранить/i }));

    // The backend `errors[].property` is normalized to the source_url field error.
    await screen.findByText(/сервер отклонил ссылку/i);
    expect(onClose).not.toHaveBeenCalled();
  });
});
