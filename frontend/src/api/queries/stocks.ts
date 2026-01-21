import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type {
  AddStockRequest,
  ConsumeStockRequest,
  UpdateStockEntryRequest,
} from "../generated/models";
import {
  deleteApiInternalV1StocksEntriesDelete,
  getApiInternalV1StocksEntriesList,
  getApiInternalV1StocksExpiring,
  patchApiInternalV1StocksEntriesUpdate,
  postApiInternalV1StocksAdd,
  postApiInternalV1StocksConsume,
} from "../generated/stock/stock";
import { queryKeys, type StockFilters } from "./keys";

export function useStockEntries(filters?: StockFilters) {
  return useQuery({
    queryKey: queryKeys.stocks.entries(filters),
    queryFn: async () => {
      const response = await getApiInternalV1StocksEntriesList({
        location: filters?.locationId,
        product: filters?.productId,
      });
      return response.data.data ?? [];
    },
  });
}

export function useExpiringStock(days: number = 7) {
  return useQuery({
    queryKey: queryKeys.stocks.expiring(days),
    queryFn: async () => {
      const response = await getApiInternalV1StocksExpiring({ days });
      return response.data.data ?? [];
    },
  });
}

export function useAddStock() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (data: AddStockRequest) => {
      const response = await postApiInternalV1StocksAdd(data);
      if (response.status === 201) {
        return response.data.data!;
      }
      throw new Error("Failed to add stock");
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.stocks.all });
    },
  });
}

export function useConsumeStock() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (data: ConsumeStockRequest) => {
      const response = await postApiInternalV1StocksConsume(data);
      if (response.status === 200) {
        return response.data.data!;
      }
      throw new Error("Failed to consume stock");
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.stocks.all });
    },
  });
}

export function useUpdateStockEntry() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({
      id,
      data,
    }: {
      id: string;
      data: UpdateStockEntryRequest;
    }) => {
      const response = await patchApiInternalV1StocksEntriesUpdate(id, data);
      if (response.status === 200) {
        return response.data.data!;
      }
      throw new Error("Failed to update stock entry");
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.stocks.all });
    },
  });
}

export function useDeleteStockEntry() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string) => deleteApiInternalV1StocksEntriesDelete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.stocks.all });
    },
  });
}
