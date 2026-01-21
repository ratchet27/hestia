# Barcode V1 Design

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Enable barcode-first stock entry via manual input. Camera scanning deferred to V2.

**Scope:**
- ScanModal for barcode lookup
- ProductForm barcode management (add/remove)
- Unknown barcode → create product flow

---

## Flow Overview

```
[Scan Button] → ScanModal
                    │
                    ├─ Barcode found → AddStockModal (product pre-selected)
                    │                        │
                    │                        └─ Submit → Close
                    │
                    └─ Barcode not found (404) → ProductForm (barcode pre-filled)
                                                      │
                                                      └─ Submit → ScanModal
```

**"Add" button** remains unchanged — opens AddStockModal with manual product selection.

---

## Task 1: Backend — Add barcodes to product requests

**Files:**
- `backend/src/Request/Product/CreateProductRequest.php`
- `backend/src/Request/Product/UpdateProductRequest.php`
- `backend/src/Service/ProductService.php`

**Step 1: Add barcodes field to CreateProductRequest**

```php
#[Assert\All([
    new Assert\Type('string'),
    new Assert\Length(max: 50),
])]
public ?array $barcodes = null;
```

**Step 2: Add barcodes field to UpdateProductRequest**

Same as above.

**Step 3: Update ProductService::create() to handle barcodes**

After creating product, if `$request->barcodes` is not null, create Barcode entities for each.

**Step 4: Update ProductService::update() to sync barcodes**

Compare incoming array with existing barcodes on product:
- Delete barcodes not in incoming array
- Add new barcodes from incoming array
- Handle duplicate barcode error (409 Conflict) with message "Штрихкод уже привязан к «{product_name}»"

**Step 5: Regenerate frontend API types**

```bash
cd /home/pavel/projects/hestia/frontend && NODE_TLS_REJECT_UNAUTHORIZED=0 bun run generate-api
```

**Step 6: Run backend lint and tests**

```bash
cd /home/pavel/projects/hestia/backend && make lint && make test
```

**Step 7: Commit**

```bash
git add -A && git commit -s -m "feat(backend): accept barcodes array in product create/update requests"
```

---

## Task 2: Frontend — ScanModal component

**Files:**
- `frontend/src/features/stock/components/ScanModal.tsx` (new)
- `frontend/src/features/stock/components/index.ts`

**Step 1: Create ScanModal component**

```tsx
// ScanModal.tsx
interface ScanModalProps {
  onProductFound: (product: ProductResponse) => void;
  onBarcodeNotFound: (barcode: string) => void;
  onClose: () => void;
}
```

UI:
```
┌─────────────────────────────┐
│  Сканировать штрихкод       │
│                             │
│  [____________________]     │
│                             │
│  [Отмена]                   │
└─────────────────────────────┘
```

Behavior:
- Input auto-focused on open
- Enter key triggers lookup
- Calls `getApiBarcodesLookup(code)`
- Loading spinner while fetching
- On success: `onProductFound(response.product)`
- On 404: `onBarcodeNotFound(barcode)`
- On other error: toast error message

**Step 2: Export from index**

Add to `frontend/src/features/stock/components/index.ts`.

**Step 3: Run frontend check**

```bash
cd /home/pavel/projects/hestia/frontend && bun run check
```

**Step 4: Commit**

```bash
git add -A && git commit -s -m "feat(frontend): add ScanModal component for barcode lookup"
```

---

## Task 3: Frontend — AddStockModal preselection

**Files:**
- `frontend/src/features/stock/components/AddStockModal.tsx`

**Step 1: Add preselectedProduct prop**

```tsx
interface AddStockModalProps {
  products: ProductResponse[];
  locations: LocationResponse[];
  preselectedProduct?: ProductResponse;  // NEW
  onSubmit: (data: AddStockFormData) => void;
  onClose: () => void;
  isSubmitting?: boolean;
}
```

**Step 2: Use in defaultValues**

```tsx
useForm<AddStockFormData>({
  defaultValues: {
    productId: preselectedProduct?.id ?? "",
    quantity: 1,
    bestBefore: "",
  },
});
```

**Step 3: Run frontend check**

```bash
cd /home/pavel/projects/hestia/frontend && bun run check
```

**Step 4: Commit**

```bash
git add -A && git commit -s -m "feat(frontend): add preselectedProduct prop to AddStockModal"
```

---

## Task 4: Frontend — ProductForm barcode section

**Files:**
- `frontend/src/features/products/ProductForm.tsx`

**Step 1: Add new props and state**

```tsx
interface ProductFormProps {
  product?: ProductResponse;
  initialBarcode?: string;  // NEW
  categories: Array<{ id: string; name: string }>;
  locations: Array<{ id: string; name: string }>;
  onSubmit: (data: CreateProductRequest) => Promise<void>;
  onCancel: () => void;
  isSubmitting: boolean;
  submitError?: Error | null;
}

// Inside component:
const [barcodesExpanded, setBarcodesExpanded] = useState(!!initialBarcode);
const [barcodes, setBarcodes] = useState<string[]>(
  product?.barcodes?.map(b => b.barcode) ??
  (initialBarcode ? [initialBarcode] : [])
);
const [newBarcode, setNewBarcode] = useState("");
```

**Step 2: Add collapsible barcode section UI**

After "Мин. запас" field, before buttons:

```tsx
{/* Barcodes section */}
<div className="border-t border-stone-200 pt-4">
  <button
    type="button"
    onClick={() => setBarcodesExpanded(!barcodesExpanded)}
    className="flex items-center gap-2 text-sm font-medium text-stone-700"
  >
    <span>{barcodesExpanded ? "▼" : "▶"}</span>
    Штрихкоды ({barcodes.length})
  </button>

  {barcodesExpanded && (
    <div className="mt-3 space-y-2">
      {barcodes.map((code) => (
        <div key={code} className="flex items-center justify-between bg-stone-50 px-3 py-2 rounded-lg">
          <span className="font-mono text-sm">{code}</span>
          <button
            type="button"
            onClick={() => setBarcodes(barcodes.filter(b => b !== code))}
            className="text-stone-400 hover:text-red-500"
          >
            ✕
          </button>
        </div>
      ))}

      <div className="flex gap-2">
        <input
          type="text"
          value={newBarcode}
          onChange={(e) => setNewBarcode(e.target.value)}
          placeholder="Введите штрихкод"
          className="flex-1 px-3 py-2 border border-stone-300 rounded-lg text-sm"
        />
        <button
          type="button"
          onClick={handleAddBarcode}
          className="px-4 py-2 bg-stone-100 rounded-lg hover:bg-stone-200 text-sm"
        >
          Добавить
        </button>
      </div>
    </div>
  )}
</div>
```

**Step 3: Add barcode handlers**

```tsx
const handleAddBarcode = () => {
  const trimmed = newBarcode.trim();
  if (!trimmed) return;
  if (barcodes.includes(trimmed)) {
    toast.error("Уже добавлен");
    return;
  }
  setBarcodes([...barcodes, trimmed]);
  setNewBarcode("");
};
```

**Step 4: Include barcodes in submit**

```tsx
const onFormSubmit = async (values: FormValues): Promise<void> => {
  const data: CreateProductRequest = {
    name: values.name,
    // ... existing fields
    barcodes: barcodes.length > 0 ? barcodes : undefined,
  };
  await onSubmit(data);
};
```

**Step 5: Handle backend duplicate error**

Map 409 Conflict to user-friendly message in error handling.

**Step 6: Run frontend check**

```bash
cd /home/pavel/projects/hestia/frontend && bun run check
```

**Step 7: Commit**

```bash
git add -A && git commit -s -m "feat(frontend): add collapsible barcode section to ProductForm"
```

---

## Task 5: Frontend — StockPage orchestration

**Files:**
- `frontend/src/features/stock/StockPage.tsx`

**Step 1: Add modal state**

```tsx
const [modalState, setModalState] = useState<
  | { type: 'none' }
  | { type: 'scan' }
  | { type: 'add'; preselectedProduct?: ProductResponse }
  | { type: 'createProduct'; barcode: string }
>({ type: 'none' });
```

**Step 2: Update button handlers**

```tsx
// "Add" button
onClick={() => setModalState({ type: 'add' })}

// "Scan" button
onClick={() => setModalState({ type: 'scan' })}
```

**Step 3: Render modals based on state**

```tsx
{modalState.type === 'scan' && (
  <ScanModal
    onProductFound={(product) => setModalState({ type: 'add', preselectedProduct: product })}
    onBarcodeNotFound={(barcode) => setModalState({ type: 'createProduct', barcode })}
    onClose={() => setModalState({ type: 'none' })}
  />
)}

{modalState.type === 'add' && (
  <AddStockModal
    products={products}
    locations={locations}
    preselectedProduct={modalState.preselectedProduct}
    onSubmit={handleAddStock}
    onClose={() => setModalState({ type: 'none' })}
    isSubmitting={isAddingStock}
  />
)}

{modalState.type === 'createProduct' && (
  <CreateProductModal
    initialBarcode={modalState.barcode}
    onSuccess={() => setModalState({ type: 'scan' })}
    onCancel={() => setModalState({ type: 'none' })}
  />
)}
```

**Step 4: Create or adapt CreateProductModal**

If ProductForm is currently used inline on products page, create a modal wrapper that:
- Fetches categories and locations
- Renders ProductForm with `initialBarcode` prop
- Handles create mutation
- Calls `onSuccess` or `onCancel`

**Step 5: Run frontend check and tests**

```bash
cd /home/pavel/projects/hestia/frontend && bun run check && bun run test:run
```

**Step 6: Commit**

```bash
git add -A && git commit -s -m "feat(frontend): wire up barcode scan flow in StockPage"
```

---

## Task 6: Translations

**Files:**
- `frontend/src/i18n/locales/ru.json`
- `frontend/src/i18n/locales/en.json`

**Step 1: Add translation keys**

```json
{
  "scan": {
    "title": "Сканировать штрихкод",
    "placeholder": "Введите штрихкод",
    "notFound": "Штрихкод не найден",
    "createProduct": "Создать товар?"
  },
  "barcodes": {
    "title": "Штрихкоды",
    "add": "Добавить",
    "placeholder": "Введите штрихкод",
    "duplicate": "Уже добавлен",
    "belongsTo": "Штрихкод уже привязан к «{{product}}»"
  }
}
```

**Step 2: Apply translations to components**

Replace hardcoded Russian strings with `t()` calls.

**Step 3: Run frontend check**

```bash
cd /home/pavel/projects/hestia/frontend && bun run check
```

**Step 4: Commit**

```bash
git add -A && git commit -s -m "feat(frontend): add barcode translations"
```

---

## Task 7: Final verification

**Step 1: Run all backend tests**

```bash
cd /home/pavel/projects/hestia/backend && make lint && make test
```

**Step 2: Run all frontend tests**

```bash
cd /home/pavel/projects/hestia/frontend && bun run check && bun run test:run
```

**Step 3: Manual test**

1. Click "Scan" → enter existing barcode → verify AddStockModal opens with product selected
2. Click "Scan" → enter unknown barcode → verify ProductForm opens with barcode pre-filled
3. Create product → verify returns to ScanModal
4. Edit existing product → verify can add/remove barcodes
5. Try adding duplicate barcode → verify error message

---

## Deferred to V2

| Item | Reason |
|------|--------|
| Camera scanner | Manual input sufficient for V1 |
| Continuous scanning mode | Can add later |
| `last_price`, `shopping_location`, `note` | Price analytics is V2 per spec |
| Open Food Facts integration | Kazakhstan reality = local DB anyway |
