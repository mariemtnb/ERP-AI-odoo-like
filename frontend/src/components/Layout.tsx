import { NavLink, Outlet } from "react-router-dom";
import {
  Boxes,
  LayoutDashboard,
  LogOut,
  MessageSquare,
  Package,
  ShoppingCart,
  Truck,
  Users,
  UserSquare2,
} from "lucide-react";
import { useAuth } from "@/features/auth/AuthContext";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import type { Role } from "@/types";

const nav: { to: string; label: string; icon: typeof Users; roles?: Role[] }[] = [
  { to: "/", label: "Dashboard", icon: LayoutDashboard },
  { to: "/products", label: "Products", icon: Package },
  { to: "/inventory", label: "Inventory", icon: Boxes },
  { to: "/customers", label: "Customers", icon: UserSquare2 },
  { to: "/suppliers", label: "Suppliers", icon: Truck },
  { to: "/purchases", label: "Purchases", icon: ShoppingCart },
  { to: "/sales", label: "Sales", icon: ShoppingCart },
  { to: "/assistant", label: "AI Assistant", icon: MessageSquare },
  { to: "/users", label: "Users", icon: Users, roles: ["admin"] },
];

export default function Layout() {
  const { user, logout } = useAuth();

  return (
    <div className="flex min-h-screen bg-slate-950 text-slate-100">
      <aside className="flex w-60 flex-col border-r border-slate-800 bg-slate-900">
        <div className="px-5 py-5 text-lg font-bold tracking-tight">
          Intelligent <span className="text-indigo-400">ERP</span>
        </div>
        <nav className="flex-1 space-y-1 px-3">
          {nav
            .filter((item) => !item.roles || item.roles.includes(user!.role))
            .map(({ to, label, icon: Icon }) => (
              <NavLink
                key={to}
                to={to}
                end={to === "/"}
                className={({ isActive }) =>
                  cn(
                    "flex items-center gap-3 rounded-md px-3 py-2 text-sm text-slate-300 hover:bg-slate-800",
                    isActive && "bg-slate-800 text-white"
                  )
                }
              >
                <Icon className="h-4 w-4" />
                {label}
              </NavLink>
            ))}
        </nav>
        <div className="border-t border-slate-800 p-4">
          <div className="mb-2 truncate text-sm text-slate-300">{user?.email}</div>
          <div className="mb-3 text-xs uppercase text-slate-500">{user?.role}</div>
          <Button variant="outline" size="sm" className="w-full" onClick={logout}>
            <LogOut className="h-4 w-4" /> Sign out
          </Button>
        </div>
      </aside>
      <main className="flex-1 p-8">
        <Outlet />
      </main>
    </div>
  );
}
