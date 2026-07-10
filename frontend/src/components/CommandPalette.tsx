import { useEffect, useMemo, useRef, useState } from "react";
import { useNavigate } from "react-router-dom";
import { AnimatePresence, motion } from "framer-motion";
import {
  Boxes, Contact, FileText, LayoutDashboard, MessageSquare, Package,
  Search, ShoppingBag, ShoppingCart, Truck, Users, UserSquare2,
} from "lucide-react";
import { useAuth } from "@/features/auth/AuthContext";
import { cn } from "@/lib/utils";

const DESTINATIONS = [
  { to: "/", label: "Dashboard", icon: LayoutDashboard, hint: "KPIs, forecast, alerts" },
  { to: "/products", label: "Products", icon: Package, hint: "Catalog & categories" },
  { to: "/inventory", label: "Inventory", icon: Boxes, hint: "Movements, transfers, warehouses" },
  { to: "/customers", label: "Customers", icon: UserSquare2, hint: "Client base" },
  { to: "/suppliers", label: "Suppliers", icon: Truck, hint: "Vendor contacts" },
  { to: "/purchases", label: "Purchases", icon: ShoppingBag, hint: "Orders, receiving, approvals" },
  { to: "/sales", label: "Sales", icon: ShoppingCart, hint: "Sales & invoices" },
  { to: "/crm", label: "CRM", icon: Contact, hint: "Lead pipeline" },
  { to: "/reports", label: "Reports", icon: FileText, hint: "Analytics & PDF export", roles: ["admin", "manager"] },
  { to: "/assistant", label: "AI Assistant", icon: MessageSquare, hint: "Ask anything, act on data" },
  { to: "/users", label: "Users", icon: Users, hint: "Accounts & roles", roles: ["admin"] },
];

export function CommandPalette({
  open,
  onClose,
}: {
  open: boolean;
  onClose: () => void;
}) {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [query, setQuery] = useState("");
  const [active, setActive] = useState(0);
  const inputRef = useRef<HTMLInputElement>(null);

  const items = useMemo(
    () =>
      DESTINATIONS.filter(
        (d) =>
          (!d.roles || d.roles.includes(user?.role ?? "")) &&
          (d.label + " " + d.hint).toLowerCase().includes(query.toLowerCase())
      ),
    [query, user]
  );

  useEffect(() => {
    if (open) {
      setQuery("");
      setActive(0);
      setTimeout(() => inputRef.current?.focus(), 50);
    }
  }, [open]);

  function go(to: string) {
    onClose();
    navigate(to);
  }

  return (
    <AnimatePresence>
      {open && (
        <motion.div
          className="fixed inset-0 z-[60] flex items-start justify-center px-4 pt-[18vh]"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.15 }}
          style={{ background: "hsl(var(--overlay) / 0.6)", backdropFilter: "blur(4px)" }}
          onMouseDown={(e) => e.target === e.currentTarget && onClose()}
        >
          <motion.div
            initial={{ opacity: 0, scale: 0.97, y: -8 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.98, y: -6 }}
            transition={{ duration: 0.18, ease: [0.22, 1, 0.36, 1] }}
            className="w-full max-w-xl overflow-hidden rounded-xl bg-surface shadow-3 ring-1 ring-inset ring-white/[0.07]"
          >
            <div className="flex items-center gap-3 border-b border-stroke-soft px-4">
              <Search className="h-4 w-4 shrink-0 text-text-3" />
              <input
                ref={inputRef}
                value={query}
                onChange={(e) => {
                  setQuery(e.target.value);
                  setActive(0);
                }}
                onKeyDown={(e) => {
                  if (e.key === "Escape") onClose();
                  if (e.key === "ArrowDown") setActive((a) => Math.min(a + 1, items.length - 1));
                  if (e.key === "ArrowUp") setActive((a) => Math.max(a - 1, 0));
                  if (e.key === "Enter" && items[active]) go(items[active].to);
                }}
                placeholder="Where to? Type to search…"
                className="h-13 w-full bg-transparent py-4 text-[15px] text-text placeholder:text-text-3 focus:outline-none"
              />
              <kbd className="rounded-md bg-surface-3 px-1.5 py-0.5 text-[10px] font-medium text-text-3">
                ESC
              </kbd>
            </div>
            <ul className="max-h-80 overflow-y-auto p-2">
              {items.length === 0 && (
                <li className="px-3 py-8 text-center text-sm text-text-3">
                  Nothing matches “{query}”
                </li>
              )}
              {items.map((d, i) => (
                <li key={d.to}>
                  <button
                    onMouseEnter={() => setActive(i)}
                    onClick={() => go(d.to)}
                    className={cn(
                      "flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors duration-100",
                      i === active ? "bg-accent/10" : ""
                    )}
                  >
                    <d.icon
                      className={cn("h-4 w-4", i === active ? "text-accent" : "text-text-3")}
                    />
                    <span className={cn("text-sm font-medium", i === active ? "text-text" : "text-text-2")}>
                      {d.label}
                    </span>
                    <span className="ml-auto truncate pl-4 text-xs text-text-3">{d.hint}</span>
                  </button>
                </li>
              ))}
            </ul>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
