import { type FormEvent, type ReactElement, useState } from "react";
import { useTranslation } from "react-i18next";
import { Navigate } from "react-router-dom";
import { ApiError } from "../../api/client";
import { useAuth } from "../../data/hooks";

export function LoginPage(): ReactElement {
  const { t } = useTranslation();
  const { user, login } = useAuth();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (user) {
    return <Navigate to="/" replace />;
  }

  const handleSubmit = async (e: FormEvent): Promise<void> => {
    e.preventDefault();
    setError("");

    if (!username.trim()) {
      setError(t("auth.usernameRequired"));
      return;
    }

    if (!password.trim()) {
      setError(t("auth.passwordRequired"));
      return;
    }

    setIsSubmitting(true);
    try {
      await login(username, password);
    } catch (err) {
      if (err instanceof ApiError && err.status === 429) {
        setError(t("auth.tooManyAttempts"));
      } else if (err instanceof ApiError && err.status === 401) {
        setError(t("auth.invalidCredentials"));
      } else {
        setError(t("auth.genericError"));
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen bg-stone-900 flex items-center justify-center p-4">
      <div className="w-full max-w-md">
        <div className="text-center mb-8">
          <h1 className="text-3xl font-bold text-amber-500">Hestia</h1>
          <p className="text-stone-500 mt-2">{t("auth.appTagline")}</p>
        </div>

        <form
          onSubmit={handleSubmit}
          className="bg-stone-800 rounded-lg p-6 shadow-lg"
        >
          <h2 className="text-xl font-semibold text-stone-200 mb-6">
            {t("auth.title")}
          </h2>

          {error && (
            <div className="mb-4 p-3 bg-red-900/50 border border-red-700 rounded text-red-200 text-sm">
              {error}
            </div>
          )}

          <div className="mb-4">
            <label
              htmlFor="username"
              className="block text-sm font-medium text-stone-300 mb-2"
            >
              {t("auth.username")}
            </label>
            <input
              type="text"
              id="username"
              name="username"
              autoComplete="username"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              className="w-full px-3 py-2 bg-stone-700 border border-stone-600 rounded text-stone-200 placeholder-stone-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent"
              placeholder={t("auth.usernamePlaceholder")}
            />
          </div>

          <div className="mb-4">
            <label
              htmlFor="password"
              className="block text-sm font-medium text-stone-300 mb-2"
            >
              {t("auth.password")}
            </label>
            <input
              type="password"
              id="password"
              name="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full px-3 py-2 bg-stone-700 border border-stone-600 rounded text-stone-200 placeholder-stone-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent"
              placeholder={t("auth.passwordPlaceholder")}
            />
          </div>

          <button
            type="submit"
            disabled={isSubmitting}
            className="w-full py-2 px-4 bg-amber-600 hover:bg-amber-500 text-white font-medium rounded transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
          >
            {isSubmitting ? t("auth.submitting") : t("auth.submit")}
          </button>
        </form>
      </div>
    </div>
  );
}
