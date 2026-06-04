import { HttpResponse, http } from "msw";
import { describe, expect, it } from "vitest";
import { server } from "@/test/mocks/server";
import { render, screen, userEvent } from "@/test/utils";
import { SettingsPage } from "./SettingsPage";

function mockBaseEndpoints() {
  server.use(
    http.get("*/api/internal/v1/locations", () =>
      HttpResponse.json({ data: [], meta: { total: 0 } }),
    ),
    http.get("*/api/internal/v1/categories", () =>
      HttpResponse.json({ data: [], meta: { total: 0 } }),
    ),
    http.get("*/api/internal/v1/telegram/status", () =>
      HttpResponse.json({
        data: { configured: true, daily_summary_time: "08:30" },
      }),
    ),
  );
}

describe("SettingsPage", () => {
  it("shows telegram status and a success toast on test send", async () => {
    mockBaseEndpoints();
    server.use(
      http.post("*/api/internal/v1/telegram/test", () =>
        HttpResponse.json({ data: { ok: true } }),
      ),
    );
    const user = userEvent.setup();
    render(<SettingsPage />);

    expect(await screen.findByText(/^Настроено$/)).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: /отправить тест/i }));
    expect(
      await screen.findByText(/тестовое сообщение отправлено/i),
    ).toBeInTheDocument();
  });

  it("shows an error toast when the test send fails", async () => {
    mockBaseEndpoints();
    server.use(
      http.post("*/api/internal/v1/telegram/test", () =>
        HttpResponse.json({ data: { ok: false, error: "boom" } }),
      ),
    );
    const user = userEvent.setup();
    render(<SettingsPage />);

    await screen.findByText(/^Настроено$/);
    await user.click(screen.getByRole("button", { name: /отправить тест/i }));
    expect(
      await screen.findByText(/не удалось отправить тестовое сообщение/i),
    ).toBeInTheDocument();
  });
});
