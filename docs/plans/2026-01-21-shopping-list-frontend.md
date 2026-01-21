# Shopping List Frontend Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Connect the shopping list frontend to the real backend API with product search, custom items, quantity editing, and fade-out animations.

**Architecture:** Replace Context/mock-based state with React Query hooks for CRUD operations. Product search uses client-side filtering of cached products. Delete on checkbox click with optimistic UI and fade animation.

**Tech Stack:** React 19, React Query 5, TypeScript, Tailwind CSS, Vitest + MSW for testing

---

## Task 1: Add Shopping List Query Keys

**Files:**
- Modify: `frontend/src/api/queries/keys.ts`

**Step 1: Add shopping list keys to queryKeys object**

```typescript
// Add after stocks key block (around line 36):
  // Shopping List
  shoppingList: {
    all: ["shopping-list"] as const,
    list: () => ["shopping-list", "list"] as const,
  },
```

**Step 2: Run type check**

Run: `cd /home/pavel/projects/hestia/frontend && bun run check`
Expected: PASS (no type errors)

**Step 3: Commit**

```bash
git add frontend/src/api/queries/keys.ts
git commit -s -m "feat(frontend): add shopping list query keys"
```

---

## Task 2: Create Shopping List API Hooks

**Files:**
- Create: `frontend/src/api/queries/shoppingList.ts`
- Modify: `frontend/src/api/queries/index.ts`

**Step 1: Create the shoppingList.ts file**

```typescript
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type {
  AddShoppingItemRequest,
  ShoppingItemResponse,
  UpdateShoppingItemRequest,
} from "../generated/models";
import {
  deleteApiInternalV1ShoppingListDelete,
  getApiInternalV1ShoppingListIndex,
  patchApiInternalV1ShoppingListUpdate,
  postApiInternalV1ShoppingListCreate,
} from "../generated/shopping-list/shopping-list";
import { queryKeys } from "./keys";

export function useShoppingList() {
  return useQuery({
    queryKey: queryKeys.shoppingList.list(),
    queryFn: async () => {
      const response = await getApiInternalV1ShoppingListIndex();
      return (response.data.data ?? []) as ShoppingItemResponse[];
    },
  });
}

export function useAddShoppingItem() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (data: AddShoppingItemRequest) => {
      const response = await postApiInternalV1ShoppingListCreate(data);
      return response.data as { data: ShoppingItemResponse };
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.shoppingList.all });
    },
  });
}

export function useUpdateShoppingItem() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({
      id,
      data,
    }: {
      id: string;
      data: UpdateShoppingItemRequest;
    }) => {
      const response = await patchApiInternalV1ShoppingListUpdate(id, data);
      return response.data as { data: ShoppingItemResponse };
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.shoppingList.all });
    },
  });
}

export function useDeleteShoppingItem() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string) => deleteApiInternalV1ShoppingListDelete(id),
    onMutate: async (id: string) => {
      await queryClient.cancelQueries({
        queryKey: queryKeys.shoppingList.list(),
      });
      const previous = queryClient.getQueryData<ShoppingItemResponse[]>(
        queryKeys.shoppingList.list(),
      );
      queryClient.setQueryData<ShoppingItemResponse[]>(
        queryKeys.shoppingList.list(),
        (old) => old?.filter((item) => item.id !== id) ?? [],
      );
      return { previous };
    },
    onError: (_err, _id, context) => {
      if (context?.previous) {
        queryClient.setQueryData(
          queryKeys.shoppingList.list(),
          context.previous,
        );
      }
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.shoppingList.all });
    },
  });
}
```

**Step 2: Export from index.ts**

Add to `frontend/src/api/queries/index.ts`:

```typescript
export {
  useShoppingList,
  useAddShoppingItem,
  useUpdateShoppingItem,
  useDeleteShoppingItem,
} from "./shoppingList";
```

**Step 3: Run type check**

Run: `cd /home/pavel/projects/hestia/frontend && bun run check`
Expected: PASS

**Step 4: Commit**

```bash
git add frontend/src/api/queries/shoppingList.ts frontend/src/api/queries/index.ts
git commit -s -m "feat(frontend): add shopping list React Query hooks"
```

---

## Task 3: Create Test Factory for Shopping Items

**Files:**
- Modify: `frontend/src/test/mocks/data.ts`

**Step 1: Add createShoppingItem factory function**

Add to `frontend/src/test/mocks/data.ts`:

```typescript
import type {
  ExpiringEntryResponse,
  LocationResponse,
  ProductBriefResponse,
  ShoppingItemResponse,
  ShoppingListSource,
  StockEntryResponse,
} from "@/api/generated/models";

// Add after createExpiringEntry function:

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
```

**Step 2: Run type check**

Run: `cd /home/pavel/projects/hestia/frontend && bun run check`
Expected: PASS

**Step 3: Commit**

```bash
git add frontend/src/test/mocks/data.ts
git commit -s -m "test(frontend): add shopping item test factory"
```

---

## Task 4: Create ProductSearchInput Component

**Files:**
- Create: `frontend/src/features/shopping/components/ProductSearchInput.tsx`

**Step 1: Create the component**

```typescript
import { useState, useRef, useEffect } from "react";
import type { ProductResponse } from "@/api/generated/models";
import { useProducts } from "@/api/queries";

interface ProductSearchInputProps {
  onAddProduct: (productId: string) => void;
  onAddCustom: (name: string) => void;
  isAdding?: boolean;
}

export function ProductSearchInput({
  onAddProduct,
  onAddCustom,
  isAdding = false,
}: ProductSearchInputProps): React.ReactElement {
  const [search, setSearch] = useState("");
  const [isOpen, setIsOpen] = useState(false);
  const [highlightedIndex, setHighlightedIndex] = useState(0);
  const inputRef = useRef<HTMLInputElement>(null);
  const dropdownRef = useRef<HTMLDivElement>(null);

  const { data: products = [] } = useProducts();

  const filteredProducts = search.trim()
    ? products
        .filter((p) => p.name.toLowerCase().includes(search.toLowerCase()))
        .slice(0, 5)
    : [];

  const showCustomOption =
    search.trim() &&
    !filteredProducts.some(
      (p) => p.name.toLowerCase() === search.toLowerCase(),
    );

  const totalOptions = filteredProducts.length + (showCustomOption ? 1 : 0);

  useEffect(() => {
    setHighlightedIndex(0);
  }, [search]);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(event.target as Node) &&
        inputRef.current &&
        !inputRef.current.contains(event.target as Node)
      ) {
        setIsOpen(false);
      }
    }

    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  const handleSelect = (product: ProductResponse) => {
    onAddProduct(product.id);
    setSearch("");
    setIsOpen(false);
  };

  const handleAddCustom = () => {
    if (search.trim()) {
      onAddCustom(search.trim());
      setSearch("");
      setIsOpen(false);
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (!isOpen || totalOptions === 0) {
      if (e.key === "Enter" && search.trim()) {
        e.preventDefault();
        handleAddCustom();
      }
      return;
    }

    switch (e.key) {
      case "ArrowDown":
        e.preventDefault();
        setHighlightedIndex((i) => (i + 1) % totalOptions);
        break;
      case "ArrowUp":
        e.preventDefault();
        setHighlightedIndex((i) => (i - 1 + totalOptions) % totalOptions);
        break;
      case "Enter":
        e.preventDefault();
        if (highlightedIndex < filteredProducts.length) {
          handleSelect(filteredProducts[highlightedIndex]);
        } else if (showCustomOption) {
          handleAddCustom();
        }
        break;
      case "Escape":
        setIsOpen(false);
        break;
    }
  };

  return (
    <div className="relative">
      <div className="flex gap-3">
        <input
          ref={inputRef}
          type="text"
          placeholder="Добавить товар..."
          value={search}
          onChange={(e) => {
            setSearch(e.target.value);
            setIsOpen(e.target.value.trim().length > 0);
          }}
          onFocus={() => {
            if (search.trim()) setIsOpen(true);
          }}
          onKeyDown={handleKeyDown}
          disabled={isAdding}
          className="flex-1 px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 disabled:opacity-50"
        />
        <button
          type="button"
          onClick={handleAddCustom}
          disabled={!search.trim() || isAdding}
          className="px-6 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Добавить
        </button>
      </div>

      {isOpen && totalOptions > 0 && (
        <div
          ref={dropdownRef}
          className="absolute z-10 w-full mt-1 bg-white border border-stone-200 rounded-lg shadow-lg overflow-hidden"
        >
          {filteredProducts.map((product, index) => (
            <button
              key={product.id}
              type="button"
              onClick={() => handleSelect(product)}
              className={`w-full px-4 py-2 text-left hover:bg-stone-50 ${
                index === highlightedIndex ? "bg-stone-100" : ""
              }`}
            >
              {product.name}
            </button>
          ))}
          {showCustomOption && (
            <button
              type="button"
              onClick={handleAddCustom}
              className={`w-full px-4 py-2 text-left hover:bg-stone-50 border-t border-stone-100 text-amber-600 ${
                highlightedIndex === filteredProducts.length ? "bg-stone-100" : ""
              }`}
            >
              Добавить «{search}»
            </button>
          )}
        </div>
      )}
    </div>
  );
}
```

**Step 2: Run type check**

Run: `cd /home/pavel/projects/hestia/frontend && bun run check`
Expected: PASS

**Step 3: Commit**

```bash
git add frontend/src/features/shopping/components/ProductSearchInput.tsx
git commit -s -m "feat(frontend): add ProductSearchInput component with dropdown"
```

---

## Task 5: Create ShoppingListItem Component

**Files:**
- Create: `frontend/src/features/shopping/components/ShoppingListItem.tsx`

**Step 1: Create the component**

```typescript
import { useState } from "react";
import type { ShoppingItemResponse } from "@/api/generated/models";

interface ShoppingListItemProps {
  item: ShoppingItemResponse;
  onClick: () => void;
  onDelete: () => void;
  isDeleting?: boolean;
}

export function ShoppingListItem({
  item,
  onClick,
  onDelete,
  isDeleting = false,
}: ShoppingListItemProps): React.ReactElement {
  const [isAnimatingOut, setIsAnimatingOut] = useState(false);

  const handleCheckboxClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    setIsAnimatingOut(true);
    setTimeout(() => {
      onDelete();
    }, 300);
  };

  return (
    <div
      onClick={onClick}
      className={`p-4 flex items-center gap-4 hover:bg-stone-50 cursor-pointer transition-all duration-300 ${
        isAnimatingOut ? "opacity-0 max-h-0 py-0 overflow-hidden" : "opacity-100 max-h-24"
      }`}
    >
      <button
        type="button"
        onClick={handleCheckboxClick}
        disabled={isDeleting || isAnimatingOut}
        className="w-6 h-6 rounded-full border-2 border-stone-300 flex items-center justify-center hover:border-green-500 transition-colors disabled:opacity-50"
        aria-label="Отметить купленным"
      />
      <div className="flex-1 min-w-0">
        <p className="font-medium text-stone-800 truncate">{item.name}</p>
        {item.note && (
          <p className="text-sm text-stone-500 truncate">{item.note}</p>
        )}
      </div>
      <span className="text-sm text-stone-500 whitespace-nowrap">
        {item.amount} шт.
      </span>
      {item.source === "auto" && (
        <span className="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">
          Авто
        </span>
      )}
    </div>
  );
}
```

**Step 2: Run type check**

Run: `cd /home/pavel/projects/hestia/frontend && bun run check`
Expected: PASS

**Step 3: Commit**

```bash
git add frontend/src/features/shopping/components/ShoppingListItem.tsx
git commit -s -m "feat(frontend): add ShoppingListItem component with fade animation"
```

---

## Task 6: Create EditItemModal Component

**Files:**
- Create: `frontend/src/features/shopping/components/EditItemModal.tsx`

**Step 1: Create the component**

```typescript
import { useState, useEffect } from "react";
import type { ShoppingItemResponse } from "@/api/generated/models";

interface EditItemModalProps {
  item: ShoppingItemResponse | null;
  isOpen: boolean;
  onClose: () => void;
  onSave: (id: string, amount: number, note: string) => void;
  isSaving?: boolean;
}

export function EditItemModal({
  item,
  isOpen,
  onClose,
  onSave,
  isSaving = false,
}: EditItemModalProps): React.ReactElement | null {
  const [amount, setAmount] = useState(1);
  const [note, setNote] = useState("");

  useEffect(() => {
    if (item) {
      setAmount(item.amount);
      setNote(item.note ?? "");
    }
  }, [item]);

  if (!isOpen || !item) return null;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (amount > 0) {
      onSave(item.id, amount, note);
    }
  };

  const handleBackdropClick = (e: React.MouseEvent) => {
    if (e.target === e.currentTarget) {
      onClose();
    }
  };

  return (
    <div
      className="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      onClick={handleBackdropClick}
    >
      <div className="bg-white rounded-xl p-6 w-full max-w-md mx-4 shadow-xl">
        <h3 className="text-lg font-semibold text-stone-800 mb-4">
          {item.name}
        </h3>

        <form onSubmit={handleSubmit}>
          <div className="space-y-4">
            <div>
              <label
                htmlFor="amount"
                className="block text-sm font-medium text-stone-700 mb-1"
              >
                Количество
              </label>
              <input
                id="amount"
                type="number"
                min="1"
                value={amount}
                onChange={(e) => setAmount(Number(e.target.value))}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
              />
            </div>

            <div>
              <label
                htmlFor="note"
                className="block text-sm font-medium text-stone-700 mb-1"
              >
                Заметка
              </label>
              <input
                id="note"
                type="text"
                value={note}
                onChange={(e) => setNote(e.target.value)}
                placeholder="Например: определённый бренд"
                className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
              />
            </div>
          </div>

          <div className="flex gap-3 mt-6">
            <button
              type="button"
              onClick={onClose}
              disabled={isSaving}
              className="flex-1 px-4 py-2 border border-stone-300 rounded-lg hover:bg-stone-50 transition-colors disabled:opacity-50"
            >
              Отмена
            </button>
            <button
              type="submit"
              disabled={isSaving || amount < 1}
              className="flex-1 px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors disabled:opacity-50"
            >
              {isSaving ? "Сохранение..." : "Сохранить"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
```

**Step 2: Run type check**

Run: `cd /home/pavel/projects/hestia/frontend && bun run check`
Expected: PASS

**Step 3: Commit**

```bash
git add frontend/src/features/shopping/components/EditItemModal.tsx
git commit -s -m "feat(frontend): add EditItemModal component"
```

---

## Task 7: Create Components Index

**Files:**
- Create: `frontend/src/features/shopping/components/index.ts`

**Step 1: Create the index file**

```typescript
export { ProductSearchInput } from "./ProductSearchInput";
export { ShoppingListItem } from "./ShoppingListItem";
export { EditItemModal } from "./EditItemModal";
```

**Step 2: Commit**

```bash
git add frontend/src/features/shopping/components/index.ts
git commit -s -m "chore(frontend): add shopping components index"
```

---

## Task 8: Refactor ShoppingPage to Use Real API

**Files:**
- Modify: `frontend/src/features/shopping/ShoppingPage.tsx`

**Step 1: Replace the entire ShoppingPage component**

```typescript
import { useState } from "react";
import type { ShoppingItemResponse } from "@/api/generated/models";
import {
  useAddShoppingItem,
  useDeleteShoppingItem,
  useShoppingList,
  useUpdateShoppingItem,
} from "@/api/queries";
import {
  EditItemModal,
  ProductSearchInput,
  ShoppingListItem,
} from "./components";

export function ShoppingPage(): React.ReactElement {
  const [editingItem, setEditingItem] = useState<ShoppingItemResponse | null>(
    null,
  );

  const { data: items = [], isLoading } = useShoppingList();
  const addMutation = useAddShoppingItem();
  const updateMutation = useUpdateShoppingItem();
  const deleteMutation = useDeleteShoppingItem();

  const pendingItems = items.filter((item) => !item.done);

  const handleAddProduct = (productId: string) => {
    addMutation.mutate({ product_id: productId, amount: 1 });
  };

  const handleAddCustom = (name: string) => {
    addMutation.mutate({ custom_name: name, amount: 1 });
  };

  const handleDelete = (id: string) => {
    deleteMutation.mutate(id);
  };

  const handleSave = (id: string, amount: number, note: string) => {
    updateMutation.mutate(
      { id, data: { amount, note: note || null } },
      {
        onSuccess: () => setEditingItem(null),
      },
    );
  };

  return (
    <div className="p-8">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-3xl font-bold text-stone-800">Список покупок</h2>
          <p className="text-stone-500 mt-1">Общий список для всей семьи</p>
        </div>
        <div className="text-right">
          <p className="text-2xl font-bold text-amber-600">
            {pendingItems.length}
          </p>
          <p className="text-sm text-stone-500">к покупке</p>
        </div>
      </div>

      <div className="bg-white rounded-xl p-4 shadow-sm border border-stone-200 mb-6">
        <ProductSearchInput
          onAddProduct={handleAddProduct}
          onAddCustom={handleAddCustom}
          isAdding={addMutation.isPending}
        />
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-stone-200">
        <div className="p-4 border-b border-stone-100">
          <h3 className="font-semibold text-stone-800">К покупке</h3>
        </div>
        <div className="divide-y divide-stone-100">
          {isLoading ? (
            <div className="p-4 text-center text-stone-500">Загрузка...</div>
          ) : pendingItems.length === 0 ? (
            <p className="p-4 text-stone-500">
              Список пуст. Найдите товар выше, чтобы добавить.
            </p>
          ) : (
            pendingItems.map((item) => (
              <ShoppingListItem
                key={item.id}
                item={item}
                onClick={() => setEditingItem(item)}
                onDelete={() => handleDelete(item.id)}
                isDeleting={deleteMutation.isPending}
              />
            ))
          )}
        </div>
      </div>

      <EditItemModal
        item={editingItem}
        isOpen={editingItem !== null}
        onClose={() => setEditingItem(null)}
        onSave={handleSave}
        isSaving={updateMutation.isPending}
      />
    </div>
  );
}
```

**Step 2: Run type check**

Run: `cd /home/pavel/projects/hestia/frontend && bun run check`
Expected: PASS

**Step 3: Commit**

```bash
git add frontend/src/features/shopping/ShoppingPage.tsx
git commit -s -m "feat(frontend): refactor ShoppingPage to use real API"
```

---

## Task 9: Clean Up Old Shopping Mock Data

**Files:**
- Modify: `frontend/src/data/mocks.ts`
- Modify: `frontend/src/data/types.ts`
- Modify: `frontend/src/data/context.tsx`
- Modify: `frontend/src/data/hooks.ts`

**Step 1: Remove mockShoppingList from mocks.ts**

In `frontend/src/data/mocks.ts`, remove:
- The `mockShoppingList` export and its data (lines 110-138)
- The `ShoppingItem` import from types

**Step 2: Remove ShoppingItem from types.ts**

In `frontend/src/data/types.ts`, remove:
- The `ShoppingItem` interface (lines 18-26)

**Step 3: Remove ShoppingContext from context.tsx**

In `frontend/src/data/context.tsx`:
- Remove `mockShoppingList` import
- Remove `ShoppingItem` import
- Remove `ShoppingContextValue` interface
- Remove `ShoppingContext` export
- Remove shopping list state from `DataProvider`
- Remove `ShoppingContext.Provider` wrapper

**Step 4: Remove useShoppingList from hooks.ts**

In `frontend/src/data/hooks.ts`:
- Remove `ShoppingContext` and `ShoppingContextValue` imports
- Remove `useShoppingList` function

**Step 5: Run type check**

Run: `cd /home/pavel/projects/hestia/frontend && bun run check`
Expected: PASS

**Step 6: Commit**

```bash
git add frontend/src/data/mocks.ts frontend/src/data/types.ts frontend/src/data/context.tsx frontend/src/data/hooks.ts
git commit -s -m "refactor(frontend): remove shopping list mock data and context"
```

---

## Task 10: Write Tests for ShoppingPage

**Files:**
- Create: `frontend/src/features/shopping/__tests__/ShoppingPage.test.tsx`

**Step 1: Create the test file**

```typescript
import { http, HttpResponse } from "msw";
import { describe, expect, it } from "vitest";
import { ShoppingPage } from "../ShoppingPage";
import { server } from "@/test/mocks/server";
import { createShoppingItem, wrapResponse } from "@/test/mocks/data";
import { render, screen, waitFor } from "@/test/utils";

describe("ShoppingPage", () => {
  it("displays loading state initially", () => {
    server.use(
      http.get("*/api/internal/v1/shopping-list", async () => {
        await new Promise((r) => setTimeout(r, 100));
        return HttpResponse.json(wrapResponse([]));
      }),
    );

    render(<ShoppingPage />);
    expect(screen.getByText("Загрузка...")).toBeInTheDocument();
  });

  it("displays empty state when list is empty", async () => {
    server.use(
      http.get("*/api/internal/v1/shopping-list", () =>
        HttpResponse.json(wrapResponse([])),
      ),
    );

    render(<ShoppingPage />);

    await waitFor(() => {
      expect(
        screen.getByText("Список пуст. Найдите товар выше, чтобы добавить."),
      ).toBeInTheDocument();
    });
  });

  it("displays shopping list items", async () => {
    server.use(
      http.get("*/api/internal/v1/shopping-list", () =>
        HttpResponse.json(
          wrapResponse([
            createShoppingItem({ name: "Молоко", amount: 2 }),
            createShoppingItem({ name: "Хлеб", amount: 1, source: "auto" }),
          ]),
        ),
      ),
    );

    render(<ShoppingPage />);

    await waitFor(() => {
      expect(screen.getByText("Молоко")).toBeInTheDocument();
      expect(screen.getByText("Хлеб")).toBeInTheDocument();
      expect(screen.getByText("Авто")).toBeInTheDocument();
    });
  });

  it("shows item count in header", async () => {
    server.use(
      http.get("*/api/internal/v1/shopping-list", () =>
        HttpResponse.json(
          wrapResponse([
            createShoppingItem({ name: "Молоко" }),
            createShoppingItem({ name: "Хлеб" }),
            createShoppingItem({ name: "Яйца" }),
          ]),
        ),
      ),
    );

    render(<ShoppingPage />);

    await waitFor(() => {
      expect(screen.getByText("3")).toBeInTheDocument();
    });
  });

  it("opens edit modal when clicking item", async () => {
    server.use(
      http.get("*/api/internal/v1/shopping-list", () =>
        HttpResponse.json(
          wrapResponse([createShoppingItem({ name: "Молоко", amount: 2 })]),
        ),
      ),
    );

    const { user } = render(<ShoppingPage />);

    await waitFor(() => {
      expect(screen.getByText("Молоко")).toBeInTheDocument();
    });

    await user.click(screen.getByText("Молоко"));

    expect(screen.getByLabelText("Количество")).toBeInTheDocument();
    expect(screen.getByLabelText("Заметка")).toBeInTheDocument();
  });

  it("adds custom item via search input", async () => {
    let addedItem: unknown = null;

    server.use(
      http.get("*/api/internal/v1/shopping-list", () =>
        HttpResponse.json(wrapResponse([])),
      ),
      http.post("*/api/internal/v1/shopping-list", async ({ request }) => {
        addedItem = await request.json();
        return HttpResponse.json(
          { data: createShoppingItem({ name: "Бананы" }) },
          { status: 201 },
        );
      }),
      http.get("*/api/internal/v1/products", () =>
        HttpResponse.json(wrapResponse([])),
      ),
    );

    const { user } = render(<ShoppingPage />);

    await waitFor(() => {
      expect(screen.getByPlaceholderText("Добавить товар...")).toBeInTheDocument();
    });

    await user.type(screen.getByPlaceholderText("Добавить товар..."), "Бананы");
    await user.click(screen.getByRole("button", { name: "Добавить" }));

    await waitFor(() => {
      expect(addedItem).toEqual({ custom_name: "Бананы", amount: 1 });
    });
  });
});
```

**Step 2: Run tests**

Run: `cd /home/pavel/projects/hestia/frontend && bun run test:run`
Expected: All tests pass

**Step 3: Commit**

```bash
git add frontend/src/features/shopping/__tests__/ShoppingPage.test.tsx
git commit -s -m "test(frontend): add ShoppingPage integration tests"
```

---

## Task 11: Final Verification

**Step 1: Run full check**

Run: `cd /home/pavel/projects/hestia/frontend && bun run check`
Expected: PASS

**Step 2: Run all tests**

Run: `cd /home/pavel/projects/hestia/frontend && bun run test:run`
Expected: All tests pass

**Step 3: Test manually in browser**

1. Start the frontend and backend
2. Navigate to /shopping
3. Verify:
   - Search dropdown appears when typing
   - Selecting product adds it to list
   - "Add custom" option appears for non-matching searches
   - Clicking item opens edit modal
   - Changing amount and saving works
   - Clicking checkbox fades out and removes item
   - Auto badge shows for auto-added items

**Step 4: Final commit (if any fixes needed)**

```bash
git add -A
git commit -s -m "fix(frontend): address review feedback"
```
