import { useTranslation } from "react-i18next";

const LANGUAGES = [
  { code: "ru", label: "Русский" },
  { code: "en", label: "English" },
] as const;

export function LanguageSwitcher() {
  const { i18n } = useTranslation();
  const current = i18n.resolvedLanguage;

  return (
    <div className="inline-flex rounded-lg border border-stone-200 bg-stone-50 p-1">
      {LANGUAGES.map(({ code, label }) => {
        const active = current === code;
        return (
          <button
            key={code}
            type="button"
            onClick={() => void i18n.changeLanguage(code)}
            aria-pressed={active}
            className={`px-4 py-1.5 text-sm rounded-md transition-colors ${
              active
                ? "bg-amber-500 text-white"
                : "text-stone-600 hover:text-stone-900"
            }`}
          >
            {label}
          </button>
        );
      })}
    </div>
  );
}
