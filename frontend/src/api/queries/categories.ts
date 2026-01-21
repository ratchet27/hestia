import { useQuery } from "@tanstack/react-query";
import { getApiCategoriesList } from "../generated/categories/categories";
import { queryKeys } from "./keys";

export function useCategories() {
  return useQuery({
    queryKey: queryKeys.categories.all,
    queryFn: async () => {
      const response = await getApiCategoriesList();
      return response.data.data ?? [];
    },
    staleTime: 10 * 60 * 1000, // Categories rarely change - 10 min cache
  });
}
