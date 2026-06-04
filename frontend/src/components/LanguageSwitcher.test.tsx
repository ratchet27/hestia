import { act, render, screen, waitFor } from "@testing-library/react";
import { userEvent } from "@testing-library/user-event";
import type { ReactNode } from "react";
import { I18nextProvider } from "react-i18next";
import { afterEach, beforeEach, describe, expect, it } from "vitest";
import i18n from "@/i18n";
import { LanguageSwitcher } from "./LanguageSwitcher";

// LanguageSwitcher only depends on i18n, so render it with just that provider
// to avoid unrelated async updates from the app's auth/data providers.
function renderSwitcher() {
  return {
    user: userEvent.setup(),
    ...render(<LanguageSwitcher />, {
      wrapper: ({ children }: { children: ReactNode }) => (
        <I18nextProvider i18n={i18n}>{children}</I18nextProvider>
      ),
    }),
  };
}

// The i18n instance is a shared singleton; pin it to a known state.
const resetLanguage = () =>
  act(async () => {
    await i18n.changeLanguage("ru");
  });

describe("LanguageSwitcher", () => {
  beforeEach(resetLanguage);
  afterEach(resetLanguage);

  it("renders both languages with the active one pressed", () => {
    renderSwitcher();

    expect(screen.getByRole("button", { name: "Русский" })).toHaveAttribute(
      "aria-pressed",
      "true",
    );
    expect(screen.getByRole("button", { name: "English" })).toHaveAttribute(
      "aria-pressed",
      "false",
    );
  });

  it("switches to English when English is clicked", async () => {
    const { user } = renderSwitcher();

    await user.click(screen.getByRole("button", { name: "English" }));

    await waitFor(() => {
      expect(screen.getByRole("button", { name: "English" })).toHaveAttribute(
        "aria-pressed",
        "true",
      );
    });
    expect(i18n.resolvedLanguage).toBe("en");
    expect(screen.getByRole("button", { name: "Русский" })).toHaveAttribute(
      "aria-pressed",
      "false",
    );
  });

  it("switches back to Russian when Русский is clicked", async () => {
    await act(async () => {
      await i18n.changeLanguage("en");
    });
    const { user } = renderSwitcher();

    await user.click(screen.getByRole("button", { name: "Русский" }));

    await waitFor(() => {
      expect(i18n.resolvedLanguage).toBe("ru");
    });
    expect(screen.getByRole("button", { name: "Русский" })).toHaveAttribute(
      "aria-pressed",
      "true",
    );
  });
});
