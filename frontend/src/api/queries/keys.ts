export interface ProductFilters {
  name?: string;
  categoryId?: string;
  active?: boolean;
  includeArchived?: boolean;
}

export interface StockFilters {
  locationId?: string;
  productId?: string;
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

  // Stocks
  stocks: {
    all: ["stocks"] as const,
    entries: (filters?: StockFilters) =>
      ["stocks", "entries", filters ?? {}] as const,
    expiring: (days: number) => ["stocks", "expiring", days] as const,
  },

  // Shopping List
  shoppingList: {
    all: ["shopping-list"] as const,
    list: () => ["shopping-list", "list"] as const,
  },

  // Tasks
  tasks: {
    all: ["tasks"] as const,
    list: (status: string) => ["tasks", status] as const,
    detail: (id: string) => ["tasks", id] as const,
  },

  // Chores
  chores: {
    all: ["chores"] as const,
    list: () => ["chores", "list"] as const,
    detail: (id: string) => ["chores", id] as const,
  },
} as const;
