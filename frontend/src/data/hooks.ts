import { useContext } from "react";
import {
  AuthContext,
  type AuthContextValue,
  RecipesContext,
  type RecipesContextValue,
} from "./context";

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
