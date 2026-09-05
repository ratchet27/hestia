import { type ReactElement, useEffect } from "react";
import { useFieldArray, useForm } from "react-hook-form";
import toast from "react-hot-toast";
import { useTranslation } from "react-i18next";
import { ApiError } from "../../api/client";
import type { SaveRecipeRequest } from "../../api/generated/models";
import { useProducts } from "../../api/queries/products";
import {
  useCreateRecipe,
  useRecipes,
  useUpdateRecipe,
} from "../../api/queries/recipes";
import { Modal } from "../../components/Modal";
import { ingredientsOf, toIngredientPayloads } from "./ingredients";

interface IngredientRow {
  product_id: string;
  required_count: number;
  consume_on_cook: boolean;
}

interface RecipeFormValues {
  name: string;
  instructions: string;
  source_url: string;
  ingredients: IngredientRow[];
}

export function RecipeForm({
  recipeId,
  onClose,
}: {
  recipeId: string | null;
  onClose: () => void;
}): ReactElement {
  const { t } = useTranslation();
  const { data: products = [] } = useProducts();
  const { data: recipes = [] } = useRecipes();
  const create = useCreateRecipe();
  const update = useUpdateRecipe();

  const existing = recipeId
    ? recipes.find((r) => r.id === recipeId)
    : undefined;

  const { register, control, handleSubmit, setError, reset, formState } =
    useForm<RecipeFormValues>({
      defaultValues: {
        name: existing?.name ?? "",
        instructions: existing?.instructions ?? "",
        source_url: existing?.source_url ?? "",
        ingredients: toIngredientPayloads(ingredientsOf(existing)),
      },
    });

  // When editing, the recipes query resolves asynchronously after mount.
  // Re-populate the form once the existing record becomes available.
  useEffect(() => {
    if (existing) {
      reset({
        name: existing.name ?? "",
        instructions: existing.instructions ?? "",
        source_url: existing.source_url ?? "",
        ingredients: toIngredientPayloads(ingredientsOf(existing)),
      });
    }
  }, [existing, reset]);

  // Highlight the offending fields on a 422 and always toast, so a failed
  // save is never silent. Runs from the submit path, not an effect, so it
  // cannot replay when the modal re-opens.
  const applyServerError = (error: unknown): void => {
    if (!(error instanceof ApiError)) throw error;

    if (error.isValidationError && error.violations?.length) {
      for (const violation of error.violations) {
        // Symfony paths like "ingredients[0].product_id" -> RHF "ingredients.0.product_id".
        const path = violation.propertyPath.replace(/\[(\d+)\]/g, ".$1");
        if (
          path === "name" ||
          path === "source_url" ||
          path === "instructions" ||
          path.startsWith("ingredients")
        ) {
          setError(path as Parameters<typeof setError>[0], {
            type: "server",
            message: violation.message,
          });
        }
      }
      toast.error(t("recipes.saveFailed"));
      return;
    }

    toast.error(error.message || t("common.error"));
  };

  const { fields, append, remove } = useFieldArray({
    control,
    name: "ingredients",
  });

  const onSubmit = handleSubmit(async (values) => {
    if (values.ingredients.length === 0) {
      setError("ingredients", {
        type: "manual",
        message: t("recipes.ingredientsRequired"),
      });
      return;
    }
    const payload: SaveRecipeRequest = {
      name: values.name,
      instructions: values.instructions || null,
      source_url: values.source_url || null,
      ingredients: values.ingredients.map((i) => ({
        product_id: i.product_id,
        required_count: Number(i.required_count),
        consume_on_cook: i.consume_on_cook,
      })),
    };
    try {
      if (recipeId) {
        await update.mutateAsync({ id: recipeId, data: payload });
      } else {
        await create.mutateAsync(payload);
      }
      onClose();
    } catch (error) {
      // Keep the form open so the user can correct and retry.
      applyServerError(error);
    }
  });

  return (
    <Modal
      title={recipeId ? t("recipes.editRecipe") : t("recipes.newRecipe")}
      onClose={onClose}
      size="lg"
    >
      <form onSubmit={onSubmit} className="space-y-4">
        <div>
          <label
            htmlFor="recipe-name"
            className="block text-sm font-medium text-stone-600 mb-1"
          >
            {t("recipes.name")}
          </label>
          <input
            id="recipe-name"
            {...register("name", { required: true })}
            className="w-full border border-stone-300 rounded-lg px-3 py-2"
          />
          {formState.errors.name && (
            <p className="text-red-600 text-sm mt-1">
              {t("recipes.nameRequired")}
            </p>
          )}
        </div>

        <div>
          <span className="block text-sm font-medium text-stone-600 mb-1">
            {t("recipes.ingredients")}
          </span>
          <div className="space-y-2">
            {fields.map((field, index) => (
              <div key={field.id} className="flex items-center gap-2">
                <select
                  {...register(`ingredients.${index}.product_id`, {
                    required: true,
                  })}
                  className="flex-1 border border-stone-300 rounded-lg px-2 py-1"
                >
                  <option value="">—</option>
                  {products.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.name}
                    </option>
                  ))}
                </select>
                <input
                  type="number"
                  min={1}
                  {...register(`ingredients.${index}.required_count`, {
                    valueAsNumber: true,
                    min: 1,
                  })}
                  className="w-16 border border-stone-300 rounded-lg px-2 py-1"
                />
                <label className="flex items-center gap-1 text-sm text-stone-600">
                  <input
                    type="checkbox"
                    {...register(`ingredients.${index}.consume_on_cook`)}
                  />
                  {t("recipes.consumeOnCook")}
                </label>
                <button
                  type="button"
                  onClick={() => remove(index)}
                  className="text-red-600 px-2"
                >
                  ✕
                </button>
              </div>
            ))}
          </div>
          <button
            type="button"
            onClick={() =>
              append({
                product_id: "",
                required_count: 1,
                consume_on_cook: true,
              })
            }
            className="mt-2 text-sm text-stone-700 underline"
          >
            {t("recipes.addIngredient")}
          </button>
          {formState.errors.ingredients?.message && (
            <p className="text-red-600 text-sm mt-1">
              {formState.errors.ingredients.message}
            </p>
          )}
        </div>

        <div>
          <label
            htmlFor="recipe-instructions"
            className="block text-sm font-medium text-stone-600 mb-1"
          >
            {t("recipes.instructions")}
          </label>
          <textarea
            id="recipe-instructions"
            {...register("instructions")}
            rows={3}
            className="w-full border border-stone-300 rounded-lg px-3 py-2"
          />
        </div>

        <div>
          <label
            htmlFor="recipe-source"
            className="block text-sm font-medium text-stone-600 mb-1"
          >
            {t("recipes.sourceUrl")}
          </label>
          <input
            id="recipe-source"
            {...register("source_url", {
              validate: (value) =>
                !value ||
                /^https?:\/\/.+/.test(value) ||
                t("recipes.invalidUrl"),
            })}
            className="w-full border border-stone-300 rounded-lg px-3 py-2"
          />
          {formState.errors.source_url && (
            <p className="text-red-600 text-sm mt-1">
              {formState.errors.source_url.message}
            </p>
          )}
        </div>

        <div className="flex justify-end gap-2 pt-2">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 border border-stone-300 rounded-lg"
          >
            {t("common.cancel")}
          </button>
          <button
            type="submit"
            disabled={create.isPending || update.isPending}
            className="px-4 py-2 bg-stone-800 text-white rounded-lg disabled:opacity-50"
          >
            {t("common.save")}
          </button>
        </div>
      </form>
    </Modal>
  );
}
