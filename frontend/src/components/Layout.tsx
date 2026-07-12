import { useEffect, useState } from "react";
import { NavLink, Outlet, useLocation, useNavigate } from "react-router-dom";
import { AnimatePresence, motion } from "framer-motion";
import {
  Boxes, ChevronsLeft, Contact, FileText, LayoutDashboard, LogOut,
  MessageSquare, Package, Search, ShoppingBag, ShoppingCart, Sparkles,
  Truck, Users, UserSquare2,
} from "lucide-react";
import { CommandPalette } from "@/components/CommandPalette";
import { Tooltip } from "@/components/ui/tooltip";
import { useAuth } from "@/features/auth/AuthContext";
import { cn } from "@/lib/utils";
import type { Role } from "@/types";

type NavItem = {
  to: string;
  label: string;
  icon: typeof Users;
  desc: string; // plain-language hover help
  roles?: Role[];
};

const SECTIONS: { title: string; items: NavItem[] }[] = [
  {
    title: "Overview",
    items: [
      { to: "/", label: "Dashboard", icon: LayoutDashboard, desc: "See how the business is doing: money earned, sales and stock alerts" },
    ],
  },
  {
    title: "Operations",
    items: [
      { to: "/products", label: "Products", icon: Package, desc: "The list of things you sell — names, prices and stock" },
      { to: "/inventory", label: "Inventory", icon: Boxes, desc: "Add or remove stock, and see the history of every change" },
      { to: "/purchases", label: "Purchases", icon: ShoppingBag, desc: "Order goods from your suppliers and receive them into stock" },
      { to: "/suppliers", label: "Suppliers", icon: Truck, desc: "The companies you buy from — names and contact details" },
    ],
  },
  {
    title: "Revenue",
    items: [
      { to: "/sales", label: "Sales", icon: ShoppingCart, desc: "Record what you sell and print invoices" },
      { to: "/customers", label: "Customers", icon: UserSquare2, desc: "The people and companies who buy from you" },
      { to: "/crm", label: "CRM", icon: Contact, desc: "Keep track of possible future customers and follow up with them" },
    ],
  },
  {
    title: "Intelligence",
    items: [
      { to: "/assistant", label: "AI Assistant", icon: MessageSquare, desc: "Ask questions in your own words — it answers and can do tasks for you" },
      { to: "/reports", label: "Reports", icon: FileText, desc: "Printable summaries of sales, purchases and stock", roles: ["admin", "manager"] },
    ],
  },
  {
    title: "Administration",
    items: [
      { to: "/users", label: "Users", icon: Users, desc: "Who can log in, and what each person is allowed to do", roles: ["admin"] },
    ],
  },
];

const PAGE_TITLES: Record<string, string> = {
  "/": "Dashboard",
  "/products": "Products",
  "/inventory": "Inventory",
  "/customers": "Customers",
  "/suppliers": "Suppliers",
  "/purchases": "Purchases",
  "/sales": "Sales",
  "/crm": "CRM",
  "/reports": "Reports",
  "/assistant": "AI Assistant",
  "/users": "Users",
};

export default function Layout() {
  const { user, logout } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();
  const [collapsed, setCollapsed] = useState(false);
  const [paletteOpen, setPaletteOpen] = useState(false);

  // ⌘K / Ctrl+K opens the command palette from anywhere.
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
        e.preventDefault();
        setPaletteOpen((v) => !v);
      }
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  const initials = (user?.first_name?.[0] ?? user?.email?.[0] ?? "?").toUpperCase();

  return (
    <div className="flex min-h-dvh bg-bg">
      {/* ───────────── sidebar ───────────── */}
      <motion.aside
        animate={{ width: collapsed ? 68 : 236 }}
        transition={{ duration: 0.26, ease: [0.22, 1, 0.36, 1] }}
        className="sticky top-0 z-30 flex h-dvh shrink-0 flex-col overflow-hidden
                   border-r border-stroke-soft bg-surface/60 backdrop-blur-xl"
      >
        {/* brand */}
        <div className={cn("flex h-16 items-center gap-2.5 px-5", collapsed && "px-0 justify-center")}>
          <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-accent/15 shadow-[inset_0_0_0_1px_hsl(var(--accent)/0.35)]">
            <Sparkles className="h-4 w-4 text-accent-strong" />
          </div>
          {!collapsed && (
            <motion.span
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              className="whitespace-nowrap text-[15px] font-semibold tracking-tight"
            >
              Nova<span className="text-accent-strong">ERP</span>
            </motion.span>
          )}
        </div>

        {/* nav sections */}
        <nav className="flex-1 space-y-5 overflow-y-auto px-3 pb-4 pt-2">
          {SECTIONS.map((section) => {
            const items = section.items.filter(
              (i) => !i.roles || i.roles.includes(user!.role)
            );
            if (items.length === 0) return null;
            return (
              <div key={section.title}>
                {!collapsed && (
                  <p className="mb-1.5 px-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-text-3">
                    {section.title}
                  </p>
                )}
                <ul className="space-y-0.5">
                  {items.map(({ to, label, icon: Icon, desc }) => (
                    <li key={to}>
                      <Tooltip label={collapsed ? `${label} — ${desc}` : desc} side="right" className="w-full">
                      <NavLink to={to} end={to === "/"} className="w-full">
                        {({ isActive }) => (
                          <span
                            className={cn(
                              "relative flex items-center gap-3 rounded-lg px-3 py-2 text-[13.5px] font-medium",
                              "transition-colors duration-150",
                              collapsed && "justify-center px-0",
                              isActive
                                ? "text-text"
                                : "text-text-2 hover:bg-white/[0.035] hover:text-text"
                            )}
                          >
                            {isActive && (
                              <motion.span
                                layoutId="nav-pill"
                                transition={{ duration: 0.28, ease: [0.22, 1, 0.36, 1] }}
                                className="absolute inset-0 rounded-lg bg-accent/[0.12] shadow-[inset_2px_0_0_hsl(var(--accent))]"
                              />
                            )}
                            <Icon
                              className={cn(
                                "relative h-[17px] w-[17px] shrink-0",
                                isActive ? "text-accent-strong" : "text-text-3"
                              )}
                            />
                            {!collapsed && <span className="relative whitespace-nowrap">{label}</span>}
                          </span>
                        )}
                      </NavLink>
                      </Tooltip>
                    </li>
                  ))}
                </ul>
              </div>
            );
          })}
        </nav>

        {/* collapse toggle */}
        <Tooltip
          label={collapsed ? "Show the full menu again" : "Shrink the menu to make more room"}
          side="right"
          className="mx-3 mb-3"
        >
        <button
          onClick={() => setCollapsed((v) => !v)}
          aria-label={collapsed ? "Expand sidebar" : "Collapse sidebar"}
          className="flex w-full items-center justify-center gap-2 rounded-lg py-2 text-text-3
                     transition-colors duration-150 hover:bg-white/[0.035] hover:text-text"
        >
          <motion.span animate={{ rotate: collapsed ? 180 : 0 }} transition={{ duration: 0.25 }}>
            <ChevronsLeft className="h-4 w-4" />
          </motion.span>
          {!collapsed && <span className="text-xs font-medium">Collapse</span>}
        </button>
        </Tooltip>
      </motion.aside>

      {/* ───────────── main column ───────────── */}
      <div className="flex min-w-0 flex-1 flex-col">
        {/* topbar */}
        <header className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-stroke-soft bg-bg/75 px-6 backdrop-blur-xl">
          <h1 className="mr-auto text-[17px] font-semibold tracking-tight">
            {PAGE_TITLES[location.pathname] ?? ""}
          </h1>

          {/* global search */}
          <Tooltip label="Jump to any page — just start typing what you need" side="bottom">
          <button
            onClick={() => setPaletteOpen(true)}
            className="group hidden h-9 items-center gap-2.5 rounded-md bg-surface-2 pl-3 pr-2 text-sm text-text-3
                       shadow-[inset_0_0_0_1px_hsl(var(--stroke-soft))] transition-all duration-200
                       hover:text-text-2 hover:shadow-[inset_0_0_0_1px_hsl(var(--stroke))] sm:flex"
          >
            <Search className="h-3.5 w-3.5" />
            <span className="pr-6">Search…</span>
            <kbd className="rounded bg-surface-3 px-1.5 py-0.5 text-[10px] font-semibold text-text-3 transition-colors group-hover:text-text-2">
              ⌘K
            </kbd>
          </button>
          </Tooltip>

          {/* ask AI */}
          <Tooltip label="Your helper: ask anything in plain words, like “what sold best this month?”" side="bottom">
          <button
            onClick={() => navigate("/assistant")}
            className="flex h-9 items-center gap-2 rounded-md bg-accent/[0.12] px-3.5 text-sm font-medium text-accent-strong
                       shadow-[inset_0_0_0_1px_hsl(var(--accent)/0.3)] transition-all duration-200
                       hover:bg-accent/[0.18] hover:shadow-accent-glow active:scale-[0.98]"
          >
            <Sparkles className="h-3.5 w-3.5" />
            <span className="hidden md:inline">Ask AI</span>
          </button>
          </Tooltip>

          {/* user */}
          <div className="flex items-center gap-2.5 pl-1.5">
            <Tooltip label={`You are signed in as ${user?.email} (${user?.role})`} side="bottom">
              <div
                className="flex h-8 w-8 items-center justify-center rounded-full bg-surface-3 text-xs font-semibold text-text
                           shadow-[inset_0_0_0_1px_hsl(var(--stroke))]"
              >
                {initials}
              </div>
            </Tooltip>
            <Tooltip label="Sign out of the app" side="bottom">
              <button
                onClick={logout}
                aria-label="Sign out"
                className="rounded-md p-2 text-text-3 transition-colors duration-150 hover:bg-surface-3 hover:text-text"
              >
                <LogOut className="h-4 w-4" />
              </button>
            </Tooltip>
          </div>
        </header>

        {/* page content with route transition */}
        <main className="mx-auto w-full max-w-[1440px] flex-1 px-6 py-8 lg:px-10">
          <AnimatePresence mode="popLayout" initial={false}>
            <motion.div
              key={location.pathname}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -6 }}
              transition={{ duration: 0.26, ease: [0.22, 1, 0.36, 1] }}
            >
              <Outlet />
            </motion.div>
          </AnimatePresence>
        </main>
      </div>

      <CommandPalette open={paletteOpen} onClose={() => setPaletteOpen(false)} />
    </div>
  );
}
