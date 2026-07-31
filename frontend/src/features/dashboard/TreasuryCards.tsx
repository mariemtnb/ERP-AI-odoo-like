import { useQuery } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import {
  AlertTriangle, Banknote, CalendarClock, Landmark, ReceiptText, Wallet,
  type LucideIcon,
} from "lucide-react";
import { getTreasuryDashboard } from "@/api/tunisia";
import { Skeleton } from "@/components/ui/skeleton";
import { formatTnd } from "@/lib/tnLabels";

/**
 * Treasury row on the dashboard: the six figures a Tunisian SME owner checks
 * first thing — what is out in cheques, what bounced, what is late, and what
 * actually came in.
 */
export function TreasuryCards() {
  const navigate = useNavigate();
  const { data, isLoading } = useQuery({
    queryKey: ["treasury"],
    queryFn: () => getTreasuryDashboard(),
  });

  if (isLoading || !data) {
    return (
      <div
        className="mb-5 grid gap-4"
        style={{ gridTemplateColumns: "repeat(auto-fit, minmax(190px, 1fr))" }}
      >
        {Array.from({ length: 6 }).map((_, i) => (
          <Skeleton key={i} className="h-[104px] rounded-lg" />
        ))}
      </div>
    );
  }

  const cards: {
    label: string;
    sub: string;
    value: string;
    icon: LucideIcon;
    tone?: "danger" | "warning";
    to: string;
  }[] = [
    {
      label: "Cheques to collect",
      sub: `${data.instruments.outstanding_incoming_count} chèques / effets en portefeuille`,
      value: formatTnd(data.instruments.outstanding_incoming_amount),
      icon: ReceiptText,
      to: "/instruments",
    },
    {
      label: "Bounced instruments",
      sub: `${data.instruments.bounced_count} impayés à régulariser`,
      value: formatTnd(data.instruments.bounced_amount),
      icon: AlertTriangle,
      tone: data.instruments.bounced_count > 0 ? "danger" : undefined,
      to: "/instruments",
    },
    {
      label: "Overdue instalments",
      sub: `${data.installments.overdue_count} échéances en retard`,
      value: formatTnd(data.installments.overdue_amount),
      icon: CalendarClock,
      tone: data.installments.overdue_count > 0 ? "warning" : undefined,
      to: "/installments",
    },
    {
      label: "To reconcile",
      sub: `${data.reconciliation.pending_count} lignes bancaires en attente`,
      value: formatTnd(data.reconciliation.pending_amount),
      icon: Landmark,
      to: "/reconciliation",
    },
    {
      label: "Cash collected",
      sub: "Encaissements en espèces",
      value: formatTnd(data.collections.cash_collected),
      icon: Wallet,
      to: "/banking",
    },
    {
      label: "Bank collections",
      sub: "Virements et versements",
      value: formatTnd(data.collections.bank_collected),
      icon: Banknote,
      to: "/banking",
    },
  ];

  return (
    <div
      className="mb-5 grid gap-4"
      style={{ gridTemplateColumns: "repeat(auto-fit, minmax(190px, 1fr))" }}
    >
      {cards.map((c) => {
        const accent =
          c.tone === "danger"
            ? "var(--rose-400)"
            : c.tone === "warning"
              ? "var(--amber-400)"
              : "var(--emerald-400)";
        return (
          <button
            key={c.label}
            onClick={() => navigate(c.to)}
            className="erp-card erp-card--hover flex flex-col gap-2 p-4 text-left"
          >
            <div className="flex items-center justify-between">
              <span className="eyebrow">{c.label}</span>
              <c.icon size={16} color={accent} />
            </div>
            <span
              className="tnum"
              style={{
                font: "600 20px/1 var(--font-sans)",
                letterSpacing: "-0.02em",
                color: c.tone ? accent : "var(--text-strong)",
              }}
            >
              {c.value}
            </span>
            <span style={{ font: "400 11px/1.3 var(--font-sans)", color: "var(--text-faint)" }}>
              {c.sub}
            </span>
          </button>
        );
      })}
    </div>
  );
}
