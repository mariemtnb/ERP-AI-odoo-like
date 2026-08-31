import { createContext, useContext, type ReactNode } from "react";
import { useQuery } from "@tanstack/react-query";
import { getContext, type MeContext } from "@/api/admin";
import { useAuth } from "@/features/auth/AuthContext";

/**
 * Effective permissions and enabled modules for the signed-in user.
 *
 * The UI uses this to hide what a user cannot do. It is a convenience, never
 * the security boundary — the backend re-checks every request, exactly as it
 * always has.
 */
interface Session {
  permissions: string[];
  features: Record<string, boolean>;
  /** Module allowlist for a custom role, or null when unrestricted. */
  modules: string[] | null;
  company: MeContext["company"];
  /** Does the user hold this permission? */
  can: (key: string) => boolean;
  /** Is this module switched on? Unknown modules default to on. */
  feature: (key: string) => boolean;
  /** May this user reach the given module? A built-in role may reach any. */
  module: (key: string | null) => boolean;
  /** True when the user's role restricts them to a module allowlist. */
  restricted: boolean;
  isLoading: boolean;
}

const SessionContext = createContext<Session>({
  permissions: [],
  features: {},
  modules: null,
  company: null,
  can: () => false,
  feature: () => true,
  module: () => true,
  restricted: false,
  isLoading: true,
});

export function SessionProvider({ children }: { children: ReactNode }) {
  const { user } = useAuth();

  const { data, isLoading } = useQuery({
    queryKey: ["me-context", user?.id],
    queryFn: getContext,
    enabled: !!user,
    staleTime: 5 * 60 * 1000,
  });

  const permissions = data?.permissions ?? [];
  const features = data?.features ?? {};
  const modules = data?.modules ?? null;

  const value: Session = {
    permissions,
    features,
    modules,
    company: data?.company ?? null,
    can: (key) => permissions.includes(key),
    // Default to enabled: a module must never vanish because the context
    // request has not landed yet.
    feature: (key) => features[key] ?? true,
    // No allowlist → unrestricted. Otherwise only listed modules, and always
    // the pathless areas (key === null, e.g. the dashboard).
    module: (key) => modules === null || key === null || modules.includes(key),
    restricted: modules !== null,
    isLoading,
  };

  return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>;
}

export function useSession() {
  return useContext(SessionContext);
}
