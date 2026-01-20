import { useTranslation } from "react-i18next";

export function LanguageSwitcher() {
  const { i18n } = useTranslation();

  // Normalize language code (e.g., "en-US" -> "en")
  const currentLang = i18n.language?.slice(0, 2) || "ru";

  return (
    <select
      value={currentLang}
      onChange={(e) => i18n.changeLanguage(e.target.value)}
      className="text-sm bg-stone-800 text-stone-300 border border-stone-600 rounded px-2 py-1 hover:border-stone-500 focus:outline-none focus:border-amber-500"
    >
      <option value="ru">RU</option>
      <option value="en">EN</option>
    </select>
  );
}
