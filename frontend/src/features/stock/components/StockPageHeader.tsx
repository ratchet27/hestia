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
  const getSubtitle = () => {
    if (expiredCount > 0) {
      return `Есть ${expiredCount} просроченных`;
    }
    if (soonCount > 0) {
      return `${soonCount} скоро истекают`;
    }
    return "Все в порядке";
  };

  return (
    <header className="flex justify-between items-start mb-8">
      <div>
        <h1 className="text-[28px] font-semibold text-stone-800 mb-1">
          Запасы
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
          Сканировать
        </button>
        <button
          type="button"
          onClick={onAddClick}
          className="flex items-center gap-2 px-5 py-2.5 bg-stone-800 text-white rounded-lg hover:bg-stone-700 transition-colors text-sm font-medium"
        >
          <Icons.Plus />
          Добавить
        </button>
      </div>
    </header>
  );
}
