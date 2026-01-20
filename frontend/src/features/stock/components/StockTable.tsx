import { useTranslation } from "react-i18next";
import type { StockEntryResponse } from "../../../api/generated/models";
import { StockRow } from "./StockRow";

interface StockTableProps {
  entries: StockEntryResponse[];
  onConsume: (entry: StockEntryResponse) => void;
  isLoading?: boolean;
}

export function StockTable({ entries, onConsume, isLoading }: StockTableProps) {
  const { t } = useTranslation();

  if (isLoading) {
    return (
      <div className="bg-white rounded-xl shadow-sm border border-stone-200 p-8 text-center text-stone-500">
        {t("common.loading")}
      </div>
    );
  }

  if (entries.length === 0) {
    return (
      <div className="bg-white rounded-xl shadow-sm border border-stone-200 p-8 text-center text-stone-500">
        {t("common.noItems")}
      </div>
    );
  }

  return (
    <div className="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
      <table className="w-full">
        <thead className="bg-stone-50 border-b border-stone-200">
          <tr>
            <th className="text-left px-4 py-3 text-xs font-semibold text-stone-500 uppercase tracking-wide">
              {t("stock.product")}
            </th>
            <th className="text-left px-4 py-3 text-xs font-semibold text-stone-500 uppercase tracking-wide">
              {t("stock.quantity")}
            </th>
            <th className="text-left px-4 py-3 text-xs font-semibold text-stone-500 uppercase tracking-wide">
              {t("stock.bestBefore")}
            </th>
            <th className="w-16" />
          </tr>
        </thead>
        <tbody>
          {entries.map((entry) => (
            <StockRow key={entry.id} entry={entry} onConsume={onConsume} />
          ))}
        </tbody>
      </table>
    </div>
  );
}
