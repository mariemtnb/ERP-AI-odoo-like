/**
 * The module catalogue, shared by the sidebar and the route guard.
 *
 * A custom role carries an allowlist of these keys; a user holding it may only
 * see and reach the matching areas. Built-in roles resolve to `modules == null`
 * on the session and keep their original, role-driven visibility.
 *
 * Keep the keys in sync with the backend `App\Support\Modules` catalogue.
 */

/** Route path → module key. Paths absent here (Dashboard, My account) are
 *  always reachable regardless of the allowlist. */
export const MODULE_BY_PATH: Record<string, string> = {
  "/products": "inventory",
  "/inventory": "inventory",
  "/lots": "lots",
  "/manufacturing": "manufacturing",
  "/suppliers": "purchasing",
  "/purchases": "purchasing",
  "/reordering": "reordering",
  "/rfqs": "rfq",
  "/customers": "sales",
  "/sales": "sales",
  "/pos": "pos",
  "/subscriptions": "subscriptions",
  "/returns": "returns",
  "/shipping": "shipping",
  "/marketing": "marketing",
  "/crm": "crm",
  "/projects": "projects",
  "/helpdesk": "helpdesk",
  "/owner": "profit",
  "/accounting": "accounting",
  "/assets": "assets",
  "/payroll": "payroll",
  "/hr": "hr",
  "/instruments": "treasury",
  "/installments": "treasury",
  "/banking": "banking",
  "/reconciliation": "banking",
  "/reports": "reports",
  "/report-builder": "bi",
  "/assistant": "ai",
};

/** Paths every signed-in user may reach, whatever their role restricts. */
const ALWAYS_ALLOWED = new Set(["/", "/settings/profile"]);

/** The module a path belongs to, or null when it has none. */
export function moduleForPath(pathname: string): string | null {
  // Normalise trailing segments: "/sales/123" still belongs to "sales".
  const top = "/" + (pathname.split("/").filter(Boolean)[0] ?? "");
  return MODULE_BY_PATH[pathname] ?? MODULE_BY_PATH[top] ?? null;
}

/**
 * May a user whose allowlist is `modules` reach `pathname`?
 *
 * `modules === null` means an unrestricted (built-in) role - everything is
 * reachable. Otherwise only the always-allowed paths and the modules on the
 * list are; a path with no module of its own (e.g. the admin screens) is NOT
 * reachable by a restricted role.
 */
export function pathAllowed(pathname: string, modules: string[] | null): boolean {
  if (modules === null) return true;
  if (ALWAYS_ALLOWED.has(pathname)) return true;
  const m = moduleForPath(pathname);
  return m !== null && modules.includes(m);
}
