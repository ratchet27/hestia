import toast from "react-hot-toast";
import { useTranslation } from "react-i18next";
import {
  useCategories,
  useCreateCategory,
  useDeleteCategory,
  useRenameCategory,
} from "../../api/queries/categories";
import {
  useCreateLocation,
  useDeleteLocation,
  useLocations,
  useRenameLocation,
} from "../../api/queries/locations";
import {
  useSendTelegramTest,
  useTelegramStatus,
} from "../../api/queries/telegram";
import { LanguageSwitcher } from "../../components/LanguageSwitcher";
import { ManagedList } from "./ManagedList";

export function SettingsPage(): React.ReactElement {
  const { t } = useTranslation();

  const locations = useLocations();
  const createLocation = useCreateLocation();
  const renameLocation = useRenameLocation();
  const deleteLocation = useDeleteLocation();

  const categories = useCategories();
  const createCategory = useCreateCategory();
  const renameCategory = useRenameCategory();
  const deleteCategory = useDeleteCategory();

  const telegram = useTelegramStatus();
  const sendTest = useSendTelegramTest();

  const onTest = async () => {
    try {
      const result = await sendTest.mutateAsync();
      if (result?.ok) toast.success(t("settings.telegramTestOk"));
      else toast.error(t("settings.telegramTestFailed"));
    } catch {
      toast.error(t("settings.telegramTestFailed"));
    }
  };

  return (
    <div className="p-8">
      <div className="mb-6">
        <h2 className="text-3xl font-bold text-stone-800">
          {t("settings.title")}
        </h2>
        <p className="text-stone-500 mt-1">{t("settings.subtitle")}</p>
      </div>

      <div className="grid max-w-5xl grid-cols-1 items-start gap-6 lg:grid-cols-2">
        {/* Left column: language + storage locations */}
        <div className="space-y-6">
          <div className="bg-white rounded-xl p-6 shadow-sm border border-stone-200">
            <h3 className="font-semibold text-stone-800 mb-4">
              {t("settings.language")}
            </h3>
            <LanguageSwitcher />
          </div>

          <ManagedList
            title={t("settings.locations")}
            items={locations.data ?? []}
            onAdd={(name) => createLocation.mutateAsync(name)}
            onRename={(id, name) => renameLocation.mutateAsync({ id, name })}
            onDelete={(id) => deleteLocation.mutateAsync(id)}
          />
        </div>

        {/* Right column: telegram + categories */}
        <div className="space-y-6">
          <div className="bg-white rounded-xl p-6 shadow-sm border border-stone-200">
            <h3 className="font-semibold text-stone-800 mb-4">
              {t("settings.telegram")}
            </h3>
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-stone-700">
                  {telegram.data?.configured
                    ? t("settings.telegramConfigured")
                    : t("settings.telegramNotConfigured")}
                </span>
                {telegram.data?.daily_summary_time && (
                  <span className="text-sm text-stone-500">
                    {t("settings.telegramDailyTime")}:{" "}
                    {telegram.data.daily_summary_time}
                  </span>
                )}
              </div>
              <button
                type="button"
                onClick={onTest}
                disabled={!telegram.data?.configured || sendTest.isPending}
                className="px-4 py-2 text-sm rounded-lg bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-40"
              >
                {t("settings.telegramSendTest")}
              </button>
            </div>
          </div>

          <ManagedList
            title={t("settings.categories")}
            items={categories.data ?? []}
            onAdd={(name) => createCategory.mutateAsync(name)}
            onRename={(id, name) => renameCategory.mutateAsync({ id, name })}
            onDelete={(id) => deleteCategory.mutateAsync(id)}
          />
        </div>
      </div>
    </div>
  );
}
