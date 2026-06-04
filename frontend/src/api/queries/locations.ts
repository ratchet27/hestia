import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  deleteApiLocationsDelete,
  getApiLocationsList,
  patchApiLocationsUpdate,
  postApiLocationsCreate,
} from "../generated/locations/locations";
import { queryKeys } from "./keys";

export function useLocations() {
  return useQuery({
    queryKey: queryKeys.locations.all,
    queryFn: async () => {
      const response = await getApiLocationsList();
      return response.data.data ?? [];
    },
    staleTime: 10 * 60 * 1000,
  });
}

export function useCreateLocation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (name: string) => postApiLocationsCreate({ name }),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: queryKeys.locations.all }),
  });
}

export function useRenameLocation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, name }: { id: string; name: string }) =>
      patchApiLocationsUpdate(id, { name }),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: queryKeys.locations.all }),
  });
}

export function useDeleteLocation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => deleteApiLocationsDelete(id),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: queryKeys.locations.all }),
  });
}
