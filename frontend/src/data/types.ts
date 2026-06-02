export interface User {
  id: string;
  name: string;
  username: string;
  email: string | null;
  roles: string[];
}

export interface StockEntry {
  id: number;
  productId: number;
  amount: number;
  bestBefore: string;
  purchasedDate: string;
  location: string;
  note: string;
}

export interface Recipe {
  id: number;
  name: string;
  ingredients: { productId: number; amount: number }[];
}

export const locations: Record<string, string> = {
  fridge: "Холодильник",
  pantry: "Кладовая",
  bathroom: "Ванная",
  other: "Другое",
};

// Utility functions
export function formatDate(dateStr: string | null): string {
  if (!dateStr) return "—";
  const date = new Date(dateStr);
  return date.toLocaleDateString("ru-RU", { day: "numeric", month: "short" });
}

export function getDaysUntil(dateStr: string | null): number {
  if (!dateStr) return Infinity;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const target = new Date(dateStr);
  target.setHours(0, 0, 0, 0);
  return Math.round(
    (target.getTime() - today.getTime()) / (1000 * 60 * 60 * 24),
  );
}

export function getExpiryStatus(
  dateStr: string,
): "expired" | "critical" | "warning" | "ok" {
  const days = getDaysUntil(dateStr);
  if (days < 0) return "expired";
  if (days <= 2) return "critical";
  if (days <= 7) return "warning";
  return "ok";
}
