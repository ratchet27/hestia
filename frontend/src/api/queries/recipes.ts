import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { SaveRecipeRequest } from "../generated/models";
import {
  deleteApiInternalV1RecipesDelete,
  getApiInternalV1RecipesIndex,
  postApiInternalV1RecipesAddMissing,
  postApiInternalV1RecipesCook,
  postApiInternalV1RecipesCreate,
  putApiInternalV1RecipesUpdate,
} from "../generated/recipes/recipes";
import { queryKeys } from "./keys";

export function useRecipes() {
  return useQuery({
    queryKey: queryKeys.recipes.list(),
    queryFn: async () => {
      const response = await getApiInternalV1RecipesIndex();
      return response.data.data ?? [];
    },
  });
}

export function useCreateRecipe() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: SaveRecipeRequest) => {
      const response = await postApiInternalV1RecipesCreate(data);
      if (response.status === 201) {
        return response.data.data!;
      }
      throw new Error("Failed to create recipe");
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
      const response = await putApiInternalV1RecipesUpdate(id, data);
      if (response.status === 200) {
        return response.data.data!;
      }
      throw new Error("Failed to update recipe");
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.recipes.all });
    },
  });
}

export function useDeleteRecipe() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      await deleteApiInternalV1RecipesDelete(id);
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
      const response = await postApiInternalV1RecipesCook(id);
      if (response.status === 200) {
        return response.data.data!;
      }
      throw new Error("Failed to cook recipe");
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.recipes.all });
      queryClient.invalidateQueries({ queryKey: queryKeys.stocks.all });
    },
  });
}

export function useAddMissingToShoppingList() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      const response = await postApiInternalV1RecipesAddMissing(id);
      if (response.status === 200) {
        return response.data.data!;
      }
      throw new Error("Failed to add missing ingredients to shopping list");
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.shoppingList.all });
    },
  });
}
