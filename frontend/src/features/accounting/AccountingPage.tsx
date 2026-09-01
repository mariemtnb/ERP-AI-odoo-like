import { useMemo, useState, type FormEvent } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { BookOpen, Download, Plus } from "lucide-react";
import {
  downloadStatementPdf,
  getIncomeStatement,
  getTrialBalance,
  listAccounts,
  listJournalEntries,
  postJournalEntry,
} from "@/api/accounting";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { PageHead } from "@/components/ui/page-head";
import { Segmented } from "@/components/ui/segmented";
import { Select } from "@/components/ui/select";
import { TableSkeleton } from "@/components/ui/skeleton";
import { Table, TBody, Td, Th, THead } from "@/components/ui/table";
import { useAuth } from "@/features/auth/AuthContext";
import { cn } from "@/lib/utils";
import { useI18n } from "@/lib/i18n";

type Tab = "journal" | "trial-balance" | "income-statement";

const TYPE_TONE: Record<string, string> = {
  asset: "sky",
  liability: "amber",
  equity: "violet",
  income: "emerald",
  expense: "rose",
};

function firstOfMonth() {
  const d = new Date();
  return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10);
}
const today = () => new Date().toISOString().slice(0, 10);
const money = (n: number | string) =>
  Number(n).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function AccountingPage() {
  const { user } = useAuth();
  const { t } = useI18n();
  const canPost = user!.role !== "employee";
  const [tab, setTab] = useState<Tab>("journal");
  const [from, setFrom] = useState(firstOfMonth());
  const [to, setTo] = useState(today());
  const [dialogOpen, setDialogOpen] = useState(false);
  const params = useMemo(() => ({ from, to }), [from, to]);

  return (
    <div>
      <PageHead title={t("nav.accounting")} sub={t("acc.sub")}>
        {tab !== "journal" && (
          <Button
            variant="outline"
            size="md"
            icon={<Download size={16} />}
            onClick={() => downloadStatementPdf(tab === "trial-balance" ? "trial-balance" : "income-statement", params)}
          >
            {t("rep.exportPdf")}
          </Button>
        )}
        {canPost && (
          <Button variant="primary" size="md" icon={<Plus size={16} />} onClick={() => setDialogOpen(true)}>
            {t("acc.newEntry")}
          </Button>
        )}
      </PageHead>

      <div className="mb-5 flex flex-wrap items-end gap-4">
        <Segmented
          id="accounting-tab"
          value={tab}
          onChange={setTab}
          options={[
            { value: "journal", label: t("acc.tab.journal") },
            { value: "trial-balance", label: t("acc.tab.trial") },
            { value: "income-statement", label: t("acc.tab.income") },
          ]}
        />
        <div className="space-y-1.5">
          <Label htmlFor="a-from">{t("common.from")}</Label>
          <Input id="a-from" type="date" className="w-40" value={from} onChange={(e) => setFrom(e.target.value)} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="a-to">{t("common.to")}</Label>
          <Input id="a-to" type="date" className="w-40" value={to} onChange={(e) => setTo(e.target.value)} />
        </div>
      </div>

      {tab === "journal" && <Journal params={params} />}
      {tab === "trial-balance" && <TrialBalanceView params={params} />}
      {tab === "income-statement" && <IncomeStatementView params={params} />}

      <ManualEntryDialog open={dialogOpen} onClose={() => setDialogOpen(false)} />
    </div>
  );
}

/* ---------------- Journal ---------------- */
function Journal({ params }: { params: { from: string; to: string } }) {
  const { t } = useI18n();
  const { data, isLoading } = useQuery({
    queryKey: ["journal", params.from, params.to],
    queryFn: () => listJournalEntries({ ...params, page_size: 50 }),
  });

  if (isLoading) return <TableSkeleton rows={6} />;
  if (!data || data.results.length === 0) {
    return (
      <EmptyState
        icon={BookOpen}
        title={t("acc.noJournal")}
        hint={t("acc.noJournalHint")}
      />
    );
  }

  return (
    <div className="space-y-3">
      {data.results.map((entry) => (
        <div key={entry.id} className="erp-card overflow-hidden">
          <div
            className="flex flex-wrap items-center justify-between gap-3"
            style={{ padding: "14px 20px", borderBottom: "1px solid var(--border-subtle)" }}
          >
            <div className="flex items-center gap-3">
              <span style={{ font: "600 13px/1 var(--font-mono)", color: "var(--emerald-400)" }}>
                {entry.number}
              </span>
              <span style={{ font: "400 13px/1 var(--font-sans)", color: "var(--text-body)" }}>
                {entry.memo || "—"}
              </span>
              <Badge tone={entry.reference_type === "manual" ? "neutral" : "sky"}>
                {entry.reference_type === "manual" ? t("acc.manual") : entry.reference_type}
              </Badge>
            </div>
            <div className="flex items-center gap-4">
              <span style={{ font: "400 12px/1 var(--font-mono)", color: "var(--text-faint)" }}>
                {entry.entry_date}
              </span>
              <span className="tnum" style={{ font: "600 14px/1 var(--font-mono)", color: "var(--text-strong)" }}>
                {money(entry.total)}
              </span>
            </div>
          </div>
          <table className="erp-table">
            <tbody>
              {entry.lines.map((l) => (
                <tr key={l.id}>
                  <td style={{ width: 90, fontFamily: "var(--font-mono)", fontSize: 12, color: "var(--text-faint)" }}>
                    {l.account_code}
                  </td>
                  <td style={{ color: "var(--text-strong)" }}>{l.account_name}</td>
                  <td style={{ color: "var(--text-muted)" }}>{l.label}</td>
                  <td className="tnum" style={{ textAlign: "right", width: 120, fontFamily: "var(--font-mono)" }}>
                    {Number(l.debit) > 0 ? money(l.debit) : ""}
                  </td>
                  <td className="tnum" style={{ textAlign: "right", width: 120, fontFamily: "var(--font-mono)", color: "var(--text-muted)" }}>
                    {Number(l.credit) > 0 ? money(l.credit) : ""}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ))}
    </div>
  );
}

/* ---------------- Trial balance ---------------- */
function TrialBalanceView({ params }: { params: { from: string; to: string } }) {
  const { t } = useI18n();
  const { data, isLoading } = useQuery({
    queryKey: ["trial-balance", params.from, params.to],
    queryFn: () => getTrialBalance(params),
  });

  if (isLoading) return <TableSkeleton rows={6} />;
  if (!data || data.rows.length === 0) {
    return <EmptyState icon={BookOpen} title={t("acc.noPosted")} hint={t("acc.noPostedHint")} />;
  }

  const balanced = Math.abs(data.total_debit - data.total_credit) < 0.005;

  return (
    <>
      <Table>
        <THead>
          <tr>
            <Th>{t("acc.code")}</Th>
            <Th>{t("acc.account")}</Th>
            <Th>{t("acc.type")}</Th>
            <Th className="text-right">{t("acc.debit")}</Th>
            <Th className="text-right">{t("acc.credit")}</Th>
            <Th className="text-right">{t("acc.balance")}</Th>
          </tr>
        </THead>
        <TBody>
          {data.rows.map((r) => (
            <tr key={r.code}>
              <Td className="font-mono text-xs">{r.code}</Td>
              <Td style={{ color: "var(--text-strong)" }}>{r.name}</Td>
              <Td><Badge tone={TYPE_TONE[r.type]}>{t(`acctype.${r.type}`)}</Badge></Td>
              <Td className="text-right">{money(r.debit)}</Td>
              <Td className="text-right">{money(r.credit)}</Td>
              <Td className="text-right" style={{ color: "var(--text-strong)" }}>{money(r.balance)}</Td>
            </tr>
          ))}
        </TBody>
      </Table>
      <p className="mt-3 flex items-center justify-end gap-3 text-sm">
        <span style={{ color: "var(--text-muted)" }}>
          {t("acc.debit")} <b className="tnum" style={{ color: "var(--text-strong)" }}>{money(data.total_debit)}</b> · {t("acc.credit")}{" "}
          <b className="tnum" style={{ color: "var(--text-strong)" }}>{money(data.total_credit)}</b>
        </span>
        <Badge tone={balanced ? "emerald" : "rose"} dot>{balanced ? t("acc.balanced") : t("acc.outOfBalance")}</Badge>
      </p>
    </>
  );
}

/* ---------------- Income statement ---------------- */
function IncomeStatementView({ params }: { params: { from: string; to: string } }) {
  const { t } = useI18n();
  const { data, isLoading } = useQuery({
    queryKey: ["income-statement", params.from, params.to],
    queryFn: () => getIncomeStatement(params),
  });

  if (isLoading) return <TableSkeleton rows={5} />;
  if (!data) return null;

  const Section = ({ title, rows, total }: { title: string; rows: typeof data.income; total: number }) => (
    <div className="erp-card">
      <div style={{ padding: "16px 20px", borderBottom: "1px solid var(--border-subtle)" }}>
        <h3 style={{ margin: 0, font: "600 15px/1 var(--font-sans)", color: "var(--text-strong)" }}>{title}</h3>
      </div>
      <div style={{ padding: "6px 20px 14px" }}>
        {rows.length === 0 ? (
          <p style={{ padding: "16px 0", font: "400 13px/1 var(--font-sans)", color: "var(--text-faint)" }}>
            {t("acc.nothingRecorded")}
          </p>
        ) : (
          rows.map((r) => (
            <div key={r.code} className="flex justify-between" style={{ padding: "10px 0", borderBottom: "1px solid var(--border-subtle)" }}>
              <span style={{ color: "var(--text-body)" }}>
                <span className="font-mono text-xs" style={{ color: "var(--text-faint)" }}>{r.code}</span> {r.name}
              </span>
              <span className="tnum" style={{ fontFamily: "var(--font-mono)", color: "var(--text-strong)" }}>{money(r.balance)}</span>
            </div>
          ))
        )}
        <div className="flex justify-between pt-3">
          <span style={{ font: "600 13px/1 var(--font-sans)", color: "var(--text-muted)" }}>{t("acc.totalWord")} {title.toLowerCase()}</span>
          <span className="tnum" style={{ font: "600 14px/1 var(--font-mono)", color: "var(--text-strong)" }}>{money(total)}</span>
        </div>
      </div>
    </div>
  );

  return (
    <div className="space-y-4">
      <div className="grid gap-4" style={{ gridTemplateColumns: "1fr 1fr" }}>
        <Section title={t("acc.income")} rows={data.income} total={data.total_income} />
        <Section title={t("acc.expenses")} rows={data.expenses} total={data.total_expenses} />
      </div>
      <div
        className="erp-card flex items-center justify-between"
        style={{
          padding: "20px 22px",
          background: "linear-gradient(160deg, color-mix(in oklab, var(--emerald-500) 10%, var(--surface-card)), var(--surface-card))",
        }}
      >
        <span style={{ font: "600 15px/1 var(--font-sans)", color: "var(--text-strong)" }}>
          {data.net_profit >= 0 ? t("acc.netProfit") : t("acc.netLoss")}
        </span>
        <span
          className="tnum"
          style={{
            font: "600 28px/1 var(--font-sans)",
            letterSpacing: "-0.03em",
            color: data.net_profit >= 0 ? "var(--emerald-400)" : "var(--rose-400)",
          }}
        >
          {money(Math.abs(data.net_profit))}
        </span>
      </div>
    </div>
  );
}

/* ---------------- Manual entry ---------------- */
type LineDraft = { account: string; debit: string; credit: string; label: string };
const emptyLine = (): LineDraft => ({ account: "", debit: "", credit: "", label: "" });

function ManualEntryDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const { t } = useI18n();
  const qc = useQueryClient();
  const { data: accounts = [] } = useQuery({ queryKey: ["accounts"], queryFn: listAccounts });
  const [memo, setMemo] = useState("");
  const [lines, setLines] = useState<LineDraft[]>([emptyLine(), emptyLine()]);
  const [error, setError] = useState("");

  const totalDebit = lines.reduce((s, l) => s + (Number(l.debit) || 0), 0);
  const totalCredit = lines.reduce((s, l) => s + (Number(l.credit) || 0), 0);
  const balanced = Math.abs(totalDebit - totalCredit) < 0.005 && totalDebit > 0;

  const mutation = useMutation({
    mutationFn: () =>
      postJournalEntry({
        memo,
        lines: lines
          .filter((l) => l.account)
          .map((l) => ({
            account: l.account,
            debit: Number(l.debit) || 0,
            credit: Number(l.credit) || 0,
            label: l.label,
          })),
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["journal"] });
      qc.invalidateQueries({ queryKey: ["trial-balance"] });
      qc.invalidateQueries({ queryKey: ["income-statement"] });
      setMemo("");
      setLines([emptyLine(), emptyLine()]);
      setError("");
      onClose();
    },
    onError: (e: any) => setError(e?.response?.data?.detail ?? t("acc.postError")),
  });

  const setLine = (i: number, k: keyof LineDraft, v: string) =>
    setLines((ls) => ls.map((l, j) => (j === i ? { ...l, [k]: v } : l)));

  function submit(e: FormEvent) {
    e.preventDefault();
    if (!balanced) {
      setError(t("acc.mustBalance"));
      return;
    }
    mutation.mutate();
  }

  return (
    <Dialog open={open} onClose={onClose} title={t("acc.newJournalEntry")} className="max-w-2xl">
      <form onSubmit={submit} className="space-y-4">
        <div className="space-y-1.5">
          <Label htmlFor="je-memo">{t("acc.memo")}</Label>
          <Input id="je-memo" value={memo} onChange={(e) => setMemo(e.target.value)} placeholder={t("acc.memoPlaceholder")} />
        </div>

        <div className="space-y-2">
          <Label>{t("docs.lines")}</Label>
          {lines.map((l, i) => (
            <div key={i} className="flex items-center gap-2">
              <Select
                aria-label="je-account"
                className="flex-1"
                value={l.account}
                onChange={(e) => setLine(i, "account", e.target.value)}
              >
                <option value="">{t("acc.accountPlaceholder")}</option>
                {accounts.map((a) => (
                  <option key={a.id} value={a.code}>{a.code} — {a.name}</option>
                ))}
              </Select>
              <Input
                aria-label="je-debit"
                className="w-28"
                type="number"
                step="0.01"
                min="0"
                placeholder={t("acc.debit")}
                value={l.debit}
                onChange={(e) => setLine(i, "debit", e.target.value)}
              />
              <Input
                aria-label="je-credit"
                className="w-28"
                type="number"
                step="0.01"
                min="0"
                placeholder={t("acc.credit")}
                value={l.credit}
                onChange={(e) => setLine(i, "credit", e.target.value)}
              />
            </div>
          ))}
          <Button type="button" variant="outline" size="sm" icon={<Plus size={14} />} onClick={() => setLines((ls) => [...ls, emptyLine()])}>
            {t("docs.addLine")}
          </Button>
        </div>

        <div className="flex items-center justify-end gap-3 text-sm">
          <span style={{ color: "var(--text-muted)" }}>
            {t("acc.debit")} <b className="tnum">{money(totalDebit)}</b> · {t("acc.credit")} <b className="tnum">{money(totalCredit)}</b>
          </span>
          <Badge tone={balanced ? "emerald" : "amber"} dot>{balanced ? t("acc.balanced") : t("acc.notBalanced")}</Badge>
        </div>

        {error && <p className="text-sm" style={{ color: "var(--rose-400)" }}>{error}</p>}
        <Button type="submit" className={cn("w-full")} loading={mutation.isPending} disabled={!balanced}>
          {t("acc.postEntry")}
        </Button>
      </form>
    </Dialog>
  );
}
