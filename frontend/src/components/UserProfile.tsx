import type { ReactElement } from "react";
import { useTranslation } from "react-i18next";
import { useAuth } from "../data/hooks";

export function UserProfile(): ReactElement | null {
  const { t } = useTranslation();
  const { user, logout } = useAuth();

  if (!user) {
    return null;
  }

  return (
    <div className="p-4 border-t border-stone-700">
      <div className="flex items-center justify-between">
        <div className="min-w-0 flex-1">
          <p className="text-sm font-medium text-stone-200 truncate">
            {user.name}
          </p>
          <p className="text-xs text-stone-500 truncate">@{user.username}</p>
        </div>
        <button
          type="button"
          onClick={logout}
          className="ml-2 px-2 py-1 text-xs text-stone-400 hover:text-stone-200 hover:bg-stone-700 rounded transition-colors"
        >
          {t("common.logout")}
        </button>
      </div>
    </div>
  );
}
