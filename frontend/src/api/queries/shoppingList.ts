import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type {
  AddShoppingItemRequest,
  ShoppingItemResponse,
  UpdateShoppingItemRequest,
} from "../generated/models";
import {
  deleteApiInternalV1ShoppingListDelete,
  getApiInternalV1ShoppingListIndex,
  patchApiInternalV1ShoppingListUpdate,
  postApiInternalV1ShoppingListCreate,
} from "../generated/shopping-list/shopping-list";
import { queryKeys } from "./keys";

export function useShoppingList() {
  return useQuery({
    queryKey: queryKeys.shoppingList.list(),
    queryFn: async () => {
      const response = await getApiInternalV1ShoppingListIndex();
      return (response.data ?? []) as ShoppingItemResponse[];
    },
  });
}

export function useAddShoppingItem() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (data: AddShoppingItemRequest) => {
      const response = await postApiInternalV1ShoppingListCreate(data);
      return response.data as { data: ShoppingItemResponse };
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.shoppingList.all });
    },
  });
}

export function useUpdateShoppingItem() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({
      id,
      data,
    }: {
      id: string;
      data: UpdateShoppingItemRequest;
    }) => {
      const response = await patchApiInternalV1ShoppingListUpdate(id, data);
      return response.data as { data: ShoppingItemResponse };
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.shoppingList.all });
    },
  });
}

export function useDeleteShoppingItem() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string) => deleteApiInternalV1ShoppingListDelete(id),
    onMutate: async (id: string) => {
      await queryClient.cancelQueries({
        queryKey: queryKeys.shoppingList.list(),
      });
      const previous = queryClient.getQueryData<ShoppingItemResponse[]>(
        queryKeys.shoppingList.list(),
      );
      queryClient.setQueryData<ShoppingItemResponse[]>(
        queryKeys.shoppingList.list(),
        (old) => old?.filter((item) => item.id !== id) ?? [],
      );
      return { previous };
    },
    onError: (_err, _id, context) => {
      if (context?.previous) {
        queryClient.setQueryData(
          queryKeys.shoppingList.list(),
          context.previous,
        );
      }
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.shoppingList.all });
    },
  });
}
