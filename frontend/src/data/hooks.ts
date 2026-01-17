import { useContext } from 'react'
import {
  ProductsContext,
  StockContext,
  ShoppingContext,
  ChoresContext,
  TasksContext,
  RecipesContext,
  AuthContext,
  type ProductsContextValue,
  type StockContextValue,
  type ShoppingContextValue,
  type ChoresContextValue,
  type TasksContextValue,
  type RecipesContextValue,
  type AuthContextValue,
} from './context'

export function useProducts(): ProductsContextValue {
  const context = useContext(ProductsContext)
  if (!context) {
    throw new Error('useProducts must be used within a DataProvider')
  }
  return context
}

export function useStock(): StockContextValue {
  const context = useContext(StockContext)
  if (!context) {
    throw new Error('useStock must be used within a DataProvider')
  }
  return context
}

export function useShoppingList(): ShoppingContextValue {
  const context = useContext(ShoppingContext)
  if (!context) {
    throw new Error('useShoppingList must be used within a DataProvider')
  }
  return context
}

export function useChores(): ChoresContextValue {
  const context = useContext(ChoresContext)
  if (!context) {
    throw new Error('useChores must be used within a DataProvider')
  }
  return context
}

export function useTasks(): TasksContextValue {
  const context = useContext(TasksContext)
  if (!context) {
    throw new Error('useTasks must be used within a DataProvider')
  }
  return context
}

export function useRecipes(): RecipesContextValue {
  const context = useContext(RecipesContext)
  if (!context) {
    throw new Error('useRecipes must be used within a DataProvider')
  }
  return context
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider')
  }
  return context
}
