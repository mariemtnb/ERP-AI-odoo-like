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
      setError(e?.response?.data?.detail ?? "Could not save this account."),
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
          Comptes bancaires — your accounts, their RIB, and the statement lines you import
          for reconciliation.
        </p>
        <div className="flex gap-2">
          <Button variant="secondary" onClick={() => setImportOpen(true)}>
            <Upload className="h-4 w-4" /> Import statement
          </Button>
          <Button onClick={() => { setError(""); setCreateOpen(true); }}>
            <Plus className="h-4 w-4" /> New account
          </Button>
        </div>
      </div>

      {warnings.length > 0 && (
        <div className="rounded-md bg-surface-2 p-3 text-sm text-warning">
          {warnings.map((w) => (
            <p key={w}>⚠ {w}</p>
          ))}
          <p className="text-xs text-text-3">
            Saved anyway — these are format hints, not blocking rules.
          </p>
        </div>
      )}

      {isLoading ? (
        <TableSkeleton rows={3} />
      ) : rows.length === 0 ? (
        <EmptyState
          icon={Landmark}
          title="No bank account yet"
          hint="Add the account your cheques are deposited into — you can then import its statements and reconcile them."
          action={
            <Button onClick={() => { setError(""); setCreateOpen(true); }}>
              <Plus className="h-4 w-4" /> New account
            </Button>
          }
        />
      ) : (
        <Table>
          <THead>
            <tr>
              <Th>Account</Th>
              <Th>Bank</Th>
              <Th>RIB</Th>
              <Th>GL account</Th>
              <Th>Last reconciled</Th>
              <Th className="text-right">Balance</Th>
            </tr>
          </THead>
          <TBody>
            {rows.map((a) => (
              <tr key={a.id}>
                <Td>
                  <span className="font-medium">{a.label}</span>
                  {a.is_default && <Badge tone="emerald" className="ml-2">default</Badge>}
                  {a.branch && <p className="text-xs text-text-3">{a.branch}</p>}
                </Td>
                <Td>{a.bank_name ?? "—"}</Td>
                <Td className="font-mono text-xs">{a.rib || "—"}</Td>
                <Td>{a.gl_account_code ?? "— (uses default mapping)"}</Td>
                <Td>{a.last_reconciled_at ?? "never"}</Td>
                <Td className="text-right font-medium">
                  {formatTnd(a.current_balance, a.currency)}
                </Td>
              </tr>
            ))}
          </TBody>
        </Table>
      )}

      {/* ── create account ── */}
      <Dialog open={createOpen} onClose={() => setCreateOpen(false)} title="New bank account">
        <form onSubmit={submit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="b-bank">Bank</Label>
              <Select id="b-bank" value={form.bank_id} onChange={set("bank_id")} required>
                <option value="">— choose —</option>
                {(banks ?? []).map((b) => (
                  <option key={b.id} value={b.id}>{b.name}</option>
                ))}
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="b-label">Label</Label>
              <Input id="b-label" value={form.label} onChange={set("label")} required placeholder="Compte courant" />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="b-branch">Branch (agence)</Label>
              <Input id="b-branch" value={form.branch} onChange={set("branch")} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="b-rib">RIB</Label>
              <Input id="b-rib" value={form.rib} onChange={set("rib")} placeholder="20 digits" />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="b-iban">IBAN</Label>
              <Input id="b-iban" value={form.iban} onChange={set("iban")} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="b-currency">Currency</Label>
              <Input id="b-currency" value={form.currency} onChange={set("currency")} maxLength={3} />
            </div>
            <div className="space-y-1.5">
              <Tooltip label="Which ledger account this bank posts to. Leave empty to use the mapped default.">
                <Label htmlFor="b-gl">GL account</Label>
              </Tooltip>
              <Select id="b-gl" value={form.gl_account_id} onChange={set("gl_account_id")}>
                <option value="">— use the mapped default —</option>
                {(glAccounts ?? []).map((a) => (
                  <option key={a.id} value={a.id}>{a.code} · {a.name}</option>
                ))}
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="b-opening">Opening balance</Label>
              <Input
                id="b-opening"
                type="number"
                step="0.001"
                value={form.opening_balance}
                onChange={set("opening_balance")}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="b-date">Opening date</Label>
              <Input id="b-date" type="date" value={form.opening_date} onChange={set("opening_date")} />
            </div>
          </div>
          <label className="flex items-center gap-2 text-sm text-text-2">
            <input
              type="checkbox"
              checked={form.is_default}
              onChange={(e) => setForm((f) => ({ ...f, is_default: e.target.checked }))}
            />
            Use as the default account for deposits
          </label>
          {error && <p className="text-sm text-danger">{error}</p>}
          <Button type="submit" className="w-full" disabled={createMutation.isPending}>
            {createMutation.isPending ? "Saving…" : "Create account"}
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
      setError(e?.response?.data?.detail ?? "Could not read this file.");
    },
  });

  const importMutation = useMutation({
    mutationFn: () => importStatementFile(Number(accountId), file!),
    onSuccess: (data) => {
      setResult(data);
      setError("");
      onImported();
    },
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Import failed."),
  });

  return (
    <Dialog open={open} onClose={onClose} title="Import a bank statement" className="max-w-2xl">
      <div className="space-y-4">
        <div className="space-y-1.5">
          <Label htmlFor="im-account">Bank account</Label>
          <Select id="im-account" value={accountId} onChange={(e) => setAccountId(e.target.value)}>
            <option value="">— choose —</option>
            {accounts.map((a) => (
              <option key={a.id} value={a.id}>{a.label}</option>
            ))}
          </Select>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="im-file">CSV file</Label>
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
            Comma or semicolon separated. Recognised columns: date / date opération, libellé,
            référence, montant (or débit and crédit), solde. Dates in JJ/MM/AAAA or ISO.
          </p>
        </div>

        {preview && (
          <div className="space-y-2">
            <p className="text-sm text-text-2">
              {preview.count} line{preview.count === 1 ? "" : "s"} found — first few:
            </p>
            <Table>
              <THead>
                <tr>
                  <Th>Date</Th>
                  <Th>Label</Th>
                  <Th>Reference</Th>
                  <Th className="text-right">Amount</Th>
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
            Imported {result.imported} line{result.imported === 1 ? "" : "s"}
            {result.skipped > 0 && ` · skipped ${result.skipped} already present`}.
          </p>
        )}
        {error && <p className="text-sm text-danger">{error}</p>}

        <Button
          className="w-full"
          disabled={!accountId || !file || importMutation.isPending}
          onClick={() => importMutation.mutate()}
        >
          {importMutation.isPending ? "Importing…" : "Import"}
        </Button>
        <p className="text-xs text-text-3">
          Re-importing an overlapping statement is safe — identical lines are skipped.
        </p>
      </div>
    </Dialog>
  );
}
