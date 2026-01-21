import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "../client";
import type {
  CreateProductRequest,
  ProductResponse,
  UpdateProductRequest,
} from "../generated/models";
import {
  deleteApiProductsDelete,
  getApiProductsShow,
  postApiProductsCreate,
  putApiProductsUpdate,
} from "../generated/products/products";
import { queryKeys } from "./keys";

interface UseProductsOptions {
  includeArchived?: boolean;
}

export function useProducts(options: UseProductsOptions = {}) {
  const { includeArchived = false } = options;

  return useQuery({
    queryKey: queryKeys.products.list({ includeArchived }),
    queryFn: async () => {
      const url = includeArchived
        ? "/api/internal/v1/products?include_archived=true"
        : "/api/internal/v1/products";
      const response = await apiFetch<{
        data: { data: ProductResponse[]; meta?: { total: number } };
        status: number;
        headers: Headers;
      }>(url);
      return response.data.data ?? [];
    },
  });
}

export function useProduct(id: string) {
  return useQuery({
    queryKey: queryKeys.products.detail(id),
    queryFn: async () => {
      const response = await getApiProductsShow(id);
      if (response.status === 200) {
        return response.data.data!;
      }
      throw new Error("Failed to fetch product");
    },
    enabled: !!id,
  });
}

export function useCreateProduct() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (data: CreateProductRequest) => {
      const response = await postApiProductsCreate(data);
      if (response.status === 201) {
        return response.data.data!;
      }
      throw new Error("Failed to create product");
    },
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
    }) => {
      const response = await putApiProductsUpdate(id, data);
      if (response.status === 200) {
        return response.data.data!;
      }
      throw new Error("Failed to update product");
    },
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.products.all });
      queryClient.invalidateQueries({
        queryKey: queryKeys.products.detail(id),
      });
    },
  });
}

export function useDeleteProduct() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string) => deleteApiProductsDelete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.products.all });
    },
  });
}
