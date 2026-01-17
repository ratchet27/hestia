import { useState, type FormEvent } from 'react'
import { Navigate } from 'react-router-dom'
import { useAuth } from '../../data/hooks'

export function LoginPage(): React.ReactElement {
  const { user, login } = useAuth()
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [rememberMe, setRememberMe] = useState(false)
  const [error, setError] = useState('')

  if (user) {
    return <Navigate to="/" replace />
  }

  const handleSubmit = (e: FormEvent): void => {
    e.preventDefault()
    setError('')

    if (!username.trim()) {
      setError('Введите имя пользователя')
      return
    }

    if (!password.trim()) {
      setError('Введите пароль')
      return
    }

    const success = login(username, password)
    if (!success) {
      setError('Неверное имя пользователя или пароль')
    }
  }

  return (
    <div className="min-h-screen bg-stone-900 flex items-center justify-center p-4">
      <div className="w-full max-w-md">
        <div className="text-center mb-8">
          <h1 className="text-3xl font-bold text-amber-500">Hestia</h1>
          <p className="text-stone-500 mt-2">Домашний учёт</p>
        </div>

        <form onSubmit={handleSubmit} className="bg-stone-800 rounded-lg p-6 shadow-lg">
          <h2 className="text-xl font-semibold text-stone-200 mb-6">Вход</h2>

          {error && (
            <div className="mb-4 p-3 bg-red-900/50 border border-red-700 rounded text-red-200 text-sm">
              {error}
            </div>
          )}

          <div className="mb-4">
            <label htmlFor="username" className="block text-sm font-medium text-stone-300 mb-2">
              Имя пользователя
            </label>
            <input
              type="text"
              id="username"
              name="username"
              autoComplete="username"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              className="w-full px-3 py-2 bg-stone-700 border border-stone-600 rounded text-stone-200 placeholder-stone-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent"
              placeholder="Введите имя пользователя"
            />
          </div>

          <div className="mb-4">
            <label htmlFor="password" className="block text-sm font-medium text-stone-300 mb-2">
              Пароль
            </label>
            <input
              type="password"
              id="password"
              name="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full px-3 py-2 bg-stone-700 border border-stone-600 rounded text-stone-200 placeholder-stone-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent"
              placeholder="Введите пароль"
            />
          </div>

          <div className="mb-6">
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={rememberMe}
                onChange={(e) => setRememberMe(e.target.checked)}
                className="w-4 h-4 rounded bg-stone-700 border-stone-600 text-amber-500 focus:ring-amber-500 focus:ring-offset-stone-800"
              />
              <span className="text-sm text-stone-400">Запомнить меня</span>
            </label>
          </div>

          <button
            type="submit"
            className="w-full py-2 px-4 bg-amber-600 hover:bg-amber-500 text-white font-medium rounded transition-colors"
          >
            Войти
          </button>
        </form>
      </div>
    </div>
  )
}
