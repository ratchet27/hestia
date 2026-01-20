import { useState } from "react";
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
  const [showAll, setShowAll] = useState(false);

  if (items.length === 0) {
    return (
      <section className="mb-8">
        <div className="flex items-center gap-3.5 bg-green-50 border border-green-200 rounded-lg px-5 py-4">
          <span className="text-2xl">&#x2705;</span>
          <div className="flex flex-col gap-0.5">
            <span className="font-semibold text-green-700 text-[15px]">
              Все под контролем
            </span>
            <span className="text-stone-600 text-[13px]">
              {nextExpiryDays !== undefined
                ? `Следующее истекает через ${nextExpiryDays} дней`
                : "Нет продуктов, требующих внимания"}
            </span>
          </div>
        </div>
      </section>
    );
  }

  const displayedItems = showAll ? items : items.slice(0, 3);

  return (
    <section className="mb-8">
      <div className="flex justify-between items-center mb-4">
        <h2 className="text-[15px] font-semibold text-stone-600">
          &#x1f550; Нужно разобраться сегодня
        </h2>
        <div className="flex items-center gap-4">
          {items.length > 3 && (
            <button
              type="button"
              onClick={() => setShowAll(!showAll)}
              className="text-[13px] text-stone-500 hover:text-amber-600 transition-colors"
            >
              {showAll ? "Свернуть" : `Показать все (${items.length})`}
            </button>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        {displayedItems.map((item) => (
          <AttentionCard
            key={item.id}
            entry={item}
            onDone={onDone}
            onThrow={onThrow}
          />
        ))}
      </div>
    </section>
  );
}
