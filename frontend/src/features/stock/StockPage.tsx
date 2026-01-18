import { useState } from "react";
import { useProducts } from "../../api/queries";
import { Icons } from "../../components/Icons";
import { useStock } from "../../data/hooks";
import {
  formatDate,
  getDaysUntil,
  getExpiryStatus,
  locations,
} from "../../data/types";

export function StockPage(): React.ReactElement {
  const { data: products = [] } = useProducts();
  const { stock } = useStock();
  const [searchTerm, setSearchTerm] = useState("");
  const [locationFilter, setLocationFilter] = useState("all");
  const [showAddModal, setShowAddModal] = useState(false);

  // Note: Stock entries use number IDs, API products use string UUIDs
  // This integration will work properly when stock API is ready
  const enrichedStock = stock
    .map((e) => ({
      ...e,
      // Products have UUID, stock has number ID - won't match until stock API integrated
      product: undefined as { name: string; category: string } | undefined,
    }))
    .filter((e) => locationFilter === "all" || e.location === locationFilter)
    .sort((a, b) => getDaysUntil(a.bestBefore) - getDaysUntil(b.bestBefore));

  return (
    <div className="p-8">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-3xl font-bold text-stone-800">Запасы</h2>
          <p className="text-stone-500 mt-1">Управление домашними запасами</p>
        </div>
        <div className="flex gap-3">
          <button
            type="button"
            onClick={() => setShowAddModal(true)}
            className="flex items-center gap-2 px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors"
          >
            <Icons.Scan />
            Сканировать
          </button>
          <button
            type="button"
            onClick={() => setShowAddModal(true)}
            className="flex items-center gap-2 px-4 py-2 bg-stone-800 text-white rounded-lg hover:bg-stone-700 transition-colors"
          >
            <Icons.Plus />
            Добавить
          </button>
        </div>
      </div>

      <div className="flex gap-4 mb-6">
        <div className="relative flex-1">
          <span className="absolute left-3 top-1/2 -translate-y-1/2 text-stone-400">
            <Icons.Search />
          </span>
          <input
            type="text"
            placeholder="Поиск по названию..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-10 pr-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
          />
        </div>
        <select
          value={locationFilter}
          onChange={(e) => setLocationFilter(e.target.value)}
          className="px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        >
          <option value="all">Все места</option>
          {Object.entries(locations).map(([key, label]) => (
            <option key={key} value={key}>
              {label}
            </option>
          ))}
        </select>
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
        <table className="w-full">
          <thead className="bg-stone-50 border-b border-stone-200">
            <tr>
              <th className="text-left px-4 py-3 text-sm font-semibold text-stone-600">
                Товар
              </th>
              <th className="text-left px-4 py-3 text-sm font-semibold text-stone-600">
                Количество
              </th>
              <th className="text-left px-4 py-3 text-sm font-semibold text-stone-600">
                Место
              </th>
              <th className="text-left px-4 py-3 text-sm font-semibold text-stone-600">
                Годен до
              </th>
              <th className="text-left px-4 py-3 text-sm font-semibold text-stone-600">
                Заметка
              </th>
              <th className="text-right px-4 py-3 text-sm font-semibold text-stone-600">
                Действия
              </th>
            </tr>
          </thead>
          <tbody>
            {enrichedStock.map((item) => {
              const status = getExpiryStatus(item.bestBefore);
              const days = getDaysUntil(item.bestBefore);
              return (
                <tr
                  key={item.id}
                  className="border-b border-stone-100 hover:bg-stone-50"
                >
                  <td className="px-4 py-3">
                    <div>
                      <p className="font-medium text-stone-800">
                        {item.product?.name ?? `Товар #${item.productId}`}
                      </p>
                      <p className="text-sm text-stone-500">
                        {item.product?.category ?? "—"}
                      </p>
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        className="w-8 h-8 flex items-center justify-center rounded bg-stone-100 hover:bg-stone-200 transition-colors"
                      >
                        <Icons.Minus />
                      </button>
                      <span className="font-medium w-12 text-center">
                        {item.amount}
                      </span>
                      <button
                        type="button"
                        className="w-8 h-8 flex items-center justify-center rounded bg-stone-100 hover:bg-stone-200 transition-colors"
                      >
                        <Icons.Plus />
                      </button>
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <span className="px-2 py-1 bg-stone-100 rounded text-sm">
                      {locations[item.location]}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <span
                      className={`px-2 py-1 rounded text-sm font-medium ${
                        status === "expired"
                          ? "bg-red-100 text-red-700"
                          : status === "critical"
                            ? "bg-orange-100 text-orange-700"
                            : status === "warning"
                              ? "bg-yellow-100 text-yellow-700"
                              : "bg-green-100 text-green-700"
                      }`}
                    >
                      {formatDate(item.bestBefore)}
                      {days <= 7 && days >= 0 && ` (${days} дн.)`}
                      {days < 0 && ` (просрочено)`}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-sm text-stone-500">
                    {item.note || "—"}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      className="text-red-500 hover:text-red-700 text-sm"
                    >
                      Списать
                    </button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {showAddModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
            <h3 className="text-xl font-bold text-stone-800 mb-4">
              Добавить в запасы
            </h3>
            <div className="space-y-4">
              <div>
                <label
                  htmlFor="stock-barcode"
                  className="block text-sm font-medium text-stone-700 mb-1"
                >
                  Штрихкод
                </label>
                <input
                  id="stock-barcode"
                  type="text"
                  placeholder="Сканируйте или введите..."
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                />
              </div>
              <div>
                <label
                  htmlFor="stock-product"
                  className="block text-sm font-medium text-stone-700 mb-1"
                >
                  Товар
                </label>
                <select
                  id="stock-product"
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                >
                  <option value="">Выберите товар...</option>
                  {products.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.name}
                    </option>
                  ))}
                </select>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label
                    htmlFor="stock-amount"
                    className="block text-sm font-medium text-stone-700 mb-1"
                  >
                    Количество
                  </label>
                  <input
                    id="stock-amount"
                    type="number"
                    defaultValue="1"
                    className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                  />
                </div>
                <div>
                  <label
                    htmlFor="stock-expiry"
                    className="block text-sm font-medium text-stone-700 mb-1"
                  >
                    Годен до
                  </label>
                  <input
                    id="stock-expiry"
                    type="date"
                    className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                  />
                </div>
              </div>
              <div>
                <label
                  htmlFor="stock-location"
                  className="block text-sm font-medium text-stone-700 mb-1"
                >
                  Место хранения
                </label>
                <select
                  id="stock-location"
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                >
                  {Object.entries(locations).map(([key, label]) => (
                    <option key={key} value={key}>
                      {label}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label
                  htmlFor="stock-note"
                  className="block text-sm font-medium text-stone-700 mb-1"
                >
                  Заметка
                </label>
                <input
                  id="stock-note"
                  type="text"
                  placeholder="Необязательно..."
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                />
              </div>
            </div>
            <div className="flex gap-3 mt-6">
              <button
                type="button"
                onClick={() => setShowAddModal(false)}
                className="flex-1 px-4 py-2 border border-stone-300 rounded-lg hover:bg-stone-50 transition-colors"
              >
                Отмена
              </button>
              <button
                type="button"
                onClick={() => setShowAddModal(false)}
                className="flex-1 px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors"
              >
                Добавить
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
