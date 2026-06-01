import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "../client";
import type {
  CreateTaskRequest,
  TaskResponse,
  UpdateTaskRequest,
} from "../generated/models";
import { queryKeys } from "./keys";

interface TaskListResponse {
  data: { data: TaskResponse[]; meta?: { total: number } };
  status: number;
  headers: Headers;
}

interface TaskSingleResponse {
  data: { data: TaskResponse };
  status: number;
  headers: Headers;
}

export function useTasks(status: "active" | "completed" | "all" = "active") {
  return useQuery({
    queryKey: queryKeys.tasks.list(status),
    queryFn: async () => {
      const response = await apiFetch<TaskListResponse>(
        `/api/internal/v1/tasks?status=${status}`,
      );
      return response.data.data ?? [];
    },
  });
}

export function useCreateTask() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: CreateTaskRequest) => {
      const response = await apiFetch<TaskSingleResponse>(
        "/api/internal/v1/tasks",
        { method: "POST", body: JSON.stringify(data) },
      );
      return response.data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.tasks.all });
    },
  });
}

export function useUpdateTask() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({
      id,
      data,
    }: {
      id: string;
      data: UpdateTaskRequest;
    }) => {
      const response = await apiFetch<TaskSingleResponse>(
        `/api/internal/v1/tasks/${id}`,
        { method: "PUT", body: JSON.stringify(data) },
      );
      return response.data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.tasks.all });
    },
  });
}

export function useDeleteTask() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      await apiFetch(`/api/internal/v1/tasks/${id}`, { method: "DELETE" });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.tasks.all });
    },
  });
}

export function useToggleTaskDone() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      const response = await apiFetch<TaskSingleResponse>(
        `/api/internal/v1/tasks/${id}/done`,
        { method: "PATCH" },
      );
      return response.data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.tasks.all });
    },
  });
}
