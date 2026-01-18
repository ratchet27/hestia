import { useState } from 'react'
import { Icons } from '../../components/Icons'
import { useShoppingList } from '../../data/hooks'
import { useProducts } from '../../api/queries'

export function ShoppingPage(): React.ReactElement {
  // Note: Shopping list items use number productId, API products use UUID
  // Product lookup won't match until shopping API is integrated
  const { data: products = [] } = useProducts()
  const { shoppingList, setShoppingList } = useShoppingList()
  const [newItem, setNewItem] = useState('')

  const toggleItem = (id: number): void => {
    setShoppingList(shoppingList.map((item) => (item.id === id ? { ...item, done: !item.done } : item)))
  }

  const addItem = (): void => {
    if (!newItem.trim()) return
    setShoppingList([
      ...shoppingList,
      {
        id: Date.now(),
        productId: null,
        customName: newItem,
        amount: 1,
        note: '',
        addedBy: 'manual',
        done: false,
      },
    ])
    setNewItem('')
  }

  const pendingItems = shoppingList.filter((i) => !i.done)
  const doneItems = shoppingList.filter((i) => i.done)

  return (
    <div className="p-8">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-3xl font-bold text-stone-800">Список покупок</h2>
          <p className="text-stone-500 mt-1">Общий список для всей семьи</p>
        </div>
        <div className="text-right">
          <p className="text-2xl font-bold text-amber-600">{pendingItems.length}</p>
          <p className="text-sm text-stone-500">к покупке</p>
        </div>
      </div>

      <div className="bg-white rounded-xl p-4 shadow-sm border border-stone-200 mb-6">
        <div className="flex gap-3">
          <input
            type="text"
            placeholder="Добавить товар..."
            value={newItem}
            onChange={(e) => setNewItem(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && addItem()}
            className="flex-1 px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
          />
          <button
            onClick={addItem}
            className="px-6 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors"
          >
            Добавить
          </button>
        </div>
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-stone-200 mb-6">
        <div className="p-4 border-b border-stone-100">
          <h3 className="font-semibold text-stone-800">К покупке</h3>
        </div>
        <div className="divide-y divide-stone-100">
          {pendingItems.length === 0 ? (
            <p className="p-4 text-stone-500">Список пуст!</p>
          ) : (
            pendingItems.map((item) => {
              const product = item.productId ? products.find((p) => p.id === item.productId) : null
              const name = product?.name || item.customName
              return (
                <div key={item.id} className="p-4 flex items-center gap-4 hover:bg-stone-50">
                  <button
                    onClick={() => toggleItem(item.id)}
                    className="w-6 h-6 rounded-full border-2 border-stone-300 flex items-center justify-center hover:border-green-500 transition-colors"
                  />
                  <div className="flex-1">
                    <p className="font-medium text-stone-800">{name}</p>
                    {item.note && <p className="text-sm text-stone-500">{item.note}</p>}
                  </div>
                  <span className="text-sm text-stone-500">{item.amount} шт.</span>
                  {item.addedBy === 'auto' && (
                    <span className="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">Авто</span>
                  )}
                </div>
              )
            })
          )}
        </div>
      </div>

      {doneItems.length > 0 && (
        <div className="bg-white rounded-xl shadow-sm border border-stone-200 opacity-60">
          <div className="p-4 border-b border-stone-100 flex justify-between items-center">
            <h3 className="font-semibold text-stone-800">Куплено</h3>
            <button className="text-sm text-red-500 hover:underline">Очистить</button>
          </div>
          <div className="divide-y divide-stone-100">
            {doneItems.map((item) => {
              const product = item.productId ? products.find((p) => p.id === item.productId) : null
              const name = product?.name || item.customName
              return (
                <div key={item.id} className="p-4 flex items-center gap-4">
                  <button
                    onClick={() => toggleItem(item.id)}
                    className="w-6 h-6 rounded-full bg-green-500 text-white flex items-center justify-center"
                  >
                    <Icons.Check />
                  </button>
                  <p className="flex-1 line-through text-stone-400">{name}</p>
                </div>
              )
            })}
          </div>
        </div>
      )}
    </div>
  )
}
