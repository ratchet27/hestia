import { useMutation, useQuery } from "@tanstack/react-query";
import {
  getApiTelegramStatus,
  postApiTelegramTest,
} from "../generated/telegram/telegram";
import { queryKeys } from "./keys";
import { unwrap } from "./unwrap";

export function useTelegramStatus() {
  return useQuery({
    queryKey: queryKeys.telegram.status,
    queryFn: async () => {
      return unwrap(await getApiTelegramStatus());
    },
    staleTime: 5 * 60 * 1000,
  });
}

export function useSendTelegramTest() {
  return useMutation({
    mutationFn: async () => {
      return unwrap(await postApiTelegramTest());
    },
  });
}
