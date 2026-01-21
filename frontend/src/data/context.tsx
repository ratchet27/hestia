import { createContext, type ReactNode, useState } from "react";
import { mockChores, mockRecipes, mockTasks, mockUser } from "./mocks";
import type { Chore, Recipe, Task, User } from "./types";

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
  login: (username: string, password: string, rememberMe?: boolean) => boolean;
  logout: () => void;
}

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
  const [chores, setChores] = useState<Chore[]>(mockChores);
  const [tasks, setTasks] = useState<Task[]>(mockTasks);
  const [recipes, setRecipes] = useState<Recipe[]>(mockRecipes);

  return (
    <ChoresContext.Provider value={{ chores, setChores }}>
      <TasksContext.Provider value={{ tasks, setTasks }}>
        <RecipesContext.Provider value={{ recipes, setRecipes }}>
          {children}
        </RecipesContext.Provider>
      </TasksContext.Provider>
    </ChoresContext.Provider>
  );
}

interface AuthProviderProps {
  children: ReactNode;
}

const AUTH_STORAGE_KEY = "hestia_auth";

export function AuthProvider({
  children,
}: AuthProviderProps): React.ReactElement {
  // Initialize user synchronously from localStorage to prevent redirect flash
  const [user, setUser] = useState<User | null>(() => {
    const stored = localStorage.getItem(AUTH_STORAGE_KEY);
    return stored === "remembered" ? mockUser : null;
  });

  const login = (
    username: string,
    password: string,
    rememberMe?: boolean,
  ): boolean => {
    if (username === "pavel" && password === "password") {
      setUser(mockUser);
      if (rememberMe) {
        localStorage.setItem(AUTH_STORAGE_KEY, "remembered");
      }
      return true;
    }
    return false;
  };

  const logout = (): void => {
    setUser(null);
    localStorage.removeItem(AUTH_STORAGE_KEY);
  };

  return (
    <AuthContext.Provider value={{ user, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}
