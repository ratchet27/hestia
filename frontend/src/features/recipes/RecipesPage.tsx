import { useStockEntries } from "../../api/queries/stocks";
import { Icons } from "../../components/Icons";
import { useRecipes } from "../../data/hooks";
import type { Recipe } from "../../data/types";

interface CheckedIngredient {
  productId: number;
  amount: number;
  product: { name: string } | undefined;
  inStock: number;
  hasEnough: boolean;
}

export function RecipesPage(): React.ReactElement {
  const { data: stockEntries = [] } = useStockEntries();
  const { recipes } = useRecipes();

  const checkIngredients = (recipe: Recipe): CheckedIngredient[] => {
    return recipe.ingredients.map((ing) => {
      // Note: recipes use number IDs, stock API uses UUIDs
      // This won't match until recipes are migrated to UUID product IDs
      const totalStock = stockEntries.filter(
        (e) => e.product.id === String(ing.productId),
      ).length;
      return {
        ...ing,
        product: undefined,
        inStock: totalStock,
        hasEnough: totalStock >= ing.amount,
      };
    });
  };

  return (
    <div className="p-8">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-3xl font-bold text-stone-800">Рецепты</h2>
          <p className="text-stone-500 mt-1">Проверка наличия ингредиентов</p>
        </div>
        <button
          type="button"
          className="flex items-center gap-2 px-4 py-2 bg-stone-800 text-white rounded-lg hover:bg-stone-700 transition-colors"
        >
          <Icons.Plus />
          Новый рецепт
        </button>
      </div>

      <div className="grid grid-cols-2 gap-6">
        {recipes.map((recipe) => {
          const ingredients = checkIngredients(recipe);
          const canMake = ingredients.every((i) => i.hasEnough);
          const missingCount = ingredients.filter((i) => !i.hasEnough).length;

          return (
            <div
              key={recipe.id}
              className="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden"
            >
              <div className="p-4 border-b border-stone-100 flex justify-between items-center">
                <h3 className="font-semibold text-stone-800 text-lg">
                  {recipe.name}
                </h3>
                {canMake ? (
                  <span className="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium">
                    Можно готовить
                  </span>
                ) : (
                  <span className="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-medium">
                    Не хватает {missingCount} ингр.
                  </span>
                )}
              </div>
              <div className="p-4">
                <h4 className="text-sm font-medium text-stone-600 mb-3">
                  Ингредиенты:
                </h4>
                <div className="space-y-2">
                  {ingredients.map((ing, idx) => (
                    <div
                      key={idx}
                      className="flex items-center justify-between"
                    >
                      <span
                        className={`${ing.hasEnough ? "text-stone-800" : "text-red-600"}`}
                      >
                        {ing.product?.name ?? `Продукт #${ing.productId}`}
                      </span>
                      <span
                        className={`text-sm ${ing.hasEnough ? "text-green-600" : "text-red-600"}`}
                      >
                        {ing.inStock} / {ing.amount}
                        {ing.hasEnough ? " \u2713" : " \u2717"}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
              <div className="p-4 bg-stone-50 border-t border-stone-100 flex gap-2">
                <button
                  type="button"
                  className="flex-1 px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  disabled={!canMake}
                >
                  Приготовить
                </button>
                {!canMake && (
                  <button
                    type="button"
                    className="px-4 py-2 border border-stone-300 rounded-lg hover:bg-white transition-colors"
                  >
                    В список покупок
                  </button>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
