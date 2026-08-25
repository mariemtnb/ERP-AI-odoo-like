import { useEffect, useState } from "react";
import { NavLink, Outlet, useLocation, useNavigate } from "react-router-dom";
import { AnimatePresence, motion } from "framer-motion";
import {
  BookOpen, Boxes, CalendarClock, Coffee, Contact, FileText, Landmark,
  LayoutDashboard, LogOut, Moon, PanelLeftClose, PanelLeftOpen, Package,
  ReceiptText, Scale, Search, Settings, ShieldCheck, ShoppingBag,
  ShoppingCart, Sparkles, Sun, TrendingUp, Truck, Users, UserSquare2, Wallet,
} from "lucide-react";
import { useTheme, THEME_ORDER } from "@/lib/theme";
import { useSession } from "@/lib/session";
import { CommandPalette } from "@/components/CommandPalette";
import { NotificationBell } from "@/components/NotificationBell";
import { BrandMark } from "@/components/BrandMark";
import { Button } from "@/components/ui/button";
import { IconButton } from "@/components/ui/icon-button";
import { useAuth } from "@/features/auth/AuthContext";
import type { Role } from "@/types";

type NavItem = {
  to: string;
  label: string;
  icon: typeof Users;
  roles?: Role[];
  live?: boolean;
  /** Module this entry belongs to; hidden when that flag is off. */
  feature?: string;
};

/* Flat nav list, order and icons per the prototype sidebar. */
const NAV: NavItem[] = [
  { to: "/", label: "Dashboard", icon: LayoutDashboard },
  { to: "/products", label: "Products", icon: Package, feature: "inventory" },
  { to: "/inventory", label: "Inventory", icon: Boxes, feature: "inventory" },
  { to: "/customers", label: "Customers", icon: UserSquare2, feature: "sales" },
  { to: "/suppliers", label: "Suppliers", icon: Truck, feature: "purchasing" },
  { to: "/purchases", label: "Purchases", icon: ShoppingBag, feature: "purchasing" },
  { to: "/sales", label: "Sales", icon: ShoppingCart, feature: "sales" },
  { to: "/crm", label: "CRM", icon: Contact, feature: "crm" },
  { to: "/owner", label: "Profit", icon: TrendingUp, roles: ["admin", "manager"] },
  { to: "/accounting", label: "Accounting", icon: BookOpen, roles: ["admin", "manager"], feature: "accounting" },
  { to: "/payroll", label: "Payroll", icon: Wallet, roles: ["admin", "manager"], feature: "payroll" },
  // Treasury — cheques, effets, instalments and the bank.
  { to: "/instruments", label: "Cheques & Kembyelet", icon: ReceiptText, roles: ["admin", "manager"], feature: "treasury" },
  { to: "/installments", label: "Installments", icon: CalendarClock, roles: ["admin", "manager"], feature: "treasury" },
  { to: "/banking", label: "Banking", icon: Landmark, roles: ["admin", "manager"], feature: "banking" },
  { to: "/reconciliation", label: "Reconciliation", icon: Scale, roles: ["admin", "manager"], feature: "banking" },
  { to: "/reports", label: "Reports", icon: FileText, roles: ["admin", "manager"], feature: "reports" },
  { to: "/assistant", label: "AI Assistant", icon: Sparkles, live: true, feature: "ai" },
  { to: "/users", label: "Users", icon: Users, roles: ["admin"] },
  { to: "/settings/localization", label: "Localization", icon: Settings, roles: ["admin"], feature: "localization" },
  { to: "/settings/administration", label: "Administration", icon: ShieldCheck, roles: ["admin"] },
];

export default function Layout() {
  const { user, logout } = useAuth();
  const { theme, toggle } = useTheme();
  const { feature } = useSession();
  const nextThemeLabel = THEME_ORDER[(THEME_ORDER.indexOf(theme) + 1) % THEME_ORDER.length];
  const location = useLocation();
  const navigate = useNavigate();
  const [collapsed, setCollapsed] = useState(false);
  const [paletteOpen, setPaletteOpen] = useState(false);

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

  // Role gates the entry; the feature flag hides the whole module.
  const items = NAV.filter(
    (i) => (!i.roles || i.roles.includes(user!.role)) && (!i.feature || feature(i.feature))
  );
  const initials =
    ((user?.first_name?.[0] ?? "") + (user?.last_name?.[0] ?? "")).toUpperCase() ||
    (user?.email?.[0] ?? "?").toUpperCase();
  const fullName =
    [user?.first_name, user?.last_name].filter(Boolean).join(" ") || user?.email;

  return (
    <div className="flex min-h-dvh" style={{ background: "var(--bg-app)" }}>
      {/* ───────────── sidebar ───────────── */}
      <motion.aside
        animate={{ width: collapsed ? 72 : 264 }}
        transition={{ duration: 0.32, ease: [0.16, 1, 0.3, 1] }}
        className="flex shrink-0 flex-col overflow-hidden"
        style={{ background: "var(--bg-panel)", borderRight: "1px solid var(--border-subtle)" }}
      >
        {/* brand + collapse */}
        <div
          className="flex items-center"
          style={{
            height: "var(--topbar-h)",
            justifyContent: collapsed ? "center" : "space-between",
            padding: collapsed ? 0 : "0 18px",
            borderBottom: "1px solid var(--border-subtle)",
          }}
        >
          {collapsed ? <BrandMark size="sm" tileOnly /> : <BrandMark size="sm" />}
          {!collapsed && (
            <IconButton size="sm" onClick={() => setCollapsed(true)} aria-label="Collapse sidebar">
              <PanelLeftClose size={16} />
            </IconButton>
          )}
        </div>
        {collapsed && (
          <IconButton
            size="md"
            onClick={() => setCollapsed(false)}
            aria-label="Expand sidebar"
            className="mx-auto mt-2.5"
          >
            <PanelLeftOpen size={16} />
          </IconButton>
        )}

        {/* nav */}
        <nav className="flex flex-1 flex-col gap-0.5 overflow-y-auto" style={{ padding: "12px" }}>
          {!collapsed && <div className="eyebrow" style={{ padding: "10px 10px 6px" }}>Workspace</div>}
          {items.map(({ to, label, icon: Icon, live }) => (
            <NavLink key={to} to={to} end={to === "/"} title={label}>
              {({ isActive }) => (
                <span
                  className="relative flex items-center gap-3 rounded-[10px] transition-colors duration-120"
                  style={{
                    justifyContent: collapsed ? "center" : "flex-start",
                    padding: collapsed ? "10px 0" : "9px 10px",
                    background: isActive ? "var(--surface-hover)" : "transparent",
                    color: isActive ? "var(--text-strong)" : "var(--text-muted)",
                    font: "500 14px/1 var(--font-sans)",
                  }}
                  onMouseEnter={(e) => {
                    if (!isActive)
                      e.currentTarget.style.background =
                        "color-mix(in oklab, var(--surface-hover) 55%, transparent)";
                  }}
                  onMouseLeave={(e) => {
                    if (!isActive) e.currentTarget.style.background = "transparent";
                  }}
                >
                  {isActive && !collapsed && (
                    <span
                      className="absolute rounded-full"
                      style={{ left: 0, top: 8, bottom: 8, width: 3, background: "var(--emerald-400)" }}
                    />
                  )}
                  <Icon size={18} color={isActive ? "var(--emerald-400)" : undefined} strokeWidth={1.75} />
                  {!collapsed && <span>{label}</span>}
                  {!collapsed && live && (
                    <span
                      className="ml-auto rounded-full"
                      style={{ width: 6, height: 6, background: "var(--emerald-400)" }}
                    />
                  )}
                </span>
              )}
            </NavLink>
          ))}
        </nav>

        {/* user chip */}
        <div style={{ padding: 12, borderTop: "1px solid var(--border-subtle)" }}>
          <div
            className="flex items-center gap-2.5"
            style={{ padding: collapsed ? 0 : "6px 8px", justifyContent: collapsed ? "center" : "flex-start" }}
          >
            <div
              className="grid shrink-0 place-items-center rounded-full font-semibold"
              style={{
                width: 34,
                height: 34,
                background: "linear-gradient(135deg,var(--emerald-500),var(--emerald-700))",
                color: "var(--text-on-accent)",
                font: "600 13px/1 var(--font-sans)",
              }}
            >
              {initials}
            </div>
            {!collapsed && (
              <div style={{ minWidth: 0 }}>
                <div
                  className="truncate"
                  style={{ font: "500 13px/1.2 var(--font-sans)", color: "var(--text-strong)" }}
                >
                  {fullName}
                </div>
                <div
                  style={{
                    font: "400 11px/1 var(--font-sans)",
                    color: "var(--text-faint)",
                    textTransform: "uppercase",
                    letterSpacing: "0.1em",
                    marginTop: 3,
                  }}
                >
                  {user?.role}
                </div>
              </div>
            )}
          </div>
        </div>
      </motion.aside>

      {/* ───────────── main column ───────────── */}
      <div className="flex min-w-0 flex-1 flex-col">
        {/* glass topbar */}
        <header
          className="sticky top-0 z-20 flex items-center gap-4"
          style={{
            height: "var(--topbar-h)",
            padding: "0 24px",
            borderBottom: "1px solid var(--border-subtle)",
            background: "color-mix(in oklab, var(--bg-app) 82%, transparent)",
            backdropFilter: "blur(12px)",
          }}
        >
          <button
            onClick={() => setPaletteOpen(true)}
            className="flex items-center gap-2.5"
            style={{
              width: 340,
              maxWidth: "40vw",
              height: 38,
              padding: "0 12px",
              borderRadius: 10,
              background: "var(--surface-inset)",
              border: "1px solid var(--border)",
              color: "var(--text-faint)",
              font: "400 13px/1 var(--font-sans)",
              cursor: "pointer",
            }}
          >
            <Search size={16} /> Search or ask anything…
            <span
              style={{
                marginLeft: "auto",
                font: "500 11px/1 var(--font-mono)",
                background: "var(--surface-hover)",
                padding: "3px 6px",
                borderRadius: 6,
              }}
            >
              ⌘K
            </span>
          </button>

          <div className="ml-auto flex items-center gap-2">
            <Button
              variant="secondary"
              size="sm"
              onClick={() => navigate("/assistant")}
              icon={<Sparkles size={15} color="var(--emerald-400)" />}
            >
              Ask AI
            </Button>
            <NotificationBell />
            <IconButton
              size="md"
              onClick={toggle}
              aria-label={`Theme: ${theme}. Click to change.`}
              title={`Theme: ${theme} — click for ${nextThemeLabel}`}
            >
              {theme === "dark" ? <Moon size={18} /> : theme === "creme" ? <Coffee size={18} /> : <Sun size={18} />}
            </IconButton>
            <IconButton size="md" onClick={logout} aria-label="Sign out">
              <LogOut size={18} />
            </IconButton>
          </div>
        </header>

        {/* content */}
        <main
          className="w-full flex-1"
          style={{ padding: "36px 40px", maxWidth: "var(--content-max)", margin: "0 auto" }}
        >
          {/* "wait" (not popLayout): the outgoing page must finish exiting
              before the next mounts, otherwise a stale invisible copy is
              left behind in the DOM. */}
          <AnimatePresence mode="wait" initial={false}>
            <motion.div
              key={location.pathname}
              initial={{ opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -6 }}
              transition={{ duration: 0.32, ease: [0.16, 1, 0.3, 1] }}
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
