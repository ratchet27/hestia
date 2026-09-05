import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { SaveTaskRequest } from "../generated/models";
import {
  deleteApiTasksDelete,
  getApiTasksList,
  patchApiTasksToggleDone,
  postApiTasksCreate,
  putApiTasksUpdate,
} from "../generated/tasks/tasks";
import { queryKeys, type TaskListStatus } from "./keys";
import { unwrap } from "./unwrap";

export function useTasks(status: TaskListStatus = "active") {
  return useQuery({
    queryKey: queryKeys.tasks.list(status),
    queryFn: async () => unwrap(await getApiTasksList({ status })),
  });
}

export function useCreateTask() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: SaveTaskRequest) =>
      unwrap(await postApiTasksCreate(data)),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.tasks.all });
    },
  });
}

export function useUpdateTask() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: string; data: SaveTaskRequest }) =>
      unwrap(await putApiTasksUpdate(id, data)),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.tasks.all });
    },
  });
}

export function useDeleteTask() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      await deleteApiTasksDelete(id);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.tasks.all });
    },
  });
}

export function useToggleTaskDone() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => unwrap(await patchApiTasksToggleDone(id)),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.tasks.all });
    },
  });
}
