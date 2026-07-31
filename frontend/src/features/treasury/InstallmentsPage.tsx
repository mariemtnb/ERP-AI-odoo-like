import { useState, type FormEvent } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CalendarClock, Plus, Wallet } from "lucide-react";
import {
  cancelInstallmentPlan, createInstallmentPlan, getInstallmentPlan,
  listBankAccounts, listInstallmentPlans, listOverdueInstallments, payInstallment,
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
import {
  INSTALLMENT_STATUS, PAYMENT_METHOD, PLAN_STATUS, STATUS_TONE,
  formatTnd, frLabel, label,
} from "@/lib/tnLabels";
import type { Installment, PaymentMethod } from "@/types";

const emptyPlan = {
  reference_type: "sale",
  reference_id: "",
  total_amount: "",
  installment_count: "3",
  frequency: "monthly",
  start_date: new Date().toISOString().slice(0, 10),
  down_payment: "",
  notes: "",
};

export default function InstallmentsPage() {
  const qc = useQueryClient();
  const [tab, setTab] = useState("plans");
  const [createOpen, setCreateOpen] = useState(false);
  const [form, setForm] = useState(emptyPlan);
  const [planId, setPlanId] = useState<number | null>(null);
  const [error, setError] = useState("");

  const { data: plans, isLoading } = useQuery({
    queryKey: ["installment-plans"],
    queryFn: () => listInstallmentPlans(),
    enabled: tab === "plans",
  });

  const { data: overdue, isLoading: overdueLoading } = useQuery({
    queryKey: ["installments-overdue"],
    queryFn: () => listOverdueInstallments(),
    enabled: tab === "overdue",
  });

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ["installment-plans"] });
    qc.invalidateQueries({ queryKey: ["installments-overdue"] });
    qc.invalidateQueries({ queryKey: ["treasury"] });
    if (planId) qc.invalidateQueries({ queryKey: ["installment-plan", planId] });
  };

  const createMutation = useMutation({
    mutationFn: () =>
      createInstallmentPlan({
        reference_type: form.reference_type as "sale" | "purchase",
        reference_id: Number(form.reference_id),
        total_amount: Number(form.total_amount),
        installment_count: Number(form.installment_count),
        frequency: form.frequency,
        start_date: form.start_date,
        down_payment: Number(form.down_payment || 0),
        notes: form.notes,
      }),
    onSuccess: () => {
      invalidate();
      setCreateOpen(false);
      setForm(emptyPlan);
      setError("");
    },
    onError: (e: any) =>
      setError(e?.response?.data?.detail ?? "Could not create this plan."),
  });

  const set = (k: keyof typeof emptyPlan) => (e: { target: { value: string } }) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  function submit(e: FormEvent) {
    e.preventDefault();
    createMutation.mutate();
  }

  // Live preview of the schedule the backend will generate.
  const financed = Number(form.total_amount || 0) - Number(form.down_payment || 0);
  const perInstallment =
    Number(form.installment_count) > 0 ? financed / Number(form.installment_count) : 0;

  const rows = plans?.results ?? [];
  const overdueRows = overdue?.results ?? [];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-text-3">
          Paiement par facilités · <span className="italic">khlas bel taqsit</span> — split an
          invoice into scheduled instalments and follow what is still owed.
        </p>
        <Button onClick={() => { setError(""); setCreateOpen(true); }}>
          <Plus className="h-4 w-4" /> New plan
        </Button>
      </div>

      <Segmented
        id="installment-tab"
        value={tab}
        onChange={setTab}
        options={[
          { value: "plans", label: "Plans" },
          { value: "overdue", label: "Overdue" },
        ]}
      />

      {tab === "plans" ? (
        isLoading ? (
          <TableSkeleton rows={5} />
        ) : rows.length === 0 ? (
          <EmptyState
            icon={CalendarClock}
            title="No payment plans yet"
            hint="Split a sale into instalments — a down payment plus monthly échéances is the usual arrangement."
            action={
              <Button onClick={() => { setError(""); setCreateOpen(true); }}>
                <Plus className="h-4 w-4" /> New plan
              </Button>
            }
          />
        ) : (
          <Table>
            <THead>
              <tr>
                <Th>Plan</Th>
                <Th>Customer</Th>
                <Th>Schedule</Th>
                <Th>Next due</Th>
                <Th>Status</Th>
                <Th className="text-right">Remaining</Th>
              </tr>
            </THead>
            <TBody>
              {rows.map((p) => (
                <tr key={p.id} className="cursor-pointer" onClick={() => setPlanId(p.id)}>
                  <Td className="font-medium">{p.number}</Td>
                  <Td>{p.customer_name ?? p.supplier_name ?? "—"}</Td>
                  <Td>
                    {p.installment_count} × {p.frequency}
                    {Number(p.down_payment) > 0 && (
                      <span className="ml-1 text-xs text-text-3">
                        (+ {formatTnd(p.down_payment)} down)
                      </span>
                    )}
                  </Td>
                  <Td>
                    {p.next_due_date ?? "—"}
                    {Number(p.overdue_amount ?? 0) > 0 && (
                      <Badge tone="red" className="ml-2">late</Badge>
                    )}
                  </Td>
                  <Td>
                    <Tooltip label={frLabel(PLAN_STATUS, p.status)}>
                      <Badge tone={STATUS_TONE[p.status] ?? "employee"}>
                        {label(PLAN_STATUS, p.status)}
                      </Badge>
                    </Tooltip>
                  </Td>
                  <Td className="text-right font-medium">{formatTnd(p.remaining_amount)}</Td>
                </tr>
              ))}
            </TBody>
          </Table>
        )
      ) : overdueLoading ? (
        <TableSkeleton rows={5} />
      ) : overdueRows.length === 0 ? (
        <EmptyState
          icon={CalendarClock}
          title="Nothing overdue"
          hint="Every instalment is either paid or not due yet."
        />
      ) : (
        <Table>
          <THead>
            <tr>
              <Th>Plan</Th>
              <Th>Customer</Th>
              <Th>#</Th>
              <Th>Due date</Th>
              <Th>Days late</Th>
              <Th className="text-right">Remaining</Th>
            </tr>
          </THead>
          <TBody>
            {overdueRows.map((i) => (
              <tr key={i.id} className="cursor-pointer" onClick={() => setPlanId(i.plan_id)}>
                <Td className="font-medium">{i.plan_number ?? `#${i.plan_id}`}</Td>
                <Td>{i.customer_name ?? "—"}</Td>
                <Td>{i.sequence}</Td>
                <Td>{i.due_date}</Td>
                <Td>
                  <Badge tone="red">{i.days_late} d</Badge>
                </Td>
                <Td className="text-right font-medium">{formatTnd(i.remaining_amount)}</Td>
              </tr>
            ))}
          </TBody>
        </Table>
      )}

      {/* ── create plan ── */}
      <Dialog open={createOpen} onClose={() => setCreateOpen(false)} title="New payment plan">
        <form onSubmit={submit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="p-type">Document</Label>
              <Select id="p-type" value={form.reference_type} onChange={set("reference_type")}>
                <option value="sale">Sale</option>
                <option value="purchase">Purchase</option>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="p-ref">Document ID</Label>
              <Input id="p-ref" type="number" value={form.reference_id} onChange={set("reference_id")} required />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="p-total">Total amount</Label>
              <Input id="p-total" type="number" step="0.001" min="0" value={form.total_amount} onChange={set("total_amount")} required />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="p-down">Down payment</Label>
              <Input id="p-down" type="number" step="0.001" min="0" value={form.down_payment} onChange={set("down_payment")} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="p-count">Instalments</Label>
              <Input id="p-count" type="number" min="1" max="120" value={form.installment_count} onChange={set("installment_count")} required />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="p-freq">Frequency</Label>
              <Select id="p-freq" value={form.frequency} onChange={set("frequency")}>
                <option value="weekly">Weekly</option>
                <option value="biweekly">Every two weeks</option>
                <option value="monthly">Monthly</option>
                <option value="quarterly">Quarterly</option>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="p-start">Start date</Label>
              <Input id="p-start" type="date" value={form.start_date} onChange={set("start_date")} required />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="p-notes">Notes</Label>
            <Input id="p-notes" value={form.notes} onChange={set("notes")} />
          </div>

          {financed > 0 && Number(form.installment_count) > 0 && (
            <p className="rounded-md bg-surface-2 p-3 text-sm text-text-2">
              {Number(form.down_payment) > 0 && (
                <>Down payment {formatTnd(form.down_payment)} today, then </>
              )}
              {form.installment_count} × ≈{formatTnd(perInstallment)} {form.frequency}.
              <span className="block text-xs text-text-3">
                The last instalment absorbs the rounding so the plan totals exactly{" "}
                {formatTnd(form.total_amount)}.
              </span>
            </p>
          )}
          <p className="text-xs text-text-3">
            A plan reschedules a debt; it does not create one. Nothing is posted until an
            instalment is actually paid.
          </p>
          {error && <p className="text-sm text-danger">{error}</p>}
          <Button type="submit" className="w-full" disabled={createMutation.isPending}>
            {createMutation.isPending ? "Saving…" : "Create plan"}
          </Button>
        </form>
      </Dialog>

      <PlanDetail id={planId} onClose={() => setPlanId(null)} onChanged={invalidate} />
    </div>
  );
}

function PlanDetail({
  id,
  onClose,
  onChanged,
}: {
  id: number | null;
  onClose: () => void;
  onChanged: () => void;
}) {
  const [payFor, setPayFor] = useState<Installment | null>(null);
  const [error, setError] = useState("");

  const { data: plan } = useQuery({
    queryKey: ["installment-plan", id],
    queryFn: () => getInstallmentPlan(id!),
    enabled: id !== null,
  });

  const cancelMutation = useMutation({
    mutationFn: () => cancelInstallmentPlan(id!),
    onSuccess: onChanged,
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not cancel."),
  });

  if (!plan) {
    return (
      <Dialog open={id !== null} onClose={onClose} title="Payment plan">
        <TableSkeleton rows={3} />
      </Dialog>
    );
  }

  return (
    <>
      <Dialog
        open={id !== null && payFor === null}
        onClose={onClose}
        title={`${plan.number} · payment schedule`}
        className="max-w-2xl"
      >
        <div className="space-y-4">
          <div className="grid grid-cols-3 gap-3 text-sm">
            <div>
              <p className="eyebrow">Total</p>
              <p className="text-lg font-semibold">{formatTnd(plan.total_amount)}</p>
            </div>
            <div>
              <p className="eyebrow">Paid</p>
              <p className="text-lg font-semibold text-positive">{formatTnd(plan.paid_amount)}</p>
            </div>
            <div>
              <p className="eyebrow">Remaining</p>
              <p className="text-lg font-semibold">{formatTnd(plan.remaining_amount)}</p>
            </div>
          </div>

          <div className="flex items-center gap-2 text-sm text-text-2">
            <Badge tone={STATUS_TONE[plan.status] ?? "employee"}>
              {label(PLAN_STATUS, plan.status)}
            </Badge>
            <span>{plan.customer_name ?? plan.supplier_name ?? "—"}</span>
            <span className="text-text-3">
              · {plan.reference_type} #{plan.reference_id}
            </span>
          </div>
          {plan.notes && <p className="text-sm text-text-2">{plan.notes}</p>}

          <Table>
            <THead>
              <tr>
                <Th>#</Th>
                <Th>Due</Th>
                <Th>Status</Th>
                <Th className="text-right">Amount</Th>
                <Th className="text-right">Paid</Th>
                <Th />
              </tr>
            </THead>
            <TBody>
              {(plan.installments ?? []).map((i) => (
                <tr key={i.id}>
                  <Td>
                    {i.sequence}
                    {i.is_down_payment && (
                      <Badge tone="sky" className="ml-1">avance</Badge>
                    )}
                  </Td>
                  <Td>{i.due_date}</Td>
                  <Td>
                    <Tooltip label={frLabel(INSTALLMENT_STATUS, i.status)}>
                      <Badge tone={STATUS_TONE[i.status] ?? "employee"}>
                        {label(INSTALLMENT_STATUS, i.status)}
                      </Badge>
                    </Tooltip>
                  </Td>
                  <Td className="text-right">{formatTnd(i.amount)}</Td>
                  <Td className="text-right">{formatTnd(i.paid_amount)}</Td>
                  <Td className="text-right">
                    {i.status !== "paid" && i.status !== "cancelled" && (
                      <Button size="sm" variant="secondary" onClick={() => setPayFor(i)}>
                        <Wallet className="h-3.5 w-3.5" /> Pay
                      </Button>
                    )}
                  </Td>
                </tr>
              ))}
            </TBody>
          </Table>

          {error && <p className="text-sm text-danger">{error}</p>}
          {plan.status === "active" && (
            <Button variant="ghost" size="sm" onClick={() => cancelMutation.mutate()}>
              Cancel plan
            </Button>
          )}
        </div>
      </Dialog>

      <PayDialog
        installment={payFor}
        onClose={() => setPayFor(null)}
        onPaid={() => {
          setPayFor(null);
          onChanged();
        }}
      />
    </>
  );
}

function PayDialog({
  installment,
  onClose,
  onPaid,
}: {
  installment: Installment | null;
  onClose: () => void;
  onPaid: () => void;
}) {
  const [amount, setAmount] = useState("");
  const [method, setMethod] = useState<PaymentMethod>("cash");
  const [bankAccountId, setBankAccountId] = useState("");
  const [reference, setReference] = useState("");
  const [error, setError] = useState("");

  const { data: bankAccounts } = useQuery({
    queryKey: ["bank-accounts"],
    queryFn: () => listBankAccounts({ active_only: true }),
  });

  const mutation = useMutation({
    mutationFn: () =>
      payInstallment(installment!.id, {
        amount: Number(amount || installment!.remaining_amount),
        method,
        bank_account_id: bankAccountId ? Number(bankAccountId) : null,
        reference,
      }),
    onSuccess: () => {
      setAmount("");
      setReference("");
      setError("");
      onPaid();
    },
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Payment failed."),
  });

  const needsBank = ["bank_transfer", "card", "bank_deposit", "bank_withdrawal"].includes(method);

  return (
    <Dialog
      open={installment !== null}
      onClose={onClose}
      title={installment ? `Pay instalment #${installment.sequence}` : "Pay"}
    >
      {installment && (
        <form
          className="space-y-4"
          onSubmit={(e) => {
            e.preventDefault();
            mutation.mutate();
          }}
        >
          <p className="text-sm text-text-2">
            Due {installment.due_date} · remaining{" "}
            <strong>{formatTnd(installment.remaining_amount)}</strong>
          </p>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="pay-amount">Amount</Label>
              <Input
                id="pay-amount"
                type="number"
                step="0.001"
                min="0"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                placeholder={installment.remaining_amount}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="pay-method">Method</Label>
              <Select
                id="pay-method"
                value={method}
                onChange={(e) => setMethod(e.target.value as PaymentMethod)}
              >
                {Object.entries(PAYMENT_METHOD).map(([key, l]) => (
                  <option key={key} value={key}>{l.en} · {l.fr}</option>
                ))}
              </Select>
            </div>
            {needsBank && (
              <div className="space-y-1.5">
                <Label htmlFor="pay-bank">Bank account</Label>
                <Select
                  id="pay-bank"
                  value={bankAccountId}
                  onChange={(e) => setBankAccountId(e.target.value)}
                >
                  <option value="">— choose —</option>
                  {(bankAccounts ?? []).map((a) => (
                    <option key={a.id} value={a.id}>{a.label}</option>
                  ))}
                </Select>
              </div>
            )}
            <div className="space-y-1.5">
              <Label htmlFor="pay-ref">Reference</Label>
              <Input id="pay-ref" value={reference} onChange={(e) => setReference(e.target.value)} />
            </div>
          </div>
          {(method === "cheque" || method === "traite") && (
            <p className="rounded-md bg-surface-2 p-3 text-xs text-text-2">
              Register the {method} in the Cheques &amp; Kembyelet screen and link it there —
              the instalment is only credited once that instrument actually clears.
            </p>
          )}
          {error && <p className="text-sm text-danger">{error}</p>}
          <Button type="submit" className="w-full" disabled={mutation.isPending}>
            {mutation.isPending ? "Recording…" : "Record payment"}
          </Button>
        </form>
      )}
    </Dialog>
  );
}
