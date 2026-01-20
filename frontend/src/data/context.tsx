import { createContext, type ReactNode, useState } from "react";
import {
  mockChores,
  mockRecipes,
  mockShoppingList,
  mockTasks,
  mockUser,
} from "./mocks";
import type { Chore, Recipe, ShoppingItem, Task, User } from "./types";

export interface ShoppingContextValue {
  shoppingList: ShoppingItem[];
  setShoppingList: React.Dispatch<React.SetStateAction<ShoppingItem[]>>;
}

export interface ChoresContextValue {
  chores: Chore[];
  setChores: React.Dispatch<React.SetStateAction<Chore[]>>;
}

export interface TasksContextValue {
  tasks: Task[];
  setTasks: React.Dispatch<React.SetStateAction<Task[]>>;
}

export interface RecipesContextValue {
  recipes: Recipe[];
  setRecipes: React.Dispatch<React.SetStateAction<Recipe[]>>;
}

export interface AuthContextValue {
  user: User | null;
  login: (username: string, password: string) => boolean;
  logout: () => void;
}

export const ShoppingContext = createContext<ShoppingContextValue | null>(null);
export const ChoresContext = createContext<ChoresContextValue | null>(null);
export const TasksContext = createContext<TasksContextValue | null>(null);
export const RecipesContext = createContext<RecipesContextValue | null>(null);
export const AuthContext = createContext<AuthContextValue | null>(null);

interface DataProviderProps {
  children: ReactNode;
}

export function DataProvider({
  children,
}: DataProviderProps): React.ReactElement {
  const [shoppingList, setShoppingList] =
    useState<ShoppingItem[]>(mockShoppingList);
  const [chores, setChores] = useState<Chore[]>(mockChores);
  const [tasks, setTasks] = useState<Task[]>(mockTasks);
  const [recipes, setRecipes] = useState<Recipe[]>(mockRecipes);

  return (
    <ShoppingContext.Provider value={{ shoppingList, setShoppingList }}>
      <ChoresContext.Provider value={{ chores, setChores }}>
        <TasksContext.Provider value={{ tasks, setTasks }}>
          <RecipesContext.Provider value={{ recipes, setRecipes }}>
            {children}
          </RecipesContext.Provider>
        </TasksContext.Provider>
      </ChoresContext.Provider>
    </ShoppingContext.Provider>
  );
}

interface AuthProviderProps {
  children: ReactNode;
}

export function AuthProvider({
  children,
}: AuthProviderProps): React.ReactElement {
  const [user, setUser] = useState<User | null>(null);

  const login = (username: string, password: string): boolean => {
    if (username === "pavel" && password === "password") {
      setUser(mockUser);
      return true;
    }
    return false;
  };

  const logout = (): void => {
    setUser(null);
  };

  return (
    <AuthContext.Provider value={{ user, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}
