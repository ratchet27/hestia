import type { TFunction } from "i18next";

export type ExpiryStatus = "expired" | "today" | "soon" | "warning" | "ok";

export function getExpiryStatus(daysUntilExpiry: number): ExpiryStatus {
  if (daysUntilExpiry < 0) return "expired";
  if (daysUntilExpiry === 0) return "today";
  if (daysUntilExpiry <= 2) return "soon";
  if (daysUntilExpiry <= 7) return "warning";
  return "ok";
}

export function getRelativeExpiryText(
  daysUntilExpiry: number,
  t: TFunction,
): string {
  if (daysUntilExpiry < -1)
    return t("expiry.daysAgo", { count: Math.abs(daysUntilExpiry) });
  if (daysUntilExpiry === -1) return t("expiry.yesterday");
  if (daysUntilExpiry === 0) return t("expiry.today");
  if (daysUntilExpiry === 1) return t("expiry.tomorrow");
  return t("expiry.inDays", { count: daysUntilExpiry });
}

export function formatExpiryDate(dateStr: string): string {
  // Parse as local date (not UTC) to avoid timezone shifts
  const [year, month, day] = dateStr.split("-").map(Number) as [
    number,
    number,
    number,
  ];
  const date = new Date(year, month - 1, day);
  return date.toLocaleDateString("ru-RU", { day: "numeric", month: "short" });
}

export const statusColors: Record<ExpiryStatus, string> = {
  expired: "text-red-700",
  today: "text-orange-700",
  soon: "text-amber-700",
  warning: "text-yellow-700",
  ok: "text-green-700",
};

export const statusBorderColors: Record<ExpiryStatus, string> = {
  expired: "border-l-red-600",
  today: "border-l-orange-600",
  soon: "border-l-amber-500",
  warning: "border-l-yellow-500",
  ok: "border-l-green-600",
};

export const statusRowBg: Record<ExpiryStatus, string> = {
  expired: "bg-red-100",
  today: "bg-orange-100",
  soon: "bg-amber-100",
  warning: "bg-yellow-50",
  ok: "",
};
