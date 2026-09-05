export interface ProductFilters {
  includeArchived?: boolean;
}

export interface StockFilters {
  locationId?: string;
  productId?: string;
}

// Every resource follows the same shape: `all` is the invalidation root,
// `list(...)`/`detail(id)` extend it. Keeping the root as a prefix of every
// key means one `invalidateQueries({ queryKey: x.all })` covers the resource.
export const queryKeys = {
  auth: {
    me: ["auth", "me"] as const,
  },

  products: {
    all: ["products"] as const,
    list: (filters?: ProductFilters) =>
      [...queryKeys.products.all, "list", filters ?? {}] as const,
    detail: (id: string) => [...queryKeys.products.all, "detail", id] as const,
  },

  categories: {
    all: ["categories"] as const,
  },

  locations: {
    all: ["locations"] as const,
  },

  stocks: {
    all: ["stocks"] as const,
    entries: (filters?: StockFilters) =>
      [...queryKeys.stocks.all, "entries", filters ?? {}] as const,
    expiring: (days: number) =>
      [...queryKeys.stocks.all, "expiring", days] as const,
  },

  shoppingList: {
    all: ["shopping-list"] as const,
    list: () => [...queryKeys.shoppingList.all, "list"] as const,
  },

  tasks: {
    all: ["tasks"] as const,
    list: (status: TaskListStatus) =>
      [...queryKeys.tasks.all, "list", status] as const,
  },

  chores: {
    all: ["chores"] as const,
    list: () => [...queryKeys.chores.all, "list"] as const,
  },

  recipes: {
    all: ["recipes"] as const,
    list: () => [...queryKeys.recipes.all, "list"] as const,
  },

  telegram: {
    status: ["telegram", "status"] as const,
  },
} as const;

export type TaskListStatus = "active" | "completed" | "all";
