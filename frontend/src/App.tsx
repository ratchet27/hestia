import { Navigate, Route, Routes } from "react-router-dom";
import { Layout } from "./components/Layout";
import { useAuth } from "./data/hooks";
import { LoginPage } from "./features/auth/LoginPage";
import { DashboardPage } from "./features/dashboard/DashboardPage";
import { ProductsPage } from "./features/products/ProductsPage";
import { RecipesPage } from "./features/recipes/RecipesPage";
import { SettingsPage } from "./features/settings/SettingsPage";
import { ShoppingPage } from "./features/shopping/ShoppingPage";
import { StockPage } from "./features/stock/StockPage";
import { TasksPage } from "./features/tasks/TasksPage";

function ProtectedRoute({
  children,
}: {
  children: React.ReactElement;
}): React.ReactElement {
  const { user, isLoading } = useAuth();
  if (isLoading) {
    return (
      <div className="min-h-screen bg-stone-900 flex items-center justify-center text-stone-400">
        Загрузка...
      </div>
    );
  }
  if (!user) {
    return <Navigate to="/login" replace />;
  }
  return children;
}

export default function App(): React.ReactElement {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route
        element={
          <ProtectedRoute>
            <Layout />
          </ProtectedRoute>
        }
      >
        <Route path="/" element={<DashboardPage />} />
        <Route path="/stock" element={<StockPage />} />
        <Route path="/products" element={<ProductsPage />} />
        <Route path="/shopping" element={<ShoppingPage />} />
        <Route path="/recipes" element={<RecipesPage />} />
        <Route path="/tasks" element={<TasksPage />} />
        <Route path="/settings" element={<SettingsPage />} />
      </Route>
    </Routes>
  );
}
