import "@testing-library/jest-dom/vitest";
import { cleanup } from "@testing-library/react";
import { afterAll, afterEach, beforeAll, beforeEach, vi } from "vitest";
import i18n from "@/i18n";
import { server } from "./mocks/server";

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
