import { useState } from 'react'
import { Icons } from '../../components/Icons'
import { useChores, useTasks } from '../../data/hooks'
import { getDaysUntil, formatDate } from '../../data/types'
import type { Chore } from '../../data/types'

const frequencyLabels: Record<Chore['frequency'], string> = {
  daily: 'Ежедневно',
  weekly: 'Еженедельно',
  monthly: 'Ежемесячно',
}

export function TasksPage(): React.ReactElement {
  const { chores, setChores } = useChores()
  const { tasks, setTasks } = useTasks()
  const [showAddTask, setShowAddTask] = useState(false)
  const [newTaskName, setNewTaskName] = useState('')

  const calculateNextDue = (frequency: Chore['frequency']): string => {
    const today = new Date()
    switch (frequency) {
      case 'daily':
        today.setDate(today.getDate() + 1)
        break
      case 'weekly':
        today.setDate(today.getDate() + 7)
        break
      case 'monthly':
        today.setMonth(today.getMonth() + 1)
        break
    }
    return today.toISOString().split('T')[0]
  }

  const markChoreDone = (id: number): void => {
    setChores(
      chores.map((c) => {
        if (c.id === id) {
          return {
            ...c,
            lastDone: new Date().toISOString().split('T')[0],
            nextDue: calculateNextDue(c.frequency),
          }
        }
        return c
      })
    )
  }

  const toggleTask = (id: number): void => {
    setTasks(tasks.map((t) => (t.id === id ? { ...t, done: !t.done } : t)))
  }

  const addTask = (): void => {
    if (!newTaskName.trim()) return
    setTasks([
      ...tasks,
      {
        id: Date.now(),
        name: newTaskName,
        dueDate: null,
        done: false,
      },
    ])
    setNewTaskName('')
    setShowAddTask(false)
  }

  return (
    <div className="p-8">
      <div className="mb-6">
        <h2 className="text-3xl font-bold text-stone-800">Задачи и дела</h2>
        <p className="text-stone-500 mt-1">Домашние обязанности и разовые задачи</p>
      </div>

      <div className="grid grid-cols-2 gap-6">
        <div>
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-xl font-semibold text-stone-800">Регулярные дела</h3>
            <button className="text-sm text-amber-600 hover:underline">+ Добавить</button>
          </div>
          <div className="space-y-3">
            {[...chores]
              .sort((a, b) => getDaysUntil(a.nextDue) - getDaysUntil(b.nextDue))
              .map((chore) => {
                const days = getDaysUntil(chore.nextDue)
                const isOverdue = days < 0
                const isDueToday = days === 0

                return (
                  <div
                    key={chore.id}
                    className={`bg-white rounded-xl p-4 shadow-sm border ${
                      isOverdue
                        ? 'border-red-300 bg-red-50'
                        : isDueToday
                          ? 'border-amber-300 bg-amber-50'
                          : 'border-stone-200'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="font-medium text-stone-800">{chore.name}</p>
                        <p className="text-sm text-stone-500">
                          {frequencyLabels[chore.frequency]} ·
                          {isOverdue
                            ? ` Просрочено на ${Math.abs(days)} дн.`
                            : isDueToday
                              ? ' Сегодня!'
                              : ` Через ${days} дн.`}
                        </p>
                      </div>
                      <button
                        onClick={() => markChoreDone(chore.id)}
                        className="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors text-sm"
                      >
                        Выполнено
                      </button>
                    </div>
                  </div>
                )
              })}
          </div>
        </div>

        <div>
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-xl font-semibold text-stone-800">Разовые задачи</h3>
            <button onClick={() => setShowAddTask(true)} className="text-sm text-amber-600 hover:underline">
              + Добавить
            </button>
          </div>

          {showAddTask && (
            <div className="bg-white rounded-xl p-4 shadow-sm border border-amber-300 mb-3">
              <input
                type="text"
                placeholder="Название задачи..."
                value={newTaskName}
                onChange={(e) => setNewTaskName(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && addTask()}
                className="w-full px-3 py-2 border border-stone-300 rounded-lg mb-3 focus:outline-none focus:ring-2 focus:ring-amber-500"
                autoFocus
              />
              <div className="flex gap-2">
                <button
                  onClick={() => setShowAddTask(false)}
                  className="flex-1 px-3 py-1 border border-stone-300 rounded-lg text-sm"
                >
                  Отмена
                </button>
                <button onClick={addTask} className="flex-1 px-3 py-1 bg-amber-500 text-white rounded-lg text-sm">
                  Добавить
                </button>
              </div>
            </div>
          )}

          <div className="space-y-3">
            {tasks
              .filter((t) => !t.done)
              .map((task) => (
                <div key={task.id} className="bg-white rounded-xl p-4 shadow-sm border border-stone-200">
                  <div className="flex items-center gap-3">
                    <button
                      onClick={() => toggleTask(task.id)}
                      className="w-6 h-6 rounded-full border-2 border-stone-300 hover:border-green-500 transition-colors"
                    />
                    <div className="flex-1">
                      <p className="font-medium text-stone-800">{task.name}</p>
                      {task.dueDate && <p className="text-sm text-stone-500">До {formatDate(task.dueDate)}</p>}
                    </div>
                  </div>
                </div>
              ))}
          </div>

          {tasks.filter((t) => t.done).length > 0 && (
            <div className="mt-6">
              <h4 className="text-sm font-medium text-stone-500 mb-3">Выполнено</h4>
              <div className="space-y-2 opacity-60">
                {tasks
                  .filter((t) => t.done)
                  .map((task) => (
                    <div key={task.id} className="bg-white rounded-xl p-4 shadow-sm border border-stone-200">
                      <div className="flex items-center gap-3">
                        <button
                          onClick={() => toggleTask(task.id)}
                          className="w-6 h-6 rounded-full bg-green-500 text-white flex items-center justify-center"
                        >
                          <Icons.Check />
                        </button>
                        <p className="line-through text-stone-400">{task.name}</p>
                      </div>
                    </div>
                  ))}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
