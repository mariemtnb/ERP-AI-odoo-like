import { useState, type FormEvent } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  AlertTriangle, Banknote, Landmark, Paperclip, Plus, ReceiptText,
} from "lucide-react";
import {
  attachToInstrument, createInstrument, getInstrument, instrumentAction,
  listBankAccounts, listBanks, listInstruments,
} from "@/api/tunisia";
import { partnersApi } from "@/api/partners";
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
  INSTRUMENT_KIND, INSTRUMENT_STATUS, STATUS_TONE, formatTnd, frLabel, label,
} from "@/lib/tnLabels";
import type { InstrumentKind } from "@/types";

const emptyForm = {
  kind: "cheque" as InstrumentKind,
  direction: "incoming",
  instrument_reference: "",
  amount: "",
  issue_date: new Date().toISOString().slice(0, 10),
  due_date: "",
  place_of_issue: "",
  customer_id: "",
  supplier_id: "",
  counterparty_name: "",
  bank_account_id: "",
  drawee_bank_id: "",
  drawee_rib: "",
  notes: "",
};

export default function InstrumentsPage() {
  const qc = useQueryClient();
  const [kind, setKind] = useState<InstrumentKind>("cheque");
  const [scope, setScope] = useState("outstanding");
  const [createOpen, setCreateOpen] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [detailId, setDetailId] = useState<number | null>(null);
  const [error, setError] = useState("");

  const params: Record<string, unknown> = { kind };
  if (scope === "outstanding") params.outstanding = true;
  if (scope === "overdue") params.overdue = true;
  if (scope === "bounced") params.status = "bounced";

  const { data, isLoading } = useQuery({
    queryKey: ["instruments", kind, scope],
    queryFn: () => listInstruments(params),
  });

  const { data: customers } = useQuery({
    queryKey: ["partners", "customers"],
    queryFn: () => partnersApi("customers").list({ page_size: 100 }),
  });
  const { data: suppliers } = useQuery({
    queryKey: ["partners", "suppliers"],
    queryFn: () => partnersApi("suppliers").list({ page_size: 100 }),
  });
  const { data: bankAccounts } = useQuery({
    queryKey: ["bank-accounts"],
    queryFn: () => listBankAccounts({ active_only: true }),
  });
  const { data: banks } = useQuery({ queryKey: ["banks"], queryFn: () => listBanks() });

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ["instruments"] });
    qc.invalidateQueries({ queryKey: ["treasury"] });
    if (detailId) qc.invalidateQueries({ queryKey: ["instrument", detailId] });
  };

  const createMutation = useMutation({
    mutationFn: () => {
      const payload: Record<string, unknown> = {
        kind: form.kind,
        direction: form.direction,
        instrument_reference: form.instrument_reference,
        amount: Number(form.amount),
        issue_date: form.issue_date,
        due_date: form.due_date || null,
        place_of_issue: form.place_of_issue,
        counterparty_name: form.counterparty_name,
        notes: form.notes,
        drawee_rib: form.drawee_rib,
      };
      if (form.direction === "incoming" && form.customer_id) {
        payload.customer_id = Number(form.customer_id);
      }
      if (form.direction === "outgoing" && form.supplier_id) {
        payload.supplier_id = Number(form.supplier_id);
      }
      if (form.bank_account_id) payload.bank_account_id = Number(form.bank_account_id);
      if (form.drawee_bank_id) payload.drawee_bank_id = Number(form.drawee_bank_id);
      return createInstrument(payload);
    },
    onSuccess: () => {
      invalidate();
      setCreateOpen(false);
      setForm({ ...emptyForm, kind });
      setError("");
    },
    onError: (e: any) =>
      setError(e?.response?.data?.detail ?? "Could not register this instrument."),
  });

  const set = (k: keyof typeof emptyForm) => (e: { target: { value: string } }) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  function submit(e: FormEvent) {
    e.preventDefault();
    createMutation.mutate();
  }

  const rows = data?.results ?? [];
  const kindLabel = INSTRUMENT_KIND[kind];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="text-sm text-text-3">
            {kindLabel.fr}
            {kindLabel.local ? ` · ${kindLabel.local}` : ""} — track what you hold, what you
            owe, and what came back unpaid.
          </p>
        </div>
        <Button
          onClick={() => {
            setError("");
            setForm({ ...emptyForm, kind });
            setCreateOpen(true);
          }}
        >
          <Plus className="h-4 w-4" /> New {kind === "cheque" ? "cheque" : "traite"}
        </Button>
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <Segmented
          id="instrument-kind"
          value={kind}
          onChange={(v) => setKind(v as InstrumentKind)}
          options={[
            { value: "cheque", label: "Cheques" },
            { value: "traite", label: "Traites / Kembyelet" },
          ]}
        />
        <Segmented
          id="instrument-scope"
          value={scope}
          onChange={setScope}
          options={[
            { value: "outstanding", label: "Outstanding" },
            { value: "overdue", label: "Overdue" },
            { value: "bounced", label: "Bounced" },
            { value: "all", label: "All" },
          ]}
        />
      </div>

      {isLoading ? (
        <TableSkeleton rows={5} />
      ) : rows.length === 0 ? (
        <EmptyState
          icon={ReceiptText}
          title={`No ${kind === "cheque" ? "cheques" : "traites"} here`}
          hint={
            scope === "outstanding"
              ? "Nothing is waiting to be collected or paid right now."
              : "Nothing matches this filter."
          }
          action={
            <Button onClick={() => { setError(""); setForm({ ...emptyForm, kind }); setCreateOpen(true); }}>
              <Plus className="h-4 w-4" /> Register one
            </Button>
          }
        />
      ) : (
        <Table>
          <THead>
            <tr>
              <Th>Number</Th>
              <Th>Reference</Th>
              <Th>Counterparty</Th>
              <Th>Due date</Th>
              <Th>Status</Th>
              <Th className="text-right">Amount</Th>
            </tr>
          </THead>
          <TBody>
            {rows.map((i) => (
              <tr
                key={i.id}
                className="cursor-pointer"
                onClick={() => setDetailId(i.id)}
              >
                <Td>
                  <span className="font-medium">{i.number}</span>
                  <span className="ml-2 text-xs text-text-3">
                    {i.direction === "incoming" ? "received" : "issued"}
                  </span>
                </Td>
                <Td>{i.instrument_reference || "—"}</Td>
                <Td>{i.counterparty_name || "—"}</Td>
                <Td>
                  {i.due_date ?? "—"}
                  {i.is_overdue && (
                    <AlertTriangle className="ml-1 inline h-3.5 w-3.5 text-danger" />
                  )}
                </Td>
                <Td>
                  <Tooltip label={frLabel(INSTRUMENT_STATUS, i.status)}>
                    <Badge tone={STATUS_TONE[i.status] ?? "employee"}>
                      {label(INSTRUMENT_STATUS, i.status)}
                    </Badge>
                  </Tooltip>
                </Td>
                <Td className="text-right font-medium">{formatTnd(i.amount)}</Td>
              </tr>
            ))}
          </TBody>
        </Table>
      )}

      {/* ── create ── */}
      <Dialog
        open={createOpen}
        onClose={() => setCreateOpen(false)}
        title={`New ${form.kind === "cheque" ? "cheque" : "traite / kembya"}`}
        className="max-w-2xl"
      >
        <form onSubmit={submit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="i-kind">Type</Label>
              <Select id="i-kind" value={form.kind} onChange={set("kind")}>
                <option value="cheque">Chèque</option>
                <option value="traite">Traite / Kembya</option>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="i-direction">Direction</Label>
              <Select id="i-direction" value={form.direction} onChange={set("direction")}>
                <option value="incoming">Received from a customer</option>
                <option value="outgoing">Issued to a supplier</option>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="i-ref">Instrument number</Label>
              <Input
                id="i-ref"
                value={form.instrument_reference}
                onChange={set("instrument_reference")}
                placeholder="Number printed on the cheque"
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="i-amount">Amount</Label>
              <Input
                id="i-amount"
                type="number"
                step="0.001"
                min="0"
                value={form.amount}
                onChange={set("amount")}
                required
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="i-issue">Issue date</Label>
              <Input id="i-issue" type="date" value={form.issue_date} onChange={set("issue_date")} required />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="i-due">
                Due date {form.kind === "traite" && <span className="text-danger">*</span>}
              </Label>
              <Input
                id="i-due"
                type="date"
                value={form.due_date}
                onChange={set("due_date")}
                required={form.kind === "traite"}
              />
            </div>

            {form.direction === "incoming" ? (
              <div className="space-y-1.5">
                <Label htmlFor="i-customer">Customer</Label>
                <Select id="i-customer" value={form.customer_id} onChange={set("customer_id")}>
                  <option value="">— not in the list —</option>
                  {(customers?.results ?? []).map((c) => (
                    <option key={c.id} value={c.id}>{c.name}</option>
                  ))}
                </Select>
              </div>
            ) : (
              <div className="space-y-1.5">
                <Label htmlFor="i-supplier">Supplier</Label>
                <Select id="i-supplier" value={form.supplier_id} onChange={set("supplier_id")}>
                  <option value="">— not in the list —</option>
                  {(suppliers?.results ?? []).map((s) => (
                    <option key={s.id} value={s.id}>{s.name}</option>
                  ))}
                </Select>
              </div>
            )}

            <div className="space-y-1.5">
              <Label htmlFor="i-name">Counterparty name</Label>
              <Input
                id="i-name"
                value={form.counterparty_name}
                onChange={set("counterparty_name")}
                placeholder="If not linked above"
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="i-bank">Our bank account</Label>
              <Select id="i-bank" value={form.bank_account_id} onChange={set("bank_account_id")}>
                <option value="">— choose later —</option>
                {(bankAccounts ?? []).map((a) => (
                  <option key={a.id} value={a.id}>{a.label} · {a.bank_name}</option>
                ))}
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="i-drawee">Counterparty's bank</Label>
              <Select id="i-drawee" value={form.drawee_bank_id} onChange={set("drawee_bank_id")}>
                <option value="">— unknown —</option>
                {(banks ?? []).map((b) => (
                  <option key={b.id} value={b.id}>{b.short_name}</option>
                ))}
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="i-rib">Drawee RIB</Label>
              <Input id="i-rib" value={form.drawee_rib} onChange={set("drawee_rib")} placeholder="20 digits" />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="i-place">Place of issue</Label>
              <Input id="i-place" value={form.place_of_issue} onChange={set("place_of_issue")} />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="i-notes">Notes</Label>
            <Input id="i-notes" value={form.notes} onChange={set("notes")} />
          </div>
          <p className="text-xs text-text-3">
            Registering it posts the entry straight away: the customer's debt becomes a
            cheque in hand (or, for a supplier, what you owe becomes a cheque issued).
          </p>
          {error && <p className="text-sm text-danger">{error}</p>}
          <Button type="submit" className="w-full" disabled={createMutation.isPending}>
            {createMutation.isPending ? "Saving…" : "Register"}
          </Button>
        </form>
      </Dialog>

      <InstrumentDetail
        id={detailId}
        onClose={() => setDetailId(null)}
        onChanged={invalidate}
        bankAccounts={bankAccounts ?? []}
      />
    </div>
  );
}

function InstrumentDetail({
  id,
  onClose,
  onChanged,
  bankAccounts,
}: {
  id: number | null;
  onClose: () => void;
  onChanged: () => void;
  bankAccounts: { id: number; label: string; bank_name: string | null }[];
}) {
  const [error, setError] = useState("");
  const [bankAccountId, setBankAccountId] = useState("");
  const [fees, setFees] = useState("");
  const [reason, setReason] = useState("");
  const [doubtful, setDoubtful] = useState(false);

  const { data: instrument } = useQuery({
    queryKey: ["instrument", id],
    queryFn: () => getInstrument(id!),
    enabled: id !== null,
  });

  const act = useMutation({
    mutationFn: ({ action, payload }: { action: any; payload?: Record<string, unknown> }) =>
      instrumentAction(id!, action, payload ?? {}),
    onSuccess: () => {
      setError("");
      setReason("");
      setFees("");
      onChanged();
    },
    onError: (e: any) => setError(e?.response?.data?.detail ?? "That step is not allowed."),
  });

  const attach = useMutation({
    mutationFn: (file: File) => attachToInstrument(id!, file),
    onSuccess: onChanged,
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Upload failed."),
  });

  if (!instrument) {
    return (
      <Dialog open={id !== null} onClose={onClose} title="Instrument">
        <TableSkeleton rows={3} />
      </Dialog>
    );
  }

  const s = instrument.status;
  const incoming = instrument.direction === "incoming";
  const canDeposit = incoming && s === "received";
  const canClear = incoming
    ? ["deposited", "pending_clearance"].includes(s)
    : s === "issued";
  const canBounce = incoming
    ? ["deposited", "pending_clearance"].includes(s)
    : s === "issued";
  const canSettle = s === "bounced";
  const canCancel = ["draft", "received", "issued"].includes(s);

  return (
    <Dialog
      open={id !== null}
      onClose={onClose}
      title={`${instrument.number} · ${label(INSTRUMENT_KIND, instrument.kind)}`}
      className="max-w-2xl"
    >
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-2xl font-semibold">{formatTnd(instrument.amount)}</p>
            <p className="text-sm text-text-2">{instrument.counterparty_name}</p>
          </div>
          <Tooltip label={frLabel(INSTRUMENT_STATUS, s)}>
            <Badge tone={STATUS_TONE[s] ?? "employee"}>{label(INSTRUMENT_STATUS, s)}</Badge>
          </Tooltip>
        </div>

        <div className="grid grid-cols-2 gap-2 text-sm text-text-2">
          <p>Reference: {instrument.instrument_reference || "—"}</p>
          <p>Issued: {instrument.issue_date}</p>
          <p>Due: {instrument.due_date ?? "—"}</p>
          <p>Bank: {instrument.bank_account_label ?? "—"}</p>
          <p>Drawee bank: {instrument.drawee_bank_name ?? "—"}</p>
          <p>Place: {instrument.place_of_issue || "—"}</p>
          {instrument.bounce_reason && (
            <p className="col-span-2 text-danger">
              Returned: {instrument.bounce_reason}
            </p>
          )}
          {Number(instrument.bank_fees) > 0 && (
            <p>Bank fees: {formatTnd(instrument.bank_fees)}</p>
          )}
        </div>
        {instrument.notes && <p className="text-sm text-text-2">{instrument.notes}</p>}

        {/* lifecycle actions */}
        <div className="space-y-3 rounded-md bg-surface-2 p-3">
          {canDeposit && (
            <div className="flex flex-wrap items-end gap-2">
              <div className="min-w-40 flex-1 space-y-1.5">
                <Label htmlFor="d-bank">Deposit into</Label>
                <Select
                  id="d-bank"
                  value={bankAccountId || String(instrument.bank_account_id ?? "")}
                  onChange={(e) => setBankAccountId(e.target.value)}
                >
                  <option value="">— choose —</option>
                  {bankAccounts.map((a) => (
                    <option key={a.id} value={a.id}>{a.label} · {a.bank_name}</option>
                  ))}
                </Select>
              </div>
              <Tooltip label="Remise à l'encaissement — hand it to the bank">
                <Button
                  size="sm"
                  onClick={() =>
                    act.mutate({
                      action: "deposit",
                      payload: {
                        bank_account_id: Number(bankAccountId || instrument.bank_account_id),
                      },
                    })
                  }
                  disabled={act.isPending}
                >
                  <Landmark className="h-4 w-4" /> Deposit
                </Button>
              </Tooltip>
            </div>
          )}

          {(canClear || canBounce) && (
            <div className="flex flex-wrap items-end gap-2">
              <div className="w-32 space-y-1.5">
                <Label htmlFor="d-fees">Bank fees</Label>
                <Input
                  id="d-fees"
                  type="number"
                  step="0.001"
                  min="0"
                  value={fees}
                  onChange={(e) => setFees(e.target.value)}
                />
              </div>
              {canClear && (
                <Tooltip label="Encaissé — the money is in the account">
                  <Button
                    size="sm"
                    onClick={() => act.mutate({ action: "clear", payload: { fees: Number(fees || 0) } })}
                    disabled={act.isPending}
                  >
                    <Banknote className="h-4 w-4" /> Mark cleared
                  </Button>
                </Tooltip>
              )}
              {canBounce && (
                <Tooltip label="Impayé — the bank returned it unpaid">
                  <Button
                    size="sm"
                    variant="destructive"
                    onClick={() =>
                      act.mutate({
                        action: "bounce",
                        payload: {
                          reason,
                          fees: Number(fees || 0),
                          move_to_doubtful: doubtful,
                        },
                      })
                    }
                    disabled={act.isPending}
                  >
                    <AlertTriangle className="h-4 w-4" /> Bounced
                  </Button>
                </Tooltip>
              )}
            </div>
          )}

          {canBounce && (
            <div className="space-y-1.5">
              <Label htmlFor="d-reason">Reason if returned</Label>
              <Input
                id="d-reason"
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder="Provision insuffisante, compte clôturé…"
              />
              {incoming && (
                <label className="flex items-center gap-2 text-xs text-text-3">
                  <input
                    type="checkbox"
                    checked={doubtful}
                    onChange={(e) => setDoubtful(e.target.checked)}
                  />
                  Move the debt to doubtful receivables (clients douteux)
                </label>
              )}
            </div>
          )}

          <div className="flex flex-wrap gap-2">
            {canSettle && (
              <Tooltip label="The customer paid another way — close this instrument">
                <Button size="sm" variant="secondary" onClick={() => act.mutate({ action: "settle" })}>
                  Mark settled
                </Button>
              </Tooltip>
            )}
            {canCancel && (
              <Button
                size="sm"
                variant="ghost"
                onClick={() => act.mutate({ action: "cancel", payload: { reason } })}
              >
                Cancel
              </Button>
            )}
            <label className="ml-auto inline-flex cursor-pointer items-center gap-1 text-sm text-text-2">
              <Paperclip className="h-4 w-4" />
              Attach scan
              <input
                type="file"
                className="hidden"
                accept="image/png,image/jpeg,image/webp,application/pdf"
                onChange={(e) => {
                  const file = e.target.files?.[0];
                  if (file) attach.mutate(file);
                }}
              />
            </label>
          </div>
        </div>

        {error && <p className="text-sm text-danger">{error}</p>}

        {(instrument.attachments ?? []).length > 0 && (
          <ul className="space-y-1 text-sm">
            {instrument.attachments!.map((a) => (
              <li key={a.id}>
                <Paperclip className="mr-1 inline h-3 w-3" />
                {a.filename}
              </li>
            ))}
          </ul>
        )}

        {/* lifecycle history */}
        <div className="space-y-2">
          <Label>History</Label>
          <ul className="max-h-52 space-y-2 overflow-y-auto text-sm">
            {(instrument.events ?? []).map((e) => (
              <li key={e.id} className="rounded-md bg-surface-2 p-2">
                <span className="text-xs uppercase text-accent-strong">{e.event}</span>{" "}
                {e.from_status && `${e.from_status} → `}
                {e.to_status}
                {e.journal_entry_number && (
                  <span className="ml-2 text-xs text-text-3">
                    posted as {e.journal_entry_number}
                  </span>
                )}
                <span className="ml-2 text-xs text-text-3">
                  {new Date(e.created_at).toLocaleString()} · {e.created_by_email}
                </span>
                {e.notes && <p className="text-xs text-text-2">{e.notes}</p>}
              </li>
            ))}
          </ul>
        </div>
      </div>
    </Dialog>
  );
}
