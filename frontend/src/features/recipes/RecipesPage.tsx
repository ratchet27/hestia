import { type ReactElement, useState } from "react";
import { useTranslation } from "react-i18next";
import {
  useAddMissingToShoppingList,
  useCookRecipe,
  useRecipes,
} from "../../api/queries/recipes";
import { Icons } from "../../components/Icons";
import { ingredientsOf } from "./ingredients";
import { RecipeForm } from "./RecipeForm";

export function RecipesPage(): ReactElement {
  const { t } = useTranslation();
  const { data: recipes = [], isLoading } = useRecipes();
  const cook = useCookRecipe();
  const addMissing = useAddMissingToShoppingList();
  const [editingId, setEditingId] = useState<string | null>(null);
  const [creating, setCreating] = useState(false);

  return (
    <div className="p-8">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-3xl font-bold text-stone-800">
            {t("recipes.title")}
          </h2>
          <p className="text-stone-500 mt-1">{t("recipes.subtitle")}</p>
        </div>
        <button
          type="button"
          onClick={() => setCreating(true)}
          className="flex items-center gap-2 px-4 py-2 bg-stone-800 text-white rounded-lg hover:bg-stone-700 transition-colors"
        >
          <Icons.Plus />
          {t("recipes.newRecipe")}
        </button>
      </div>

      {isLoading && <p className="text-stone-500">{t("common.loading")}</p>}

      <div className="grid grid-cols-2 gap-6">
        {recipes.map((recipe) => {
          const ingredients = ingredientsOf(recipe);
          const missingCount = ingredients.filter((i) => !i.has_enough).length;

          return (
            <div
              key={recipe.id}
              className="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden"
            >
              <div className="p-4 border-b border-stone-100 flex justify-between items-center">
                <h3 className="font-semibold text-stone-800 text-lg">
                  {recipe.name}
                </h3>
                {recipe.cookable ? (
                  <span className="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium">
                    {t("recipes.canCook")}
                  </span>
                ) : (
                  <span className="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-medium">
                    {t("recipes.missingIngredients", { count: missingCount })}
                  </span>
                )}
              </div>
              <div className="p-4">
                <h4 className="text-sm font-medium text-stone-600 mb-3">
                  {t("recipes.ingredients")}
                </h4>
                <div className="space-y-2">
                  {ingredients.map((ing) => (
                    <div
                      key={ing.id}
                      className="flex items-center justify-between"
                    >
                      <span
                        className={
                          ing.has_enough ? "text-stone-800" : "text-red-600"
                        }
                      >
                        {ing.product_name}
                        {ing.consume_on_cook ? "" : ` ${t("recipes.staple")}`}
                      </span>
                      <span
                        className={`text-sm ${ing.has_enough ? "text-green-600" : "text-red-600"}`}
                      >
                        {ing.in_stock} / {ing.required_count}
                        {ing.has_enough ? " ✓" : " ✗"}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
              <div className="p-4 bg-stone-50 border-t border-stone-100 flex gap-2">
                <button
                  type="button"
                  disabled={
                    !recipe.cookable ||
                    (cook.isPending && cook.variables === recipe.id)
                  }
                  onClick={() => cook.mutate(recipe.id)}
                  className="flex-1 px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {t("recipes.cook")}
                </button>
                {!recipe.cookable && (
                  <button
                    type="button"
                    disabled={
                      addMissing.isPending && addMissing.variables === recipe.id
                    }
                    onClick={() => addMissing.mutate(recipe.id)}
                    className="px-4 py-2 border border-stone-300 rounded-lg hover:bg-white transition-colors"
                  >
                    {t("recipes.addToShoppingList")}
                  </button>
                )}
                <button
                  type="button"
                  onClick={() => setEditingId(recipe.id)}
                  className="px-4 py-2 border border-stone-300 rounded-lg hover:bg-white transition-colors"
                >
                  {t("common.edit")}
                </button>
              </div>
            </div>
          );
        })}
      </div>

      {(creating || editingId) && (
        <RecipeForm
          recipeId={editingId}
          onClose={() => {
            setCreating(false);
            setEditingId(null);
          }}
        />
      )}
    </div>
  );
}
