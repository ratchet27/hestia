# Shopping List Frontend Implementation Design

## Overview

Connect the shopping list frontend to the real backend API, replacing mock data with live API integration. Add functional product search, custom item support, quantity editing, and smooth animations.

## Requirements Summary

| Requirement | Implementation |
|-------------|----------------|
| Connect to real API | Replace Context/mocks with React Query |
| Product search | Client-side filtering of cached products |
| Custom items | Add items with `custom_name` when product not found |
| Edit quantity | Modal with amount + note fields |
| Mark as bought | DELETE item with fade animation |
| Auto badge | Show badge for `source === 'auto'` items |

## Architecture

### State Management

Replace local Context with React Query:

```
useShoppingList()      → GET /shopping-list
useAddShoppingItem()   → POST /shopping-list
useUpdateShoppingItem() → PATCH /shopping-list/{uuid}
useDeleteShoppingItem() → DELETE /shopping-list/{uuid}
```

### Data Flow

```
Products API (cached) → Client-side filtering → Search dropdown
                                                      ↓
                                            Selection / Custom
                                                      ↓
                                              Add mutation
                                                      ↓
                                          Shopping List Query → UI
                                                      ↓
                                            DELETE on checkbox
```

### Type Migration

- Use backend types directly (`ShoppingItemResponse`)
- All IDs are UUIDs (strings)
- Remove local `ShoppingItem` type

## Components

### 1. ProductSearchInput

Search input with inline dropdown for adding items.

**Behavior:**
- Filters products client-side as user types
- Dropdown appears after 1+ character
- Shows max 5-7 matching products
- "Add '{text}' as custom item" option at bottom
- Click product → add with `product_id`
- Click custom → add with `custom_name`
- Input clears after add

**Props:**
```typescript
interface ProductSearchInputProps {
  onAddItem: (item: AddShoppingItemRequest) => void;
  isAdding?: boolean;
}
```

### 2. ShoppingListItem

Single row in the shopping list.

**Display:**
- Checkbox (left)
- Item name
- Amount (e.g., "×3")
- "Auto" badge if `source === 'auto'`
- Note (smaller text, if exists)

**Interactions:**
- Click row (not checkbox) → open edit modal
- Click checkbox → delete with fade animation

**Props:**
```typescript
interface ShoppingListItemProps {
  item: ShoppingItemResponse;
  onEdit: (item: ShoppingItemResponse) => void;
  onDelete: (id: string) => void;
  isDeleting?: boolean;
}
```

### 3. EditItemModal

Modal for editing item quantity and note.

**Fields:**
- Item name (read-only, displayed)
- Amount (number input)
- Note (text input)

**Actions:**
- Save → PATCH with new values
- Cancel → close modal

**Props:**
```typescript
interface EditItemModalProps {
  item: ShoppingItemResponse | null;
  isOpen: boolean;
  onClose: () => void;
  onSave: (id: string, data: UpdateShoppingItemRequest) => void;
  isSaving?: boolean;
}
```

## Animation

### Fade-out on Delete

```css
.shopping-item-exit {
  opacity: 1;
  max-height: 80px;
}

.shopping-item-exit-active {
  opacity: 0;
  max-height: 0;
  transition: opacity 300ms ease-out, max-height 300ms ease-out;
}
```

Or with Tailwind:
```typescript
// Use framer-motion or react-transition-group
// Animate: opacity 0, height collapse over 300ms
```

## API Integration

### Query Keys

```typescript
export const queryKeys = {
  // ...existing keys
  shoppingList: {
    all: ['shopping-list'] as const,
    list: () => [...queryKeys.shoppingList.all, 'list'] as const,
    detail: (id: string) => [...queryKeys.shoppingList.all, id] as const,
  },
};
```

### Hooks

```typescript
// List all items
export function useShoppingList() {
  return useQuery({
    queryKey: queryKeys.shoppingList.list(),
    queryFn: async () => {
      const response = await getApiInternalV1ShoppingListIndex();
      return response.data.data ?? [];
    },
  });
}

// Add item
export function useAddShoppingItem() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: postApiInternalV1ShoppingListCreate,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.shoppingList.all });
    },
  });
}

// Update item
export function useUpdateShoppingItem() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }) => patchApiInternalV1ShoppingListUpdate(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.shoppingList.all });
    },
  });
}

// Delete item (mark as done)
export function useDeleteShoppingItem() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: deleteApiInternalV1ShoppingListDelete,
    onMutate: async (id) => {
      // Optimistic update
      await queryClient.cancelQueries({ queryKey: queryKeys.shoppingList.list() });
      const previous = queryClient.getQueryData(queryKeys.shoppingList.list());
      queryClient.setQueryData(queryKeys.shoppingList.list(), (old) =>
        old?.filter((item) => item.id !== id)
      );
      return { previous };
    },
    onError: (err, id, context) => {
      queryClient.setQueryData(queryKeys.shoppingList.list(), context?.previous);
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.shoppingList.all });
    },
  });
}
```

## File Changes

### Create

| File | Purpose |
|------|---------|
| `frontend/src/api/queries/shoppingList.ts` | React Query hooks |
| `frontend/src/features/shopping/components/ProductSearchInput.tsx` | Search + dropdown |
| `frontend/src/features/shopping/components/ShoppingListItem.tsx` | Item row |
| `frontend/src/features/shopping/components/EditItemModal.tsx` | Edit modal |
| `frontend/src/features/shopping/hooks/useProductSearch.ts` | Search filter logic |
| `frontend/src/features/shopping/__tests__/ShoppingPage.test.tsx` | Tests |

### Modify

| File | Changes |
|------|---------|
| `frontend/src/features/shopping/ShoppingPage.tsx` | Refactor to use new components and hooks |
| `frontend/src/api/queries/keys.ts` | Add shopping list query keys |

### Remove

| File | Reason |
|------|--------|
| `frontend/src/data/mocks.ts` | Remove `mockShoppingList` |
| `frontend/src/data/types.ts` | Remove `ShoppingItem` type |
| `frontend/src/data/context.tsx` | Remove `ShoppingContext` |
| `frontend/src/data/hooks.ts` | Remove `useShoppingList` hook |

## Error Handling

- Toast notifications on API errors
- Optimistic updates with rollback on failure
- React Query retry mechanism

## Empty State

"Your shopping list is empty. Search for products above to add items."

## Loading States

- Initial load: skeleton/spinner
- Add item: optimistic, rollback on error
- Delete: fade animation, rollback on error
- Edit modal: Save button loading state
