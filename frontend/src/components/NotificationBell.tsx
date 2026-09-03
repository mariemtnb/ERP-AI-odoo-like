import { useEffect, useRef, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { AlertTriangle, Bell, CheckCheck, Info, RefreshCw } from "lucide-react";
import {
  getUnreadCount, listNotifications, markAllNotificationsRead,
  markNotificationRead, scanNotifications, type AppNotification,
} from "@/api/notifications";
import { IconButton } from "@/components/ui/icon-button";
import { useSession } from "@/lib/session";
import { useAuth } from "@/features/auth/AuthContext";

/**
 * The top-bar bell. Polls the unread count in the background and opens a panel
 * of recent notifications. This is a convenience surface - the backend is the
 * source of truth and scopes everything to the signed-in user.
 */
export function NotificationBell() {
  const qc = useQueryClient();
  const navigate = useNavigate();
  const { feature } = useSession();
  const { user } = useAuth();
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  // Only managers/admins may trigger a scan (matches the API).
  const canScan = user?.role === "admin" || user?.role === "manager";
  const enabled = feature("notifications");

  const { data: unread = 0 } = useQuery({
    queryKey: ["notifications-unread"],
    queryFn: getUnreadCount,
    refetchInterval: 60_000,   // check once a minute
    enabled,
  });

  const { data: list } = useQuery({
    queryKey: ["notifications-list"],
    queryFn: () => listNotifications({ page_size: 15 }),
    enabled: open,
  });

  const refresh = () => {
    qc.invalidateQueries({ queryKey: ["notifications-unread"] });
    qc.invalidateQueries({ queryKey: ["notifications-list"] });
  };

  const read = useMutation({ mutationFn: (id: number) => markNotificationRead(id), onSuccess: refresh });
  const readAll = useMutation({ mutationFn: markAllNotificationsRead, onSuccess: refresh });
  const scan = useMutation({ mutationFn: scanNotifications, onSuccess: refresh });

  // Close when clicking outside the panel.
  useEffect(() => {
    function onClick(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    }
    if (open) document.addEventListener("mousedown", onClick);
    return () => document.removeEventListener("mousedown", onClick);
  }, [open]);

  if (!enabled) return null;

  function openNotification(n: AppNotification) {
    if (!n.is_read) read.mutate(n.id);
    if (n.link) {
      setOpen(false);
      navigate(n.link);
    }
  }

  const rows = list?.results ?? [];

  return (
    <div ref={ref} className="relative">
      <IconButton
        size="md"
        aria-label={`Notifications${unread ? ` (${unread} unread)` : ""}`}
        title="Notifications"
        onClick={() => setOpen((v) => !v)}
        style={{ position: "relative" }}
      >
        <Bell size={18} />
        {unread > 0 && (
          <span
            className="absolute grid place-items-center rounded-full"
            style={{
              top: 4, right: 3, minWidth: 15, height: 15, padding: "0 3px",
              background: "var(--rose-400)", color: "#fff",
              font: "600 9px/1 var(--font-sans)", border: "2px solid var(--bg-app)",
            }}
          >
            {unread > 9 ? "9+" : unread}
          </span>
        )}
      </IconButton>

      {open && (
        <div
          className="absolute right-0 z-50 mt-2 w-96 overflow-hidden rounded-xl shadow-lg"
          style={{ background: "var(--bg-panel)", border: "1px solid var(--border)" }}
        >
          <div
            className="flex items-center justify-between px-4 py-3"
            style={{ borderBottom: "1px solid var(--border-subtle)" }}
          >
            <span style={{ font: "600 13px/1 var(--font-sans)", color: "var(--text-strong)" }}>
              Notifications
            </span>
            <div className="flex items-center gap-1">
              {canScan && (
                <IconButton size="sm" aria-label="Check now" title="Check now" onClick={() => scan.mutate()}>
                  <RefreshCw size={14} className={scan.isPending ? "animate-spin" : ""} />
                </IconButton>
              )}
              {unread > 0 && (
                <IconButton size="sm" aria-label="Mark all read" title="Mark all read" onClick={() => readAll.mutate()}>
                  <CheckCheck size={14} />
                </IconButton>
              )}
            </div>
          </div>

          <div className="max-h-[26rem] overflow-y-auto">
            {rows.length === 0 ? (
              <p className="px-4 py-10 text-center text-sm text-text-3">
                You're all caught up.
              </p>
            ) : (
              rows.map((n) => (
                <button
                  key={n.id}
                  onClick={() => openNotification(n)}
                  className="flex w-full gap-3 px-4 py-3 text-left transition-colors"
                  style={{
                    borderBottom: "1px solid var(--border-subtle)",
                    background: n.is_read ? "transparent" : "var(--surface-hover)",
                  }}
                >
                  <span className="mt-0.5 shrink-0">
                    {n.severity === "critical" ? (
                      <AlertTriangle size={16} color="var(--rose-400)" />
                    ) : n.severity === "warning" ? (
                      <AlertTriangle size={16} color="var(--amber-400)" />
                    ) : (
                      <Info size={16} color="var(--sky-400)" />
                    )}
                  </span>
                  <span className="min-w-0 flex-1">
                    <span
                      className="block truncate"
                      style={{ font: "500 13px/1.3 var(--font-sans)", color: "var(--text-strong)" }}
                    >
                      {n.title}
                    </span>
                    {n.body && (
                      <span
                        className="mt-0.5 block"
                        style={{ font: "400 12px/1.4 var(--font-sans)", color: "var(--text-muted)" }}
                      >
                        {n.body}
                      </span>
                    )}
                    <span
                      className="mt-1 block"
                      style={{ font: "400 11px/1 var(--font-sans)", color: "var(--text-faint)" }}
                    >
                      {new Date(n.created_at).toLocaleString()}
                    </span>
                  </span>
                </button>
              ))
            )}
          </div>
        </div>
      )}
    </div>
  );
}
