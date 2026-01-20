import { useTranslation } from "react-i18next";
import { Icons } from "../../../components/Icons";

interface StockPageHeaderProps {
  expiredCount: number;
  soonCount: number;
  onScanClick: () => void;
  onAddClick: () => void;
}

export function StockPageHeader({
  expiredCount,
  soonCount,
  onScanClick,
  onAddClick,
}: StockPageHeaderProps) {
  const { t } = useTranslation();

  const getSubtitle = () => {
    if (expiredCount > 0) {
      return t("stock.expiredCount", { count: expiredCount });
    }
    if (soonCount > 0) {
      return t("stock.soonExpiring", { count: soonCount });
    }
    return t("stock.allOk");
  };

  return (
    <header className="flex justify-between items-start mb-8">
      <div>
        <h1 className="text-[28px] font-semibold text-stone-800 mb-1">
          {t("stock.title")}
        </h1>
        <p className="text-[15px] text-stone-500">{getSubtitle()}</p>
      </div>
      <div className="flex gap-3">
        <button
          type="button"
          onClick={onScanClick}
          className="flex items-center gap-2 px-5 py-2.5 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors text-sm font-medium"
        >
          <Icons.Scan />
          {t("stock.scan")}
        </button>
        <button
          type="button"
          onClick={onAddClick}
          className="flex items-center gap-2 px-5 py-2.5 bg-stone-800 text-white rounded-lg hover:bg-stone-700 transition-colors text-sm font-medium"
        >
          <Icons.Plus />
          {t("common.add")}
        </button>
      </div>
    </header>
  );
}
