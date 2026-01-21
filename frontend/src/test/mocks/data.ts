import type {
  ExpiringEntryResponse,
  LocationResponse,
  ProductBriefResponse,
  ShoppingItemResponse,
  ShoppingListSource,
  StockEntryResponse,
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

export function wrapResponse<T>(data: T, meta: Record<string, unknown> = {}) {
  return { data, meta };
}
