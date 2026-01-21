import { useState } from "react";
import { useTranslation } from "react-i18next";
import type { ExpiringEntryResponse } from "../../../api/generated/models";
import { AttentionCard } from "./AttentionCard";

interface AttentionSectionProps {
  items: ExpiringEntryResponse[];
  onDone: (entry: ExpiringEntryResponse) => void;
  onThrow: (entry: ExpiringEntryResponse) => void;
  nextExpiryDays?: number;
}

export function AttentionSection({
  items,
  onDone,
  onThrow,
  nextExpiryDays,
}: AttentionSectionProps) {
  const { t } = useTranslation();
  const [showAll, setShowAll] = useState(false);

  if (items.length === 0) {
    return (
      <section className="mb-8">
        <div className="flex items-center gap-3.5 bg-green-50 border border-green-200 rounded-lg px-5 py-4">
          <span className="text-2xl">&#x2705;</span>
          <div className="flex flex-col gap-0.5">
            <span className="font-semibold text-green-700 text-[15px]">
              {t("stock.allUnderControl")}
            </span>
            <span className="text-stone-600 text-[13px]">
              {nextExpiryDays !== undefined
                ? t("stock.nextExpiry", { days: nextExpiryDays })
                : t("stock.noAttentionNeeded")}
            </span>
          </div>
        </div>
      </section>
    );
  }

  const displayedItems = showAll ? items : items.slice(0, 4);

  return (
    <section className="mb-8">
      <div className="flex justify-between items-center mb-4">
        <h2 className="text-xl font-semibold text-stone-600">
          {t("stock.needToHandleToday")}
        </h2>
        <div className="flex items-center gap-4">
          {items.length > 4 && (
            <button
              type="button"
              onClick={() => setShowAll(!showAll)}
              className="text-[13px] text-stone-500 hover:text-amber-600 transition-colors"
            >
              {showAll
                ? t("stock.collapse")
                : t("stock.showAll", { count: items.length })}
            </button>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        {displayedItems.map((item) => (
          <AttentionCard
            key={item.id}
            entry={item}
            onDone={onDone}
            onThrow={onThrow}
          />
        ))}
      </div>
      <hr className="mt-8 border-stone-200" />
    </section>
  );
}
