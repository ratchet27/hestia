import { useTranslation } from "react-i18next";
import { useNavigate } from "react-router-dom";
import { useProducts } from "../../api/queries";
import { useExpiringStock, useStockEntries } from "../../api/queries/stocks";
import { Icons } from "../../components/Icons";
import { useChores, useShoppingList, useTasks } from "../../data/hooks";
import { formatDate, getDaysUntil } from "../../data/types";
import {
  getExpiryStatus,
  getRelativeExpiryText,
} from "../stock/utils/expiryStatus";

export function DashboardPage(): React.ReactElement {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data: products = [] } = useProducts();
  const { data: stockEntries = [] } = useStockEntries();
  const { data: expiringItems = [] } = useExpiringStock(7);
  const { shoppingList } = useShoppingList();
  const { chores } = useChores();
  const { tasks } = useTasks();

  // Low stock calculation
  const lowStockItems = products
    .filter((p) => p.min_stock > 0)
    .map((p) => {
      const totalStock = stockEntries.filter(
        (e) => e.product.id === p.id,
      ).length;
      return { ...p, totalStock, isLow: totalStock < p.min_stock };
    })
    .filter((p) => p.isLow);

  const todayChores = chores.filter((c) => getDaysUntil(c.nextDue) <= 0);
  const upcomingTasks = tasks.filter((t) => !t.done).slice(0, 3);
  const shoppingCount = shoppingList.filter((i) => !i.done).length;

  return (
    <div className="p-8">
      <div className="mb-8">
        <h2 className="text-3xl font-bold text-stone-800">
          {t("dashboard.welcome")}
        </h2>
        <p className="text-stone-500 mt-1">{t("dashboard.subtitle")}</p>
      </div>

      <div className="grid grid-cols-4 gap-4 mb-8">
        <div className="bg-white rounded-xl p-5 shadow-sm border border-stone-200">
          <div className="text-3xl font-bold text-stone-800">
            {stockEntries.length}
          </div>
          <div className="text-sm text-stone-500">
            {t("dashboard.stockItems")}
          </div>
        </div>
        <button
          type="button"
          className="bg-white rounded-xl p-5 shadow-sm border border-stone-200 cursor-pointer hover:border-amber-400 transition-colors text-left"
          onClick={() => navigate("/shopping")}
        >
          <div className="text-3xl font-bold text-amber-600">
            {shoppingCount}
          </div>
          <div className="text-sm text-stone-500">
            {t("dashboard.shoppingItems")}
          </div>
        </button>
        <div className="bg-white rounded-xl p-5 shadow-sm border border-stone-200">
          <div className="text-3xl font-bold text-red-500">
            {expiringItems.length}
          </div>
          <div className="text-sm text-stone-500">
            {t("dashboard.expiringItems")}
          </div>
        </div>
        <div className="bg-white rounded-xl p-5 shadow-sm border border-stone-200">
          <div className="text-3xl font-bold text-blue-500">
            {todayChores.length}
          </div>
          <div className="text-sm text-stone-500">
            {t("dashboard.todayTasks")}
          </div>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-6">
        <div className="bg-white rounded-xl shadow-sm border border-stone-200">
          <div className="p-4 border-b border-stone-100 flex items-center justify-between">
            <h3 className="font-semibold text-stone-800 flex items-center gap-2">
              <span className="text-red-500">
                <Icons.Warning />
              </span>
              {t("dashboard.expiringProducts")}
            </h3>
            <button
              type="button"
              onClick={() => navigate("/stock")}
              className="text-sm text-amber-600 hover:underline"
            >
              {t("dashboard.viewAllStock")}
            </button>
          </div>
          <div className="p-4">
            {expiringItems.length === 0 ? (
              <p className="text-stone-500 text-sm">{t("dashboard.allGood")}</p>
            ) : (
              <div className="space-y-3">
                {expiringItems.slice(0, 5).map((item) => {
                  const status = getExpiryStatus(item.days_until_expiry);
                  return (
                    <div
                      key={item.id}
                      className="flex items-center justify-between"
                    >
                      <div>
                        <p className="font-medium text-stone-800">
                          {item.product.name}
                        </p>
                        <p className="text-sm text-stone-500">
                          1 {item.product.unit} · {item.location.name}
                        </p>
                      </div>
                      <span
                        className={`px-3 py-1 rounded-full text-sm font-medium ${
                          status === "expired"
                            ? "bg-red-100 text-red-700"
                            : status === "today"
                              ? "bg-orange-100 text-orange-700"
                              : "bg-yellow-100 text-yellow-700"
                        }`}
                      >
                        {getRelativeExpiryText(item.days_until_expiry, t)}
                      </span>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-stone-200">
          <div className="p-4 border-b border-stone-100 flex items-center justify-between">
            <h3 className="font-semibold text-stone-800">
              {t("dashboard.lowStock")}
            </h3>
            <button
              type="button"
              onClick={() => navigate("/products")}
              className="text-sm text-amber-600 hover:underline"
            >
              {t("dashboard.viewAllProducts")}
            </button>
          </div>
          <div className="p-4">
            {lowStockItems.length === 0 ? (
              <p className="text-stone-500 text-sm">
                {t("dashboard.enoughStock")}
              </p>
            ) : (
              <div className="space-y-3">
                {lowStockItems.map((item) => (
                  <div
                    key={item.id}
                    className="flex items-center justify-between"
                  >
                    <div>
                      <p className="font-medium text-stone-800">{item.name}</p>
                      <p className="text-sm text-stone-500">
                        {t("dashboard.min")}
                        {item.min_stock}
                      </p>
                    </div>
                    <span className="px-3 py-1 rounded-full text-sm font-medium bg-amber-100 text-amber-700">
                      {t("dashboard.remaining")}
                      {item.totalStock}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-stone-200">
          <div className="p-4 border-b border-stone-100 flex items-center justify-between">
            <h3 className="font-semibold text-stone-800">
              {t("dashboard.todayTasksSection")}
            </h3>
            <button
              type="button"
              onClick={() => navigate("/tasks")}
              className="text-sm text-amber-600 hover:underline"
            >
              {t("dashboard.viewAllTasks")}
            </button>
          </div>
          <div className="p-4">
            {todayChores.length === 0 ? (
              <p className="text-stone-500 text-sm">
                {t("dashboard.noTasksToday")}
              </p>
            ) : (
              <div className="space-y-3">
                {todayChores.map((chore) => (
                  <div
                    key={chore.id}
                    className="flex items-center justify-between"
                  >
                    <p className="font-medium text-stone-800">{chore.name}</p>
                    <button
                      type="button"
                      className="px-3 py-1 bg-green-500 text-white rounded-lg text-sm hover:bg-green-600 transition-colors"
                    >
                      {t("dashboard.completed")}
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-stone-200">
          <div className="p-4 border-b border-stone-100 flex items-center justify-between">
            <h3 className="font-semibold text-stone-800">
              {t("dashboard.upcomingTasks")}
            </h3>
            <button
              type="button"
              onClick={() => navigate("/tasks")}
              className="text-sm text-amber-600 hover:underline"
            >
              {t("dashboard.viewAllTasks")}
            </button>
          </div>
          <div className="p-4">
            {upcomingTasks.length === 0 ? (
              <p className="text-stone-500 text-sm">
                {t("dashboard.noActiveTasks")}
              </p>
            ) : (
              <div className="space-y-3">
                {upcomingTasks.map((task) => (
                  <div
                    key={task.id}
                    className="flex items-center justify-between"
                  >
                    <p className="font-medium text-stone-800">{task.name}</p>
                    <span className="text-sm text-stone-500">
                      {formatDate(task.dueDate)}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
