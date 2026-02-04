# Frontend Test Coverage Plan

## Overview

Add tests for 8 untested frontend components following existing project patterns.

## Scope

### Included (API-backed, stable UI)

| Component | Type | Tests | Priority |
|-----------|------|-------|----------|
| StockRow | Component | 5 | 1 |
| ShoppingListItem | Component | 5 | 2 |
| ProductSearchInput | Component | 6 | 3 |
| AddStockModal | Component | 5 | 4 |
| ScanModal | Component | 4 | 5 |
| ProductForm | Component | 8 | 6 |
| ProductsPage | Page | 6 | 7 |
| DashboardPage | Page | 5 | 8 |

### Excluded (mocked/unstable)

- LoginPage - placeholder
- TasksPage - mocked data
- SettingsPage - UI will change
- RecipesPage - UI will change
- StockTable - wrapper only, tested via StockPage
- Dashboard chores/tasks sections - use mocked context

## Test Cases

### 1. StockRow.test.tsx

```
- renders product name and unit
- displays expiry info with date
- shows warning emoji for expired items
- shows alarm emoji for items expiring today
- calls onConsume when checkmark clicked
```

### 2. ShoppingListItem.test.tsx

```
- renders item name and amount
- shows note when present
- shows "Авто" badge for auto source
- calls onClick when row clicked
- calls onDelete when checkbox clicked
```

### 3. ProductSearchInput.test.tsx

```
- renders input and add button
- filters products as user types
- selects product from dropdown
- adds custom item when no match
- navigates dropdown with keyboard
- closes dropdown on escape
```

### 4. AddStockModal.test.tsx

```
- renders form with product and location selects
- preselects product when provided
- auto-fills location from product default
- validates required fields
- calls onSubmit with form data
```

### 5. ScanModal.test.tsx

```
- focuses input on mount
- calls onProductFound when barcode matches
- calls onBarcodeNotFound when 404
- disables buttons while loading
```

### 6. ProductForm.test.tsx

```
- renders all form fields
- validates required name field
- manages barcode list (add/remove)
- prevents duplicate barcodes
- submits form with all data
- displays server validation errors
- shows barcode conflict error
- toggles barcode section visibility
```

### 7. ProductsPage.test.tsx

```
- loads and displays products
- shows loading skeleton initially
- shows error state on API failure
- filters by search term
- filters by category
- opens add modal and creates product
```

### 8. DashboardPage.test.tsx (API sections only)

```
- displays stock count card
- displays shopping count card
- displays expiring items section
- displays low stock items section
- navigates to stock page on click
```

## Patterns to Follow

1. Use mock data factories from `@/test/mocks/data`
2. Use MSW for API mocking in page tests
3. Use `vi.fn()` for callback props
4. Use `user.click` / `user.type` for interactions
5. Assert Russian text directly
6. Keep 4-8 tests per component

## Mock Data Additions Needed

Add to `@/test/mocks/data.ts`:

```typescript
export function createProductResponse(overrides = {}): ProductResponse
export function createCategory(overrides = {}): CategoryResponse
```
