import { Outlet } from "react-router-dom";
import { Navigation } from "./Navigation";

export function Layout(): React.ReactElement {
  return (
    <div className="min-h-screen bg-stone-100">
      <Navigation />
      <main className="ml-56">
        <Outlet />
      </main>
    </div>
  );
}
