# Frontend Testing Design

## Goals

- Confidence in refactoring (catch regressions)
- Documentation (tests as living docs)
- Bug prevention (catch logic errors before production)

## Test Pyramid

```
                    ┌─────────┐
                    │  E2E    │  ← Skip for now
                   ─┴─────────┴─
                 ┌───────────────┐
                 │  Integration  │  ← Page components with MSW
                ─┴───────────────┴─
              ┌───────────────────────┐
              │    Component Tests    │  ← Individual components with MSW
             ─┴───────────────────────┴─
          ┌───────────────────────────────┐
          │         Unit Tests            │  ← Pure functions (expiryStatus)
         ─┴───────────────────────────────┴─
```

**Ratios:**
- Unit tests: ~20% - Pure utilities only
- Component tests: ~50% - Individual components in isolation
- Integration tests: ~30% - Full pages with real data flow

## API Mocking Strategy

**MSW (Mock Service Worker)** at network level because:
- React Query hooks are thin wrappers - mocking them skips too much real code
- Catches API contract mismatches
- Tests remain valid when hook internals change
- Industry standard for React Query testing

## Test Scope

### Unit Tests (pure functions)

```
src/features/stock/utils/expiryStatus.ts
├── getExpiryStatus()       → all 5 status boundaries
├── getRelativeExpiryText() → Russian text for each case
└── formatExpiryDate()      → date formatting
```

### Component Tests (isolated, with MSW)

```
src/features/stock/components/
├── AttentionCard      → renders product info, calls onDone/onThrow
├── AttentionSection   → shows cards, handles empty state
├── LocationTabs       → tab selection, counts display
├── StockTable         → rows render, loading state, empty state
├── StockRow           → expiry styling, consume button
├── AddStockModal      → form validation, submit flow
└── StockPageHeader    → badge counts, button clicks
```

### Integration Tests (full page, real hooks + MSW)

```
src/features/stock/StockPage.tsx
├── loads and displays stock entries
├── filters by location tab
├── filters by search term
├── shows expiring items in attention section
├── add stock flow (open modal → submit → list updates)
└── error states when API fails
```

### NOT Testing

- Generated API code (`src/api/generated/`) - Orval's responsibility
- `Layout`, `Navigation` - low value, high churn
- Styling/CSS classes - brittle, visual testing is better

## File Structure

```
src/
├── test/
│   ├── setup.ts           # existing - global setup
│   ├── utils.tsx          # existing - custom render
│   ├── types.d.ts         # existing - type definitions
│   └── mocks/
│       ├── handlers.ts    # MSW request handlers
│       ├── server.ts      # MSW server setup
│       └── data.ts        # Factory functions for test data
│
├── features/stock/
│   ├── utils/
│   │   ├── expiryStatus.ts
│   │   └── expiryStatus.test.ts
│   ├── components/
│   │   ├── AttentionCard.tsx
│   │   ├── AttentionCard.test.tsx
│   │   └── ...
│   ├── StockPage.tsx
│   └── StockPage.test.tsx
```

## Technical Implementation

### MSW Handlers

```typescript
// src/test/mocks/handlers.ts
import { http, HttpResponse } from 'msw'
import { createStockEntry, wrapResponse } from './data'

// Default success handlers
export const handlers = [
  http.get('*/api/internal/v1/stocks/entries', () =>
    HttpResponse.json(wrapResponse([createStockEntry()]))
  ),
  http.get('*/api/internal/v1/stocks/expiring', () =>
    HttpResponse.json(wrapResponse([]))
  ),
  http.get('*/api/internal/v1/locations', () =>
    HttpResponse.json(wrapResponse([]))
  ),
  http.get('*/api/internal/v1/products', () =>
    HttpResponse.json(wrapResponse([]))
  ),
]

// Error handlers for testing failure states
export const errorHandlers = {
  stockEntriesFailed: http.get('*/api/internal/v1/stocks/entries', () =>
    HttpResponse.json({ message: 'Server error' }, { status: 500 })
  ),
  stockEntriesUnauthorized: http.get('*/api/internal/v1/stocks/entries', () =>
    HttpResponse.json({ message: 'Unauthorized' }, { status: 401 })
  ),
  addStockValidationError: http.post('*/api/internal/v1/stocks/add', () =>
    HttpResponse.json({
      message: 'Validation failed',
      violations: [{ propertyPath: 'quantity', message: 'Must be positive' }]
    }, { status: 422 })
  ),
}
```

### MSW Server Setup

```typescript
// src/test/mocks/server.ts
import { setupServer } from 'msw/node'
import { handlers } from './handlers'

export const server = setupServer(...handlers)
```

### Test Setup Integration

```typescript
// src/test/setup.ts (updated)
import "@testing-library/jest-dom/vitest";
import { cleanup } from "@testing-library/react";
import { afterAll, afterEach, beforeAll } from "vitest";
import { server } from "./mocks/server";

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => {
  cleanup()
  server.resetHandlers()
})
afterAll(() => server.close())
```

### Factory Functions (Typed)

```typescript
// src/test/mocks/data.ts
import type {
  StockEntryResponse,
  ExpiringEntryResponse,
  LocationResponse,
  ProductBriefResponse
} from "@/api/generated/models";

export function createStockEntry(
  overrides: Partial<StockEntryResponse> = {}
): StockEntryResponse {
  const { product, location, ...rest } = overrides
  return {
    id: crypto.randomUUID(),
    product: { id: crypto.randomUUID(), name: "Молоко", unit: "шт", ...product },
    location: { id: crypto.randomUUID(), name: "Холодильник", ...location },
    quantity: 1,
    best_before: "2025-01-25",
    created_at: new Date().toISOString(),
    ...rest,
  }
}

export function createExpiringEntry(
  overrides: Partial<ExpiringEntryResponse> = {}
): ExpiringEntryResponse {
  const { product, location, ...rest } = overrides
  return {
    id: crypto.randomUUID(),
    product: { id: crypto.randomUUID(), name: "Молоко", unit: "шт", ...product },
    location: { id: crypto.randomUUID(), name: "Холодильник", ...location },
    best_before: "2025-01-25",
    days_until_expiry: 2,
    ...rest,
  }
}

export function createLocation(
  overrides: Partial<LocationResponse> = {}
): LocationResponse {
  return {
    id: crypto.randomUUID(),
    name: "Холодильник",
    ...overrides,
  }
}

export function wrapResponse<T>(data: T, meta: Record<string, unknown> = {}) {
  return { data, meta }
}
```

## Test Examples

### Unit Test

```typescript
// src/features/stock/utils/expiryStatus.test.ts
import { describe, it, expect } from "vitest";
import { getExpiryStatus, getRelativeExpiryText } from "./expiryStatus";

describe("getExpiryStatus", () => {
  it('returns "expired" for negative days', () => {
    expect(getExpiryStatus(-1)).toBe("expired")
    expect(getExpiryStatus(-30)).toBe("expired")
  })

  it('returns "today" for zero days', () => {
    expect(getExpiryStatus(0)).toBe("today")
  })

  it('returns "soon" for 1-2 days', () => {
    expect(getExpiryStatus(1)).toBe("soon")
    expect(getExpiryStatus(2)).toBe("soon")
  })

  it('returns "warning" for 3-7 days', () => {
    expect(getExpiryStatus(3)).toBe("warning")
    expect(getExpiryStatus(7)).toBe("warning")
  })

  it('returns "ok" for more than 7 days', () => {
    expect(getExpiryStatus(8)).toBe("ok")
    expect(getExpiryStatus(100)).toBe("ok")
  })
})

describe("getRelativeExpiryText", () => {
  it('returns Russian text for expired items', () => {
    expect(getRelativeExpiryText(-5)).toBe("5 дн. назад")
    expect(getRelativeExpiryText(-1)).toBe("вчера")
  })

  it('returns "сегодня" for today', () => {
    expect(getRelativeExpiryText(0)).toBe("сегодня")
  })

  it('returns "завтра" for tomorrow', () => {
    expect(getRelativeExpiryText(1)).toBe("завтра")
  })

  it('returns days remaining for future dates', () => {
    expect(getRelativeExpiryText(5)).toBe("через 5 дн.")
  })
})
```

### Component Test

```typescript
// src/features/stock/components/AttentionCard.test.tsx
import { describe, it, expect, vi } from "vitest";
import { render, screen, userEvent } from "@/test/utils";
import { AttentionCard } from "./AttentionCard";
import { createExpiringEntry } from "@/test/mocks/data";

describe("AttentionCard", () => {
  it("displays product name and location", () => {
    const entry = createExpiringEntry({
      product: { name: "Молоко" },
      location: { name: "Холодильник" }
    })

    render(<AttentionCard entry={entry} onDone={vi.fn()} onThrow={vi.fn()} />)

    expect(screen.getByText("Молоко")).toBeInTheDocument()
    expect(screen.getByText("Холодильник")).toBeInTheDocument()
  })

  it('calls onDone when "Готово" clicked', async () => {
    const onDone = vi.fn()
    const entry = createExpiringEntry()
    const user = userEvent.setup()

    render(<AttentionCard entry={entry} onDone={onDone} onThrow={vi.fn()} />)
    await user.click(screen.getByRole("button", { name: "Готово" }))

    expect(onDone).toHaveBeenCalledWith(entry)
  })

  it('calls onThrow when "Выбросить" clicked', async () => {
    const onThrow = vi.fn()
    const entry = createExpiringEntry()
    const user = userEvent.setup()

    render(<AttentionCard entry={entry} onDone={vi.fn()} onThrow={onThrow} />)
    await user.click(screen.getByRole("button", { name: "Выбросить" }))

    expect(onThrow).toHaveBeenCalledWith(entry)
  })
})
```

### Integration Test

```typescript
// src/features/stock/StockPage.test.tsx
import { describe, it, expect } from "vitest";
import { http, HttpResponse } from "msw";
import { render, screen } from "@/test/utils";
import { server } from "@/test/mocks/server";
import { StockPage } from "./StockPage";
import { createStockEntry, createLocation, wrapResponse } from "@/test/mocks/data";
import { errorHandlers } from "@/test/mocks/handlers";

describe("StockPage", () => {
  it("loads and displays stock entries", async () => {
    server.use(
      http.get("*/api/internal/v1/stocks/entries", () =>
        HttpResponse.json(wrapResponse([
          createStockEntry({ product: { name: "Молоко" } }),
          createStockEntry({ product: { name: "Хлеб" } }),
        ]))
      )
    )

    render(<StockPage />)

    expect(await screen.findByText("Молоко")).toBeInTheDocument()
    expect(screen.getByText("Хлеб")).toBeInTheDocument()
  })

  it("filters by location when tab clicked", async () => {
    const fridgeId = crypto.randomUUID()
    const pantryId = crypto.randomUUID()

    server.use(
      http.get("*/api/internal/v1/stocks/entries", () =>
        HttpResponse.json(wrapResponse([
          createStockEntry({
            product: { name: "Молоко" },
            location: { id: fridgeId, name: "Холодильник" }
          }),
          createStockEntry({
            product: { name: "Крупа" },
            location: { id: pantryId, name: "Кладовка" }
          }),
        ]))
      ),
      http.get("*/api/internal/v1/locations", () =>
        HttpResponse.json(wrapResponse([
          createLocation({ id: fridgeId, name: "Холодильник" }),
          createLocation({ id: pantryId, name: "Кладовка" }),
        ]))
      )
    )

    const user = userEvent.setup()
    render(<StockPage />)

    // Wait for data to load
    await screen.findByText("Молоко")

    // Click location tab
    await user.click(screen.getByRole("button", { name: /Холодильник/ }))

    expect(screen.getByText("Молоко")).toBeInTheDocument()
    expect(screen.queryByText("Крупа")).not.toBeInTheDocument()
  })

  it("shows error state when API fails", async () => {
    server.use(errorHandlers.stockEntriesFailed)

    render(<StockPage />)

    // Note: This requires error handling UI in StockPage
    expect(await screen.findByText(/error/i)).toBeInTheDocument()
  })
})
```

## Implementation Order

1. Install MSW: `bun add -D msw`
2. Create MSW setup (`src/test/mocks/server.ts`, `handlers.ts`, `data.ts`)
3. Update `src/test/setup.ts` to integrate MSW
4. Unit tests for `expiryStatus.ts` (no MSW needed)
5. Component tests (simplest first: `LocationTabs`, `AttentionCard`)
6. Integration test for `StockPage`

## Test Count Estimate

| Layer | Files | Tests |
|-------|-------|-------|
| Unit (expiryStatus) | 1 | ~15 |
| Component (Stock) | 7 | ~25 |
| Integration (StockPage) | 1 | ~8 |
| **Total** | 9 | ~48 |

## Dependencies

```bash
bun add -D msw
```
