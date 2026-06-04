import "@testing-library/jest-dom/vitest";
import { cleanup } from "@testing-library/react";
import { afterAll, afterEach, beforeAll, beforeEach, vi } from "vitest";
import i18n from "@/i18n";
import { server } from "./mocks/server";

// react-hot-toast uses matchMedia; provide a minimal stub in jsdom
Object.defineProperty(window, "matchMedia", {
  writable: true,
  value: vi.fn().mockImplementation((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  })),
});

let consoleError: ReturnType<typeof vi.spyOn>;

beforeAll(() => {
  server.listen({ onUnhandledRequest: "error" });
  i18n.changeLanguage("ru");
});

beforeEach(() => {
  consoleError = vi.spyOn(console, "error").mockImplementation((...args) => {
    throw new Error(`Unexpected console.error in test: ${args.join(" ")}`);
  });
});

afterEach(() => {
  cleanup();
  server.resetHandlers();
  consoleError.mockRestore();
});

afterAll(() => server.close());
