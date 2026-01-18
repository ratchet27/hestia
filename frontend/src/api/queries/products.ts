import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type {
  CreateProductRequest,
  ProductResponse,
  UpdateProductRequest,
} from "../generated/models";
import {
  deleteApiProductsDelete,
  getApiProductsList,
  getApiProductsShow,
  postApiProductsCreate,
  putApiProductsUpdate,
} from "../generated/products/products";
import { queryKeys } from "./keys";

export function useProducts() {
  return useQuery({
    queryKey: queryKeys.products.all,
    queryFn: async () => {
      const response = await getApiProductsList();
      return (response.data as unknown as ProductResponse[]) ?? [];
    },
  });
}

export function useProduct(id: string) {
  return useQuery({
    queryKey: queryKeys.products.detail(id),
    queryFn: async () => {
      const response = await getApiProductsShow(id);
      return response.data as ProductResponse;
    },
    enabled: !!id,
  });
}

export function useCreateProduct() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (data: CreateProductRequest) => {
      const response = await postApiProductsCreate(data);
      return response.data as ProductResponse;
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
      return response.data as ProductResponse;
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
