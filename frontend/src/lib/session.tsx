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
  company: MeContext["company"];
  /** Does the user hold this permission? */
  can: (key: string) => boolean;
  /** Is this module switched on? Unknown modules default to on. */
  feature: (key: string) => boolean;
  isLoading: boolean;
}

const SessionContext = createContext<Session>({
  permissions: [],
  features: {},
  company: null,
  can: () => false,
  feature: () => true,
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

  const value: Session = {
    permissions,
    features,
    company: data?.company ?? null,
    can: (key) => permissions.includes(key),
    // Default to enabled: a module must never vanish because the context
    // request has not landed yet.
    feature: (key) => features[key] ?? true,
    isLoading,
  };

  return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>;
}

export function useSession() {
  return useContext(SessionContext);
}
