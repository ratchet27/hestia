export interface User {
  id: number
  name: string
  username: string
  email: string
}

export interface StockEntry {
  id: number
  productId: number
  amount: number
  bestBefore: string
  purchasedDate: string
  location: string
  note: string
}

export interface ShoppingItem {
  id: number
  productId: number | null
  customName?: string
  amount: number
  note: string
  addedBy: 'auto' | 'manual'
  done: boolean
}

export interface Chore {
  id: number
  name: string
  frequency: 'daily' | 'weekly' | 'monthly'
  lastDone: string
  nextDue: string
  assignee: string | null
}

export interface Task {
  id: number
  name: string
  dueDate: string | null
  done: boolean
}

export interface Recipe {
  id: number
  name: string
  ingredients: { productId: number; amount: number }[]
}

export const locations: Record<string, string> = {
  fridge: 'Холодильник',
  pantry: 'Кладовая',
  bathroom: 'Ванная',
  other: 'Другое',
}

// Utility functions
export function formatDate(dateStr: string | null): string {
  if (!dateStr) return '—'
  const date = new Date(dateStr)
  return date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' })
}

export function getDaysUntil(dateStr: string | null): number {
  if (!dateStr) return Infinity
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const target = new Date(dateStr)
  const diff = Math.ceil((target.getTime() - today.getTime()) / (1000 * 60 * 60 * 24))
  return diff
}

export function getExpiryStatus(dateStr: string): 'expired' | 'critical' | 'warning' | 'ok' {
  const days = getDaysUntil(dateStr)
  if (days < 0) return 'expired'
  if (days <= 2) return 'critical'
  if (days <= 7) return 'warning'
  return 'ok'
}
