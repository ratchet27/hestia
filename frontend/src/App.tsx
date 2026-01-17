import { useState } from 'react'

// ============================================
// TYPES
// ============================================

interface Product {
  id: number
  name: string
  category: string
  defaultExpiryDays: number
  minStock: number
  defaultLocation: string
  barcode: string
  active: boolean
}

interface StockEntry {
  id: number
  productId: number
  amount: number
  bestBefore: string
  purchasedDate: string
  location: string
  note: string
}

interface ShoppingItem {
  id: number
  productId: number | null
  customName?: string
  amount: number
  note: string
  addedBy: 'auto' | 'manual'
  done: boolean
}

interface Chore {
  id: number
  name: string
  frequency: 'daily' | 'weekly' | 'monthly'
  lastDone: string
  nextDue: string
  assignee: string | null
}

interface Task {
  id: number
  name: string
  dueDate: string | null
  done: boolean
}

interface Recipe {
  id: number
  name: string
  ingredients: { productId: number; amount: number }[]
}

type PageId = 'dashboard' | 'stock' | 'products' | 'shopping' | 'recipes' | 'tasks' | 'settings'

// ============================================
// MOCK DATA
// ============================================

const mockProducts: Product[] = [
  {
    id: 1,
    name: 'Молоко 3.2%',
    category: 'Молочные',
    defaultExpiryDays: 7,
    minStock: 2,
    defaultLocation: 'fridge',
    barcode: '4870204301234',
    active: true,
  },
  {
    id: 2,
    name: 'Хлеб белый',
    category: 'Хлеб',
    defaultExpiryDays: 3,
    minStock: 1,
    defaultLocation: 'pantry',
    barcode: '4870204301235',
    active: true,
  },
  {
    id: 3,
    name: 'Яйца С1 (10 шт)',
    category: 'Молочные',
    defaultExpiryDays: 21,
    minStock: 1,
    defaultLocation: 'fridge',
    barcode: '4870204301236',
    active: true,
  },
  {
    id: 4,
    name: 'Сыр Российский',
    category: 'Молочные',
    defaultExpiryDays: 30,
    minStock: 1,
    defaultLocation: 'fridge',
    barcode: '4870204301237',
    active: true,
  },
  {
    id: 5,
    name: 'Масло сливочное',
    category: 'Молочные',
    defaultExpiryDays: 60,
    minStock: 1,
    defaultLocation: 'fridge',
    barcode: '4870204301238',
    active: true,
  },
  {
    id: 6,
    name: 'Курица (филе)',
    category: 'Мясо',
    defaultExpiryDays: 3,
    minStock: 0,
    defaultLocation: 'fridge',
    barcode: '4870204301239',
    active: true,
  },
  {
    id: 7,
    name: 'Рис длиннозёрный',
    category: 'Крупы',
    defaultExpiryDays: 365,
    minStock: 1,
    defaultLocation: 'pantry',
    barcode: '4870204301240',
    active: true,
  },
  {
    id: 8,
    name: 'Макароны спагетти',
    category: 'Крупы',
    defaultExpiryDays: 365,
    minStock: 2,
    defaultLocation: 'pantry',
    barcode: '4870204301241',
    active: true,
  },
  {
    id: 9,
    name: 'Томатная паста',
    category: 'Консервы',
    defaultExpiryDays: 180,
    minStock: 1,
    defaultLocation: 'pantry',
    barcode: '4870204301242',
    active: true,
  },
  {
    id: 10,
    name: 'Шампунь',
    category: 'Гигиена',
    defaultExpiryDays: 730,
    minStock: 1,
    defaultLocation: 'bathroom',
    barcode: '4870204301243',
    active: true,
  },
]

const mockStockEntries: StockEntry[] = [
  {
    id: 1,
    productId: 1,
    amount: 2,
    bestBefore: '2026-01-20',
    purchasedDate: '2026-01-15',
    location: 'fridge',
    note: '',
  },
  {
    id: 2,
    productId: 2,
    amount: 1,
    bestBefore: '2026-01-19',
    purchasedDate: '2026-01-17',
    location: 'pantry',
    note: '',
  },
  {
    id: 3,
    productId: 3,
    amount: 1,
    bestBefore: '2026-02-05',
    purchasedDate: '2026-01-15',
    location: 'fridge',
    note: '',
  },
  {
    id: 4,
    productId: 4,
    amount: 1,
    bestBefore: '2026-02-15',
    purchasedDate: '2026-01-10',
    location: 'fridge',
    note: 'Открыт 15.01',
  },
  {
    id: 5,
    productId: 5,
    amount: 1,
    bestBefore: '2026-03-01',
    purchasedDate: '2026-01-05',
    location: 'fridge',
    note: '',
  },
  {
    id: 6,
    productId: 6,
    amount: 0.5,
    bestBefore: '2026-01-18',
    purchasedDate: '2026-01-15',
    location: 'fridge',
    note: '500г',
  },
  {
    id: 7,
    productId: 7,
    amount: 1,
    bestBefore: '2027-01-01',
    purchasedDate: '2025-12-20',
    location: 'pantry',
    note: '',
  },
  {
    id: 8,
    productId: 8,
    amount: 3,
    bestBefore: '2027-06-01',
    purchasedDate: '2025-12-15',
    location: 'pantry',
    note: '',
  },
  {
    id: 9,
    productId: 9,
    amount: 2,
    bestBefore: '2026-07-01',
    purchasedDate: '2026-01-10',
    location: 'pantry',
    note: '',
  },
  {
    id: 10,
    productId: 1,
    amount: 1,
    bestBefore: '2026-01-18',
    purchasedDate: '2026-01-12',
    location: 'fridge',
    note: 'Скоро истекает!',
  },
]

const mockShoppingList: ShoppingItem[] = [
  { id: 1, productId: 10, amount: 1, note: 'Любой бренд', addedBy: 'auto', done: false },
  {
    id: 2,
    productId: null,
    customName: 'Бананы',
    amount: 1,
    note: '1 кг',
    addedBy: 'manual',
    done: false,
  },
  {
    id: 3,
    productId: null,
    customName: 'Апельсины',
    amount: 5,
    note: '',
    addedBy: 'manual',
    done: true,
  },
  { id: 4, productId: 2, amount: 2, note: '', addedBy: 'auto', done: false },
]

const mockChores: Chore[] = [
  {
    id: 1,
    name: 'Пропылесосить квартиру',
    frequency: 'weekly',
    lastDone: '2026-01-10',
    nextDue: '2026-01-17',
    assignee: null,
  },
  {
    id: 2,
    name: 'Помыть полы',
    frequency: 'weekly',
    lastDone: '2026-01-12',
    nextDue: '2026-01-19',
    assignee: null,
  },
  {
    id: 3,
    name: 'Вынести мусор',
    frequency: 'daily',
    lastDone: '2026-01-16',
    nextDue: '2026-01-17',
    assignee: null,
  },
  {
    id: 4,
    name: 'Полить цветы',
    frequency: 'weekly',
    lastDone: '2026-01-14',
    nextDue: '2026-01-21',
    assignee: null,
  },
  {
    id: 5,
    name: 'Протереть пыль',
    frequency: 'weekly',
    lastDone: '2026-01-11',
    nextDue: '2026-01-18',
    assignee: null,
  },
  {
    id: 6,
    name: 'Постирать бельё',
    frequency: 'weekly',
    lastDone: '2026-01-13',
    nextDue: '2026-01-20',
    assignee: null,
  },
]

const mockTasks: Task[] = [
  { id: 1, name: 'Записаться к врачу', dueDate: '2026-01-20', done: false },
  { id: 2, name: 'Оплатить коммуналку', dueDate: '2026-01-25', done: false },
  { id: 3, name: 'Купить подарок маме', dueDate: '2026-01-28', done: false },
  { id: 4, name: 'Вызвать сантехника', dueDate: '2026-01-15', done: true },
]

const mockRecipes: Recipe[] = [
  {
    id: 1,
    name: 'Паста с курицей',
    ingredients: [
      { productId: 8, amount: 1 },
      { productId: 6, amount: 0.3 },
      { productId: 9, amount: 0.5 },
    ],
  },
  {
    id: 2,
    name: 'Омлет с сыром',
    ingredients: [
      { productId: 3, amount: 0.3 },
      { productId: 4, amount: 0.1 },
      { productId: 5, amount: 0.05 },
    ],
  },
  { id: 3, name: 'Рис с овощами', ingredients: [{ productId: 7, amount: 0.2 }] },
]

const locations: Record<string, string> = {
  fridge: 'Холодильник',
  pantry: 'Кладовая',
  bathroom: 'Ванная',
  other: 'Другое',
}

const categories = [
  'Молочные',
  'Хлеб',
  'Мясо',
  'Крупы',
  'Консервы',
  'Гигиена',
  'Овощи',
  'Фрукты',
  'Напитки',
]

// ============================================
// UTILITY FUNCTIONS
// ============================================

function formatDate(dateStr: string | null): string {
  if (!dateStr) return '—'
  const date = new Date(dateStr)
  return date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' })
}

function getDaysUntil(dateStr: string | null): number {
  if (!dateStr) return Infinity
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const target = new Date(dateStr)
  const diff = Math.ceil((target.getTime() - today.getTime()) / (1000 * 60 * 60 * 24))
  return diff
}

function getExpiryStatus(dateStr: string): 'expired' | 'critical' | 'warning' | 'ok' {
  const days = getDaysUntil(dateStr)
  if (days < 0) return 'expired'
  if (days <= 2) return 'critical'
  if (days <= 7) return 'warning'
  return 'ok'
}

// ============================================
// ICONS
// ============================================

const Icons = {
  Dashboard: (): React.ReactElement => (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
      />
    </svg>
  ),
  Stock: (): React.ReactElement => (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
      />
    </svg>
  ),
  Products: (): React.ReactElement => (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
      />
    </svg>
  ),
  Shopping: (): React.ReactElement => (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"
      />
    </svg>
  ),
  Recipes: (): React.ReactElement => (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
      />
    </svg>
  ),
  Tasks: (): React.ReactElement => (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
      />
    </svg>
  ),
  Settings: (): React.ReactElement => (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
      />
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
      />
    </svg>
  ),
  Scan: (): React.ReactElement => (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"
      />
    </svg>
  ),
  Plus: (): React.ReactElement => (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
    </svg>
  ),
  Minus: (): React.ReactElement => (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 12H4" />
    </svg>
  ),
  Check: (): React.ReactElement => (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
    </svg>
  ),
  Warning: (): React.ReactElement => (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
      />
    </svg>
  ),
  Search: (): React.ReactElement => (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
      />
    </svg>
  ),
}

// ============================================
// NAVIGATION
// ============================================

interface NavigationProps {
  currentPage: PageId
  setCurrentPage: (page: PageId) => void
}

function Navigation({ currentPage, setCurrentPage }: NavigationProps): React.ReactElement {
  const navItems: { id: PageId; label: string; icon: () => React.ReactElement }[] = [
    { id: 'dashboard', label: 'Главная', icon: Icons.Dashboard },
    { id: 'stock', label: 'Запасы', icon: Icons.Stock },
    { id: 'products', label: 'Товары', icon: Icons.Products },
    { id: 'shopping', label: 'Покупки', icon: Icons.Shopping },
    { id: 'recipes', label: 'Рецепты', icon: Icons.Recipes },
    { id: 'tasks', label: 'Задачи', icon: Icons.Tasks },
    { id: 'settings', label: 'Настройки', icon: Icons.Settings },
  ]

  const today = new Date()
  const dateStr = today.toLocaleDateString('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
  const dayStr = today.toLocaleDateString('ru-RU', { weekday: 'long' })

  return (
    <nav className="fixed left-0 top-0 h-full w-56 bg-stone-900 text-stone-300 flex flex-col">
      <div className="p-5 border-b border-stone-700">
        <h1 className="text-xl font-bold text-amber-500 tracking-tight">Hestia</h1>
        <p className="text-xs text-stone-500 mt-1">Домашний учёт</p>
      </div>
      <div className="flex-1 py-4">
        {navItems.map((item) => {
          const Icon = item.icon
          const isActive = currentPage === item.id
          return (
            <button
              key={item.id}
              onClick={() => setCurrentPage(item.id)}
              className={`w-full flex items-center gap-3 px-5 py-3 text-left transition-all ${
                isActive ? 'bg-amber-600 text-white' : 'hover:bg-stone-800 hover:text-white'
              }`}
            >
              <Icon />
              <span className="font-medium">{item.label}</span>
            </button>
          )
        })}
      </div>
      <div className="p-4 border-t border-stone-700 text-xs text-stone-500">
        <p>{dateStr}</p>
        <p className="mt-1 capitalize">{dayStr}</p>
      </div>
    </nav>
  )
}

// ============================================
// DASHBOARD PAGE
// ============================================

interface DashboardPageProps {
  setCurrentPage: (page: PageId) => void
}

function DashboardPage({ setCurrentPage }: DashboardPageProps): React.ReactElement {
  const expiringItems = mockStockEntries
    .filter((e) => getExpiryStatus(e.bestBefore) !== 'ok')
    .map((e) => ({
      ...e,
      product: mockProducts.find((p) => p.id === e.productId),
    }))
    .sort((a, b) => getDaysUntil(a.bestBefore) - getDaysUntil(b.bestBefore))

  const lowStockItems = mockProducts
    .filter((p) => p.minStock > 0)
    .map((p) => {
      const totalStock = mockStockEntries
        .filter((e) => e.productId === p.id)
        .reduce((sum, e) => sum + e.amount, 0)
      return { ...p, totalStock, isLow: totalStock < p.minStock }
    })
    .filter((p) => p.isLow)

  const todayChores = mockChores.filter((c) => getDaysUntil(c.nextDue) <= 0)
  const upcomingTasks = mockTasks.filter((t) => !t.done).slice(0, 3)
  const shoppingCount = mockShoppingList.filter((i) => !i.done).length

  return (
    <div className="p-8">
      <div className="mb-8">
        <h2 className="text-3xl font-bold text-stone-800">Добро пожаловать!</h2>
        <p className="text-stone-500 mt-1">Обзор домашнего хозяйства на сегодня</p>
      </div>

      <div className="grid grid-cols-4 gap-4 mb-8">
        <div className="bg-white rounded-xl p-5 shadow-sm border border-stone-200">
          <div className="text-3xl font-bold text-stone-800">{mockStockEntries.length}</div>
          <div className="text-sm text-stone-500">Позиций в запасах</div>
        </div>
        <div
          className="bg-white rounded-xl p-5 shadow-sm border border-stone-200 cursor-pointer hover:border-amber-400 transition-colors"
          onClick={() => setCurrentPage('shopping')}
        >
          <div className="text-3xl font-bold text-amber-600">{shoppingCount}</div>
          <div className="text-sm text-stone-500">В списке покупок</div>
        </div>
        <div className="bg-white rounded-xl p-5 shadow-sm border border-stone-200">
          <div className="text-3xl font-bold text-red-500">{expiringItems.length}</div>
          <div className="text-sm text-stone-500">Истекает/Истекло</div>
        </div>
        <div className="bg-white rounded-xl p-5 shadow-sm border border-stone-200">
          <div className="text-3xl font-bold text-blue-500">{todayChores.length}</div>
          <div className="text-sm text-stone-500">Дел на сегодня</div>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-6">
        <div className="bg-white rounded-xl shadow-sm border border-stone-200">
          <div className="p-4 border-b border-stone-100 flex items-center justify-between">
            <h3 className="font-semibold text-stone-800 flex items-center gap-2">
              <span className="text-red-500">
                <Icons.Warning />
              </span>
              Истекающие продукты
            </h3>
            <button
              onClick={() => setCurrentPage('stock')}
              className="text-sm text-amber-600 hover:underline"
            >
              Все запасы →
            </button>
          </div>
          <div className="p-4">
            {expiringItems.length === 0 ? (
              <p className="text-stone-500 text-sm">Всё в порядке!</p>
            ) : (
              <div className="space-y-3">
                {expiringItems.map((item) => {
                  const status = getExpiryStatus(item.bestBefore)
                  const days = getDaysUntil(item.bestBefore)
                  return (
                    <div key={item.id} className="flex items-center justify-between">
                      <div>
                        <p className="font-medium text-stone-800">{item.product?.name}</p>
                        <p className="text-sm text-stone-500">
                          {item.amount} шт · {locations[item.location]}
                        </p>
                      </div>
                      <span
                        className={`px-3 py-1 rounded-full text-sm font-medium ${
                          status === 'expired'
                            ? 'bg-red-100 text-red-700'
                            : status === 'critical'
                              ? 'bg-orange-100 text-orange-700'
                              : 'bg-yellow-100 text-yellow-700'
                        }`}
                      >
                        {days < 0
                          ? `Истекло ${Math.abs(days)} дн. назад`
                          : days === 0
                            ? 'Сегодня!'
                            : `${days} дн.`}
                      </span>
                    </div>
                  )
                })}
              </div>
            )}
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-stone-200">
          <div className="p-4 border-b border-stone-100 flex items-center justify-between">
            <h3 className="font-semibold text-stone-800">Низкий запас</h3>
            <button
              onClick={() => setCurrentPage('products')}
              className="text-sm text-amber-600 hover:underline"
            >
              Все товары →
            </button>
          </div>
          <div className="p-4">
            {lowStockItems.length === 0 ? (
              <p className="text-stone-500 text-sm">Всего достаточно!</p>
            ) : (
              <div className="space-y-3">
                {lowStockItems.map((item) => (
                  <div key={item.id} className="flex items-center justify-between">
                    <div>
                      <p className="font-medium text-stone-800">{item.name}</p>
                      <p className="text-sm text-stone-500">Мин: {item.minStock}</p>
                    </div>
                    <span className="px-3 py-1 rounded-full text-sm font-medium bg-amber-100 text-amber-700">
                      Осталось: {item.totalStock}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-stone-200">
          <div className="p-4 border-b border-stone-100 flex items-center justify-between">
            <h3 className="font-semibold text-stone-800">Дела на сегодня</h3>
            <button
              onClick={() => setCurrentPage('tasks')}
              className="text-sm text-amber-600 hover:underline"
            >
              Все задачи →
            </button>
          </div>
          <div className="p-4">
            {todayChores.length === 0 ? (
              <p className="text-stone-500 text-sm">На сегодня дел нет!</p>
            ) : (
              <div className="space-y-3">
                {todayChores.map((chore) => (
                  <div key={chore.id} className="flex items-center justify-between">
                    <p className="font-medium text-stone-800">{chore.name}</p>
                    <button className="px-3 py-1 bg-green-500 text-white rounded-lg text-sm hover:bg-green-600 transition-colors">
                      Выполнено
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-stone-200">
          <div className="p-4 border-b border-stone-100 flex items-center justify-between">
            <h3 className="font-semibold text-stone-800">Ближайшие задачи</h3>
            <button
              onClick={() => setCurrentPage('tasks')}
              className="text-sm text-amber-600 hover:underline"
            >
              Все задачи →
            </button>
          </div>
          <div className="p-4">
            {upcomingTasks.length === 0 ? (
              <p className="text-stone-500 text-sm">Нет активных задач</p>
            ) : (
              <div className="space-y-3">
                {upcomingTasks.map((task) => (
                  <div key={task.id} className="flex items-center justify-between">
                    <p className="font-medium text-stone-800">{task.name}</p>
                    <span className="text-sm text-stone-500">{formatDate(task.dueDate)}</span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}

// ============================================
// STOCK PAGE
// ============================================

function StockPage(): React.ReactElement {
  const [searchTerm, setSearchTerm] = useState('')
  const [locationFilter, setLocationFilter] = useState('all')
  const [showAddModal, setShowAddModal] = useState(false)

  const enrichedStock = mockStockEntries
    .map((e) => ({
      ...e,
      product: mockProducts.find((p) => p.id === e.productId),
    }))
    .filter((e) => e.product)
    .filter((e) => locationFilter === 'all' || e.location === locationFilter)
    .filter((e) => e.product!.name.toLowerCase().includes(searchTerm.toLowerCase()))
    .sort((a, b) => getDaysUntil(a.bestBefore) - getDaysUntil(b.bestBefore))

  return (
    <div className="p-8">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-3xl font-bold text-stone-800">Запасы</h2>
          <p className="text-stone-500 mt-1">Управление домашними запасами</p>
        </div>
        <div className="flex gap-3">
          <button
            onClick={() => setShowAddModal(true)}
            className="flex items-center gap-2 px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors"
          >
            <Icons.Scan />
            Сканировать
          </button>
          <button
            onClick={() => setShowAddModal(true)}
            className="flex items-center gap-2 px-4 py-2 bg-stone-800 text-white rounded-lg hover:bg-stone-700 transition-colors"
          >
            <Icons.Plus />
            Добавить
          </button>
        </div>
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
          value={locationFilter}
          onChange={(e) => setLocationFilter(e.target.value)}
          className="px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        >
          <option value="all">Все места</option>
          {Object.entries(locations).map(([key, label]) => (
            <option key={key} value={key}>
              {label}
            </option>
          ))}
        </select>
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
        <table className="w-full">
          <thead className="bg-stone-50 border-b border-stone-200">
            <tr>
              <th className="text-left px-4 py-3 text-sm font-semibold text-stone-600">Товар</th>
              <th className="text-left px-4 py-3 text-sm font-semibold text-stone-600">
                Количество
              </th>
              <th className="text-left px-4 py-3 text-sm font-semibold text-stone-600">Место</th>
              <th className="text-left px-4 py-3 text-sm font-semibold text-stone-600">Годен до</th>
              <th className="text-left px-4 py-3 text-sm font-semibold text-stone-600">Заметка</th>
              <th className="text-right px-4 py-3 text-sm font-semibold text-stone-600">
                Действия
              </th>
            </tr>
          </thead>
          <tbody>
            {enrichedStock.map((item) => {
              const status = getExpiryStatus(item.bestBefore)
              const days = getDaysUntil(item.bestBefore)
              return (
                <tr key={item.id} className="border-b border-stone-100 hover:bg-stone-50">
                  <td className="px-4 py-3">
                    <div>
                      <p className="font-medium text-stone-800">{item.product!.name}</p>
                      <p className="text-sm text-stone-500">{item.product!.category}</p>
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                      <button className="w-8 h-8 flex items-center justify-center rounded bg-stone-100 hover:bg-stone-200 transition-colors">
                        <Icons.Minus />
                      </button>
                      <span className="font-medium w-12 text-center">{item.amount}</span>
                      <button className="w-8 h-8 flex items-center justify-center rounded bg-stone-100 hover:bg-stone-200 transition-colors">
                        <Icons.Plus />
                      </button>
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <span className="px-2 py-1 bg-stone-100 rounded text-sm">
                      {locations[item.location]}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <span
                      className={`px-2 py-1 rounded text-sm font-medium ${
                        status === 'expired'
                          ? 'bg-red-100 text-red-700'
                          : status === 'critical'
                            ? 'bg-orange-100 text-orange-700'
                            : status === 'warning'
                              ? 'bg-yellow-100 text-yellow-700'
                              : 'bg-green-100 text-green-700'
                      }`}
                    >
                      {formatDate(item.bestBefore)}
                      {days <= 7 && days >= 0 && ` (${days} дн.)`}
                      {days < 0 && ` (просрочено)`}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-sm text-stone-500">{item.note || '—'}</td>
                  <td className="px-4 py-3 text-right">
                    <button className="text-red-500 hover:text-red-700 text-sm">Списать</button>
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>

      {showAddModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
            <h3 className="text-xl font-bold text-stone-800 mb-4">Добавить в запасы</h3>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">Штрихкод</label>
                <input
                  type="text"
                  placeholder="Сканируйте или введите..."
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">Товар</label>
                <select className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500">
                  <option value="">Выберите товар...</option>
                  {mockProducts.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.name}
                    </option>
                  ))}
                </select>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-stone-700 mb-1">
                    Количество
                  </label>
                  <input
                    type="number"
                    defaultValue="1"
                    className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-stone-700 mb-1">Годен до</label>
                  <input
                    type="date"
                    className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                  />
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">
                  Место хранения
                </label>
                <select className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500">
                  {Object.entries(locations).map(([key, label]) => (
                    <option key={key} value={key}>
                      {label}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">Заметка</label>
                <input
                  type="text"
                  placeholder="Необязательно..."
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                />
              </div>
            </div>
            <div className="flex gap-3 mt-6">
              <button
                onClick={() => setShowAddModal(false)}
                className="flex-1 px-4 py-2 border border-stone-300 rounded-lg hover:bg-stone-50 transition-colors"
              >
                Отмена
              </button>
              <button
                onClick={() => setShowAddModal(false)}
                className="flex-1 px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors"
              >
                Добавить
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

// ============================================
// PRODUCTS PAGE
// ============================================

function ProductsPage(): React.ReactElement {
  const [searchTerm, setSearchTerm] = useState('')
  const [categoryFilter, setCategoryFilter] = useState('all')
  const [showAddModal, setShowAddModal] = useState(false)

  const filteredProducts = mockProducts
    .filter((p) => categoryFilter === 'all' || p.category === categoryFilter)
    .filter((p) => p.name.toLowerCase().includes(searchTerm.toLowerCase()))

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
            <option key={cat} value={cat}>
              {cat}
            </option>
          ))}
        </select>
      </div>

      <div className="grid grid-cols-3 gap-4">
        {filteredProducts.map((product) => {
          const totalStock = mockStockEntries
            .filter((e) => e.productId === product.id)
            .reduce((sum, e) => sum + e.amount, 0)
          const isLow = product.minStock > 0 && totalStock < product.minStock

          return (
            <div
              key={product.id}
              className="bg-white rounded-xl p-4 shadow-sm border border-stone-200 hover:border-amber-400 transition-colors cursor-pointer"
            >
              <div className="flex justify-between items-start mb-3">
                <span className="px-2 py-1 bg-stone-100 rounded text-xs text-stone-600">
                  {product.category}
                </span>
                {isLow && (
                  <span className="px-2 py-1 bg-amber-100 rounded text-xs text-amber-700">
                    Мало!
                  </span>
                )}
              </div>
              <h3 className="font-semibold text-stone-800 mb-2">{product.name}</h3>
              <div className="space-y-1 text-sm text-stone-500">
                <p>Штрихкод: {product.barcode}</p>
                <p>Срок годности: {product.defaultExpiryDays} дн.</p>
                <p>Место: {locations[product.defaultLocation]}</p>
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

      {showAddModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
            <h3 className="text-xl font-bold text-stone-800 mb-4">Новый товар</h3>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">Название</label>
                <input
                  type="text"
                  placeholder="Например: Молоко 3.2%"
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">Категория</label>
                <select className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500">
                  {categories.map((cat) => (
                    <option key={cat} value={cat}>
                      {cat}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">Штрихкод</label>
                <input
                  type="text"
                  placeholder="Сканируйте или введите..."
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-stone-700 mb-1">
                    Срок годности (дни)
                  </label>
                  <input
                    type="number"
                    defaultValue="7"
                    className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-stone-700 mb-1">
                    Мин. запас
                  </label>
                  <input
                    type="number"
                    defaultValue="0"
                    className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                  />
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">
                  Место хранения по умолчанию
                </label>
                <select className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500">
                  {Object.entries(locations).map(([key, label]) => (
                    <option key={key} value={key}>
                      {label}
                    </option>
                  ))}
                </select>
              </div>
            </div>
            <div className="flex gap-3 mt-6">
              <button
                onClick={() => setShowAddModal(false)}
                className="flex-1 px-4 py-2 border border-stone-300 rounded-lg hover:bg-stone-50 transition-colors"
              >
                Отмена
              </button>
              <button
                onClick={() => setShowAddModal(false)}
                className="flex-1 px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors"
              >
                Создать
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

// ============================================
// SHOPPING PAGE
// ============================================

function ShoppingPage(): React.ReactElement {
  const [items, setItems] = useState(mockShoppingList)
  const [newItem, setNewItem] = useState('')

  const toggleItem = (id: number): void => {
    setItems(items.map((item) => (item.id === id ? { ...item, done: !item.done } : item)))
  }

  const addItem = (): void => {
    if (!newItem.trim()) return
    setItems([
      ...items,
      {
        id: Date.now(),
        productId: null,
        customName: newItem,
        amount: 1,
        note: '',
        addedBy: 'manual',
        done: false,
      },
    ])
    setNewItem('')
  }

  const pendingItems = items.filter((i) => !i.done)
  const doneItems = items.filter((i) => i.done)

  return (
    <div className="p-8">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-3xl font-bold text-stone-800">Список покупок</h2>
          <p className="text-stone-500 mt-1">Общий список для всей семьи</p>
        </div>
        <div className="text-right">
          <p className="text-2xl font-bold text-amber-600">{pendingItems.length}</p>
          <p className="text-sm text-stone-500">к покупке</p>
        </div>
      </div>

      <div className="bg-white rounded-xl p-4 shadow-sm border border-stone-200 mb-6">
        <div className="flex gap-3">
          <input
            type="text"
            placeholder="Добавить товар..."
            value={newItem}
            onChange={(e) => setNewItem(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && addItem()}
            className="flex-1 px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
          />
          <button
            onClick={addItem}
            className="px-6 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors"
          >
            Добавить
          </button>
        </div>
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-stone-200 mb-6">
        <div className="p-4 border-b border-stone-100">
          <h3 className="font-semibold text-stone-800">К покупке</h3>
        </div>
        <div className="divide-y divide-stone-100">
          {pendingItems.length === 0 ? (
            <p className="p-4 text-stone-500">Список пуст!</p>
          ) : (
            pendingItems.map((item) => {
              const product = item.productId
                ? mockProducts.find((p) => p.id === item.productId)
                : null
              const name = product?.name || item.customName
              return (
                <div key={item.id} className="p-4 flex items-center gap-4 hover:bg-stone-50">
                  <button
                    onClick={() => toggleItem(item.id)}
                    className="w-6 h-6 rounded-full border-2 border-stone-300 flex items-center justify-center hover:border-green-500 transition-colors"
                  />
                  <div className="flex-1">
                    <p className="font-medium text-stone-800">{name}</p>
                    {item.note && <p className="text-sm text-stone-500">{item.note}</p>}
                  </div>
                  <span className="text-sm text-stone-500">{item.amount} шт.</span>
                  {item.addedBy === 'auto' && (
                    <span className="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">
                      Авто
                    </span>
                  )}
                </div>
              )
            })
          )}
        </div>
      </div>

      {doneItems.length > 0 && (
        <div className="bg-white rounded-xl shadow-sm border border-stone-200 opacity-60">
          <div className="p-4 border-b border-stone-100 flex justify-between items-center">
            <h3 className="font-semibold text-stone-800">Куплено</h3>
            <button className="text-sm text-red-500 hover:underline">Очистить</button>
          </div>
          <div className="divide-y divide-stone-100">
            {doneItems.map((item) => {
              const product = item.productId
                ? mockProducts.find((p) => p.id === item.productId)
                : null
              const name = product?.name || item.customName
              return (
                <div key={item.id} className="p-4 flex items-center gap-4">
                  <button
                    onClick={() => toggleItem(item.id)}
                    className="w-6 h-6 rounded-full bg-green-500 text-white flex items-center justify-center"
                  >
                    <Icons.Check />
                  </button>
                  <p className="flex-1 line-through text-stone-400">{name}</p>
                </div>
              )
            })}
          </div>
        </div>
      )}
    </div>
  )
}

// ============================================
// RECIPES PAGE
// ============================================

function RecipesPage(): React.ReactElement {
  interface CheckedIngredient {
    productId: number
    amount: number
    product: Product | undefined
    inStock: number
    hasEnough: boolean
  }

  const checkIngredients = (recipe: Recipe): CheckedIngredient[] => {
    return recipe.ingredients.map((ing) => {
      const totalStock = mockStockEntries
        .filter((e) => e.productId === ing.productId)
        .reduce((sum, e) => sum + e.amount, 0)
      return {
        ...ing,
        product: mockProducts.find((p) => p.id === ing.productId),
        inStock: totalStock,
        hasEnough: totalStock >= ing.amount,
      }
    })
  }

  return (
    <div className="p-8">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-3xl font-bold text-stone-800">Рецепты</h2>
          <p className="text-stone-500 mt-1">Проверка наличия ингредиентов</p>
        </div>
        <button className="flex items-center gap-2 px-4 py-2 bg-stone-800 text-white rounded-lg hover:bg-stone-700 transition-colors">
          <Icons.Plus />
          Новый рецепт
        </button>
      </div>

      <div className="grid grid-cols-2 gap-6">
        {mockRecipes.map((recipe) => {
          const ingredients = checkIngredients(recipe)
          const canMake = ingredients.every((i) => i.hasEnough)
          const missingCount = ingredients.filter((i) => !i.hasEnough).length

          return (
            <div
              key={recipe.id}
              className="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden"
            >
              <div className="p-4 border-b border-stone-100 flex justify-between items-center">
                <h3 className="font-semibold text-stone-800 text-lg">{recipe.name}</h3>
                {canMake ? (
                  <span className="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium">
                    ✓ Можно готовить
                  </span>
                ) : (
                  <span className="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-medium">
                    Не хватает {missingCount} ингр.
                  </span>
                )}
              </div>
              <div className="p-4">
                <h4 className="text-sm font-medium text-stone-600 mb-3">Ингредиенты:</h4>
                <div className="space-y-2">
                  {ingredients.map((ing, idx) => (
                    <div key={idx} className="flex items-center justify-between">
                      <span className={`${ing.hasEnough ? 'text-stone-800' : 'text-red-600'}`}>
                        {ing.product?.name}
                      </span>
                      <span
                        className={`text-sm ${ing.hasEnough ? 'text-green-600' : 'text-red-600'}`}
                      >
                        {ing.inStock} / {ing.amount}
                        {ing.hasEnough ? ' ✓' : ' ✗'}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
              <div className="p-4 bg-stone-50 border-t border-stone-100 flex gap-2">
                <button
                  className="flex-1 px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  disabled={!canMake}
                >
                  Приготовить
                </button>
                {!canMake && (
                  <button className="px-4 py-2 border border-stone-300 rounded-lg hover:bg-white transition-colors">
                    В список покупок
                  </button>
                )}
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}

// ============================================
// TASKS PAGE
// ============================================

function TasksPage(): React.ReactElement {
  const [chores, setChores] = useState(mockChores)
  const [tasks, setTasks] = useState(mockTasks)
  const [showAddTask, setShowAddTask] = useState(false)
  const [newTaskName, setNewTaskName] = useState('')

  const calculateNextDue = (frequency: Chore['frequency']): string => {
    const today = new Date()
    switch (frequency) {
      case 'daily':
        today.setDate(today.getDate() + 1)
        break
      case 'weekly':
        today.setDate(today.getDate() + 7)
        break
      case 'monthly':
        today.setMonth(today.getMonth() + 1)
        break
    }
    return today.toISOString().split('T')[0]
  }

  const markChoreDone = (id: number): void => {
    setChores(
      chores.map((c) => {
        if (c.id === id) {
          return {
            ...c,
            lastDone: new Date().toISOString().split('T')[0],
            nextDue: calculateNextDue(c.frequency),
          }
        }
        return c
      })
    )
  }

  const toggleTask = (id: number): void => {
    setTasks(tasks.map((t) => (t.id === id ? { ...t, done: !t.done } : t)))
  }

  const addTask = (): void => {
    if (!newTaskName.trim()) return
    setTasks([
      ...tasks,
      {
        id: Date.now(),
        name: newTaskName,
        dueDate: null,
        done: false,
      },
    ])
    setNewTaskName('')
    setShowAddTask(false)
  }

  const frequencyLabels: Record<Chore['frequency'], string> = {
    daily: 'Ежедневно',
    weekly: 'Еженедельно',
    monthly: 'Ежемесячно',
  }

  return (
    <div className="p-8">
      <div className="mb-6">
        <h2 className="text-3xl font-bold text-stone-800">Задачи и дела</h2>
        <p className="text-stone-500 mt-1">Домашние обязанности и разовые задачи</p>
      </div>

      <div className="grid grid-cols-2 gap-6">
        <div>
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-xl font-semibold text-stone-800">Регулярные дела</h3>
            <button className="text-sm text-amber-600 hover:underline">+ Добавить</button>
          </div>
          <div className="space-y-3">
            {[...chores]
              .sort((a, b) => getDaysUntil(a.nextDue) - getDaysUntil(b.nextDue))
              .map((chore) => {
                const days = getDaysUntil(chore.nextDue)
                const isOverdue = days < 0
                const isDueToday = days === 0

                return (
                  <div
                    key={chore.id}
                    className={`bg-white rounded-xl p-4 shadow-sm border ${
                      isOverdue
                        ? 'border-red-300 bg-red-50'
                        : isDueToday
                          ? 'border-amber-300 bg-amber-50'
                          : 'border-stone-200'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="font-medium text-stone-800">{chore.name}</p>
                        <p className="text-sm text-stone-500">
                          {frequencyLabels[chore.frequency]} ·
                          {isOverdue
                            ? ` Просрочено на ${Math.abs(days)} дн.`
                            : isDueToday
                              ? ' Сегодня!'
                              : ` Через ${days} дн.`}
                        </p>
                      </div>
                      <button
                        onClick={() => markChoreDone(chore.id)}
                        className="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors text-sm"
                      >
                        Выполнено
                      </button>
                    </div>
                  </div>
                )
              })}
          </div>
        </div>

        <div>
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-xl font-semibold text-stone-800">Разовые задачи</h3>
            <button
              onClick={() => setShowAddTask(true)}
              className="text-sm text-amber-600 hover:underline"
            >
              + Добавить
            </button>
          </div>

          {showAddTask && (
            <div className="bg-white rounded-xl p-4 shadow-sm border border-amber-300 mb-3">
              <input
                type="text"
                placeholder="Название задачи..."
                value={newTaskName}
                onChange={(e) => setNewTaskName(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && addTask()}
                className="w-full px-3 py-2 border border-stone-300 rounded-lg mb-3 focus:outline-none focus:ring-2 focus:ring-amber-500"
                autoFocus
              />
              <div className="flex gap-2">
                <button
                  onClick={() => setShowAddTask(false)}
                  className="flex-1 px-3 py-1 border border-stone-300 rounded-lg text-sm"
                >
                  Отмена
                </button>
                <button
                  onClick={addTask}
                  className="flex-1 px-3 py-1 bg-amber-500 text-white rounded-lg text-sm"
                >
                  Добавить
                </button>
              </div>
            </div>
          )}

          <div className="space-y-3">
            {tasks
              .filter((t) => !t.done)
              .map((task) => (
                <div
                  key={task.id}
                  className="bg-white rounded-xl p-4 shadow-sm border border-stone-200"
                >
                  <div className="flex items-center gap-3">
                    <button
                      onClick={() => toggleTask(task.id)}
                      className="w-6 h-6 rounded-full border-2 border-stone-300 hover:border-green-500 transition-colors"
                    />
                    <div className="flex-1">
                      <p className="font-medium text-stone-800">{task.name}</p>
                      {task.dueDate && (
                        <p className="text-sm text-stone-500">До {formatDate(task.dueDate)}</p>
                      )}
                    </div>
                  </div>
                </div>
              ))}
          </div>

          {tasks.filter((t) => t.done).length > 0 && (
            <div className="mt-6">
              <h4 className="text-sm font-medium text-stone-500 mb-3">Выполнено</h4>
              <div className="space-y-2 opacity-60">
                {tasks
                  .filter((t) => t.done)
                  .map((task) => (
                    <div
                      key={task.id}
                      className="bg-white rounded-xl p-4 shadow-sm border border-stone-200"
                    >
                      <div className="flex items-center gap-3">
                        <button
                          onClick={() => toggleTask(task.id)}
                          className="w-6 h-6 rounded-full bg-green-500 text-white flex items-center justify-center"
                        >
                          <Icons.Check />
                        </button>
                        <p className="line-through text-stone-400">{task.name}</p>
                      </div>
                    </div>
                  ))}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

// ============================================
// SETTINGS PAGE
// ============================================

function SettingsPage(): React.ReactElement {
  return (
    <div className="p-8">
      <div className="mb-6">
        <h2 className="text-3xl font-bold text-stone-800">Настройки</h2>
        <p className="text-stone-500 mt-1">Конфигурация системы</p>
      </div>

      <div className="max-w-2xl space-y-6">
        <div className="bg-white rounded-xl p-6 shadow-sm border border-stone-200">
          <h3 className="font-semibold text-stone-800 mb-4">Профиль</h3>
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">Имя</label>
              <input
                type="text"
                defaultValue="Администратор"
                className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">Email</label>
              <input
                type="email"
                defaultValue="admin@home.local"
                className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
              />
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl p-6 shadow-sm border border-stone-200">
          <h3 className="font-semibold text-stone-800 mb-4">Telegram</h3>
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-stone-800">Статус подключения</p>
                <p className="text-sm text-stone-500">@hestia_bot</p>
              </div>
              <span className="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm">
                Подключён
              </span>
            </div>
            <div className="border-t border-stone-100 pt-4">
              <p className="font-medium text-stone-800 mb-3">Уведомления</p>
              <div className="space-y-3">
                <label className="flex items-center justify-between">
                  <span className="text-stone-700">Ежедневный отчёт об истекающих</span>
                  <input type="checkbox" defaultChecked className="w-5 h-5 accent-amber-500" />
                </label>
                <label className="flex items-center justify-between">
                  <span className="text-stone-700">Еженедельный отчёт о делах</span>
                  <input type="checkbox" defaultChecked className="w-5 h-5 accent-amber-500" />
                </label>
                <label className="flex items-center justify-between">
                  <span className="text-stone-700">Напоминания о задачах</span>
                  <input type="checkbox" defaultChecked className="w-5 h-5 accent-amber-500" />
                </label>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl p-6 shadow-sm border border-stone-200">
          <h3 className="font-semibold text-stone-800 mb-4">Места хранения</h3>
          <div className="space-y-2">
            {Object.entries(locations).map(([key, label]) => (
              <div
                key={key}
                className="flex items-center justify-between py-2 border-b border-stone-100 last:border-0"
              >
                <span className="text-stone-800">{label}</span>
                <button className="text-sm text-stone-500 hover:text-red-500">Удалить</button>
              </div>
            ))}
          </div>
          <button className="mt-4 text-sm text-amber-600 hover:underline">+ Добавить место</button>
        </div>

        <div className="bg-white rounded-xl p-6 shadow-sm border border-stone-200">
          <h3 className="font-semibold text-stone-800 mb-4">Данные</h3>
          <div className="space-y-3">
            <button className="w-full text-left px-4 py-3 border border-stone-200 rounded-lg hover:bg-stone-50 transition-colors">
              <p className="font-medium text-stone-800">Экспорт данных</p>
              <p className="text-sm text-stone-500">Скачать все данные в JSON</p>
            </button>
            <button className="w-full text-left px-4 py-3 border border-stone-200 rounded-lg hover:bg-stone-50 transition-colors">
              <p className="font-medium text-stone-800">Импорт данных</p>
              <p className="text-sm text-stone-500">Загрузить данные из файла</p>
            </button>
            <button className="w-full text-left px-4 py-3 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
              <p className="font-medium text-red-600">Очистить все данные</p>
              <p className="text-sm text-red-400">Удалить все записи (необратимо)</p>
            </button>
          </div>
        </div>

        <div className="text-center text-sm text-stone-400 py-4">Hestia v0.1.0</div>
      </div>
    </div>
  )
}

// ============================================
// MAIN APP
// ============================================

export default function App(): React.ReactElement {
  const [currentPage, setCurrentPage] = useState<PageId>('dashboard')

  const renderPage = (): React.ReactElement => {
    switch (currentPage) {
      case 'dashboard':
        return <DashboardPage setCurrentPage={setCurrentPage} />
      case 'stock':
        return <StockPage />
      case 'products':
        return <ProductsPage />
      case 'shopping':
        return <ShoppingPage />
      case 'recipes':
        return <RecipesPage />
      case 'tasks':
        return <TasksPage />
      case 'settings':
        return <SettingsPage />
      default:
        return <DashboardPage setCurrentPage={setCurrentPage} />
    }
  }

  return (
    <div className="min-h-screen bg-stone-100">
      <Navigation currentPage={currentPage} setCurrentPage={setCurrentPage} />
      <main className="ml-56">{renderPage()}</main>
    </div>
  )
}
