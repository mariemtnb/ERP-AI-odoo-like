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
import { useI18n } from "@/lib/i18n";

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
  const { t } = useI18n();
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
      setError(e?.response?.data?.detail ?? t("inst.createError")),
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
            {kindLabel.local ? ` · ${kindLabel.local}` : ""} {t("inst.introSuffix")}
          </p>
        </div>
        <Button
          onClick={() => {
            setError("");
            setForm({ ...emptyForm, kind });
            setCreateOpen(true);
          }}
        >
          <Plus className="h-4 w-4" /> {t(`inst.new.${kind}`)}
        </Button>
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <Segmented
          id="instrument-kind"
          value={kind}
          onChange={(v) => setKind(v as InstrumentKind)}
          options={[
            { value: "cheque", label: t("inst.tab.cheques") },
            { value: "traite", label: t("inst.tab.traites") },
          ]}
        />
        <Segmented
          id="instrument-scope"
          value={scope}
          onChange={setScope}
          options={[
            { value: "outstanding", label: t("inst.scope.outstanding") },
            { value: "overdue", label: t("inst.scope.overdue") },
            { value: "bounced", label: t("inst.scope.bounced") },
            { value: "all", label: t("common.all") },
          ]}
        />
      </div>

      {isLoading ? (
        <TableSkeleton rows={5} />
      ) : rows.length === 0 ? (
        <EmptyState
          icon={ReceiptText}
          title={kind === "cheque" ? t("inst.noneCheque") : t("inst.noneTraite")}
          hint={
            scope === "outstanding"
              ? t("inst.noneOutstanding")
              : t("inst.noneFilter")
          }
          action={
            <Button onClick={() => { setError(""); setForm({ ...emptyForm, kind }); setCreateOpen(true); }}>
              <Plus className="h-4 w-4" /> {t("inst.registerOne")}
            </Button>
          }
        />
      ) : (
        <Table>
          <THead>
            <tr>
              <Th>{t("docs.col.number")}</Th>
              <Th>{t("bnk.reference")}</Th>
              <Th>{t("inst.counterparty")}</Th>
              <Th>{t("inst.dueDate")}</Th>
              <Th>{t("common.status")}</Th>
              <Th className="text-right">{t("subs.amount")}</Th>
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
                    {i.direction === "incoming" ? t("inst.received") : t("inst.issued")}
                  </span>
                </Td>
                <Td>{i.instrument_reference || "-"}</Td>
                <Td>{i.counterparty_name || "-"}</Td>
                <Td>
                  {i.due_date ?? "-"}
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
        title={form.kind === "cheque" ? t("inst.newTitleCheque") : t("inst.newTitleTraite")}
        className="max-w-2xl"
      >
        <form onSubmit={submit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="i-kind">{t("inst.type")}</Label>
              <Select id="i-kind" value={form.kind} onChange={set("kind")}>
                <option value="cheque">{t("inst.chequeOpt")}</option>
                <option value="traite">{t("inst.traiteOpt")}</option>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="i-direction">{t("inst.direction")}</Label>
              <Select id="i-direction" value={form.direction} onChange={set("direction")}>
                <option value="incoming">{t("inst.dirIn")}</option>
                <option value="outgoing">{t("inst.dirOut")}</option>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="i-ref">{t("inst.instrumentNumber")}</Label>
              <Input
                id="i-ref"
                value={form.instrument_reference}
                onChange={set("instrument_reference")}
                placeholder={t("inst.numberPlaceholder")}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="i-amount">{t("subs.amount")}</Label>
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
              <Label htmlFor="i-issue">{t("inst.issueDate")}</Label>
              <Input id="i-issue" type="date" value={form.issue_date} onChange={set("issue_date")} required />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="i-due">
                {t("inst.dueDate")} {form.kind === "traite" && <span className="text-danger">*</span>}
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
                <Label htmlFor="i-customer">{t("field.customer")}</Label>
                <Select id="i-customer" value={form.customer_id} onChange={set("customer_id")}>
                  <option value="">{t("inst.notInList")}</option>
                  {(customers?.results ?? []).map((c) => (
                    <option key={c.id} value={c.id}>{c.name}</option>
                  ))}
                </Select>
              </div>
            ) : (
              <div className="space-y-1.5">
                <Label htmlFor="i-supplier">{t("field.supplier")}</Label>
                <Select id="i-supplier" value={form.supplier_id} onChange={set("supplier_id")}>
                  <option value="">{t("inst.notInList")}</option>
                  {(suppliers?.results ?? []).map((s) => (
                    <option key={s.id} value={s.id}>{s.name}</option>
                  ))}
                </Select>
              </div>
            )}

            <div className="space-y-1.5">
              <Label htmlFor="i-name">{t("inst.counterpartyName")}</Label>
              <Input
                id="i-name"
                value={form.counterparty_name}
                onChange={set("counterparty_name")}
                placeholder={t("inst.ifNotLinked")}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="i-bank">{t("inst.ourBank")}</Label>
              <Select id="i-bank" value={form.bank_account_id} onChange={set("bank_account_id")}>
                <option value="">{t("inst.chooseLater")}</option>
                {(bankAccounts ?? []).map((a) => (
                  <option key={a.id} value={a.id}>{a.label} · {a.bank_name}</option>
                ))}
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="i-drawee">{t("inst.counterpartyBank")}</Label>
              <Select id="i-drawee" value={form.drawee_bank_id} onChange={set("drawee_bank_id")}>
                <option value="">{t("inst.unknown")}</option>
                {(banks ?? []).map((b) => (
                  <option key={b.id} value={b.id}>{b.short_name}</option>
                ))}
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="i-rib">{t("inst.draweeRib")}</Label>
              <Input id="i-rib" value={form.drawee_rib} onChange={set("drawee_rib")} placeholder={t("bnk.ribPlaceholder")} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="i-place">{t("inst.placeOfIssue")}</Label>
              <Input id="i-place" value={form.place_of_issue} onChange={set("place_of_issue")} />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="i-notes">{t("field.notes")}</Label>
            <Input id="i-notes" value={form.notes} onChange={set("notes")} />
          </div>
          <p className="text-xs text-text-3">
            {t("inst.registerNote")}
          </p>
          {error && <p className="text-sm text-danger">{error}</p>}
          <Button type="submit" className="w-full" disabled={createMutation.isPending}>
            {createMutation.isPending ? t("common.saving") : t("inst.register")}
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
  const { t } = useI18n();
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
    onError: (e: any) => setError(e?.response?.data?.detail ?? t("inst.stepNotAllowed")),
  });

  const attach = useMutation({
    mutationFn: (file: File) => attachToInstrument(id!, file),
    onSuccess: onChanged,
    onError: (e: any) => setError(e?.response?.data?.detail ?? t("inst.uploadFailed")),
  });

  if (!instrument) {
    return (
      <Dialog open={id !== null} onClose={onClose} title={t("inst.instrument")}>
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
          <p>{t("inst.referenceLabel")} {instrument.instrument_reference || "-"}</p>
          <p>{t("inst.issuedLabel")} {instrument.issue_date}</p>
          <p>{t("inst.dueLabel")} {instrument.due_date ?? "-"}</p>
          <p>{t("inst.bankLabel")} {instrument.bank_account_label ?? "-"}</p>
          <p>{t("inst.draweeBankLabel")} {instrument.drawee_bank_name ?? "-"}</p>
          <p>{t("inst.placeLabel")} {instrument.place_of_issue || "-"}</p>
          {instrument.bounce_reason && (
            <p className="col-span-2 text-danger">
              {t("inst.returnedLabel")} {instrument.bounce_reason}
            </p>
          )}
          {Number(instrument.bank_fees) > 0 && (
            <p>{t("inst.bankFeesLabel")} {formatTnd(instrument.bank_fees)}</p>
          )}
        </div>
        {instrument.notes && <p className="text-sm text-text-2">{instrument.notes}</p>}

        {/* lifecycle actions */}
        <div className="space-y-3 rounded-md bg-surface-2 p-3">
          {canDeposit && (
            <div className="flex flex-wrap items-end gap-2">
              <div className="min-w-40 flex-1 space-y-1.5">
                <Label htmlFor="d-bank">{t("inst.depositInto")}</Label>
                <Select
                  id="d-bank"
                  value={bankAccountId || String(instrument.bank_account_id ?? "")}
                  onChange={(e) => setBankAccountId(e.target.value)}
                >
                  <option value="">{t("pay.choose")}</option>
                  {bankAccounts.map((a) => (
                    <option key={a.id} value={a.id}>{a.label} · {a.bank_name}</option>
                  ))}
                </Select>
              </div>
              <Tooltip label={t("inst.depositTip")}>
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
                  <Landmark className="h-4 w-4" /> {t("inst.deposit")}
                </Button>
              </Tooltip>
            </div>
          )}

          {(canClear || canBounce) && (
            <div className="flex flex-wrap items-end gap-2">
              <div className="w-32 space-y-1.5">
                <Label htmlFor="d-fees">{t("inst.bankFees")}</Label>
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
                <Tooltip label={t("inst.clearTip")}>
                  <Button
                    size="sm"
                    onClick={() => act.mutate({ action: "clear", payload: { fees: Number(fees || 0) } })}
                    disabled={act.isPending}
                  >
                    <Banknote className="h-4 w-4" /> {t("inst.markCleared")}
                  </Button>
                </Tooltip>
              )}
              {canBounce && (
                <Tooltip label={t("inst.bounceTip")}>
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
                    <AlertTriangle className="h-4 w-4" /> {t("inst.bounced")}
                  </Button>
                </Tooltip>
              )}
            </div>
          )}

          {canBounce && (
            <div className="space-y-1.5">
              <Label htmlFor="d-reason">{t("inst.reasonIfReturned")}</Label>
              <Input
                id="d-reason"
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder={t("inst.reasonPlaceholder")}
              />
              {incoming && (
                <label className="flex items-center gap-2 text-xs text-text-3">
                  <input
                    type="checkbox"
                    checked={doubtful}
                    onChange={(e) => setDoubtful(e.target.checked)}
                  />
                  {t("inst.moveDoubtful")}
                </label>
              )}
            </div>
          )}

          <div className="flex flex-wrap gap-2">
            {canSettle && (
              <Tooltip label={t("inst.settleTip")}>
                <Button size="sm" variant="secondary" onClick={() => act.mutate({ action: "settle" })}>
                  {t("inst.markSettled")}
                </Button>
              </Tooltip>
            )}
            {canCancel && (
              <Button
                size="sm"
                variant="ghost"
                onClick={() => act.mutate({ action: "cancel", payload: { reason } })}
              >
                {t("common.cancel")}
              </Button>
            )}
            <label className="ml-auto inline-flex cursor-pointer items-center gap-1 text-sm text-text-2">
              <Paperclip className="h-4 w-4" />
              {t("inst.attachScan")}
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
          <Label>{t("inst.history")}</Label>
          <ul className="max-h-52 space-y-2 overflow-y-auto text-sm">
            {(instrument.events ?? []).map((e) => (
              <li key={e.id} className="rounded-md bg-surface-2 p-2">
                <span className="text-xs uppercase text-accent-strong">{e.event}</span>{" "}
                {e.from_status && `${e.from_status} → `}
                {e.to_status}
                {e.journal_entry_number && (
                  <span className="ml-2 text-xs text-text-3">
                    {t("inst.postedAs")} {e.journal_entry_number}
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
