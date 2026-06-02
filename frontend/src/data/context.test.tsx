import { HttpResponse, http } from "msw";
import type React from "react";
import { describe, expect, it } from "vitest";
import { server } from "@/test/mocks/server";
import { render, screen, waitFor } from "@/test/utils";
import { useAuth } from "./hooks";

function Probe(): React.ReactElement {
  const { user, isLoading } = useAuth();
  if (isLoading) return <span>loading</span>;
  return <span>{user ? user.username : "anon"}</span>;
}

describe("AuthProvider bootstrap", () => {
  it("hydrates the user from /auth/me", async () => {
    server.use(
      http.get("*/api/internal/v1/auth/me", () =>
        HttpResponse.json({
          data: {
            id: "u1",
            username: "pavel",
            name: "Pavel",
            email: null,
            roles: ["ROLE_USER"],
          },
        }),
      ),
    );
    // AllProviders (test/utils) already wraps AuthProvider, which bootstraps /auth/me.
    render(<Probe />);
    await waitFor(() => expect(screen.getByText("pavel")).toBeInTheDocument());
  });

  it("treats a 401 from /auth/me as anonymous", async () => {
    server.use(
      http.get("*/api/internal/v1/auth/me", () =>
        HttpResponse.json(
          { message: "Authentication required." },
          { status: 401 },
        ),
      ),
    );
    render(<Probe />);
    await waitFor(() => expect(screen.getByText("anon")).toBeInTheDocument());
  });
});
