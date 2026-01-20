import { useTranslation } from "react-i18next";
import type { LocationResponse } from "../../../api/generated/models";

interface LocationTabsProps {
  locations: LocationResponse[];
  selectedLocationId: string | null;
  onSelect: (locationId: string | null) => void;
  counts: Record<string, number>;
  totalCount: number;
}

export function LocationTabs({
  locations,
  selectedLocationId,
  onSelect,
  counts,
  totalCount,
}: LocationTabsProps) {
  const { t } = useTranslation();

  return (
    <div className="flex gap-2 border-b border-stone-200 mb-4">
      <button
        type="button"
        onClick={() => onSelect(null)}
        className={`px-4 py-2.5 text-sm border-b-2 -mb-px transition-colors ${
          selectedLocationId === null
            ? "text-amber-600 border-amber-500 font-medium"
            : "text-stone-500 border-transparent hover:text-stone-700"
        }`}
      >
        {t("common.all")}
        <span
          className={`ml-1.5 px-1.5 py-0.5 rounded-full text-xs ${
            selectedLocationId === null
              ? "bg-amber-100 text-amber-600"
              : "bg-stone-100 text-stone-500"
          }`}
        >
          {totalCount}
        </span>
      </button>

      {locations.map((location) => (
        <button
          key={location.id}
          type="button"
          onClick={() => onSelect(location.id)}
          className={`px-4 py-2.5 text-sm border-b-2 -mb-px transition-colors ${
            selectedLocationId === location.id
              ? "text-amber-600 border-amber-500 font-medium"
              : "text-stone-500 border-transparent hover:text-stone-700"
          }`}
        >
          {location.name}
          <span
            className={`ml-1.5 px-1.5 py-0.5 rounded-full text-xs ${
              selectedLocationId === location.id
                ? "bg-amber-100 text-amber-600"
                : "bg-stone-100 text-stone-500"
            }`}
          >
            {counts[location.id] ?? 0}
          </span>
        </button>
      ))}
    </div>
  );
}
