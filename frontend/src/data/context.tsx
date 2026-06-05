import { createContext, type ReactNode, useEffect, useState } from "react";
import { apiFetch } from "../api/client";
import type { User } from "./types";

export interface AuthContextValue {
  user: User | null;
  isLoading: boolean;
  login: (username: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

export const AuthContext = createContext<AuthContextValue | null>(null);

interface DataProviderProps {
  children: ReactNode;
}

export function DataProvider({
  children,
}: DataProviderProps): React.ReactElement {
  return <>{children}</>;
}

interface AuthProviderProps {
  children: ReactNode;
}

export function AuthProvider({
  children,
}: AuthProviderProps): React.ReactElement {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;

    apiFetch<{ data: { data?: User }; status: number; headers: Headers }>(
      "/api/internal/v1/auth/me",
    )
      .then((result) => {
        if (!cancelled) {
          setUser(result.data.data ?? null);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setUser(null);
        }
      })
      .finally(() => {
        if (!cancelled) {
          setIsLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const login = async (username: string, password: string): Promise<void> => {
    // Attempt CSRF token fetch; ignore failures
    try {
      await apiFetch("/api/internal/v1/auth/csrf");
    } catch {
      // ignore
    }

    const result = await apiFetch<{
      data: { data?: User };
      status: number;
      headers: Headers;
    }>("/api/internal/v1/auth/login", {
      method: "POST",
      body: JSON.stringify({ username, password }),
    });

    setUser(result.data.data ?? null);
  };

  const logout = async (): Promise<void> => {
    try {
      await apiFetch("/api/internal/v1/auth/logout", { method: "POST" });
    } catch {
      // ignore failures
    }
    setUser(null);
  };

  return (
    <AuthContext.Provider value={{ user, isLoading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}
