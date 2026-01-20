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
  const date = new Date(dateStr);
  return date.toLocaleDateString("ru-RU", { day: "numeric", month: "short" });
}

export const statusColors: Record<ExpiryStatus, string> = {
  expired: "text-red-600",
  today: "text-orange-600",
  soon: "text-amber-600",
  warning: "text-yellow-600",
  ok: "text-green-600",
};

export const statusBorderColors: Record<ExpiryStatus, string> = {
  expired: "border-l-red-500",
  today: "border-l-orange-500",
  soon: "border-l-yellow-500",
  warning: "border-l-yellow-400",
  ok: "border-l-green-500",
};

export const statusRowBg: Record<ExpiryStatus, string> = {
  expired: "bg-red-50",
  today: "bg-orange-50",
  soon: "bg-amber-50",
  warning: "",
  ok: "",
};
