import { useState } from 'react'
import { Icons } from '../../components/Icons'
import { useProducts, useStock } from '../../data/hooks'
import { locations, categories } from '../../data/types'

export function ProductsPage(): React.ReactElement {
  const { products } = useProducts()
  const { stock } = useStock()
  const [searchTerm, setSearchTerm] = useState('')
  const [categoryFilter, setCategoryFilter] = useState('all')
  const [showAddModal, setShowAddModal] = useState(false)

  const filteredProducts = products
    .filter((p) => categoryFilter === 'all' || p.category === categoryFilter)
    .filter((p) => p.name.toLowerCase().includes(searchTerm.toLowerCase()))

  return (
    <div className="p-8">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-3xl font-bold text-stone-800">Товары</h2>
          <p className="text-stone-500 mt-1">Справочник товаров и штрихкодов</p>
        </div>
        <button
          onClick={() => setShowAddModal(true)}
          className="flex items-center gap-2 px-4 py-2 bg-stone-800 text-white rounded-lg hover:bg-stone-700 transition-colors"
        >
          <Icons.Plus />
          Новый товар
        </button>
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
          value={categoryFilter}
          onChange={(e) => setCategoryFilter(e.target.value)}
          className="px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        >
          <option value="all">Все категории</option>
          {categories.map((cat) => (
            <option key={cat} value={cat}>
              {cat}
            </option>
          ))}
        </select>
      </div>

      <div className="grid grid-cols-3 gap-4">
        {filteredProducts.map((product) => {
          const totalStock = stock.filter((e) => e.productId === product.id).reduce((sum, e) => sum + e.amount, 0)
          const isLow = product.minStock > 0 && totalStock < product.minStock

          return (
            <div
              key={product.id}
              className="bg-white rounded-xl p-4 shadow-sm border border-stone-200 hover:border-amber-400 transition-colors cursor-pointer"
            >
              <div className="flex justify-between items-start mb-3">
                <span className="px-2 py-1 bg-stone-100 rounded text-xs text-stone-600">{product.category}</span>
                {isLow && <span className="px-2 py-1 bg-amber-100 rounded text-xs text-amber-700">Мало!</span>}
              </div>
              <h3 className="font-semibold text-stone-800 mb-2">{product.name}</h3>
              <div className="space-y-1 text-sm text-stone-500">
                <p>Штрихкод: {product.barcode}</p>
                <p>Срок годности: {product.defaultExpiryDays} дн.</p>
                <p>Место: {locations[product.defaultLocation]}</p>
                {product.minStock > 0 && <p>Мин. запас: {product.minStock}</p>}
              </div>
              <div className="mt-3 pt-3 border-t border-stone-100 flex justify-between items-center">
                <span className="text-lg font-bold text-stone-800">{totalStock} шт.</span>
                <span className={`text-sm ${product.active ? 'text-green-600' : 'text-stone-400'}`}>
                  {product.active ? 'Активен' : 'Архив'}
                </span>
              </div>
            </div>
          )
        })}
      </div>

      {showAddModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
            <h3 className="text-xl font-bold text-stone-800 mb-4">Новый товар</h3>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">Название</label>
                <input
                  type="text"
                  placeholder="Например: Молоко 3.2%"
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">Категория</label>
                <select className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500">
                  {categories.map((cat) => (
                    <option key={cat} value={cat}>
                      {cat}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">Штрихкод</label>
                <input
                  type="text"
                  placeholder="Сканируйте или введите..."
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-stone-700 mb-1">Срок годности (дни)</label>
                  <input
                    type="number"
                    defaultValue="7"
                    className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-stone-700 mb-1">Мин. запас</label>
                  <input
                    type="number"
                    defaultValue="0"
                    className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                  />
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">Место хранения по умолчанию</label>
                <select className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500">
                  {Object.entries(locations).map(([key, label]) => (
                    <option key={key} value={key}>
                      {label}
                    </option>
                  ))}
                </select>
              </div>
            </div>
            <div className="flex gap-3 mt-6">
              <button
                onClick={() => setShowAddModal(false)}
                className="flex-1 px-4 py-2 border border-stone-300 rounded-lg hover:bg-stone-50 transition-colors"
              >
                Отмена
              </button>
              <button
                onClick={() => setShowAddModal(false)}
                className="flex-1 px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors"
              >
                Создать
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
