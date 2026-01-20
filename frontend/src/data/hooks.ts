import { useContext } from "react";
import {
  AuthContext,
  type AuthContextValue,
  ChoresContext,
  type ChoresContextValue,
  RecipesContext,
  type RecipesContextValue,
  ShoppingContext,
  type ShoppingContextValue,
  TasksContext,
  type TasksContextValue,
} from "./context";

export function useShoppingList(): ShoppingContextValue {
  const context = useContext(ShoppingContext);
  if (!context) {
    throw new Error("useShoppingList must be used within a DataProvider");
  }
  return context;
}

export function useChores(): ChoresContextValue {
  const context = useContext(ChoresContext);
  if (!context) {
    throw new Error("useChores must be used within a DataProvider");
  }
  return context;
}

export function useTasks(): TasksContextValue {
  const context = useContext(TasksContext);
  if (!context) {
    throw new Error("useTasks must be used within a DataProvider");
  }
  return context;
}

export function useRecipes(): RecipesContextValue {
  const context = useContext(RecipesContext);
  if (!context) {
    throw new Error("useRecipes must be used within a DataProvider");
  }
  return context;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
}
