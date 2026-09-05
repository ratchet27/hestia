import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  deleteApiChoresDelete,
  getApiChoresList,
  postApiChoresCreate,
  postApiChoresDone,
  putApiChoresUpdate,
} from "../generated/chores/chores";
import type { SaveChoreRequest } from "../generated/models";
import { queryKeys } from "./keys";
import { unwrap } from "./unwrap";

export function useChores() {
  return useQuery({
    queryKey: queryKeys.chores.list(),
    queryFn: async () => unwrap(await getApiChoresList()),
  });
}

export function useCreateChore() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: SaveChoreRequest) =>
      unwrap(await postApiChoresCreate(data)),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.chores.all });
    },
  });
}

export function useUpdateChore() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: string; data: SaveChoreRequest }) =>
      unwrap(await putApiChoresUpdate(id, data)),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.chores.all });
    },
  });
}

export function useDeleteChore() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      await deleteApiChoresDelete(id);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.chores.all });
    },
  });
}

export function useMarkChoreDone() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => unwrap(await postApiChoresDone(id)),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.chores.all });
    },
  });
}
