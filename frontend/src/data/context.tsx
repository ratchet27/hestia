import { createContext, useState, type ReactNode } from 'react'
import type { Product, StockEntry, ShoppingItem, Chore, Task, Recipe } from './types'
import {
  mockProducts,
  mockStockEntries,
  mockShoppingList,
  mockChores,
  mockTasks,
  mockRecipes,
} from './mocks'

export interface ProductsContextValue {
  products: Product[]
  setProducts: React.Dispatch<React.SetStateAction<Product[]>>
}

export interface StockContextValue {
  stock: StockEntry[]
  setStock: React.Dispatch<React.SetStateAction<StockEntry[]>>
}

export interface ShoppingContextValue {
  shoppingList: ShoppingItem[]
  setShoppingList: React.Dispatch<React.SetStateAction<ShoppingItem[]>>
}

export interface ChoresContextValue {
  chores: Chore[]
  setChores: React.Dispatch<React.SetStateAction<Chore[]>>
}

export interface TasksContextValue {
  tasks: Task[]
  setTasks: React.Dispatch<React.SetStateAction<Task[]>>
}

export interface RecipesContextValue {
  recipes: Recipe[]
  setRecipes: React.Dispatch<React.SetStateAction<Recipe[]>>
}

export const ProductsContext = createContext<ProductsContextValue | null>(null)
export const StockContext = createContext<StockContextValue | null>(null)
export const ShoppingContext = createContext<ShoppingContextValue | null>(null)
export const ChoresContext = createContext<ChoresContextValue | null>(null)
export const TasksContext = createContext<TasksContextValue | null>(null)
export const RecipesContext = createContext<RecipesContextValue | null>(null)

interface DataProviderProps {
  children: ReactNode
}

export function DataProvider({ children }: DataProviderProps): React.ReactElement {
  const [products, setProducts] = useState<Product[]>(mockProducts)
  const [stock, setStock] = useState<StockEntry[]>(mockStockEntries)
  const [shoppingList, setShoppingList] = useState<ShoppingItem[]>(mockShoppingList)
  const [chores, setChores] = useState<Chore[]>(mockChores)
  const [tasks, setTasks] = useState<Task[]>(mockTasks)
  const [recipes, setRecipes] = useState<Recipe[]>(mockRecipes)

  return (
    <ProductsContext.Provider value={{ products, setProducts }}>
      <StockContext.Provider value={{ stock, setStock }}>
        <ShoppingContext.Provider value={{ shoppingList, setShoppingList }}>
          <ChoresContext.Provider value={{ chores, setChores }}>
            <TasksContext.Provider value={{ tasks, setTasks }}>
              <RecipesContext.Provider value={{ recipes, setRecipes }}>
                {children}
              </RecipesContext.Provider>
            </TasksContext.Provider>
          </ChoresContext.Provider>
        </ShoppingContext.Provider>
      </StockContext.Provider>
    </ProductsContext.Provider>
  )
}
