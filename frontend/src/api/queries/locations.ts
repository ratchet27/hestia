import { useQuery } from '@tanstack/react-query'
import { queryKeys } from './keys'
import { getApiLocationsList } from '../generated/locations/locations'
import type { LocationResponse } from '../generated/models'

export function useLocations() {
  return useQuery({
    queryKey: queryKeys.locations.all,
    queryFn: async () => {
      const response = await getApiLocationsList()
      return (response.data as unknown as LocationResponse[]) ?? []
    },
    staleTime: 10 * 60 * 1000, // Locations rarely change - 10 min cache
  })
}
