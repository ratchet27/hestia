import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type {
  CreateProductRequest,
  UpdateProductRequest,
} from "../generated/models";
import {
  getApiProductsList,
  postApiProductsCreate,
  putApiProductsUpdate,
} from "../generated/products/products";
import { type ProductFilters, queryKeys } from "./keys";
import { unwrap } from "./unwrap";

export function useProducts(filters: ProductFilters = {}) {
  const { includeArchived = false } = filters;

  return useQuery({
    queryKey: queryKeys.products.list({ includeArchived }),
    queryFn: async () =>
      unwrap(
        await getApiProductsList(
          includeArchived ? { include_archived: true } : undefined,
        ),
      ),
  });
}

export function useCreateProduct() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (data: CreateProductRequest) =>
      unwrap(await postApiProductsCreate(data)),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.products.all });
    },
  });
}

export function useUpdateProduct() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({
      id,
      data,
    }: {
      id: string;
      data: UpdateProductRequest;
    }) => unwrap(await putApiProductsUpdate(id, data)),
    onSuccess: () => {
      // `products.all` is a prefix of every product key, detail included.
      queryClient.invalidateQueries({ queryKey: queryKeys.products.all });
      // min_stock or active changes reconcile the AUTO shopping items server-side.
      queryClient.invalidateQueries({ queryKey: queryKeys.shoppingList.all });
    },
  });
}
