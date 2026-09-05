import i18n from "../i18n";

const DAY_MS = 24 * 60 * 60 * 1000;
const DATE_ONLY = /^\d{4}-\d{2}-\d{2}$/;

// The API sends either a date-only string ("2025-01-15", best_before) or an
// ISO datetime (next_due_at, due_date). Date-only strings are parsed as LOCAL
// midnight; `new Date("2025-01-15")` would treat them as UTC and shift the day
// in any timezone east of Greenwich.
function parse(dateStr: string): Date {
  if (DATE_ONLY.test(dateStr)) {
    const [year, month, day] = dateStr.split("-").map(Number) as [
      number,
      number,
      number,
    ];
    return new Date(year, month - 1, day);
  }
  return new Date(dateStr);
}

function startOfDay(date: Date): Date {
  const copy = new Date(date);
  copy.setHours(0, 0, 0, 0);
  return copy;
}

/** Whole days from local today to the given date; negative when in the past. */
export function getDaysUntil(dateStr: string | null): number {
  if (!dateStr) return Infinity;
  const today = startOfDay(new Date());
  const target = startOfDay(parse(dateStr));
  return Math.round((target.getTime() - today.getTime()) / DAY_MS);
}

/** "15 янв." / "Jan 15" in the active UI language. */
export function formatShortDate(dateStr: string | null): string {
  if (!dateStr) return "—";
  return parse(dateStr).toLocaleDateString(i18n.resolvedLanguage ?? "ru", {
    day: "numeric",
    month: "short",
  });
}
