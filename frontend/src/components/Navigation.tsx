import { NavLink } from "react-router-dom";
import { Icons } from "./Icons";
import { UserProfile } from "./UserProfile";

// TODO: Move date/time display (and possibly weather) to top-right corner of main content area

const navItems = [
  { path: "/", label: "Главная", icon: Icons.Dashboard },
  { path: "/stock", label: "Запасы", icon: Icons.Stock },
  { path: "/products", label: "Товары", icon: Icons.Products },
  { path: "/shopping", label: "Покупки", icon: Icons.Shopping },
  { path: "/recipes", label: "Рецепты", icon: Icons.Recipes },
  { path: "/tasks", label: "Задачи", icon: Icons.Tasks },
  { path: "/settings", label: "Настройки", icon: Icons.Settings },
];

export function Navigation(): React.ReactElement {
  return (
    <nav className="fixed left-0 top-0 h-full w-56 bg-stone-900 text-stone-300 flex flex-col">
      <div className="p-5 border-b border-stone-700">
        <h1 className="text-xl font-bold text-amber-500 tracking-tight">
          Hestia
        </h1>
        <p className="text-xs text-stone-500 mt-1">Домашний учёт</p>
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
              <span className="font-medium">{item.label}</span>
            </NavLink>
          );
        })}
      </div>
      <UserProfile />
    </nav>
  );
}
