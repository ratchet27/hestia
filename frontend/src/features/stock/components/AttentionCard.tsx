import { useTranslation } from "react-i18next";
import type { ExpiringEntryResponse } from "../../../api/generated/models";
import {
  formatExpiryDate,
  getExpiryStatus,
  getRelativeExpiryText,
  statusBorderColors,
  statusColors,
} from "../utils/expiryStatus";

interface AttentionCardProps {
  entry: ExpiringEntryResponse;
  onDone: (entry: ExpiringEntryResponse) => void;
  onThrow: (entry: ExpiringEntryResponse) => void;
}

export function AttentionCard({ entry, onDone, onThrow }: AttentionCardProps) {
  const { t } = useTranslation();
  const status = getExpiryStatus(entry.days_until_expiry);
  const relativeText = getRelativeExpiryText(entry.days_until_expiry, t);
  const dateText = formatExpiryDate(entry.best_before);

  return (
    <div
      className={`bg-white rounded-lg p-3 border-l-[3px] shadow-sm flex flex-col h-36 ${statusBorderColors[status]}`}
    >
      <div className="flex items-center gap-1.5">
        <span className="font-medium text-sm flex-1 text-stone-800">
          {entry.product.name}
        </span>
        <span className="text-xs text-stone-500 bg-stone-100 px-1.5 py-0.5 rounded">
          {entry.location.name}
        </span>
      </div>

      <div className="flex justify-between items-start mt-2 text-[13px]">
        <span className="text-stone-500">1 {entry.product.unit}</span>
        <div className="flex flex-col items-end">
          <span className={`font-medium ${statusColors[status]}`}>
            {status === "expired" && "\u26a0\ufe0f "}
            {status === "today" && "\u23f0 "}
            {relativeText}
          </span>
          <span className="text-[11px] text-stone-400 mt-0.5">
            ({dateText})
          </span>
        </div>
      </div>

      <div className="flex gap-1.5 mt-auto">
        <button
          type="button"
          onClick={() => onDone(entry)}
          className="flex-1 px-2.5 py-1 rounded text-xs bg-green-300 text-green-800 border border-green-500 hover:bg-green-400 transition-colors"
        >
          {t("common.done")}
        </button>
        <button
          type="button"
          onClick={() => onThrow(entry)}
          className="flex-1 px-2.5 py-1 rounded text-xs bg-red-300 text-red-800 border border-red-500 hover:bg-red-400 transition-colors"
        >
          {t("stock.throw")}
        </button>
      </div>
    </div>
  );
}
