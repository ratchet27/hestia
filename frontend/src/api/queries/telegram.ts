import { useMutation, useQuery } from "@tanstack/react-query";
import {
  getApiTelegramStatus,
  postApiTelegramTest,
} from "../generated/telegram/telegram";
import { queryKeys } from "./keys";

export function useTelegramStatus() {
  return useQuery({
    queryKey: queryKeys.telegram.status,
    queryFn: async () => {
      const response = await getApiTelegramStatus();
      return response.data.data;
    },
    staleTime: 5 * 60 * 1000,
  });
}

export function useSendTelegramTest() {
  return useMutation({
    mutationFn: async () => {
      const response = await postApiTelegramTest();
      return response.data.data;
    },
  });
}
