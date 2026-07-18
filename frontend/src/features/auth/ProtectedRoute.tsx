import { Navigate, Outlet } from "react-router-dom";
import { useAuth } from "./AuthContext";
import type { Role } from "@/types";

export function ProtectedRoute({ roles }: { roles?: Role[] }) {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <div className="flex min-h-dvh items-center justify-center bg-bg">
        <span className="h-6 w-6 animate-spin rounded-full border-2 border-stroke border-t-accent" />
      </div>
    );
  }
  if (!user) return <Navigate to="/welcome" replace />;
  if (roles && !roles.includes(user.role)) return <Navigate to="/" replace />;
  return <Outlet />;
}
