import type {
  RecipeIngredientPayload,
  RecipeIngredientResponse,
  RecipeResponse,
} from "../../api/generated/models";

// The API serialises `ingredients` either as an array or as an object keyed by
// index (a Doctrine collection quirk the generated type reflects); normalise
// once here instead of at every read site.
export function ingredientsOf(
  recipe: Pick<RecipeResponse, "ingredients"> | undefined,
): RecipeIngredientResponse[] {
  const ingredients = recipe?.ingredients;
  if (!ingredients) return [];
  return Array.isArray(ingredients) ? ingredients : Object.values(ingredients);
}

export function toIngredientPayloads(
  ingredients: RecipeIngredientResponse[],
): RecipeIngredientPayload[] {
  return ingredients.map((i) => ({
    product_id: i.product_id,
    required_count: i.required_count ?? 1,
    consume_on_cook: i.consume_on_cook ?? true,
  }));
}
