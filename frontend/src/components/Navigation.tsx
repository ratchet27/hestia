import { useTranslation } from "react-i18next";
import { NavLink } from "react-router-dom";
import { Icons } from "./Icons";
import { UserProfile } from "./UserProfile";

// TODO: Move date/time display (and possibly weather) to top-right corner of main content area

const navItems = [
  { path: "/", labelKey: "nav.home", icon: Icons.Dashboard },
  { path: "/stock", labelKey: "nav.stock", icon: Icons.Stock },
  { path: "/products", labelKey: "nav.products", icon: Icons.Products },
  { path: "/shopping", labelKey: "nav.shopping", icon: Icons.Shopping },
  { path: "/recipes", labelKey: "nav.recipes", icon: Icons.Recipes },
  { path: "/tasks", labelKey: "nav.tasks", icon: Icons.Tasks },
  { path: "/settings", labelKey: "nav.settings", icon: Icons.Settings },
];

export function Navigation(): React.ReactElement {
  const { t } = useTranslation();

  return (
    <nav className="fixed left-0 top-0 h-full w-56 bg-stone-900 text-stone-300 flex flex-col">
      <div className="p-5 border-b border-stone-700">
        <h1 className="text-xl font-bold text-amber-500 tracking-tight">
          Hestia
        </h1>
        <p className="text-xs text-stone-500 mt-1">{t("nav.tagline")}</p>
      </div>
      <div className="flex-1 py-4">
        {navItems.map((item) => {
          const Icon = item.icon;
          return (
            <NavLink
              key={item.path}
              to={item.path}
              className={({ isActive }) =>
                `w-full flex items-center gap-3 px-5 py-3 text-left transition-all ${
                  isActive
                    ? "bg-amber-600 text-white"
                    : "hover:bg-stone-800 hover:text-white"
                }`
              }
            >
              <Icon />
              <span className="font-medium">{t(item.labelKey)}</span>
            </NavLink>
          );
        })}
      </div>
      <UserProfile />
    </nav>
  );
}
