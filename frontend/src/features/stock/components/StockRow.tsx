import type { StockEntryResponse } from "../../../api/generated/models";
import {
  formatExpiryDate,
  getExpiryStatus,
  getRelativeExpiryText,
  statusColors,
  statusRowBg,
} from "../utils/expiryStatus";

interface StockRowProps {
  entry: StockEntryResponse;
  onConsume: (entry: StockEntryResponse) => void;
}

function getDaysUntil(dateStr: string | null | undefined): number {
  if (!dateStr) return Infinity;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const target = new Date(dateStr);
  return Math.ceil(
    (target.getTime() - today.getTime()) / (1000 * 60 * 60 * 24),
  );
}

export function StockRow({ entry, onConsume }: StockRowProps) {
  const days = getDaysUntil(entry.best_before);
  const status = entry.best_before ? getExpiryStatus(days) : "ok";
  const relativeText = entry.best_before ? getRelativeExpiryText(days) : null;
  const dateText = entry.best_before
    ? formatExpiryDate(entry.best_before)
    : null;

  return (
    <tr
      className={`border-b border-stone-100 hover:bg-stone-50 ${statusRowBg[status]}`}
    >
      <td className="px-4 py-3.5">
        <div className="flex items-center gap-2.5">
          <span className="font-medium text-stone-800">
            {entry.product.name}
          </span>
        </div>
      </td>
      <td className="px-4 py-3.5 text-stone-600">1 {entry.product.unit}</td>
      <td className="px-4 py-3.5">
        {entry.best_before ? (
          <div className="flex flex-col">
            <span className={`font-medium text-[13px] ${statusColors[status]}`}>
              {status === "expired" && "\u26a0\ufe0f "}
              {status === "today" && "\u23f0 "}
              {relativeText}
            </span>
            <span className="text-[11px] text-stone-400 mt-0.5">
              ({dateText})
            </span>
          </div>
        ) : (
          <span className="text-stone-400">&mdash;</span>
        )}
      </td>
      <td className="px-4 py-3.5 text-right">
        <button
          type="button"
          onClick={() => onConsume(entry)}
          className="w-7 h-7 rounded border border-stone-200 bg-white text-stone-500 hover:bg-green-50 hover:border-green-200 hover:text-green-600 transition-colors text-sm"
          title="Использовано"
        >
          &#x2713;
        </button>
      </td>
    </tr>
  );
}
