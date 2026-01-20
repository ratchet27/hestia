# i18n Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add internationalization support with Russian (primary) and English (secondary) languages using translation keys.

**Architecture:** Use react-i18next for React integration. Store translations in JSON files organized by namespace. Components use `useTranslation` hook with `t('key')` function calls instead of hardcoded strings.

**Tech Stack:** react-i18next, i18next, i18next-browser-languagedetector

---

## Task 1: Install i18n Dependencies

**Files:**
- Modify: `package.json`

**Step 1: Install packages**

```bash
bun add i18next react-i18next i18next-browser-languagedetector
```

**Step 2: Verify installation**

```bash
grep -E "i18next|react-i18next" package.json
```

Expected: Shows all three packages in dependencies

**Step 3: Commit**

```bash
git add package.json bun.lock
git commit -m "chore: add i18n dependencies"
```

---

## Task 2: Create i18n Configuration

**Files:**
- Create: `src/i18n/index.ts`
- Create: `src/i18n/locales/ru.json`
- Create: `src/i18n/locales/en.json`

**Step 1: Create i18n config**

```typescript
// src/i18n/index.ts
import i18n from "i18next";
import { initReactI18next } from "react-i18next";
import LanguageDetector from "i18next-browser-languagedetector";

import en from "./locales/en.json";
import ru from "./locales/ru.json";

i18n
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    resources: {
      ru: { translation: ru },
      en: { translation: en },
    },
    fallbackLng: "ru",
    interpolation: {
      escapeValue: false, // React already escapes
    },
    detection: {
      order: ["localStorage", "navigator"],
      caches: ["localStorage"],
    },
  });

export default i18n;
```

**Step 2: Create Russian translations (primary)**

```json
// src/i18n/locales/ru.json
{
  "common": {
    "loading": "Загрузка...",
    "cancel": "Отмена",
    "add": "Добавить",
    "adding": "Добавление...",
    "done": "Готово",
    "delete": "Удалить",
    "save": "Сохранить",
    "edit": "Редактировать",
    "search": "Поиск",
    "all": "Все",
    "noItems": "Нет записей",
    "logout": "Выйти"
  },
  "nav": {
    "home": "Главная",
    "stock": "Запасы",
    "products": "Товары",
    "shopping": "Покупки",
    "recipes": "Рецепты",
    "tasks": "Задачи",
    "settings": "Настройки",
    "tagline": "Домашний учёт"
  },
  "dashboard": {
    "welcome": "Добро пожаловать!",
    "subtitle": "Обзор домашнего хозяйства на сегодня",
    "stockItems": "Позиций в запасах",
    "shoppingItems": "В списке покупок",
    "expiringItems": "Истекает/Истекло",
    "todayTasks": "Дел на сегодня",
    "expiringProducts": "Истекающие продукты",
    "viewAllStock": "Все запасы →",
    "allGood": "Всё в порядке!",
    "lowStock": "Низкий запас",
    "viewAllProducts": "Все товары →",
    "enoughStock": "Всего достаточно!",
    "min": "Мин: ",
    "remaining": "Осталось: ",
    "todayTasksSection": "Дела на сегодня",
    "viewAllTasks": "Все задачи →",
    "noTasksToday": "На сегодня дел нет!",
    "completed": "Выполнено",
    "upcomingTasks": "Ближайшие задачи",
    "noActiveTasks": "Нет активных задач"
  },
  "stock": {
    "title": "Запасы",
    "allStock": "📦 Все запасы",
    "searchPlaceholder": "Поиск по названию...",
    "allOk": "Все в порядке",
    "expiredCount_one": "Есть {{count}} просроченный",
    "expiredCount_few": "Есть {{count}} просроченных",
    "expiredCount_many": "Есть {{count}} просроченных",
    "soonExpiring_one": "{{count}} скоро истекает",
    "soonExpiring_few": "{{count}} скоро истекают",
    "soonExpiring_many": "{{count}} скоро истекают",
    "scan": "Сканировать",
    "throw": "Выбросить",
    "consumed": "Использовано",
    "product": "Товар",
    "quantity": "Количество",
    "bestBefore": "Годен до",
    "allUnderControl": "Все под контролем",
    "nextExpiry": "Следующее истекает через {{days}} дней",
    "noAttentionNeeded": "Нет продуктов, требующих внимания",
    "needToHandleToday": "🕐 Нужно разобраться сегодня",
    "collapse": "Свернуть",
    "showAll": "Показать все ({{count}})"
  },
  "addStock": {
    "title": "Добавить в запасы",
    "product": "Товар",
    "selectProduct": "Выберите товар...",
    "productRequired": "Выберите товар",
    "location": "Место хранения",
    "selectLocation": "Выберите место...",
    "locationRequired": "Выберите место",
    "quantity": "Количество",
    "quantityRequired": "Укажите количество",
    "quantityMin": "Минимум 1",
    "bestBefore": "Годен до"
  },
  "recipes": {
    "title": "Рецепты",
    "subtitle": "Проверка наличия ингредиентов",
    "newRecipe": "Новый рецепт",
    "canCook": "Можно готовить",
    "missingIngredients": "Не хватает {{count}} ингр.",
    "ingredients": "Ингредиенты:",
    "cook": "Приготовить",
    "addToShoppingList": "В список покупок"
  },
  "expiry": {
    "expired": "просрочено",
    "today": "сегодня",
    "tomorrow": "завтра",
    "yesterday": "вчера",
    "daysAgo": "{{count}} дн. назад",
    "inDays": "через {{count}} дн."
  }
}
```

**Step 3: Create English translations**

```json
// src/i18n/locales/en.json
{
  "common": {
    "loading": "Loading...",
    "cancel": "Cancel",
    "add": "Add",
    "adding": "Adding...",
    "done": "Done",
    "delete": "Delete",
    "save": "Save",
    "edit": "Edit",
    "search": "Search",
    "all": "All",
    "noItems": "No items",
    "logout": "Logout"
  },
  "nav": {
    "home": "Home",
    "stock": "Stock",
    "products": "Products",
    "shopping": "Shopping",
    "recipes": "Recipes",
    "tasks": "Tasks",
    "settings": "Settings",
    "tagline": "Home Management"
  },
  "dashboard": {
    "welcome": "Welcome!",
    "subtitle": "Your household overview for today",
    "stockItems": "Items in stock",
    "shoppingItems": "Shopping list items",
    "expiringItems": "Expiring/Expired",
    "todayTasks": "Tasks for today",
    "expiringProducts": "Expiring products",
    "viewAllStock": "All stock →",
    "allGood": "All good!",
    "lowStock": "Low stock",
    "viewAllProducts": "All products →",
    "enoughStock": "Stock is sufficient!",
    "min": "Min: ",
    "remaining": "Left: ",
    "todayTasksSection": "Today's tasks",
    "viewAllTasks": "All tasks →",
    "noTasksToday": "No tasks for today!",
    "completed": "Completed",
    "upcomingTasks": "Upcoming tasks",
    "noActiveTasks": "No active tasks"
  },
  "stock": {
    "title": "Stock",
    "allStock": "📦 All stock",
    "searchPlaceholder": "Search by name...",
    "allOk": "All good",
    "expiredCount_one": "{{count}} expired item",
    "expiredCount_other": "{{count}} expired items",
    "soonExpiring_one": "{{count}} expiring soon",
    "soonExpiring_other": "{{count}} expiring soon",
    "scan": "Scan",
    "throw": "Throw away",
    "consumed": "Consumed",
    "product": "Product",
    "quantity": "Quantity",
    "bestBefore": "Best before",
    "allUnderControl": "All under control",
    "nextExpiry": "Next expiry in {{days}} days",
    "noAttentionNeeded": "No products need attention",
    "needToHandleToday": "🕐 Need to handle today",
    "collapse": "Collapse",
    "showAll": "Show all ({{count}})"
  },
  "addStock": {
    "title": "Add to stock",
    "product": "Product",
    "selectProduct": "Select product...",
    "productRequired": "Select a product",
    "location": "Storage location",
    "selectLocation": "Select location...",
    "locationRequired": "Select a location",
    "quantity": "Quantity",
    "quantityRequired": "Enter quantity",
    "quantityMin": "Minimum 1",
    "bestBefore": "Best before"
  },
  "recipes": {
    "title": "Recipes",
    "subtitle": "Check ingredient availability",
    "newRecipe": "New recipe",
    "canCook": "Ready to cook",
    "missingIngredients": "Missing {{count}} ingr.",
    "ingredients": "Ingredients:",
    "cook": "Cook",
    "addToShoppingList": "Add to shopping list"
  },
  "expiry": {
    "expired": "expired",
    "today": "today",
    "tomorrow": "tomorrow",
    "yesterday": "yesterday",
    "daysAgo": "{{count}} days ago",
    "inDays": "in {{count}} days"
  }
}
```

**Step 4: Commit**

```bash
git add src/i18n/
git commit -m "feat(i18n): add i18n configuration and translation files"
```

---

## Task 3: Initialize i18n in App Entry Point

**Files:**
- Modify: `src/main.tsx`

**Step 1: Import i18n before App**

Add at the top of `src/main.tsx`:

```typescript
import "./i18n";
```

This must be imported before any component that uses translations.

**Step 2: Verify app still loads**

```bash
bun run dev
```

Open browser, verify no console errors.

**Step 3: Commit**

```bash
git add src/main.tsx
git commit -m "feat(i18n): initialize i18n in app entry point"
```

---

## Task 4: Update Navigation Component

**Files:**
- Modify: `src/components/Navigation.tsx`

**Step 1: Add useTranslation hook**

```typescript
import { useTranslation } from "react-i18next";

// Inside component:
const { t } = useTranslation();
```

**Step 2: Replace hardcoded strings**

Replace navigation items:
- `"Главная"` → `{t("nav.home")}`
- `"Запасы"` → `{t("nav.stock")}`
- `"Товары"` → `{t("nav.products")}`
- `"Покупки"` → `{t("nav.shopping")}`
- `"Рецепты"` → `{t("nav.recipes")}`
- `"Задачи"` → `{t("nav.tasks")}`
- `"Настройки"` → `{t("nav.settings")}`
- `"Домашний учёт"` → `{t("nav.tagline")}`

**Step 3: Verify navigation renders correctly**

```bash
bun run dev
```

Check navigation shows Russian text (default language).

**Step 4: Commit**

```bash
git add src/components/Navigation.tsx
git commit -m "feat(i18n): translate Navigation component"
```

---

## Task 5: Update Stock Feature Components

**Files:**
- Modify: `src/features/stock/StockPage.tsx`
- Modify: `src/features/stock/components/StockPageHeader.tsx`
- Modify: `src/features/stock/components/LocationTabs.tsx`
- Modify: `src/features/stock/components/StockTable.tsx`
- Modify: `src/features/stock/components/StockRow.tsx`
- Modify: `src/features/stock/components/AttentionSection.tsx`
- Modify: `src/features/stock/components/AttentionCard.tsx`
- Modify: `src/features/stock/components/AddStockModal.tsx`

**Step 1: Update StockPage.tsx**

Add hook and replace:
- `"📦 Все запасы"` → `{t("stock.allStock")}`
- `"Поиск по названию..."` → `{t("stock.searchPlaceholder")}`

**Step 2: Update StockPageHeader.tsx**

Replace:
- `"Запасы"` → `{t("stock.title")}`
- `"Все в порядке"` → `{t("stock.allOk")}`
- Dynamic expired/soon messages using `t("stock.expiredCount", { count })` and `t("stock.soonExpiring", { count })`
- `"Сканировать"` → `{t("stock.scan")}`
- `"Добавить"` → `{t("common.add")}`

**Step 3: Update LocationTabs.tsx**

Replace:
- `"Все"` → `{t("common.all")}`

**Step 4: Update StockTable.tsx**

Replace:
- `"Загрузка..."` → `{t("common.loading")}`
- `"Нет записей"` → `{t("common.noItems")}`
- `"Товар"` → `{t("stock.product")}`
- `"Количество"` → `{t("stock.quantity")}`
- `"Годен до"` → `{t("stock.bestBefore")}`

**Step 5: Update StockRow.tsx**

Replace:
- `"Использовано"` (title attr) → `{t("stock.consumed")}`

**Step 6: Update AttentionSection.tsx**

Replace:
- `"Все под контролем"` → `{t("stock.allUnderControl")}`
- `"Нет продуктов, требующих внимания"` → `{t("stock.noAttentionNeeded")}`
- Dynamic expiry message using `t("stock.nextExpiry", { days })`
- `"🕐 Нужно разобраться сегодня"` → `{t("stock.needToHandleToday")}`
- `"Свернуть"` → `{t("stock.collapse")}`
- `"Показать все (${count})"` → `{t("stock.showAll", { count })}`

**Step 7: Update AttentionCard.tsx**

Replace:
- `"Готово"` → `{t("common.done")}`
- `"Выбросить"` → `{t("stock.throw")}`

**Step 8: Update AddStockModal.tsx**

Replace all form labels, placeholders, validation messages, and buttons with corresponding `t()` calls from `addStock.*` and `common.*` namespaces.

**Step 9: Verify stock page works**

```bash
bun run dev
```

Navigate to Stock page, verify all text displays correctly.

**Step 10: Commit**

```bash
git add src/features/stock/
git commit -m "feat(i18n): translate Stock feature components"
```

---

## Task 6: Update expiryStatus Utility

**Files:**
- Modify: `src/features/stock/utils/expiryStatus.ts`

**Step 1: Update getRelativeExpiryText to accept t function**

```typescript
import type { TFunction } from "i18next";

export function getRelativeExpiryText(
  daysUntilExpiry: number,
  t: TFunction,
): string {
  if (daysUntilExpiry < -1)
    return t("expiry.daysAgo", { count: Math.abs(daysUntilExpiry) });
  if (daysUntilExpiry === -1) return t("expiry.yesterday");
  if (daysUntilExpiry === 0) return t("expiry.today");
  if (daysUntilExpiry === 1) return t("expiry.tomorrow");
  return t("expiry.inDays", { count: daysUntilExpiry });
}
```

**Step 2: Update all call sites**

In components using `getRelativeExpiryText`, pass the `t` function:

```typescript
const relativeText = getRelativeExpiryText(entry.days_until_expiry, t);
```

**Step 3: Update unit tests**

Create a mock `t` function for tests:

```typescript
// In expiryStatus.test.ts
const mockT = (key: string, options?: { count?: number }) => {
  const translations: Record<string, string> = {
    "expiry.yesterday": "вчера",
    "expiry.today": "сегодня",
    "expiry.tomorrow": "завтра",
  };
  if (key === "expiry.daysAgo") return `${options?.count} дн. назад`;
  if (key === "expiry.inDays") return `через ${options?.count} дн.`;
  return translations[key] || key;
};

// Update tests to pass mockT
expect(getRelativeExpiryText(-5, mockT)).toBe("5 дн. назад");
```

**Step 4: Run tests**

```bash
bun run test:run
```

All tests should pass.

**Step 5: Commit**

```bash
git add src/features/stock/utils/ src/features/stock/components/
git commit -m "feat(i18n): update expiryStatus utility to use translations"
```

---

## Task 7: Update Dashboard and Recipes Pages

**Files:**
- Modify: `src/features/dashboard/DashboardPage.tsx`
- Modify: `src/features/recipes/RecipesPage.tsx`

**Step 1: Update DashboardPage.tsx**

Add `useTranslation` hook and replace all ~20 hardcoded strings with corresponding `t("dashboard.*")` calls.

**Step 2: Update RecipesPage.tsx**

Add `useTranslation` hook and replace all ~10 hardcoded strings with corresponding `t("recipes.*")` calls.

**Step 3: Verify pages render correctly**

```bash
bun run dev
```

**Step 4: Commit**

```bash
git add src/features/dashboard/ src/features/recipes/
git commit -m "feat(i18n): translate Dashboard and Recipes pages"
```

---

## Task 8: Update UserProfile Component

**Files:**
- Modify: `src/components/UserProfile.tsx`

**Step 1: Add useTranslation and replace**

- `"Выйти"` → `{t("common.logout")}`

**Step 2: Commit**

```bash
git add src/components/UserProfile.tsx
git commit -m "feat(i18n): translate UserProfile component"
```

---

## Task 9: Update Test Utilities for i18n

**Files:**
- Modify: `src/test/utils.tsx`

**Step 1: Add I18nextProvider to test wrapper**

```typescript
import { I18nextProvider } from "react-i18next";
import i18n from "@/i18n";

function AllProviders({ children }: WrapperProps) {
  const queryClient = createTestQueryClient();

  return (
    <I18nextProvider i18n={i18n}>
      <QueryClientProvider client={queryClient}>
        <BrowserRouter>{children}</BrowserRouter>
      </QueryClientProvider>
    </I18nextProvider>
  );
}
```

**Step 2: Run all tests**

```bash
bun run test:run
```

All 30 tests should still pass (they test Russian UI text which is the default).

**Step 3: Commit**

```bash
git add src/test/utils.tsx
git commit -m "test: add I18nextProvider to test utilities"
```

---

## Task 10: Add Language Switcher (Optional Enhancement)

**Files:**
- Create: `src/components/LanguageSwitcher.tsx`
- Modify: `src/components/Navigation.tsx` (to include switcher)

**Step 1: Create LanguageSwitcher component**

```typescript
// src/components/LanguageSwitcher.tsx
import { useTranslation } from "react-i18next";

export function LanguageSwitcher() {
  const { i18n } = useTranslation();

  return (
    <select
      value={i18n.language}
      onChange={(e) => i18n.changeLanguage(e.target.value)}
      className="text-sm bg-transparent border border-stone-300 rounded px-2 py-1"
    >
      <option value="ru">RU</option>
      <option value="en">EN</option>
    </select>
  );
}
```

**Step 2: Add to Navigation**

Import and render `<LanguageSwitcher />` in the navigation sidebar.

**Step 3: Test language switching**

```bash
bun run dev
```

Switch language, verify all text changes.

**Step 4: Commit**

```bash
git add src/components/LanguageSwitcher.tsx src/components/Navigation.tsx
git commit -m "feat(i18n): add language switcher component"
```

---

## Task 11: Final Verification

**Step 1: Run full test suite**

```bash
bun run test:run
```

Expected: All tests pass

**Step 2: Run type check and lint**

```bash
bun run check
```

Expected: No errors

**Step 3: Manual verification**

- Start dev server: `bun run dev`
- Verify Russian (default) renders correctly
- Switch to English, verify all text changes
- Verify localStorage persists language choice

**Step 4: Final commit**

```bash
git add -A
git commit -m "feat(i18n): complete internationalization implementation"
```

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1 | Install dependencies | package.json |
| 2 | Create i18n config + translations | src/i18n/* |
| 3 | Initialize in main.tsx | src/main.tsx |
| 4 | Translate Navigation | src/components/Navigation.tsx |
| 5 | Translate Stock feature | src/features/stock/**/* |
| 6 | Update expiryStatus utility | src/features/stock/utils/* |
| 7 | Translate Dashboard + Recipes | src/features/dashboard/*, recipes/* |
| 8 | Translate UserProfile | src/components/UserProfile.tsx |
| 9 | Update test utilities | src/test/utils.tsx |
| 10 | Add language switcher | src/components/LanguageSwitcher.tsx |
| 11 | Final verification | - |

**Total strings:** ~60+ user-facing strings across ~15 files
**Languages:** Russian (primary), English (secondary)
