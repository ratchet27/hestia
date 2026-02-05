import type {
  CategoryResponse,
  ChoreResponse,
  ExpiringEntryResponse,
  LocationResponse,
  ProductBriefResponse,
  ProductResponse,
  ShoppingItemResponse,
  ShoppingListSource,
  StockEntryResponse,
  TaskResponse,
} from "@/api/generated/models";

export function createProduct(
  overrides: Partial<ProductBriefResponse> = {},
): ProductBriefResponse {
  return {
    id: crypto.randomUUID(),
    name: "Молоко",
    unit: "шт",
    ...overrides,
  };
}

export function createLocation(
  overrides: Partial<LocationResponse> = {},
): LocationResponse {
  return {
    id: crypto.randomUUID(),
    name: "Холодильник",
    ...overrides,
  };
}

export function createStockEntry(
  overrides: Omit<Partial<StockEntryResponse>, "product" | "location"> & {
    product?: Partial<ProductBriefResponse>;
    location?: Partial<LocationResponse>;
  } = {},
): StockEntryResponse {
  const { product, location, ...rest } = overrides;
  return {
    id: crypto.randomUUID(),
    product: createProduct(product),
    location: createLocation(location),
    best_before: "2025-01-25",
    created_at: new Date().toISOString(),
    ...rest,
  };
}

export function createExpiringEntry(
  overrides: Omit<Partial<ExpiringEntryResponse>, "product" | "location"> & {
    product?: Partial<ProductBriefResponse>;
    location?: Partial<LocationResponse>;
  } = {},
): ExpiringEntryResponse {
  const { product, location, ...rest } = overrides;
  return {
    id: crypto.randomUUID(),
    product: createProduct(product),
    location: createLocation(location),
    best_before: "2025-01-25",
    days_until_expiry: 2,
    ...rest,
  };
}

export function createShoppingItem(
  overrides: Partial<ShoppingItemResponse> = {},
): ShoppingItemResponse {
  return {
    id: crypto.randomUUID(),
    name: "Молоко",
    amount: 1,
    source: "manual" as ShoppingListSource,
    done: false,
    created_at: new Date().toISOString(),
    ...overrides,
  };
}

export function createCategory(
  overrides: Partial<CategoryResponse> = {},
): CategoryResponse {
  return {
    id: crypto.randomUUID(),
    name: "Молочные продукты",
    ...overrides,
  };
}

export function createProductResponse(
  overrides: Omit<Partial<ProductResponse>, "category" | "default_location"> & {
    category?: Partial<CategoryResponse>;
    default_location?: Partial<LocationResponse>;
  } = {},
): ProductResponse {
  const { category, default_location, ...rest } = overrides;
  return {
    id: crypto.randomUUID(),
    name: "Молоко",
    unit: "шт",
    category: createCategory(category),
    default_location: createLocation(default_location),
    min_stock: 0,
    active: true,
    created_at: new Date().toISOString(),
    ...rest,
  };
}

export function createTaskResponse(
  overrides: Partial<TaskResponse> = {},
): TaskResponse {
  return {
    id: crypto.randomUUID(),
    name: "Записаться к врачу",
    due_date: "2026-02-15T00:00:00+00:00",
    priority: "medium",
    done: false,
    done_at: null,
    created_at: new Date().toISOString(),
    ...overrides,
  };
}

export function createChoreResponse(
  overrides: Partial<ChoreResponse> = {},
): ChoreResponse {
  return {
    id: crypto.randomUUID(),
    name: "Пропылесосить квартиру",
    schedule_type: "interval",
    schedule_value: 7,
    assignee: null,
    last_done_at: null,
    next_due_at: new Date(Date.now() + 86400000 * 3).toISOString(),
    created_at: new Date().toISOString(),
    updated_at: null,
    ...overrides,
  };
}

export function wrapResponse<T>(data: T, meta: Record<string, unknown> = {}) {
  return { data, meta };
}
