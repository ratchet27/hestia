import { HttpResponse, http } from "msw";
import { createStockEntry, wrapResponse } from "./data";

// Default success handlers - provide sensible defaults for all API endpoints
export const handlers = [
  http.get("*/api/internal/v1/auth/me", () =>
    HttpResponse.json({ message: "Authentication required." }, { status: 401 }),
  ),
  http.get("*/api/internal/v1/recipes", () =>
    HttpResponse.json({ data: [], meta: {} }),
  ),
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
