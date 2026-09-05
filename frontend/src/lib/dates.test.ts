import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import i18n from "../i18n";
import { formatShortDate, getDaysUntil } from "./dates";

describe("getDaysUntil", () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ["Date"] });
    vi.setSystemTime(new Date(2026, 2, 10, 15, 30));
  });
  afterEach(() => vi.useRealTimers());

  it("counts whole days from local today", () => {
    expect(getDaysUntil("2026-03-13")).toBe(3);
    expect(getDaysUntil("2026-03-10")).toBe(0);
    expect(getDaysUntil("2026-03-08")).toBe(-2);
  });

  it("ignores the time of day of an ISO datetime", () => {
    expect(getDaysUntil(new Date(2026, 2, 11, 0, 5).toISOString())).toBe(1);
    expect(getDaysUntil(new Date(2026, 2, 10, 23, 59).toISOString())).toBe(0);
  });

  it("returns Infinity for a missing date", () => {
    expect(getDaysUntil(null)).toBe(Infinity);
  });
});

describe("formatShortDate", () => {
  afterEach(() => i18n.changeLanguage("ru"));

  it("formats a date-only string in Russian", () => {
    expect(formatShortDate("2025-01-15")).toMatch(/15 янв/i);
    expect(formatShortDate("2025-12-31")).toMatch(/31 дек/i);
  });

  it("follows the active language", async () => {
    await i18n.changeLanguage("en");
    expect(formatShortDate("2025-01-15")).toMatch(/Jan 15/);
  });

  it("renders a dash for a missing date", () => {
    expect(formatShortDate(null)).toBe("—");
  });
});
