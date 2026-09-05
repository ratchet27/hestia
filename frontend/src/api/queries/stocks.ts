import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { AddStockRequest, ConsumeStockRequest } from "../generated/models";
import {
  getApiInternalV1StocksEntriesList,
  getApiInternalV1StocksExpiring,
  postApiInternalV1StocksAdd,
  postApiInternalV1StocksConsume,
} from "../generated/stock/stock";
import { queryKeys, type StockFilters } from "./keys";
import { unwrap } from "./unwrap";

export function useStockEntries(filters?: StockFilters) {
  return useQuery({
    queryKey: queryKeys.stocks.entries(filters),
    queryFn: async () => {
      return unwrap(
        await getApiInternalV1StocksEntriesList({
          location: filters?.locationId,
          product: filters?.productId,
        }),
      );
    },
  });
}

export function useExpiringStock(days: number = 7) {
  return useQuery({
    queryKey: queryKeys.stocks.expiring(days),
    queryFn: async () => {
      return unwrap(await getApiInternalV1StocksExpiring({ days }));
    },
  });
}

export function useAddStock() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (data: AddStockRequest) => {
      return unwrap(await postApiInternalV1StocksAdd(data));
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.stocks.all });
      // The backend reconciles AUTO shopping items on every stock change.
      queryClient.invalidateQueries({ queryKey: queryKeys.shoppingList.all });
    },
  });
}

export function useConsumeStock() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (data: ConsumeStockRequest) => {
      return unwrap(await postApiInternalV1StocksConsume(data));
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.stocks.all });
      // The backend reconciles AUTO shopping items on every stock change.
      queryClient.invalidateQueries({ queryKey: queryKeys.shoppingList.all });
    },
  });
}
