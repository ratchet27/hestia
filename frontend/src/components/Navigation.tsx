import { NavLink } from 'react-router-dom'
import { Icons } from './Icons'

const navItems = [
  { path: '/', label: 'Главная', icon: Icons.Dashboard },
  { path: '/stock', label: 'Запасы', icon: Icons.Stock },
  { path: '/products', label: 'Товары', icon: Icons.Products },
  { path: '/shopping', label: 'Покупки', icon: Icons.Shopping },
  { path: '/recipes', label: 'Рецепты', icon: Icons.Recipes },
  { path: '/tasks', label: 'Задачи', icon: Icons.Tasks },
  { path: '/settings', label: 'Настройки', icon: Icons.Settings },
]

export function Navigation(): React.ReactElement {
  const today = new Date()
  const dateStr = today.toLocaleDateString('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
  const dayStr = today.toLocaleDateString('ru-RU', { weekday: 'long' })

  return (
    <nav className="fixed left-0 top-0 h-full w-56 bg-stone-900 text-stone-300 flex flex-col">
      <div className="p-5 border-b border-stone-700">
        <h1 className="text-xl font-bold text-amber-500 tracking-tight">Hestia</h1>
        <p className="text-xs text-stone-500 mt-1">Домашний учёт</p>
      </div>
      <div className="flex-1 py-4">
        {navItems.map((item) => {
          const Icon = item.icon
          return (
            <NavLink
              key={item.path}
              to={item.path}
              className={({ isActive }) =>
                `w-full flex items-center gap-3 px-5 py-3 text-left transition-all ${
                  isActive ? 'bg-amber-600 text-white' : 'hover:bg-stone-800 hover:text-white'
                }`
              }
            >
              <Icon />
              <span className="font-medium">{item.label}</span>
            </NavLink>
          )
        })}
      </div>
      <div className="p-4 border-t border-stone-700 text-xs text-stone-500">
        <p>{dateStr}</p>
        <p className="mt-1 capitalize">{dayStr}</p>
      </div>
    </nav>
  )
}
