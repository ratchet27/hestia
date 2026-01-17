import { Routes, Route } from 'react-router-dom'
import { Layout } from './components/Layout'
import { DashboardPage } from './features/dashboard/DashboardPage'
import { StockPage } from './features/stock/StockPage'
import { ProductsPage } from './features/products/ProductsPage'
import { ShoppingPage } from './features/shopping/ShoppingPage'
import { RecipesPage } from './features/recipes/RecipesPage'
import { TasksPage } from './features/tasks/TasksPage'
import { SettingsPage } from './features/settings/SettingsPage'

export default function App(): React.ReactElement {
  return (
    <Routes>
      <Route element={<Layout />}>
        <Route path="/" element={<DashboardPage />} />
        <Route path="/stock" element={<StockPage />} />
        <Route path="/products" element={<ProductsPage />} />
        <Route path="/shopping" element={<ShoppingPage />} />
        <Route path="/recipes" element={<RecipesPage />} />
        <Route path="/tasks" element={<TasksPage />} />
        <Route path="/settings" element={<SettingsPage />} />
      </Route>
    </Routes>
  )
}
