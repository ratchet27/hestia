import { useNavigate } from 'react-router-dom'
import { Icons } from '../../components/Icons'
import { useProducts, useStock, useShoppingList, useChores, useTasks } from '../../data/hooks'
import { getExpiryStatus, getDaysUntil, formatDate, locations } from '../../data/types'

export function DashboardPage(): React.ReactElement {
  const navigate = useNavigate()
  const { products } = useProducts()
  const { stock } = useStock()
  const { shoppingList } = useShoppingList()
  const { chores } = useChores()
  const { tasks } = useTasks()

  const expiringItems = stock
    .filter((e) => getExpiryStatus(e.bestBefore) !== 'ok')
    .map((e) => ({
      ...e,
      product: products.find((p) => p.id === e.productId),
    }))
    .sort((a, b) => getDaysUntil(a.bestBefore) - getDaysUntil(b.bestBefore))

  const lowStockItems = products
    .filter((p) => p.minStock > 0)
    .map((p) => {
      const totalStock = stock.filter((e) => e.productId === p.id).reduce((sum, e) => sum + e.amount, 0)
      return { ...p, totalStock, isLow: totalStock < p.minStock }
    })
    .filter((p) => p.isLow)

  const todayChores = chores.filter((c) => getDaysUntil(c.nextDue) <= 0)
  const upcomingTasks = tasks.filter((t) => !t.done).slice(0, 3)
  const shoppingCount = shoppingList.filter((i) => !i.done).length

  return (
    <div className="p-8">
      <div className="mb-8">
        <h2 className="text-3xl font-bold text-stone-800">Добро пожаловать!</h2>
        <p className="text-stone-500 mt-1">Обзор домашнего хозяйства на сегодня</p>
      </div>

      <div className="grid grid-cols-4 gap-4 mb-8">
        <div className="bg-white rounded-xl p-5 shadow-sm border border-stone-200">
          <div className="text-3xl font-bold text-stone-800">{stock.length}</div>
          <div className="text-sm text-stone-500">Позиций в запасах</div>
        </div>
        <div
          className="bg-white rounded-xl p-5 shadow-sm border border-stone-200 cursor-pointer hover:border-amber-400 transition-colors"
          onClick={() => navigate('/shopping')}
        >
          <div className="text-3xl font-bold text-amber-600">{shoppingCount}</div>
          <div className="text-sm text-stone-500">В списке покупок</div>
        </div>
        <div className="bg-white rounded-xl p-5 shadow-sm border border-stone-200">
          <div className="text-3xl font-bold text-red-500">{expiringItems.length}</div>
          <div className="text-sm text-stone-500">Истекает/Истекло</div>
        </div>
        <div className="bg-white rounded-xl p-5 shadow-sm border border-stone-200">
          <div className="text-3xl font-bold text-blue-500">{todayChores.length}</div>
          <div className="text-sm text-stone-500">Дел на сегодня</div>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-6">
        <div className="bg-white rounded-xl shadow-sm border border-stone-200">
          <div className="p-4 border-b border-stone-100 flex items-center justify-between">
            <h3 className="font-semibold text-stone-800 flex items-center gap-2">
              <span className="text-red-500">
                <Icons.Warning />
              </span>
              Истекающие продукты
            </h3>
            <button onClick={() => navigate('/stock')} className="text-sm text-amber-600 hover:underline">
              Все запасы →
            </button>
          </div>
          <div className="p-4">
            {expiringItems.length === 0 ? (
              <p className="text-stone-500 text-sm">Всё в порядке!</p>
            ) : (
              <div className="space-y-3">
                {expiringItems.map((item) => {
                  const status = getExpiryStatus(item.bestBefore)
                  const days = getDaysUntil(item.bestBefore)
                  return (
                    <div key={item.id} className="flex items-center justify-between">
                      <div>
                        <p className="font-medium text-stone-800">{item.product?.name}</p>
                        <p className="text-sm text-stone-500">
                          {item.amount} шт · {locations[item.location]}
                        </p>
                      </div>
                      <span
                        className={`px-3 py-1 rounded-full text-sm font-medium ${
                          status === 'expired'
                            ? 'bg-red-100 text-red-700'
                            : status === 'critical'
                              ? 'bg-orange-100 text-orange-700'
                              : 'bg-yellow-100 text-yellow-700'
                        }`}
                      >
                        {days < 0
                          ? `Истекло ${Math.abs(days)} дн. назад`
                          : days === 0
                            ? 'Сегодня!'
                            : `${days} дн.`}
                      </span>
                    </div>
                  )
                })}
              </div>
            )}
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-stone-200">
          <div className="p-4 border-b border-stone-100 flex items-center justify-between">
            <h3 className="font-semibold text-stone-800">Низкий запас</h3>
            <button onClick={() => navigate('/products')} className="text-sm text-amber-600 hover:underline">
              Все товары →
            </button>
          </div>
          <div className="p-4">
            {lowStockItems.length === 0 ? (
              <p className="text-stone-500 text-sm">Всего достаточно!</p>
            ) : (
              <div className="space-y-3">
                {lowStockItems.map((item) => (
                  <div key={item.id} className="flex items-center justify-between">
                    <div>
                      <p className="font-medium text-stone-800">{item.name}</p>
                      <p className="text-sm text-stone-500">Мин: {item.minStock}</p>
                    </div>
                    <span className="px-3 py-1 rounded-full text-sm font-medium bg-amber-100 text-amber-700">
                      Осталось: {item.totalStock}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-stone-200">
          <div className="p-4 border-b border-stone-100 flex items-center justify-between">
            <h3 className="font-semibold text-stone-800">Дела на сегодня</h3>
            <button onClick={() => navigate('/tasks')} className="text-sm text-amber-600 hover:underline">
              Все задачи →
            </button>
          </div>
          <div className="p-4">
            {todayChores.length === 0 ? (
              <p className="text-stone-500 text-sm">На сегодня дел нет!</p>
            ) : (
              <div className="space-y-3">
                {todayChores.map((chore) => (
                  <div key={chore.id} className="flex items-center justify-between">
                    <p className="font-medium text-stone-800">{chore.name}</p>
                    <button className="px-3 py-1 bg-green-500 text-white rounded-lg text-sm hover:bg-green-600 transition-colors">
                      Выполнено
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-stone-200">
          <div className="p-4 border-b border-stone-100 flex items-center justify-between">
            <h3 className="font-semibold text-stone-800">Ближайшие задачи</h3>
            <button onClick={() => navigate('/tasks')} className="text-sm text-amber-600 hover:underline">
              Все задачи →
            </button>
          </div>
          <div className="p-4">
            {upcomingTasks.length === 0 ? (
              <p className="text-stone-500 text-sm">Нет активных задач</p>
            ) : (
              <div className="space-y-3">
                {upcomingTasks.map((task) => (
                  <div key={task.id} className="flex items-center justify-between">
                    <p className="font-medium text-stone-800">{task.name}</p>
                    <span className="text-sm text-stone-500">{formatDate(task.dueDate)}</span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
