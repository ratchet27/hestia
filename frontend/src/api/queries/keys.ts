export interface ProductFilters {
  name?: string;
  categoryId?: string;
  active?: boolean;
}

export const queryKeys = {
  // Products
  products: {
    all: ["products"] as const,
    list: (filters?: ProductFilters) => ["products", filters ?? {}] as const,
    detail: (id: string) => ["product", id] as const,
  },

  // Categories
  categories: {
    all: ["categories"] as const,
  },

  // Locations
  locations: {
    all: ["locations"] as const,
  },
} as const;
