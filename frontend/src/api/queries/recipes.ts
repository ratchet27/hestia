import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { SaveRecipeRequest } from "../generated/models";
import {
  getApiInternalV1RecipesIndex,
  postApiInternalV1RecipesAddMissing,
  postApiInternalV1RecipesCook,
  postApiInternalV1RecipesCreate,
  putApiInternalV1RecipesUpdate,
} from "../generated/recipes/recipes";
import { queryKeys } from "./keys";
import { unwrap } from "./unwrap";

export function useRecipes() {
  return useQuery({
    queryKey: queryKeys.recipes.list(),
    queryFn: async () => {
      return unwrap(await getApiInternalV1RecipesIndex());
    },
  });
}

export function useCreateRecipe() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: SaveRecipeRequest) => {
      return unwrap(await postApiInternalV1RecipesCreate(data));
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.recipes.all });
    },
  });
}

export function useUpdateRecipe() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({
      id,
      data,
    }: {
      id: string;
      data: SaveRecipeRequest;
    }) => {
      return unwrap(await putApiInternalV1RecipesUpdate(id, data));
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.recipes.all });
    },
  });
}

export function useCookRecipe() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      return unwrap(await postApiInternalV1RecipesCook(id));
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.recipes.all });
      queryClient.invalidateQueries({ queryKey: queryKeys.stocks.all });
      // Cooking consumes stock, which can push products below min_stock.
      queryClient.invalidateQueries({ queryKey: queryKeys.shoppingList.all });
    },
  });
}

export function useAddMissingToShoppingList() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      return unwrap(await postApiInternalV1RecipesAddMissing(id));
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.shoppingList.all });
    },
  });
}
