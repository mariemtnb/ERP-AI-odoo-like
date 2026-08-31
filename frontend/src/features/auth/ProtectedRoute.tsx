import { Navigate, Outlet, useLocation } from "react-router-dom";
import { useAuth } from "./AuthContext";
import { useSession } from "@/lib/session";
import { pathAllowed } from "@/lib/modules";
import type { Role } from "@/types";

export function ProtectedRoute({ roles }: { roles?: Role[] }) {
  const { user, loading } = useAuth();
  const { restricted, modules, isLoading } = useSession();
  const location = useLocation();

  if (loading || (user && isLoading)) {
    return (
      <div className="flex min-h-dvh items-center justify-center bg-bg">
        <span className="h-6 w-6 animate-spin rounded-full border-2 border-stroke border-t-accent" />
      </div>
    );
  }
  if (!user) return <Navigate to="/welcome" replace />;

  // A custom role is gated by its module allowlist, not by the built-in role
  // arrays (which never name it). The allowlist is the whole boundary.
  if (restricted) {
    return pathAllowed(location.pathname, modules) ? <Outlet /> : <Navigate to="/" replace />;
  }

  // Super admin is a superset of every role, so it clears any role gate.
  if (roles && user.role !== "super_admin" && !roles.includes(user.role)) {
    return <Navigate to="/" replace />;
  }
  return <Outlet />;
}
