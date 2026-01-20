import "@testing-library/jest-dom/vitest";
import { cleanup } from "@testing-library/react";
import { afterAll, afterEach, beforeAll } from "vitest";
import i18n from "@/i18n";
import { server } from "./mocks/server";

// Start MSW server before all tests and set default language
beforeAll(() => {
  server.listen({ onUnhandledRequest: "error" });
  i18n.changeLanguage("ru");
});

// Cleanup after each test (unmount components, reset MSW handlers)
afterEach(() => {
  cleanup();
  server.resetHandlers();
});

// Close MSW server after all tests
afterAll(() => server.close());
