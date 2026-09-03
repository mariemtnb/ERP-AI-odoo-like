import { useRef, useState, type FormEvent } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Landmark, Plus, Upload } from "lucide-react";
import {
  createBankAccount, importStatementFile, listBankAccounts, listBanks,
  previewStatementFile,
} from "@/api/tunisia";
import { listAccounts } from "@/api/accounting";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { TableSkeleton } from "@/components/ui/skeleton";
import { Table, TBody, Td, Th, THead } from "@/components/ui/table";
import { Tooltip } from "@/components/ui/tooltip";
import { formatTnd } from "@/lib/tnLabels";
import { useI18n } from "@/lib/i18n";

const emptyAccount = {
  bank_id: "",
  label: "",
  branch: "",
  rib: "",
  iban: "",
  currency: "TND",
  gl_account_id: "",
  opening_balance: "0",
  opening_date: new Date().toISOString().slice(0, 10),
  is_default: false,
};

export default function BankingPage() {
  const { t } = useI18n();
  const qc = useQueryClient();
  const [createOpen, setCreateOpen] = useState(false);
  const [importOpen, setImportOpen] = useState(false);
  const [form, setForm] = useState(emptyAccount);
  const [error, setError] = useState("");
  const [warnings, setWarnings] = useState<string[]>([]);

  const { data: accounts, isLoading } = useQuery({
    queryKey: ["bank-accounts"],
    queryFn: () => listBankAccounts(),
  });
  const { data: banks } = useQuery({ queryKey: ["banks"], queryFn: () => listBanks() });
  const { data: glAccounts } = useQuery({
    queryKey: ["accounts"],
    queryFn: () => listAccounts(),
  });

  const createMutation = useMutation({
    mutationFn: () =>
      createBankAccount({
        ...form,
        bank_id: Number(form.bank_id),
        gl_account_id: form.gl_account_id ? Number(form.gl_account_id) : null,
        opening_balance: form.opening_balance as unknown as string,
      } as any),
    onSuccess: (account) => {
      qc.invalidateQueries({ queryKey: ["bank-accounts"] });
      setWarnings(account.warnings ?? []);
      setCreateOpen(false);
      setForm(emptyAccount);
      setError("");
    },
    onError: (e: any) =>
      setError(e?.response?.data?.detail ?? t("bnk.couldNotSaveAccount")),
  });

  const set = (k: keyof typeof emptyAccount) => (e: { target: { value: string } }) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  function submit(e: FormEvent) {
    e.preventDefault();
    createMutation.mutate();
  }

  const rows = accounts ?? [];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-text-3">
          {t("bnk.sub")}
        </p>
        <div className="flex gap-2">
          <Button variant="secondary" onClick={() => setImportOpen(true)}>
            <Upload className="h-4 w-4" /> {t("bnk.importStatement")}
          </Button>
          <Button onClick={() => { setError(""); setCreateOpen(true); }}>
            <Plus className="h-4 w-4" /> {t("bnk.newAccount")}
          </Button>
        </div>
      </div>

      {warnings.length > 0 && (
        <div className="rounded-md bg-surface-2 p-3 text-sm text-warning">
          {warnings.map((w) => (
            <p key={w}>⚠ {w}</p>
          ))}
          <p className="text-xs text-text-3">
            {t("bnk.savedAnyway")}
          </p>
        </div>
      )}

      {isLoading ? (
        <TableSkeleton rows={3} />
      ) : rows.length === 0 ? (
        <EmptyState
          icon={Landmark}
          title={t("bnk.noAccount")}
          hint={t("bnk.noAccountHint")}
          action={
            <Button onClick={() => { setError(""); setCreateOpen(true); }}>
              <Plus className="h-4 w-4" /> {t("bnk.newAccount")}
            </Button>
          }
        />
      ) : (
        <Table>
          <THead>
            <tr>
              <Th>{t("bnk.account")}</Th>
              <Th>{t("bnk.bank")}</Th>
              <Th>{t("bnk.rib")}</Th>
              <Th>{t("bnk.glAccount")}</Th>
              <Th>{t("bnk.lastReconciled")}</Th>
              <Th className="text-right">{t("acc.balance")}</Th>
            </tr>
          </THead>
          <TBody>
            {rows.map((a) => (
              <tr key={a.id}>
                <Td>
                  <span className="font-medium">{a.label}</span>
                  {a.is_default && <Badge tone="emerald" className="ml-2">{t("bnk.default")}</Badge>}
                  {a.branch && <p className="text-xs text-text-3">{a.branch}</p>}
                </Td>
                <Td>{a.bank_name ?? "-"}</Td>
                <Td className="font-mono text-xs">{a.rib || "-"}</Td>
                <Td>{a.gl_account_code ?? t("bnk.usesDefaultMapping")}</Td>
                <Td>{a.last_reconciled_at ?? t("bnk.never")}</Td>
                <Td className="text-right font-medium">
                  {formatTnd(a.current_balance, a.currency)}
                </Td>
              </tr>
            ))}
          </TBody>
        </Table>
      )}

      {/* ── create account ── */}
      <Dialog open={createOpen} onClose={() => setCreateOpen(false)} title={t("bnk.newBankAccount")}>
        <form onSubmit={submit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="b-bank">{t("bnk.bank")}</Label>
              <Select id="b-bank" value={form.bank_id} onChange={set("bank_id")} required>
                <option value="">{t("pay.choose")}</option>
                {(banks ?? []).map((b) => (
                  <option key={b.id} value={b.id}>{b.name}</option>
                ))}
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="b-label">{t("bnk.label")}</Label>
              <Input id="b-label" value={form.label} onChange={set("label")} required placeholder={t("bnk.labelPlaceholder")} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="b-branch">{t("bnk.branch")}</Label>
              <Input id="b-branch" value={form.branch} onChange={set("branch")} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="b-rib">{t("bnk.rib")}</Label>
              <Input id="b-rib" value={form.rib} onChange={set("rib")} placeholder={t("bnk.ribPlaceholder")} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="b-iban">{t("bnk.iban")}</Label>
              <Input id="b-iban" value={form.iban} onChange={set("iban")} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="b-currency">{t("bnk.currency")}</Label>
              <Input id="b-currency" value={form.currency} onChange={set("currency")} maxLength={3} />
            </div>
            <div className="space-y-1.5">
              <Tooltip label={t("bnk.glTip")}>
                <Label htmlFor="b-gl">{t("bnk.glAccount")}</Label>
              </Tooltip>
              <Select id="b-gl" value={form.gl_account_id} onChange={set("gl_account_id")}>
                <option value="">{t("bnk.useMappedDefault")}</option>
                {(glAccounts ?? []).map((a) => (
                  <option key={a.id} value={a.id}>{a.code} · {a.name}</option>
                ))}
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="b-opening">{t("bnk.openingBalance")}</Label>
              <Input
                id="b-opening"
                type="number"
                step="0.001"
                value={form.opening_balance}
                onChange={set("opening_balance")}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="b-date">{t("bnk.openingDate")}</Label>
              <Input id="b-date" type="date" value={form.opening_date} onChange={set("opening_date")} />
            </div>
          </div>
          <label className="flex items-center gap-2 text-sm text-text-2">
            <input
              type="checkbox"
              checked={form.is_default}
              onChange={(e) => setForm((f) => ({ ...f, is_default: e.target.checked }))}
            />
            {t("bnk.useDefault")}
          </label>
          {error && <p className="text-sm text-danger">{error}</p>}
          <Button type="submit" className="w-full" disabled={createMutation.isPending}>
            {createMutation.isPending ? t("common.saving") : t("bnk.createAccount")}
          </Button>
        </form>
      </Dialog>

      <ImportDialog
        open={importOpen}
        onClose={() => setImportOpen(false)}
        accounts={rows}
        onImported={() => {
          qc.invalidateQueries({ queryKey: ["bank-transactions"] });
          qc.invalidateQueries({ queryKey: ["treasury"] });
          setImportOpen(false);
        }}
      />
    </div>
  );
}

function ImportDialog({
  open,
  onClose,
  accounts,
  onImported,
}: {
  open: boolean;
  onClose: () => void;
  accounts: { id: number; label: string }[];
  onImported: () => void;
}) {
  const { t } = useI18n();
  const [accountId, setAccountId] = useState("");
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<{ count: number; rows: any[] } | null>(null);
  const [result, setResult] = useState<{ imported: number; skipped: number } | null>(null);
  const [error, setError] = useState("");
  const inputRef = useRef<HTMLInputElement>(null);

  const previewMutation = useMutation({
    mutationFn: (f: File) => previewStatementFile(f),
    onSuccess: (data) => {
      setPreview(data);
      setError("");
    },
    onError: (e: any) => {
      setPreview(null);
      setError(e?.response?.data?.detail ?? t("bnk.couldNotRead"));
    },
  });

  const importMutation = useMutation({
    mutationFn: () => importStatementFile(Number(accountId), file!),
    onSuccess: (data) => {
      setResult(data);
      setError("");
      onImported();
    },
    onError: (e: any) => setError(e?.response?.data?.detail ?? t("bnk.importFailed")),
  });

  return (
    <Dialog open={open} onClose={onClose} title={t("bnk.importTitle")} className="max-w-2xl">
      <div className="space-y-4">
        <div className="space-y-1.5">
          <Label htmlFor="im-account">{t("bnk.bankAccount")}</Label>
          <Select id="im-account" value={accountId} onChange={(e) => setAccountId(e.target.value)}>
            <option value="">{t("pay.choose")}</option>
            {accounts.map((a) => (
              <option key={a.id} value={a.id}>{a.label}</option>
            ))}
          </Select>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="im-file">{t("bnk.csvFile")}</Label>
          <input
            ref={inputRef}
            id="im-file"
            type="file"
            accept=".csv,.txt"
            className="text-sm text-text-2"
            onChange={(e) => {
              const f = e.target.files?.[0] ?? null;
              setFile(f);
              setResult(null);
              if (f) previewMutation.mutate(f);
            }}
          />
          <p className="text-xs text-text-3">
            {t("bnk.csvHint")}
          </p>
        </div>

        {preview && (
          <div className="space-y-2">
            <p className="text-sm text-text-2">
              {preview.count} {t("bnk.linesFound")}
            </p>
            <Table>
              <THead>
                <tr>
                  <Th>{t("common.date")}</Th>
                  <Th>{t("bnk.labelCol")}</Th>
                  <Th>{t("bnk.reference")}</Th>
                  <Th className="text-right">{t("subs.amount")}</Th>
                </tr>
              </THead>
              <TBody>
                {preview.rows.slice(0, 6).map((r, i) => (
                  <tr key={i}>
                    <Td>{r.operation_date}</Td>
                    <Td>{r.label}</Td>
                    <Td>{r.reference}</Td>
                    <Td className="text-right">{formatTnd(r.amount)}</Td>
                  </tr>
                ))}
              </TBody>
            </Table>
          </div>
        )}

        {result && (
          <p className="rounded-md bg-surface-2 p-3 text-sm text-positive">
            {t("bnk.importedPrefix")} {result.imported} {t("bnk.linesWord")}
            {result.skipped > 0 && ` · ${result.skipped} ${t("bnk.skippedWord")}`}.
          </p>
        )}
        {error && <p className="text-sm text-danger">{error}</p>}

        <Button
          className="w-full"
          disabled={!accountId || !file || importMutation.isPending}
          onClick={() => importMutation.mutate()}
        >
          {importMutation.isPending ? t("bnk.importing") : t("bnk.import")}
        </Button>
        <p className="text-xs text-text-3">
          {t("bnk.reimportSafe")}
        </p>
      </div>
    </Dialog>
  );
}
