import {
  type KeyboardEvent,
  type MouseEvent,
  type ReactElement,
  useState,
} from "react";
import { useTranslation } from "react-i18next";
import type { ShoppingItemResponse } from "@/api/generated/models";

interface ShoppingListItemProps {
  item: ShoppingItemResponse;
  onClick: () => void;
  onDelete: () => void;
  isDeleting?: boolean;
}

export function ShoppingListItem({
  item,
  onClick,
  onDelete,
  isDeleting = false,
}: ShoppingListItemProps): ReactElement {
  const { t } = useTranslation();
  const [isAnimatingOut, setIsAnimatingOut] = useState(false);

  const handleCheckboxClick = (e: MouseEvent) => {
    e.stopPropagation();
    setIsAnimatingOut(true);
    setTimeout(() => {
      onDelete();
    }, 300);
  };

  const handleKeyDown = (e: KeyboardEvent) => {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      onClick();
    }
  };

  return (
    // biome-ignore lint/a11y/useSemanticElements: Contains nested button, can't use button element
    <div
      role="button"
      tabIndex={0}
      onClick={onClick}
      onKeyDown={handleKeyDown}
      className={`p-4 flex items-center gap-4 hover:bg-stone-50 cursor-pointer transition-all duration-300 ${
        isAnimatingOut
          ? "opacity-0 max-h-0 py-0 overflow-hidden"
          : "opacity-100 max-h-24"
      }`}
    >
      <button
        type="button"
        onClick={handleCheckboxClick}
        disabled={isDeleting || isAnimatingOut}
        className="w-6 h-6 rounded-full border-2 border-stone-300 flex items-center justify-center hover:border-green-500 transition-colors disabled:opacity-50"
        aria-label={t("shopping.markBought")}
      />
      <div className="flex-1 min-w-0">
        <p className="font-medium text-stone-800 truncate">{item.name}</p>
        {item.note && (
          <p className="text-sm text-stone-500 truncate">{item.note}</p>
        )}
      </div>
      <span className="text-sm text-stone-500 whitespace-nowrap">
        {t("shopping.amount", { amount: item.amount })}
      </span>
      {item.source === "auto" && (
        <span className="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">
          {t("shopping.auto")}
        </span>
      )}
    </div>
  );
}
