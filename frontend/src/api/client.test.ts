import { HttpResponse, http } from "msw";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { server } from "@/test/mocks/server";
import { apiFetch } from "./client";

describe("apiFetch 401 redirect", () => {
  let assignMock: ReturnType<typeof vi.fn>;
  let originalLocation: Location;

  beforeEach(() => {
    originalLocation = window.location;
    assignMock = vi.fn();
    Object.defineProperty(window, "location", {
      writable: true,
      configurable: true,
      value: { ...window.location, assign: assignMock, pathname: "/tasks" },
    });
  });

  afterEach(() => {
    Object.defineProperty(window, "location", {
      writable: true,
      configurable: true,
      value: originalLocation,
    });
  });

  it("redirects to /login on a 401 from a non-auth endpoint", async () => {
    server.use(
      http.get("*/api/internal/v1/tasks", () =>
        HttpResponse.json({ message: "x" }, { status: 401 }),
      ),
    );

    await expect(apiFetch("/api/internal/v1/tasks")).rejects.toThrow();
    expect(assignMock).toHaveBeenCalledWith("/login");
  });

  it("does NOT redirect to /login on a 401 from /auth/me", async () => {
    server.use(
      http.get("*/api/internal/v1/auth/me", () =>
        HttpResponse.json({ message: "x" }, { status: 401 }),
      ),
    );

    await expect(apiFetch("/api/internal/v1/auth/me")).rejects.toThrow();
    expect(assignMock).not.toHaveBeenCalled();
  });
});
