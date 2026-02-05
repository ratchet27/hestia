import { HttpResponse, http } from "msw";
import { createStockEntry, wrapResponse } from "./data";

// Default success handlers - provide sensible defaults for all API endpoints
export const handlers = [
  http.get("*/api/internal/v1/stocks/entries", () =>
    HttpResponse.json(wrapResponse([createStockEntry()])),
  ),
  http.get("*/api/internal/v1/stocks/expiring", () =>
    HttpResponse.json(wrapResponse([])),
  ),
  http.get("*/api/internal/v1/locations", () =>
    HttpResponse.json(wrapResponse([])),
  ),
  http.get("*/api/internal/v1/products", () =>
    HttpResponse.json(wrapResponse([])),
  ),
  http.get("*/api/internal/v1/tasks", () =>
    HttpResponse.json(wrapResponse([])),
  ),
  http.get("*/api/internal/v1/chores", () =>
    HttpResponse.json(wrapResponse([])),
  ),
];

// Error handlers for testing failure states
export const errorHandlers = {
  tasksFailed: http.get("*/api/internal/v1/tasks", () =>
    HttpResponse.json({ message: "Server error" }, { status: 500 }),
  ),
  choresFailed: http.get("*/api/internal/v1/chores", () =>
    HttpResponse.json({ message: "Server error" }, { status: 500 }),
  ),
  stockEntriesFailed: http.get("*/api/internal/v1/stocks/entries", () =>
    HttpResponse.json({ message: "Server error" }, { status: 500 }),
  ),
  stockEntriesUnauthorized: http.get("*/api/internal/v1/stocks/entries", () =>
    HttpResponse.json({ message: "Unauthorized" }, { status: 401 }),
  ),
  addStockValidationError: http.post("*/api/internal/v1/stocks/add", () =>
    HttpResponse.json(
      {
        message: "Validation failed",
        violations: [{ propertyPath: "quantity", message: "Must be positive" }],
      },
      { status: 422 },
    ),
  ),
};
