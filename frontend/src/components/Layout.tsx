import { useEffect, useState } from "react";
import { NavLink, Outlet, useLocation, useNavigate } from "react-router-dom";
import { motion } from "framer-motion";
import {
  BarChart3, BookOpen, Boxes, Building2, CalendarClock, ClipboardList, Coffee, Coins, Contact, Factory, FileText, FolderKanban, Landmark,
  LayoutDashboard, LifeBuoy, LogOut, Megaphone, Moon, PackageCheck, PanelLeftClose, PanelLeftOpen, Package,
  ReceiptText, RefreshCw, Repeat, Scale, Search, Settings, ShieldCheck, ShoppingBag,
  ShoppingCart, Sparkles, Store, Sun, Tag, TrendingUp, Truck, Undo2, UserCog, Users, UserSquare2, Wallet,
} from "lucide-react";
import { useTheme, THEME_ORDER } from "@/lib/theme";
import { useSession } from "@/lib/session";
import { useI18n } from "@/lib/i18n";
import { LanguageSwitcher } from "@/components/LanguageSwitcher";
import { pathAllowed } from "@/lib/modules";
import { CommandPalette } from "@/components/CommandPalette";
import { NotificationBell } from "@/components/NotificationBell";
import { BrandMark } from "@/components/BrandMark";
import AppBackground from "@/components/AppBackground";
import { ErrorBoundary } from "@/components/ErrorBoundary";
import { Button } from "@/components/ui/button";
import { IconButton } from "@/components/ui/icon-button";
import { useAuth } from "@/features/auth/AuthContext";
import type { Role } from "@/types";

type NavItem = {
  to: string;
  /** i18n key for the label, e.g. "nav.dashboard". */
  label: string;
  icon: typeof Users;
  roles?: Role[];
  live?: boolean;
  /** Module this entry belongs to; hidden when that flag is off. */
  feature?: string;
};

type NavSection = { title: string; items: NavItem[] };

/* Grouped nav: sections keep the (now 30+) modules navigable.
   `title` and `label` hold i18n keys resolved through `t()` at render. */
const NAV_SECTIONS: NavSection[] = [
  {
    title: "section.overview",
    items: [{ to: "/", label: "nav.dashboard", icon: LayoutDashboard }],
  },
  {
    title: "section.catalog",
    items: [
      { to: "/products", label: "nav.products", icon: Package, feature: "inventory" },
      { to: "/inventory", label: "nav.inventory", icon: Boxes, feature: "inventory" },
      { to: "/lots", label: "nav.lots", icon: CalendarClock, roles: ["admin", "manager"], feature: "lots" },
      { to: "/manufacturing", label: "nav.manufacturing", icon: Factory, roles: ["admin", "manager"], feature: "manufacturing" },
    ],
  },
  {
    title: "section.purchasing",
    items: [
      { to: "/suppliers", label: "nav.suppliers", icon: Truck, feature: "purchasing" },
      { to: "/purchases", label: "nav.purchases", icon: ShoppingBag, feature: "purchasing" },
      { to: "/reordering", label: "nav.reordering", icon: RefreshCw, roles: ["admin", "manager"], feature: "reordering" },
      { to: "/rfqs", label: "nav.rfqs", icon: ClipboardList, roles: ["admin", "manager"], feature: "rfq" },
    ],
  },
  {
    title: "section.sales",
    items: [
      { to: "/customers", label: "nav.customers", icon: UserSquare2, feature: "sales" },
      { to: "/sales", label: "nav.sales", icon: ShoppingCart, feature: "sales" },
      { to: "/pos", label: "nav.pos", icon: Store, feature: "pos" },
      { to: "/pricelists", label: "nav.pricelists", icon: Tag, roles: ["admin", "manager"], feature: "pricelists" },
      { to: "/subscriptions", label: "nav.subscriptions", icon: Repeat, roles: ["admin", "manager"], feature: "subscriptions" },
      { to: "/returns", label: "nav.returns", icon: Undo2, roles: ["admin", "manager"], feature: "returns" },
      { to: "/shipping", label: "nav.shipping", icon: PackageCheck, feature: "shipping" },
      { to: "/marketing", label: "nav.marketing", icon: Megaphone, roles: ["admin", "manager"], feature: "marketing" },
      { to: "/crm", label: "nav.crm", icon: Contact, feature: "crm" },
    ],
  },
  {
    title: "section.services",
    items: [
      { to: "/projects", label: "nav.projects", icon: FolderKanban, feature: "projects" },
      { to: "/helpdesk", label: "nav.helpdesk", icon: LifeBuoy, feature: "helpdesk" },
    ],
  },
  {
    title: "section.finance",
    items: [
      { to: "/owner", label: "nav.profit", icon: TrendingUp, roles: ["admin", "manager"] },
      { to: "/accounting", label: "nav.accounting", icon: BookOpen, roles: ["admin", "manager"], feature: "accounting" },
      { to: "/assets", label: "nav.assets", icon: Building2, roles: ["admin", "manager"], feature: "assets" },
    ],
  },
  {
    title: "section.people",
    items: [
      { to: "/payroll", label: "nav.payroll", icon: Wallet, roles: ["admin", "manager"], feature: "payroll" },
      { to: "/hr", label: "nav.hr", icon: UserCog, roles: ["admin", "manager"], feature: "hr" },
    ],
  },
  {
    title: "section.treasury",
    items: [
      { to: "/instruments", label: "nav.instruments", icon: ReceiptText, roles: ["admin", "manager"], feature: "treasury" },
      { to: "/installments", label: "nav.installments", icon: CalendarClock, roles: ["admin", "manager"], feature: "treasury" },
      { to: "/banking", label: "nav.banking", icon: Landmark, roles: ["admin", "manager"], feature: "banking" },
      { to: "/reconciliation", label: "nav.reconciliation", icon: Scale, roles: ["admin", "manager"], feature: "banking" },
    ],
  },
  {
    title: "section.insights",
    items: [
      { to: "/reports", label: "nav.reports", icon: FileText, roles: ["admin", "manager"], feature: "reports" },
      { to: "/report-builder", label: "nav.reportBuilder", icon: BarChart3, roles: ["admin", "manager"], feature: "bi" },
      { to: "/assistant", label: "nav.assistant", icon: Sparkles, live: true, feature: "ai" },
    ],
  },
  {
    title: "section.admin",
    items: [
      { to: "/users", label: "nav.users", icon: Users, roles: ["admin"] },
      { to: "/settings/roles", label: "nav.roles", icon: ShieldCheck, roles: ["super_admin"] },
      { to: "/settings/localization", label: "nav.localization", icon: Settings, roles: ["admin"], feature: "localization" },
      { to: "/settings/currencies", label: "nav.currencies", icon: Coins, roles: ["admin"], feature: "currencies" },
      { to: "/settings/administration", label: "nav.administration", icon: ShieldCheck, roles: ["admin"] },
    ],
  },
];

export default function Layout() {
  const { user, logout } = useAuth();
  const { theme, toggle } = useTheme();
  const { feature, modules, restricted } = useSession();
  const { t } = useI18n();
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

  // Two visibility regimes:
  //  • A custom (restricted) role is gated purely by its module allowlist —
  //    the role name means nothing to the built-in `roles` arrays, so those
  //    are bypassed and the allowlist decides.
  //  • A built-in role keeps the original role + feature-flag gating.
  // In both cases a disabled feature flag still hides the entry.
  const isSuper = user!.role === "super_admin";
  const canSee = (i: NavItem) => {
    if (i.feature && !feature(i.feature)) return false;
    if (restricted) return pathAllowed(i.to, modules);
    return isSuper || !i.roles || i.roles.includes(user!.role);
  };
  const visibleSections = NAV_SECTIONS.map((s) => ({
    title: s.title,
    items: s.items.filter(canSee),
  })).filter((s) => s.items.length > 0);
  const initials =
    ((user?.first_name?.[0] ?? "") + (user?.last_name?.[0] ?? "")).toUpperCase() ||
    (user?.email?.[0] ?? "?").toUpperCase();
  const fullName =
    [user?.first_name, user?.last_name].filter(Boolean).join(" ") || user?.email;

  return (
    <div className="flex min-h-dvh" style={{ background: "transparent", position: "relative", zIndex: 1 }}>
      <AppBackground />
      {/* ───────────── sidebar ───────────── */}
      <motion.aside
        animate={{ width: collapsed ? 72 : 264 }}
        transition={{ duration: 0.32, ease: [0.16, 1, 0.3, 1] }}
        className="flex shrink-0 flex-col overflow-hidden"
        style={{
          background: "var(--bg-panel)",
          borderRight: "1px solid var(--border-subtle)",
          // Pin the sidebar to the viewport so its nav scrolls internally
          // instead of the whole column growing past the fold.
          position: "sticky",
          top: 0,
          alignSelf: "flex-start",
          height: "100dvh",
        }}
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
          {visibleSections.map((section, si) => (
            <div key={section.title} className="flex flex-col gap-0.5">
              {!collapsed ? (
                <div className="eyebrow" style={{ padding: si === 0 ? "6px 10px 4px" : "14px 10px 4px" }}>
                  {t(section.title)}
                </div>
              ) : (
                si > 0 && <div style={{ height: 1, margin: "8px", background: "var(--border-subtle)" }} />
              )}
              {section.items.map(({ to, label, icon: Icon, live }) => (
                <NavLink key={to} to={to} end={to === "/"} title={t(label)}>
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
                          style={{ insetInlineStart: 0, top: 8, bottom: 8, width: 3, background: "var(--emerald-400)" }}
                        />
                      )}
                      <Icon size={18} color={isActive ? "var(--emerald-400)" : undefined} strokeWidth={1.75} />
                      {!collapsed && <span>{t(label)}</span>}
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
            </div>
          ))}
        </nav>

        {/* user chip → my account */}
        <div style={{ padding: 12, borderTop: "1px solid var(--border-subtle)" }}>
          <NavLink
            to="/settings/profile"
            title={t("top.myAccount")}
            className="flex items-center gap-2.5 rounded-[10px]"
            style={({ isActive }) => ({
              padding: collapsed ? "6px 0" : "6px 8px",
              justifyContent: collapsed ? "center" : "flex-start",
              background: isActive ? "var(--surface-hover)" : "transparent",
            })}
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
                  {user?.role ? t(`role.${user.role}`) : ""}
                </div>
              </div>
            )}
          </NavLink>
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
            <Search size={16} /> {t("top.search")}
            <span
              style={{
                marginInlineStart: "auto",
                font: "500 11px/1 var(--font-mono)",
                background: "var(--surface-hover)",
                padding: "3px 6px",
                borderRadius: 6,
              }}
            >
              ⌘K
            </span>
          </button>

          <div className="ms-auto flex items-center gap-2">
            <Button
              variant="secondary"
              size="sm"
              onClick={() => navigate("/assistant")}
              icon={<Sparkles size={15} color="var(--emerald-400)" />}
            >
              {t("top.askAI")}
            </Button>
            <NotificationBell />
            <LanguageSwitcher />
            <IconButton
              size="md"
              onClick={toggle}
              aria-label={`${t("top.theme")}: ${theme}`}
              title={`${t("top.theme")}: ${theme} → ${nextThemeLabel}`}
            >
              {theme === "dark" ? <Moon size={18} /> : theme === "creme" ? <Coffee size={18} /> : <Sun size={18} />}
            </IconButton>
            <IconButton size="md" onClick={logout} aria-label={t("top.signOut")}>
              <LogOut size={18} />
            </IconButton>
          </div>
        </header>

        {/* content */}
        <main
          className="w-full flex-1"
          style={{ padding: "36px 40px", maxWidth: "var(--content-max)", margin: "0 auto" }}
        >
          {/* Keyed remount with a fade-in only — no exit animation. An
              AnimatePresence "wait" here made each navigation block on the
              previous page's exit, so rapid navigation could leave a page
              unrendered until a manual refresh. */}
          <ErrorBoundary resetKey={location.pathname}>
            <motion.div
              key={location.pathname}
              initial={{ opacity: 0, y: 6 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.2, ease: "easeOut" }}
            >
              <Outlet />
            </motion.div>
          </ErrorBoundary>
        </main>
      </div>

      <CommandPalette open={paletteOpen} onClose={() => setPaletteOpen(false)} />
    </div>
  );
}
