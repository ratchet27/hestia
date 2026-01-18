# Product API Frontend Integration Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Integrate the backend Product API with the React frontend, replacing mock data with auto-generated TypeScript clients.

**Architecture:** Use Orval to generate plain fetch functions and types from OpenAPI spec. Custom React Query hooks in `/api/queries/` wrap these fetchers with standardized query keys and invalidation logic. Products page uses these custom hooks, replacing the existing Context-based approach.

**Tech Stack:** React 19, TanStack Query v5, React Hook Form, react-hot-toast, Orval (build-time only)

---

## Architecture Overview

```
frontend/src/api/
├── client.ts              # apiFetch wrapper (base URL, credentials, CSRF, errors)
├── generated/             # Orval output: plain fetchers + types only
│   ├── products.ts        # getProducts(), createProduct(), etc.
│   ├── categories.ts      # getCategories()
│   ├── locations.ts       # getLocations()
│   └── models/            # TypeScript types
└── queries/
    ├── keys.ts            # Query key factory
    ├── products.ts        # useProducts(), useProduct(), useCreateProduct()
    ├── categories.ts      # useCategories()
    └── locations.ts       # useLocations()
```

**Query Key Convention:**
- `['products', filters]` - product list with filters
- `['product', id]` - single product
- `['categories']` - all categories
- `['locations']` - all locations

**Error Handling:**
- 500 / network errors → global toast
- Mutation failures → global toast
- 422 validation → mapped to React Hook Form field errors
- 404 → handled locally (no global toast)

---

## Task 1: Install Dependencies

**Files:**
- Modify: `frontend/package.json`

**Step 1: Install production dependencies**

Run: `cd /home/pavel/projects/hestia/frontend && npm install @tanstack/react-query react-hook-form react-hot-toast`

Expected: Packages added to dependencies in package.json

**Step 2: Install dev dependencies**

Run: `cd /home/pavel/projects/hestia/frontend && npm install -D orval`

Expected: orval added to devDependencies in package.json

**Step 3: Verify installation**

Run: `cd /home/pavel/projects/hestia/frontend && npm ls @tanstack/react-query react-hook-form react-hot-toast orval`

Expected: All packages listed with versions

**Step 4: Commit**

```bash
cd /home/pavel/projects/hestia && git add frontend/package.json frontend/package-lock.json frontend/bun.lock 2>/dev/null || true
git commit -s -m "feat(frontend): add react-query, react-hook-form, toast, orval dependencies"
```

---

## Task 2: Create Environment Configuration

**Files:**
- Create: `frontend/.env`
- Create: `frontend/.env.example`
- Modify: `frontend/.gitignore` (if needed)

**Step 1: Create .env.example**

Create `frontend/.env.example`:
```
# Runtime: API base URL for fetch requests
VITE_API_BASE_URL=http://localhost:8000
```

**Step 2: Create .env**

Create `frontend/.env`:
```
VITE_API_BASE_URL=http://localhost:8000
```

**Step 3: Verify .gitignore includes .env**

Check `frontend/.gitignore` - if `.env` is not listed, add it.

**Step 4: Commit**

```bash
cd /home/pavel/projects/hestia && git add frontend/.env.example frontend/.gitignore
git commit -s -m "chore(frontend): add environment configuration"
```

---

## Task 3: Create Orval Configuration

**Files:**
- Create: `frontend/orval.config.ts`
- Modify: `frontend/package.json` (add scripts)

**Note:** Orval generates plain fetchers only (not React Query hooks). Custom hooks are written manually for control over query keys.

**Step 1: Create orval.config.ts**

Create `frontend/orval.config.ts`:
```typescript
import { defineConfig } from 'orval'

export default defineConfig({
  hestia: {
    input: {
      target: process.env.API_SPEC_URL || 'http://localhost:8000/api/doc.json',
    },
    output: {
      mode: 'tags-split',
      target: './src/api/generated',
      schemas: './src/api/generated/models',
      client: 'fetch',
      baseUrl: false,
      override: {
        mutator: {
          path: './src/api/client.ts',
          name: 'apiFetch',
        },
      },
    },
  },
})
```

**Step 2: Add npm scripts to package.json**

Add to `frontend/package.json` scripts section:
```json
{
  "scripts": {
    "generate-api": "orval",
    "generate-api:watch": "orval --watch"
  }
}
```

**Step 3: Commit**

```bash
cd /home/pavel/projects/hestia && git add frontend/orval.config.ts frontend/package.json
git commit -s -m "chore(frontend): add orval configuration for API client generation"
```

---

## Task 4: Create API Fetch Client

**Files:**
- Create: `frontend/src/api/client.ts`

**Step 1: Create api directory**

Run: `mkdir -p /home/pavel/projects/hestia/frontend/src/api`

**Step 2: Create client.ts**

Create `frontend/src/api/client.ts`:
```typescript
const BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'

export interface ApiErrorResponse {
  status: number
  message: string
  violations?: Array<{ propertyPath: string; message: string }>
}

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public violations?: Array<{ propertyPath: string; message: string }>
  ) {
    super(message)
    this.name = 'ApiError'
  }

  get isValidationError(): boolean {
    return this.status === 422
  }

  get isNotFound(): boolean {
    return this.status === 404
  }

  get isServerError(): boolean {
    return this.status >= 500
  }
}

// CSRF token placeholder - will be implemented with session auth
function getCsrfToken(): string | null {
  // TODO: Implement when session auth is added
  // Options: read from cookie, meta tag, or dedicated endpoint
  return null
}

export async function apiFetch<T>(
  url: string,
  options?: RequestInit
): Promise<T> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    ...options?.headers,
  }

  // Add CSRF token if available
  const csrfToken = getCsrfToken()
  if (csrfToken) {
    ;(headers as Record<string, string>)['X-CSRF-Token'] = csrfToken
  }

  const response = await fetch(`${BASE_URL}${url}`, {
    ...options,
    headers,
    credentials: 'include', // Include cookies for session auth
  })

  // Handle 204 No Content
  if (response.status === 204) {
    return undefined as T
  }

  // Parse response body
  let data: unknown
  try {
    data = await response.json()
  } catch {
    // Response body is not JSON
    if (!response.ok) {
      throw new ApiError(response.status, 'Request failed')
    }
    return undefined as T
  }

  // Handle errors
  if (!response.ok) {
    const errorData = data as { detail?: string; message?: string; violations?: Array<{ propertyPath: string; message: string }> }
    throw new ApiError(
      response.status,
      errorData.detail || errorData.message || 'Request failed',
      errorData.violations
    )
  }

  return data as T
}
```

**Step 3: Commit**

```bash
cd /home/pavel/projects/hestia && git add frontend/src/api/client.ts
git commit -s -m "feat(frontend): add apiFetch wrapper with credentials and error handling"
```

---

## Task 5: Generate API Client

**Files:**
- Create: `frontend/src/api/generated/` (auto-generated)

**Prerequisites:** Backend must be running at localhost:8000

**Step 1: Start backend (if not running)**

Run: `cd /home/pavel/projects/hestia/backend && docker compose up -d`

Wait for backend to be healthy.

**Step 2: Verify OpenAPI spec is accessible**

Run: `curl -s http://localhost:8000/api/doc.json | head -20`

Expected: JSON output starting with `{"openapi":"3.0.0"`

**Step 3: Generate API client**

Run: `cd /home/pavel/projects/hestia/frontend && npm run generate-api`

Expected: Files created in `src/api/generated/` with plain fetch functions

**Step 4: Verify generated files**

Run: `ls -la /home/pavel/projects/hestia/frontend/src/api/generated/`

Expected: Directory with fetchers (e.g., products.ts, categories.ts) and models/

**Step 5: Commit**

```bash
cd /home/pavel/projects/hestia && git add frontend/src/api/generated/
git commit -s -m "feat(frontend): generate API client from OpenAPI spec"
```

---

## Task 6: Create Query Key Factory

**Files:**
- Create: `frontend/src/api/queries/keys.ts`

**Step 1: Create queries directory**

Run: `mkdir -p /home/pavel/projects/hestia/frontend/src/api/queries`

**Step 2: Create keys.ts**

Create `frontend/src/api/queries/keys.ts`:
```typescript
export interface ProductFilters {
  name?: string
  categoryId?: string
  active?: boolean
}

export const queryKeys = {
  // Products
  products: {
    all: ['products'] as const,
    list: (filters?: ProductFilters) => ['products', filters ?? {}] as const,
    detail: (id: string) => ['product', id] as const,
  },

  // Categories
  categories: {
    all: ['categories'] as const,
  },

  // Locations
  locations: {
    all: ['locations'] as const,
  },
} as const
```

**Step 3: Commit**

```bash
cd /home/pavel/projects/hestia && git add frontend/src/api/queries/keys.ts
git commit -s -m "feat(frontend): add query key factory"
```

---

## Task 7: Create Query Client with Error Handling

**Files:**
- Create: `frontend/src/api/queryClient.ts`

**Step 1: Create queryClient.ts**

Create `frontend/src/api/queryClient.ts`:
```typescript
import { QueryClient, QueryCache, MutationCache } from '@tanstack/react-query'
import toast from 'react-hot-toast'
import { ApiError } from './client'

function handleGlobalError(error: unknown): void {
  if (error instanceof ApiError) {
    // 422 validation errors are handled by forms
    if (error.isValidationError) {
      return
    }

    // 404 errors are handled locally by components
    if (error.isNotFound) {
      return
    }

    // Server errors and other failures get toasts
    toast.error(error.message)
  } else if (error instanceof Error) {
    toast.error(error.message)
  } else {
    toast.error('Произошла ошибка')
  }
}

export const queryClient = new QueryClient({
  queryCache: new QueryCache({
    onError: handleGlobalError,
  }),
  mutationCache: new MutationCache({
    onError: handleGlobalError,
  }),
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutes
      retry: (failureCount, error) => {
        // Don't retry on 4xx errors
        if (error instanceof ApiError && error.status >= 400 && error.status < 500) {
          return false
        }
        return failureCount < 1
      },
      refetchOnWindowFocus: false,
    },
    mutations: {
      retry: 0,
    },
  },
})
```

**Step 2: Commit**

```bash
cd /home/pavel/projects/hestia && git add frontend/src/api/queryClient.ts
git commit -s -m "feat(frontend): add query client with selective error handling"
```

---

## Task 8: Create Custom Query Hooks

**Files:**
- Create: `frontend/src/api/queries/products.ts`
- Create: `frontend/src/api/queries/categories.ts`
- Create: `frontend/src/api/queries/locations.ts`
- Create: `frontend/src/api/queries/index.ts`

**Note:** These hooks wrap the generated fetchers. Verify the exact function names in `src/api/generated/` and adjust imports accordingly.

**Step 1: Create products.ts**

Create `frontend/src/api/queries/products.ts`:
```typescript
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { queryKeys, type ProductFilters } from './keys'
// Import generated fetchers - verify exact names from generated code
import {
  getApiInternalV1Products,
  postApiInternalV1Products,
  getApiInternalV1ProductsUuid,
  putApiInternalV1ProductsUuid,
  deleteApiInternalV1ProductsUuid,
} from '../generated/products'
import type { CreateProductRequest, UpdateProductRequest } from '../generated/models'

export function useProducts(filters?: ProductFilters) {
  return useQuery({
    queryKey: queryKeys.products.list(filters),
    queryFn: () => getApiInternalV1Products({
      name: filters?.name,
      category_id: filters?.categoryId,
      active: filters?.active,
    }),
  })
}

export function useProduct(id: string) {
  return useQuery({
    queryKey: queryKeys.products.detail(id),
    queryFn: () => getApiInternalV1ProductsUuid(id),
    enabled: !!id,
  })
}

export function useCreateProduct() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (data: CreateProductRequest) => postApiInternalV1Products(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.products.all })
    },
  })
}

export function useUpdateProduct() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }: { id: string; data: UpdateProductRequest }) =>
      putApiInternalV1ProductsUuid(id, data),
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.products.all })
      queryClient.invalidateQueries({ queryKey: queryKeys.products.detail(id) })
    },
  })
}

export function useDeleteProduct() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => deleteApiInternalV1ProductsUuid(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.products.all })
    },
  })
}
```

**Step 2: Create categories.ts**

Create `frontend/src/api/queries/categories.ts`:
```typescript
import { useQuery } from '@tanstack/react-query'
import { queryKeys } from './keys'
// Import generated fetcher - verify exact name from generated code
import { getApiInternalV1Categories } from '../generated/categories'

export function useCategories() {
  return useQuery({
    queryKey: queryKeys.categories.all,
    queryFn: () => getApiInternalV1Categories(),
    staleTime: 10 * 60 * 1000, // Categories rarely change - 10 min cache
  })
}
```

**Step 3: Create locations.ts**

Create `frontend/src/api/queries/locations.ts`:
```typescript
import { useQuery } from '@tanstack/react-query'
import { queryKeys } from './keys'
// Import generated fetcher - verify exact name from generated code
import { getApiInternalV1Locations } from '../generated/locations'

export function useLocations() {
  return useQuery({
    queryKey: queryKeys.locations.all,
    queryFn: () => getApiInternalV1Locations(),
    staleTime: 10 * 60 * 1000, // Locations rarely change - 10 min cache
  })
}
```

**Step 4: Create index.ts barrel export**

Create `frontend/src/api/queries/index.ts`:
```typescript
export * from './keys'
export * from './products'
export * from './categories'
export * from './locations'
```

**Step 5: Commit**

```bash
cd /home/pavel/projects/hestia && git add frontend/src/api/queries/
git commit -s -m "feat(frontend): add custom React Query hooks with standardized keys"
```

---

## Task 9: Add Providers to App Root

**Files:**
- Modify: `frontend/src/main.tsx`

**Step 1: Update main.tsx**

Replace `frontend/src/main.tsx` with:
```typescript
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import { QueryClientProvider } from '@tanstack/react-query'
import { Toaster } from 'react-hot-toast'
import { queryClient } from './api/queryClient'
import { DataProvider, AuthProvider } from './data/context'
import './index.css'
import App from './App'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AuthProvider>
          <DataProvider>
            <App />
            <Toaster
              position="top-right"
              toastOptions={{
                duration: 4000,
                error: { duration: 4000 },
                success: { duration: 2000 },
              }}
            />
          </DataProvider>
        </AuthProvider>
      </BrowserRouter>
    </QueryClientProvider>
  </StrictMode>
)
```

**Step 2: Verify app still runs**

Run: `cd /home/pavel/projects/hestia/frontend && npm run dev`

Expected: App starts without errors

**Step 3: Commit**

```bash
cd /home/pavel/projects/hestia && git add frontend/src/main.tsx
git commit -s -m "feat(frontend): add QueryClientProvider and Toaster to app root"
```

---

## Task 10: Create Product Card Skeleton

**Files:**
- Create: `frontend/src/components/ProductSkeleton.tsx`

**Step 1: Create ProductSkeleton.tsx**

Create `frontend/src/components/ProductSkeleton.tsx`:
```typescript
export function ProductSkeleton(): React.ReactElement {
  return (
    <div className="bg-white rounded-xl p-4 shadow-sm border border-stone-200 animate-pulse">
      <div className="flex justify-between items-start mb-3">
        <div className="h-6 w-20 bg-stone-200 rounded" />
      </div>
      <div className="h-5 w-3/4 bg-stone-200 rounded mb-2" />
      <div className="space-y-2">
        <div className="h-4 w-1/2 bg-stone-200 rounded" />
        <div className="h-4 w-2/3 bg-stone-200 rounded" />
        <div className="h-4 w-1/3 bg-stone-200 rounded" />
      </div>
      <div className="mt-3 pt-3 border-t border-stone-100 flex justify-between items-center">
        <div className="h-6 w-12 bg-stone-200 rounded" />
        <div className="h-4 w-16 bg-stone-200 rounded" />
      </div>
    </div>
  )
}

export function ProductsGridSkeleton({ count = 6 }: { count?: number }): React.ReactElement {
  return (
    <div className="grid grid-cols-3 gap-4">
      {Array.from({ length: count }).map((_, i) => (
        <ProductSkeleton key={i} />
      ))}
    </div>
  )
}
```

**Step 2: Commit**

```bash
cd /home/pavel/projects/hestia && git add frontend/src/components/ProductSkeleton.tsx
git commit -s -m "feat(frontend): add product card skeleton loader"
```

---

## Task 11: Create Product Form Component

**Files:**
- Create: `frontend/src/features/products/ProductForm.tsx`

**Step 1: Create ProductForm.tsx**

Create `frontend/src/features/products/ProductForm.tsx`:
```typescript
import { useForm } from 'react-hook-form'
import { useEffect } from 'react'
import type { ProductResponse, CreateProductRequest } from '../../api/generated/models'
import { ApiError } from '../../api/client'

interface ProductFormProps {
  product?: ProductResponse
  categories: Array<{ id: string; name: string }>
  locations: Array<{ id: string; name: string }>
  onSubmit: (data: CreateProductRequest) => Promise<void>
  onCancel: () => void
  isSubmitting: boolean
  submitError?: Error | null
}

interface FormValues {
  name: string
  categoryId: string
  defaultLocationId: string
  defaultExpiryDays: string
  minStock: string
  active: boolean
}

export function ProductForm({
  product,
  categories,
  locations,
  onSubmit,
  onCancel,
  isSubmitting,
  submitError,
}: ProductFormProps): React.ReactElement {
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<FormValues>({
    defaultValues: {
      name: product?.name ?? '',
      categoryId: product?.category.id ?? categories[0]?.id ?? '',
      defaultLocationId: product?.defaultLocation.id ?? locations[0]?.id ?? '',
      defaultExpiryDays: product?.defaultExpiryDays?.toString() ?? '',
      minStock: product?.minStock?.toString() ?? '0',
      active: product?.active ?? true,
    },
  })

  // Map 422 validation errors to form fields
  useEffect(() => {
    if (submitError instanceof ApiError && submitError.isValidationError && submitError.violations) {
      submitError.violations.forEach((violation) => {
        const field = violation.propertyPath as keyof FormValues
        if (['name', 'categoryId', 'defaultLocationId', 'defaultExpiryDays', 'minStock', 'active'].includes(field)) {
          setError(field, { type: 'server', message: violation.message })
        }
      })
    }
  }, [submitError, setError])

  const onFormSubmit = async (values: FormValues): Promise<void> => {
    const data: CreateProductRequest = {
      name: values.name,
      categoryId: values.categoryId,
      defaultLocationId: values.defaultLocationId,
      defaultExpiryDays: values.defaultExpiryDays ? parseInt(values.defaultExpiryDays, 10) : undefined,
      minStock: parseInt(values.minStock, 10) || 0,
      active: values.active,
    }
    await onSubmit(data)
  }

  return (
    <form onSubmit={handleSubmit(onFormSubmit)} className="space-y-4">
      <div>
        <label className="block text-sm font-medium text-stone-700 mb-1">Название</label>
        <input
          type="text"
          placeholder="Например: Молоко 3.2%"
          {...register('name', { required: 'Название обязательно' })}
          className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        />
        {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name.message}</p>}
      </div>

      <div>
        <label className="block text-sm font-medium text-stone-700 mb-1">Категория</label>
        <select
          {...register('categoryId', { required: 'Категория обязательна' })}
          className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        >
          {categories.map((cat) => (
            <option key={cat.id} value={cat.id}>
              {cat.name}
            </option>
          ))}
        </select>
        {errors.categoryId && <p className="mt-1 text-sm text-red-600">{errors.categoryId.message}</p>}
      </div>

      <div>
        <label className="block text-sm font-medium text-stone-700 mb-1">Место хранения</label>
        <select
          {...register('defaultLocationId', { required: 'Место хранения обязательно' })}
          className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        >
          {locations.map((loc) => (
            <option key={loc.id} value={loc.id}>
              {loc.name}
            </option>
          ))}
        </select>
        {errors.defaultLocationId && <p className="mt-1 text-sm text-red-600">{errors.defaultLocationId.message}</p>}
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">Срок годности (дни)</label>
          <input
            type="number"
            placeholder="Необязательно"
            {...register('defaultExpiryDays', {
              min: { value: 1, message: 'Должно быть больше 0' },
            })}
            className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
          />
          {errors.defaultExpiryDays && <p className="mt-1 text-sm text-red-600">{errors.defaultExpiryDays.message}</p>}
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">Мин. запас</label>
          <input
            type="number"
            {...register('minStock', {
              min: { value: 0, message: 'Не может быть отрицательным' },
            })}
            className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
          />
          {errors.minStock && <p className="mt-1 text-sm text-red-600">{errors.minStock.message}</p>}
        </div>
      </div>

      <div className="flex items-center gap-2">
        <input
          type="checkbox"
          id="active"
          {...register('active')}
          className="w-4 h-4 text-amber-500 border-stone-300 rounded focus:ring-amber-500"
        />
        <label htmlFor="active" className="text-sm font-medium text-stone-700">
          Активен
        </label>
      </div>

      <div className="flex gap-3 mt-6">
        <button
          type="button"
          onClick={onCancel}
          disabled={isSubmitting}
          className="flex-1 px-4 py-2 border border-stone-300 rounded-lg hover:bg-stone-50 transition-colors disabled:opacity-50"
        >
          Отмена
        </button>
        <button
          type="submit"
          disabled={isSubmitting}
          className="flex-1 px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors disabled:opacity-50"
        >
          {isSubmitting ? 'Сохранение...' : product ? 'Сохранить' : 'Создать'}
        </button>
      </div>
    </form>
  )
}
```

**Step 2: Commit**

```bash
cd /home/pavel/projects/hestia && git add frontend/src/features/products/ProductForm.tsx
git commit -s -m "feat(frontend): add ProductForm component with react-hook-form"
```

---

## Task 12: Refactor ProductsPage to Use Custom Hooks

**Files:**
- Modify: `frontend/src/features/products/ProductsPage.tsx`

**Note:** Uses `isLoading` for skeleton (initial load only), not `isFetching` (which includes background refetch).

**Step 1: Update ProductsPage.tsx**

Replace `frontend/src/features/products/ProductsPage.tsx` with:
```typescript
import { useState } from 'react'
import toast from 'react-hot-toast'
import { Icons } from '../../components/Icons'
import { ProductsGridSkeleton } from '../../components/ProductSkeleton'
import { ProductForm } from './ProductForm'
import { useStock } from '../../data/hooks'
import { useProducts, useCreateProduct, useCategories, useLocations } from '../../api/queries'
import type { CreateProductRequest, ProductResponse } from '../../api/generated/models'

export function ProductsPage(): React.ReactElement {
  const { stock } = useStock()
  const [searchTerm, setSearchTerm] = useState('')
  const [categoryFilter, setCategoryFilter] = useState<string>('all')
  const [showAddModal, setShowAddModal] = useState(false)

  // Fetch products from API - only show skeleton on initial load
  const {
    data: products = [],
    isLoading: productsLoading,
    isError: productsError,
  } = useProducts({
    name: searchTerm || undefined,
    categoryId: categoryFilter !== 'all' ? categoryFilter : undefined,
  })

  // Fetch categories and locations for form dropdowns
  const { data: categories = [] } = useCategories()
  const { data: locations = [] } = useLocations()

  // Create product mutation
  const createProduct = useCreateProduct()

  const handleCreateProduct = async (data: CreateProductRequest): Promise<void> => {
    await createProduct.mutateAsync(data)
    toast.success('Товар создан')
    setShowAddModal(false)
  }

  // Calculate total stock for a product (stock still uses mock data)
  const getTotalStock = (productId: string): number => {
    // Note: Stock entries still use number IDs, this will need adjustment
    // when stock API is integrated
    return 0
  }

  // Show skeleton only on initial load, not background refetch
  if (productsLoading) {
    return (
      <div className="p-8">
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="text-3xl font-bold text-stone-800">Товары</h2>
            <p className="text-stone-500 mt-1">Справочник товаров и штрихкодов</p>
          </div>
        </div>
        <ProductsGridSkeleton count={9} />
      </div>
    )
  }

  if (productsError) {
    return (
      <div className="p-8">
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="text-3xl font-bold text-stone-800">Товары</h2>
            <p className="text-stone-500 mt-1">Справочник товаров и штрихкодов</p>
          </div>
        </div>
        <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
          Не удалось загрузить товары. Проверьте подключение к серверу.
        </div>
      </div>
    )
  }

  return (
    <div className="p-8">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-3xl font-bold text-stone-800">Товары</h2>
          <p className="text-stone-500 mt-1">Справочник товаров и штрихкодов</p>
        </div>
        <button
          onClick={() => setShowAddModal(true)}
          className="flex items-center gap-2 px-4 py-2 bg-stone-800 text-white rounded-lg hover:bg-stone-700 transition-colors"
        >
          <Icons.Plus />
          Новый товар
        </button>
      </div>

      <div className="flex gap-4 mb-6">
        <div className="relative flex-1">
          <span className="absolute left-3 top-1/2 -translate-y-1/2 text-stone-400">
            <Icons.Search />
          </span>
          <input
            type="text"
            placeholder="Поиск по названию..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-10 pr-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
          />
        </div>
        <select
          value={categoryFilter}
          onChange={(e) => setCategoryFilter(e.target.value)}
          className="px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        >
          <option value="all">Все категории</option>
          {categories.map((cat) => (
            <option key={cat.id} value={cat.id}>
              {cat.name}
            </option>
          ))}
        </select>
      </div>

      <div className="grid grid-cols-3 gap-4">
        {products.map((product: ProductResponse) => {
          const totalStock = getTotalStock(product.id)
          const isLow = product.minStock > 0 && totalStock < product.minStock

          return (
            <div
              key={product.id}
              className="bg-white rounded-xl p-4 shadow-sm border border-stone-200 hover:border-amber-400 transition-colors cursor-pointer"
            >
              <div className="flex justify-between items-start mb-3">
                <span className="px-2 py-1 bg-stone-100 rounded text-xs text-stone-600">
                  {product.category.name}
                </span>
                {isLow && <span className="px-2 py-1 bg-amber-100 rounded text-xs text-amber-700">Мало!</span>}
              </div>
              <h3 className="font-semibold text-stone-800 mb-2">{product.name}</h3>
              <div className="space-y-1 text-sm text-stone-500">
                {product.barcodes.length > 0 && <p>Штрихкод: {product.barcodes[0].code}</p>}
                {product.defaultExpiryDays && <p>Срок годности: {product.defaultExpiryDays} дн.</p>}
                <p>Место: {product.defaultLocation.name}</p>
                {product.minStock > 0 && <p>Мин. запас: {product.minStock}</p>}
              </div>
              <div className="mt-3 pt-3 border-t border-stone-100 flex justify-between items-center">
                <span className="text-lg font-bold text-stone-800">{totalStock} шт.</span>
                <span className={`text-sm ${product.active ? 'text-green-600' : 'text-stone-400'}`}>
                  {product.active ? 'Активен' : 'Архив'}
                </span>
              </div>
            </div>
          )
        })}
      </div>

      {products.length === 0 && (
        <div className="text-center py-12 text-stone-500">
          Товары не найдены
        </div>
      )}

      {showAddModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
            <h3 className="text-xl font-bold text-stone-800 mb-4">Новый товар</h3>
            <ProductForm
              categories={categories}
              locations={locations}
              onSubmit={handleCreateProduct}
              onCancel={() => setShowAddModal(false)}
              isSubmitting={createProduct.isPending}
              submitError={createProduct.error}
            />
          </div>
        </div>
      )}
    </div>
  )
}
```

**Step 2: Verify the app compiles**

Run: `cd /home/pavel/projects/hestia/frontend && npm run build`

Expected: Build succeeds

**Step 3: Commit**

```bash
cd /home/pavel/projects/hestia && git add frontend/src/features/products/ProductsPage.tsx
git commit -s -m "feat(frontend): integrate ProductsPage with custom query hooks"
```

---

## Task 13: Remove Products from Mock Data

**Files:**
- Modify: `frontend/src/data/mocks.ts`
- Modify: `frontend/src/data/types.ts`
- Modify: `frontend/src/data/context.tsx`
- Modify: `frontend/src/data/hooks.ts`

**Step 1: Remove Product interface from types.ts**

In `frontend/src/data/types.ts`, remove:
- The `Product` interface (lines 8-17)
- Keep `locations` constant (used by stock which is still mocked)
- Remove `categories` constant (now from API)

**Step 2: Remove mockProducts from mocks.ts**

In `frontend/src/data/mocks.ts`:
- Remove `Product` from the import
- Remove the entire `mockProducts` array

**Step 3: Remove ProductsContext from context.tsx**

In `frontend/src/data/context.tsx`:
- Remove `Product` from the type import
- Remove `mockProducts` from the mocks import
- Remove `ProductsContextValue` interface
- Remove `ProductsContext` creation
- Remove `products` state from `DataProvider`
- Remove `ProductsContext.Provider` wrapper

**Step 4: Remove useProducts from hooks.ts**

In `frontend/src/data/hooks.ts`:
- Remove `ProductsContext` from import
- Remove `ProductsContextValue` from import
- Remove the `useProducts` function

**Step 5: Verify app still compiles**

Run: `cd /home/pavel/projects/hestia/frontend && npm run build`

Expected: Build succeeds

**Step 6: Commit**

```bash
cd /home/pavel/projects/hestia && git add frontend/src/data/
git commit -s -m "refactor(frontend): remove products from mock data and context"
```

---

## Task 14: Test Full Integration

**Prerequisites:** Backend must be running with seeded data

**Step 1: Ensure backend is running**

Run: `cd /home/pavel/projects/hestia/backend && docker compose up -d`

**Step 2: Seed database if empty**

Run: `cd /home/pavel/projects/hestia/backend && docker compose exec php bin/console app:seed`

**Step 3: Start frontend dev server**

Run: `cd /home/pavel/projects/hestia/frontend && npm run dev`

**Step 4: Manual testing checklist**

- [ ] Products page loads and shows skeleton while loading (initial load only)
- [ ] Products are displayed from API (not mock data)
- [ ] Category filter works (fetches categories from API)
- [ ] Search filter works
- [ ] Background refetch does NOT show skeleton (no flicker)
- [ ] "Новый товар" button opens modal
- [ ] Create product form shows categories/locations from API
- [ ] Submitting form creates product and shows success toast
- [ ] Validation errors (422) show inline on form fields
- [ ] Network errors (500) show toast notification
- [ ] 404 errors do NOT show global toast
- [ ] Empty state shows when no products match filters

**Step 5: Commit any fixes**

If any adjustments were needed, commit them:
```bash
cd /home/pavel/projects/hestia && git add -A
git commit -s -m "fix(frontend): address integration issues found during testing"
```

---

## Task 15: Final Cleanup and Lint

**Step 1: Run linter**

Run: `cd /home/pavel/projects/hestia/frontend && npm run lint:fix`

**Step 2: Run formatter**

Run: `cd /home/pavel/projects/hestia/frontend && npm run format`

**Step 3: Verify build**

Run: `cd /home/pavel/projects/hestia/frontend && npm run build`

Expected: Clean build with no errors

**Step 4: Commit cleanup**

```bash
cd /home/pavel/projects/hestia && git add frontend/
git commit -s -m "chore(frontend): lint and format product integration code"
```

---

## Summary

After completing all tasks, you will have:

1. **Dependencies installed:** TanStack Query, React Hook Form, react-hot-toast, Orval
2. **API client generated:** Plain fetchers + types from OpenAPI spec
3. **Custom hooks:** `/api/queries/` with standardized query keys
4. **Error handling:**
   - 500/network → toast
   - Mutation failures → toast
   - 422 → form field errors
   - 404 → local handling (no toast)
5. **Loading states:** Skeleton on initial load only (not background refetch)
6. **Products integrated:** ProductsPage uses real API, mocks removed
7. **Other entities unchanged:** Stock, shopping, etc. still use mocks
8. **CSRF ready:** Hook point in apiFetch for future session auth

**Next steps after this plan:**
- Add edit/delete functionality to ProductsPage
- Integrate stock API when ready
- Add optimistic updates for better UX
- Implement session auth with CSRF
