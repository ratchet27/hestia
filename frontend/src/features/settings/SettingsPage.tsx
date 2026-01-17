import { locations } from '../../data/types'

export function SettingsPage(): React.ReactElement {
  return (
    <div className="p-8">
      <div className="mb-6">
        <h2 className="text-3xl font-bold text-stone-800">Настройки</h2>
        <p className="text-stone-500 mt-1">Конфигурация системы</p>
      </div>

      <div className="max-w-2xl space-y-6">
        <div className="bg-white rounded-xl p-6 shadow-sm border border-stone-200">
          <h3 className="font-semibold text-stone-800 mb-4">Профиль</h3>
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">Имя</label>
              <input
                type="text"
                defaultValue="Администратор"
                className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">Email</label>
              <input
                type="email"
                defaultValue="admin@home.local"
                className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
              />
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl p-6 shadow-sm border border-stone-200">
          <h3 className="font-semibold text-stone-800 mb-4">Telegram</h3>
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-stone-800">Статус подключения</p>
                <p className="text-sm text-stone-500">@hestia_bot</p>
              </div>
              <span className="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm">Подключён</span>
            </div>
            <div className="border-t border-stone-100 pt-4">
              <p className="font-medium text-stone-800 mb-3">Уведомления</p>
              <div className="space-y-3">
                <label className="flex items-center justify-between">
                  <span className="text-stone-700">Ежедневный отчёт об истекающих</span>
                  <input type="checkbox" defaultChecked className="w-5 h-5 accent-amber-500" />
                </label>
                <label className="flex items-center justify-between">
                  <span className="text-stone-700">Еженедельный отчёт о делах</span>
                  <input type="checkbox" defaultChecked className="w-5 h-5 accent-amber-500" />
                </label>
                <label className="flex items-center justify-between">
                  <span className="text-stone-700">Напоминания о задачах</span>
                  <input type="checkbox" defaultChecked className="w-5 h-5 accent-amber-500" />
                </label>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl p-6 shadow-sm border border-stone-200">
          <h3 className="font-semibold text-stone-800 mb-4">Места хранения</h3>
          <div className="space-y-2">
            {Object.entries(locations).map(([key, label]) => (
              <div key={key} className="flex items-center justify-between py-2 border-b border-stone-100 last:border-0">
                <span className="text-stone-800">{label}</span>
                <button className="text-sm text-stone-500 hover:text-red-500">Удалить</button>
              </div>
            ))}
          </div>
          <button className="mt-4 text-sm text-amber-600 hover:underline">+ Добавить место</button>
        </div>

        <div className="bg-white rounded-xl p-6 shadow-sm border border-stone-200">
          <h3 className="font-semibold text-stone-800 mb-4">Данные</h3>
          <div className="space-y-3">
            <button className="w-full text-left px-4 py-3 border border-stone-200 rounded-lg hover:bg-stone-50 transition-colors">
              <p className="font-medium text-stone-800">Экспорт данных</p>
              <p className="text-sm text-stone-500">Скачать все данные в JSON</p>
            </button>
            <button className="w-full text-left px-4 py-3 border border-stone-200 rounded-lg hover:bg-stone-50 transition-colors">
              <p className="font-medium text-stone-800">Импорт данных</p>
              <p className="text-sm text-stone-500">Загрузить данные из файла</p>
            </button>
            <button className="w-full text-left px-4 py-3 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
              <p className="font-medium text-red-600">Очистить все данные</p>
              <p className="text-sm text-red-400">Удалить все записи (необратимо)</p>
            </button>
          </div>
        </div>

        <div className="text-center text-sm text-stone-400 py-4">Hestia v0.1.0</div>
      </div>
    </div>
  )
}
