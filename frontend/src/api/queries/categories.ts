import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  deleteApiCategoriesDelete,
  getApiCategoriesList,
  patchApiCategoriesUpdate,
  postApiCategoriesCreate,
} from "../generated/categories/categories";
import { queryKeys } from "./keys";

export function useCategories() {
  return useQuery({
    queryKey: queryKeys.categories.all,
    queryFn: async () => {
      const response = await getApiCategoriesList();
      return response.data.data ?? [];
    },
    staleTime: 10 * 60 * 1000,
  });
}

export function useCreateCategory() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (name: string) => postApiCategoriesCreate({ name }),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: queryKeys.categories.all }),
  });
}

export function useRenameCategory() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, name }: { id: string; name: string }) =>
      patchApiCategoriesUpdate(id, { name }),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: queryKeys.categories.all }),
  });
}

export function useDeleteCategory() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => deleteApiCategoriesDelete(id),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: queryKeys.categories.all }),
  });
}
