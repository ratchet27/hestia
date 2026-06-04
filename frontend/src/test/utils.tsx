import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { type RenderOptions, render } from "@testing-library/react";
import { userEvent } from "@testing-library/user-event";
import type { ReactElement, ReactNode } from "react";
import { Toaster } from "react-hot-toast";
import { I18nextProvider } from "react-i18next";
import { BrowserRouter } from "react-router-dom";
import { AuthProvider, DataProvider } from "@/data/context";
import i18n from "@/i18n";

// Create a fresh QueryClient for each test
function createTestQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: {
        retry: false, // Don't retry failed queries in tests
        gcTime: 0,
      },
      mutations: {
        retry: false,
      },
    },
  });
}

interface WrapperProps {
  children: ReactNode;
}

// Wraps components with all necessary providers
function AllProviders({ children }: WrapperProps) {
  const queryClient = createTestQueryClient();

  return (
    <I18nextProvider i18n={i18n}>
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <DataProvider>
            <BrowserRouter>{children}</BrowserRouter>
          </DataProvider>
        </AuthProvider>
        <Toaster />
      </QueryClientProvider>
    </I18nextProvider>
  );
}

// Custom render that includes providers and user event
function customRender(
  ui: ReactElement,
  options?: Omit<RenderOptions, "wrapper">,
) {
  return {
    user: userEvent.setup(),
    ...render(ui, { wrapper: AllProviders, ...options }),
  };
}

// Re-export everything from testing-library
export * from "@testing-library/react";
export { userEvent } from "@testing-library/user-event";

// Override render with our custom version
export { customRender as render };
