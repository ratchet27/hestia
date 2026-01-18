import { useQuery } from '@tanstack/react-query'
import { queryKeys } from './keys'
import { getApiCategoriesList } from '../generated/categories/categories'
import type { CategoryResponse } from '../generated/models'

export function useCategories() {
  return useQuery({
    queryKey: queryKeys.categories.all,
    queryFn: async () => {
      const response = await getApiCategoriesList()
      return (response.data as unknown as CategoryResponse[]) ?? []
    },
    staleTime: 10 * 60 * 1000, // Categories rarely change - 10 min cache
  })
}
