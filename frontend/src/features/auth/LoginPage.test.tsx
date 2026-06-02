import { HttpResponse, http } from "msw";
import { describe, expect, it } from "vitest";
import { server } from "@/test/mocks/server";
import { render, screen, waitFor } from "@/test/utils";
import { LoginPage } from "./LoginPage";

describe("LoginPage", () => {
  it("logs in and shows no error on valid credentials", async () => {
    server.use(
      http.get(
        "*/api/internal/v1/auth/csrf",
        () => new HttpResponse(null, { status: 204 }),
      ),
      http.post("*/api/internal/v1/auth/login", () =>
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
    const { user } = render(<LoginPage />);
    await user.type(screen.getByLabelText("Имя пользователя"), "pavel");
    await user.type(screen.getByLabelText("Пароль"), "secret123");
    await user.click(screen.getByRole("button", { name: "Войти" }));
    await waitFor(() => {
      expect(
        screen.queryByText("Неверное имя пользователя или пароль"),
      ).not.toBeInTheDocument();
    });
  });

  it("shows an error on 401", async () => {
    server.use(
      http.get(
        "*/api/internal/v1/auth/csrf",
        () => new HttpResponse(null, { status: 204 }),
      ),
      http.post("*/api/internal/v1/auth/login", () =>
        HttpResponse.json({ message: "Invalid credentials." }, { status: 401 }),
      ),
    );
    const { user } = render(<LoginPage />);
    await user.type(screen.getByLabelText("Имя пользователя"), "pavel");
    await user.type(screen.getByLabelText("Пароль"), "wrong");
    await user.click(screen.getByRole("button", { name: "Войти" }));
    await waitFor(() => {
      expect(
        screen.getByText("Неверное имя пользователя или пароль"),
      ).toBeInTheDocument();
    });
  });
});
