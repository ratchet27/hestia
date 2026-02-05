import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "../client";
import type {
  ChoreResponse,
  CreateChoreRequest,
  UpdateChoreRequest,
} from "../generated/models";
import { queryKeys } from "./keys";

interface ChoreListResponse {
  data: { data: ChoreResponse[]; meta?: { total: number } };
  status: number;
  headers: Headers;
}

interface ChoreSingleResponse {
  data: { data: ChoreResponse };
  status: number;
  headers: Headers;
}

export function useChores() {
  return useQuery({
    queryKey: queryKeys.chores.list(),
    queryFn: async () => {
      const response = await apiFetch<ChoreListResponse>(
        "/api/internal/v1/chores",
      );
      return response.data.data ?? [];
    },
  });
}

export function useCreateChore() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: CreateChoreRequest) => {
      const response = await apiFetch<ChoreSingleResponse>(
        "/api/internal/v1/chores",
        { method: "POST", body: JSON.stringify(data) },
      );
      return response.data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.chores.all });
    },
  });
}

export function useUpdateChore() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({
      id,
      data,
    }: {
      id: string;
      data: UpdateChoreRequest;
    }) => {
      const response = await apiFetch<ChoreSingleResponse>(
        `/api/internal/v1/chores/${id}`,
        { method: "PUT", body: JSON.stringify(data) },
      );
      return response.data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.chores.all });
    },
  });
}

export function useDeleteChore() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      await apiFetch(`/api/internal/v1/chores/${id}`, { method: "DELETE" });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.chores.all });
    },
  });
}

export function useMarkChoreDone() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      const response = await apiFetch<ChoreSingleResponse>(
        `/api/internal/v1/chores/${id}/done`,
        { method: "POST" },
      );
      return response.data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.chores.all });
    },
  });
}
