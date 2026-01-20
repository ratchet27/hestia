import { describe, expect, it } from "vitest";
import {
  formatExpiryDate,
  getExpiryStatus,
  getRelativeExpiryText,
} from "./expiryStatus";

const mockT = ((key: string, options?: { count?: number }) => {
  const translations: Record<string, string> = {
    "expiry.yesterday": "вчера",
    "expiry.today": "сегодня",
    "expiry.tomorrow": "завтра",
  };
  if (key === "expiry.daysAgo") return `${options?.count} дн. назад`;
  if (key === "expiry.inDays") return `через ${options?.count} дн.`;
  return translations[key] || key;
}) as typeof import("i18next").t;

describe("getExpiryStatus", () => {
  it('returns "expired" for negative days', () => {
    expect(getExpiryStatus(-1)).toBe("expired");
    expect(getExpiryStatus(-30)).toBe("expired");
  });

  it('returns "today" for zero days', () => {
    expect(getExpiryStatus(0)).toBe("today");
  });

  it('returns "soon" for 1-2 days', () => {
    expect(getExpiryStatus(1)).toBe("soon");
    expect(getExpiryStatus(2)).toBe("soon");
  });

  it('returns "warning" for 3-7 days', () => {
    expect(getExpiryStatus(3)).toBe("warning");
    expect(getExpiryStatus(7)).toBe("warning");
  });

  it('returns "ok" for more than 7 days', () => {
    expect(getExpiryStatus(8)).toBe("ok");
    expect(getExpiryStatus(100)).toBe("ok");
  });
});

describe("getRelativeExpiryText", () => {
  it("returns days ago for items expired more than 1 day", () => {
    expect(getRelativeExpiryText(-5, mockT)).toBe("5 дн. назад");
    expect(getRelativeExpiryText(-2, mockT)).toBe("2 дн. назад");
  });

  it('returns "вчера" for yesterday', () => {
    expect(getRelativeExpiryText(-1, mockT)).toBe("вчера");
  });

  it('returns "сегодня" for today', () => {
    expect(getRelativeExpiryText(0, mockT)).toBe("сегодня");
  });

  it('returns "завтра" for tomorrow', () => {
    expect(getRelativeExpiryText(1, mockT)).toBe("завтра");
  });

  it("returns days remaining for future dates", () => {
    expect(getRelativeExpiryText(5, mockT)).toBe("через 5 дн.");
    expect(getRelativeExpiryText(2, mockT)).toBe("через 2 дн.");
  });
});

describe("formatExpiryDate", () => {
  it("formats date in Russian locale", () => {
    // Note: Output may vary slightly based on locale settings
    const result = formatExpiryDate("2025-01-15");
    expect(result).toContain("15");
    expect(result).toMatch(/янв/i);
  });

  it("handles various date formats", () => {
    const result = formatExpiryDate("2025-12-31");
    expect(result).toContain("31");
    expect(result).toMatch(/дек/i);
  });
});
