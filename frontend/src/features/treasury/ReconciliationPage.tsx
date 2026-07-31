import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CheckCircle2, Download, Flag, Scale, Unlink } from "lucide-react";
import {
  disputeTransaction, downloadReconciliationPdf, getMatchSuggestions,
  getReconciliationReport, listBankAccounts, listBankTransactions,
  matchTransaction, unmatch,
} from "@/api/tunisia";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Segmented } from "@/components/ui/segmented";
import { Select } from "@/components/ui/select";
import { TableSkeleton } from "@/components/ui/skeleton";
import { Table, TBody, Td, Th, THead } from "@/components/ui/table";
import { Tooltip } from "@/components/ui/tooltip";
import { BANK_TX_STATUS, STATUS_TONE, formatTnd, frLabel, label } from "@/lib/tnLabels";

export default function ReconciliationPage() {
  const qc = useQueryClient();
  const [accountId, setAccountId] = useState("");
  const [status, setStatus] = useState("unmatched");
  const [txId, setTxId] = useState<number | null>(null);

  const { data: accounts } = useQuery({
    queryKey: ["bank-accounts"],
    queryFn: () => listBankAccounts({ active_only: true }),
  });

  // Default to the first account once they load.
  useEffect(() => {
    if (!accountId && accounts?.length) setAccountId(String(accounts[0].id));
  }, [accounts, accountId]);

  const { data: transactions, isLoading } = useQuery({
    queryKey: ["bank-transactions", accountId, status],
    queryFn: () =>
      listBankTransactions({
        bank_account: accountId,
        ...(status === "all" ? {} : { status }),
        page_size: 50,
      }),
    enabled: !!accountId,
  });

  const { data: report } = useQuery({
    queryKey: ["reconciliation-report", accountId],
    queryFn: () => getReconciliationReport({ bank_account: Number(accountId) }),
    enabled: !!accountId,
  });

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ["bank-transactions"] });
    qc.invalidateQueries({ queryKey: ["reconciliation-report"] });
    qc.invalidateQueries({ queryKey: ["instruments"] });
    qc.invalidateQueries({ queryKey: ["treasury"] });
  };

  const rows = transactions?.results ?? [];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-text-3">
          Rapprochement bancaire — match each statement line to the payment, cheque or
          instalment it represents.
        </p>
        {accountId && (
          <Button
            variant="secondary"
            onClick={() => downloadReconciliationPdf({ bank_account: Number(accountId) })}
          >
            <Download className="h-4 w-4" /> Export PDF
          </Button>
        )}
      </div>

      {/* balances */}
      {report && (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <SummaryTile label="Statement balance" value={formatTnd(report.statement_balance)} />
          <SummaryTile label="Book balance" value={formatTnd(report.book_balance)} />
          <SummaryTile
            label="Difference"
            value={formatTnd(report.difference)}
            tone={Number(report.difference) === 0 ? "positive" : "danger"}
          />
          <SummaryTile
            label="In transit"
            value={formatTnd(report.instruments_in_transit.amount)}
            hint={`${report.instruments_in_transit.count} instrument(s) deposited, not yet credited`}
          />
        </div>
      )}

      <div className="flex flex-wrap items-center gap-3">
        <div className="w-64 space-y-1.5">
          <Label htmlFor="r-account">Bank account</Label>
          <Select id="r-account" value={accountId} onChange={(e) => setAccountId(e.target.value)}>
            {(accounts ?? []).map((a) => (
              <option key={a.id} value={a.id}>{a.label} · {a.bank_name}</option>
            ))}
          </Select>
        </div>
        <div className="pt-6">
          <Segmented
            id="reconciliation-status"
            value={status}
            onChange={setStatus}
            options={[
              { value: "unmatched", label: "Unmatched" },
              { value: "partially_matched", label: "Partial" },
              { value: "disputed", label: "Disputed" },
              { value: "matched", label: "Matched" },
              { value: "all", label: "All" },
            ]}
          />
        </div>
      </div>

      {isLoading ? (
        <TableSkeleton rows={5} />
      ) : rows.length === 0 ? (
        <EmptyState
          icon={CheckCircle2}
          title="Nothing to reconcile here"
          hint={
            status === "unmatched"
              ? "Every imported line for this account has been matched."
              : "No statement line matches this filter. Import a statement from the Banking screen."
          }
        />
      ) : (
        <Table>
          <THead>
            <tr>
              <Th>Date</Th>
              <Th>Label</Th>
              <Th>Reference</Th>
              <Th>Status</Th>
              <Th className="text-right">Amount</Th>
              <Th className="text-right">Remaining</Th>
            </tr>
          </THead>
          <TBody>
            {rows.map((t) => (
              <tr key={t.id} className="cursor-pointer" onClick={() => setTxId(t.id)}>
                <Td>{t.operation_date}</Td>
                <Td className="max-w-xs truncate">{t.label}</Td>
                <Td>{t.reference || "—"}</Td>
                <Td>
                  <Tooltip label={frLabel(BANK_TX_STATUS, t.status)}>
                    <Badge tone={STATUS_TONE[t.status] ?? "employee"}>
                      {label(BANK_TX_STATUS, t.status)}
                    </Badge>
                  </Tooltip>
                </Td>
                <Td
                  className={`text-right font-medium ${
                    t.direction === "credit" ? "text-positive" : "text-danger"
                  }`}
                >
                  {formatTnd(t.amount)}
                </Td>
                <Td className="text-right">{formatTnd(t.remaining_amount)}</Td>
              </tr>
            ))}
          </TBody>
        </Table>
      )}

      <MatchDialog id={txId} onClose={() => setTxId(null)} onChanged={invalidate} />
    </div>
  );
}

function SummaryTile({
  label: text,
  value,
  hint,
  tone,
}: {
  label: string;
  value: string;
  hint?: string;
  tone?: "positive" | "danger";
}) {
  return (
    <div className="erp-card p-4">
      <p className="eyebrow">{text}</p>
      <p
        className={`mt-1 text-xl font-semibold tnum ${
          tone === "positive" ? "text-positive" : tone === "danger" ? "text-danger" : ""
        }`}
      >
        {value}
      </p>
      {hint && <p className="mt-1 text-xs text-text-3">{hint}</p>}
    </div>
  );
}

function MatchDialog({
  id,
  onClose,
  onChanged,
}: {
  id: number | null;
  onClose: () => void;
  onChanged: () => void;
}) {
  const [amount, setAmount] = useState("");
  const [note, setNote] = useState("");
  const [disputeReason, setDisputeReason] = useState("");
  const [error, setError] = useState("");

  const { data, refetch } = useQuery({
    queryKey: ["match-suggestions", id],
    queryFn: () => getMatchSuggestions(id!),
    enabled: id !== null,
  });

  const done = () => {
    setError("");
    setAmount("");
    setNote("");
    refetch();
    onChanged();
  };

  const matchMutation = useMutation({
    mutationFn: (input: { matchable_type: string; matchable_id?: number | null; amount: number }) =>
      matchTransaction(id!, { ...input, note }),
    onSuccess: done,
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not match."),
  });

  const unmatchMutation = useMutation({
    mutationFn: (matchId: number) => unmatch(matchId),
    onSuccess: done,
  });

  const disputeMutation = useMutation({
    mutationFn: () => disputeTransaction(id!, disputeReason),
    onSuccess: () => {
      setDisputeReason("");
      done();
    },
  });

  const tx = data?.transaction;

  if (!tx) {
    return (
      <Dialog open={id !== null} onClose={onClose} title="Reconcile">
        <TableSkeleton rows={3} />
      </Dialog>
    );
  }

  const remaining = Number(tx.remaining_amount);

  return (
    <Dialog open={id !== null} onClose={onClose} title="Reconcile a statement line" className="max-w-2xl">
      <div className="space-y-4">
        <div>
          <p className="text-sm text-text-2">{tx.operation_date} · {tx.reference || "no reference"}</p>
          <p className="text-lg font-medium">{tx.label}</p>
          <p
            className={`text-2xl font-semibold ${
              tx.direction === "credit" ? "text-positive" : "text-danger"
            }`}
          >
            {formatTnd(tx.amount)}
          </p>
          <p className="text-sm text-text-3">
            {formatTnd(tx.remaining_amount)} left to explain ·{" "}
            <Badge tone={STATUS_TONE[tx.status] ?? "employee"}>
              {label(BANK_TX_STATUS, tx.status)}
            </Badge>
          </p>
        </div>

        {/* existing matches */}
        {(tx.matches ?? []).length > 0 && (
          <div className="space-y-2">
            <Label>Already matched</Label>
            <ul className="space-y-1 text-sm">
              {tx.matches!.map((m) => (
                <li
                  key={m.id}
                  className="flex items-center justify-between rounded-md bg-surface-2 p-2"
                >
                  <span>
                    <span className="text-xs uppercase text-accent-strong">{m.matchable_type}</span>{" "}
                    {m.matched_label} · {formatTnd(m.amount)}
                    {m.journal_entry_number && (
                      <span className="ml-2 text-xs text-text-3">{m.journal_entry_number}</span>
                    )}
                  </span>
                  <Button
                    size="sm"
                    variant="ghost"
                    onClick={() => unmatchMutation.mutate(m.id)}
                  >
                    <Unlink className="h-3.5 w-3.5" /> Undo
                  </Button>
                </li>
              ))}
            </ul>
          </div>
        )}

        {remaining > 0 && (
          <>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label htmlFor="m-amount">Amount to apply</Label>
                <Input
                  id="m-amount"
                  type="number"
                  step="0.001"
                  min="0"
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                  placeholder={tx.remaining_amount}
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="m-note">Note</Label>
                <Input id="m-note" value={note} onChange={(e) => setNote(e.target.value)} />
              </div>
            </div>

            <div className="space-y-2">
              <Label>Suggested matches</Label>
              {(data?.suggestions ?? []).length === 0 ? (
                <p className="text-sm text-text-3">
                  Nothing in the ERP looks like this line. Record it as an adjustment below if
                  it is a bank charge or interest.
                </p>
              ) : (
                <ul className="space-y-1">
                  {data!.suggestions.map((s) => (
                    <li
                      key={`${s.type}-${s.id}`}
                      className="flex items-center justify-between rounded-md bg-surface-2 p-2 text-sm"
                    >
                      <span>
                        <span className="text-xs uppercase text-accent-strong">{s.type}</span>{" "}
                        {s.label}
                        <span className="ml-2 text-xs text-text-3">
                          {s.amount} · {s.date ?? "no date"}
                        </span>
                      </span>
                      <Button
                        size="sm"
                        onClick={() =>
                          matchMutation.mutate({
                            matchable_type: s.type,
                            matchable_id: s.id,
                            amount: Number(amount || Math.min(remaining, Number(s.amount))),
                          })
                        }
                        disabled={matchMutation.isPending}
                      >
                        <Scale className="h-3.5 w-3.5" /> Match
                      </Button>
                    </li>
                  ))}
                </ul>
              )}
              <p className="text-xs text-text-3">
                Matching a deposited cheque marks it cleared — that bank line <em>is</em> the
                moment it cleared.
              </p>
            </div>

            <div className="flex flex-wrap gap-2 border-t border-white/[0.06] pt-3">
              <Tooltip label="Bank charge, interest or an unidentified line — posts to fees or suspense">
                <Button
                  size="sm"
                  variant="secondary"
                  onClick={() =>
                    matchMutation.mutate({
                      matchable_type: "adjustment",
                      matchable_id: null,
                      amount: Number(amount || remaining),
                    })
                  }
                  disabled={matchMutation.isPending}
                >
                  Record as adjustment
                </Button>
              </Tooltip>
              <Input
                className="max-w-56"
                placeholder="Dispute reason…"
                value={disputeReason}
                onChange={(e) => setDisputeReason(e.target.value)}
              />
              <Button
                size="sm"
                variant="destructive"
                disabled={!disputeReason || disputeMutation.isPending}
                onClick={() => disputeMutation.mutate()}
              >
                <Flag className="h-3.5 w-3.5" /> Dispute
              </Button>
            </div>
          </>
        )}

        {error && <p className="text-sm text-danger">{error}</p>}
      </div>
    </Dialog>
  );
}
