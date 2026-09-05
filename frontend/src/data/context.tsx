import { useQuery, useQueryClient } from "@tanstack/react-query";
import { createContext, type ReactElement, type ReactNode } from "react";
import {
  getApiAuthCsrf,
  getApiAuthMe,
  postApiAuthLogin,
  postApiAuthLogout,
} from "../api/generated/auth/auth";
import type { UserResponse } from "../api/generated/models";
import { queryKeys } from "../api/queries/keys";
import { unwrap } from "../api/queries/unwrap";

export interface AuthContextValue {
  user: UserResponse | null;
  isLoading: boolean;
  login: (username: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

export const AuthContext = createContext<AuthContextValue | null>(null);

interface AuthProviderProps {
  children: ReactNode;
}

export function AuthProvider({ children }: AuthProviderProps): ReactElement {
  const queryClient = useQueryClient();

  // The session is a query like any other: TanStack owns the loading flag,
  // request cancellation and dedup. A 401 from /auth/me is "anonymous", not an
  // error, so it resolves to null instead of putting the query in error state.
  const { data: user = null, isLoading } = useQuery({
    queryKey: queryKeys.auth.me,
    queryFn: async (): Promise<UserResponse | null> => {
      try {
        return unwrap(await getApiAuthMe());
      } catch {
        return null;
      }
    },
    staleTime: Infinity,
    retry: false,
  });

  const login = async (username: string, password: string): Promise<void> => {
    // Primes the XSRF-TOKEN cookie the login POST must echo; a failure here
    // surfaces as a 403 on the login call itself.
    try {
      await getApiAuthCsrf();
    } catch {
      // ignore
    }

    const me = unwrap(await postApiAuthLogin({ username, password }));
    queryClient.setQueryData(queryKeys.auth.me, me);
  };

  const logout = async (): Promise<void> => {
    try {
      await postApiAuthLogout();
    } catch {
      // The cookie is gone either way; the UI treats the user as signed out.
    }
    queryClient.setQueryData(queryKeys.auth.me, null);
    queryClient.removeQueries({ predicate: (q) => q.queryKey[0] !== "auth" });
  };

  return (
    <AuthContext.Provider value={{ user, isLoading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}
