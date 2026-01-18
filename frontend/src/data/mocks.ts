import type { StockEntry, ShoppingItem, Chore, Task, Recipe, User } from './types'

export const mockUser: User = {
  id: 1,
  name: 'Pavel',
  username: 'pavel',
  email: 'ratchet270@gmail.com',
}

export const mockStockEntries: StockEntry[] = [
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

export const mockShoppingList: ShoppingItem[] = [
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

export const mockChores: Chore[] = [
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

export const mockTasks: Task[] = [
  { id: 1, name: 'Записаться к врачу', dueDate: '2026-01-20', done: false },
  { id: 2, name: 'Оплатить коммуналку', dueDate: '2026-01-25', done: false },
  { id: 3, name: 'Купить подарок маме', dueDate: '2026-01-28', done: false },
  { id: 4, name: 'Вызвать сантехника', dueDate: '2026-01-15', done: true },
]

export const mockRecipes: Recipe[] = [
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
