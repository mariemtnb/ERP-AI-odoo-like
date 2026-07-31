import { useEffect, useState, type FormEvent } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { BookOpen, Save } from "lucide-react";
import {
  applyChartTemplate, getCompanyProfile, listAccountMappings, listJournals,
  updateAccountMappings, updateCompanyProfile,
} from "@/api/tunisia";
import { listAccounts } from "@/api/accounting";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Segmented } from "@/components/ui/segmented";
import { Select } from "@/components/ui/select";
import { TableSkeleton } from "@/components/ui/skeleton";
import { Table, TBody, Td, Th, THead } from "@/components/ui/table";
import { Tooltip } from "@/components/ui/tooltip";
import { FISCAL_REGIME } from "@/lib/tnLabels";
import type { CompanyProfile } from "@/types";

export default function LocalizationPage() {
  const [tab, setTab] = useState("profile");

  return (
    <div className="space-y-6">
      <p className="text-sm text-text-3">
        Paramètres de localisation — the fiscal identity of the company, the accounting
        journals, and which ledger account each kind of movement posts to.
      </p>

      <Segmented
        id="localization-tab"
        value={tab}
        onChange={setTab}
        options={[
          { value: "profile", label: "Company & fiscal" },
          { value: "mappings", label: "Account mapping" },
          { value: "journals", label: "Journals" },
        ]}
      />

      {tab === "profile" && <ProfileTab />}
      {tab === "mappings" && <MappingsTab />}
      {tab === "journals" && <JournalsTab />}
    </div>
  );
}

function ProfileTab() {
  const qc = useQueryClient();
  const [form, setForm] = useState<Partial<CompanyProfile>>({});
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState("");

  const { data: profile, isLoading } = useQuery({
    queryKey: ["company-profile"],
    queryFn: getCompanyProfile,
  });

  useEffect(() => {
    if (profile) setForm(profile);
  }, [profile]);

  const mutation = useMutation({
    mutationFn: () => updateCompanyProfile(form),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["company-profile"] });
      setSaved(true);
      setError("");
      setTimeout(() => setSaved(false), 2500);
    },
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not save."),
  });

  const set = (k: keyof CompanyProfile) => (e: { target: { value: string } }) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  const check = (k: keyof CompanyProfile) => (e: { target: { checked: boolean } }) =>
    setForm((f) => ({ ...f, [k]: e.target.checked }));

  if (isLoading) return <TableSkeleton rows={6} />;

  function submit(e: FormEvent) {
    e.preventDefault();
    mutation.mutate();
  }

  return (
    <form onSubmit={submit} className="space-y-6">
      <section className="erp-card space-y-4 p-5">
        <h3 className="text-sm font-semibold">Identity</h3>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field id="legal_name" label="Legal name" value={form.legal_name} onChange={set("legal_name")} />
          <Field id="trade_name" label="Trade name" value={form.trade_name} onChange={set("trade_name")} />
          <Field id="address" label="Address" value={form.address} onChange={set("address")} />
          <Field id="city" label="City" value={form.city} onChange={set("city")} />
          <Field id="postal_code" label="Postal code" value={form.postal_code} onChange={set("postal_code")} />
          <Field id="phone" label="Phone" value={form.phone} onChange={set("phone")} />
          <Field id="email" label="Email" value={form.email} onChange={set("email")} />
        </div>
      </section>

      <section className="erp-card space-y-4 p-5">
        <div className="flex items-center justify-between">
          <h3 className="text-sm font-semibold">Tax identifiers</h3>
          {profile?.full_tax_id && (
            <Badge tone="sky">{profile.full_tax_id}</Badge>
          )}
        </div>
        <div className="grid gap-4 sm:grid-cols-4">
          <Field
            id="tax_id"
            label="Matricule fiscal"
            value={form.tax_id}
            onChange={set("tax_id")}
            hint="Main tax identifier"
          />
          <Field id="vat_code" label="VAT code" value={form.vat_code} onChange={set("vat_code")} />
          <Field id="category_code" label="Category" value={form.category_code} onChange={set("category_code")} />
          <Field
            id="establishment_code"
            label="Establishment"
            value={form.establishment_code}
            onChange={set("establishment_code")}
          />
          <Field id="trade_register" label="Registre de commerce" value={form.trade_register} onChange={set("trade_register")} />
          <Field id="customs_code" label="Customs code" value={form.customs_code} onChange={set("customs_code")} />
        </div>
        {(profile?.warnings ?? []).length > 0 && (
          <p className="text-sm text-warning">
            ⚠ {profile!.warnings!.join(" ")}
          </p>
        )}
      </section>

      <section className="erp-card space-y-4 p-5">
        <h3 className="text-sm font-semibold">Fiscal settings</h3>
        <div className="grid gap-4 sm:grid-cols-3">
          <div className="space-y-1.5">
            <Label htmlFor="fiscal_regime">Regime</Label>
            <Select id="fiscal_regime" value={form.fiscal_regime ?? "reel"} onChange={set("fiscal_regime")}>
              {Object.entries(FISCAL_REGIME).map(([key, l]) => (
                <option key={key} value={key}>{l.en} · {l.fr}</option>
              ))}
            </Select>
          </div>
          <Field id="default_vat_rate" label="Default VAT rate (%)" type="number" value={form.default_vat_rate} onChange={set("default_vat_rate")} />
          <Field id="withholding_rate" label="Withholding rate (%)" type="number" value={form.withholding_rate} onChange={set("withholding_rate")} />
          <Field
            id="stamp_duty_amount"
            label="Stamp duty (timbre fiscal)"
            type="number"
            value={form.stamp_duty_amount}
            onChange={set("stamp_duty_amount")}
          />
          <Field id="currency" label="Currency" value={form.currency} onChange={set("currency")} />
          <Field
            id="currency_decimals"
            label="Decimals"
            type="number"
            value={form.currency_decimals}
            onChange={set("currency_decimals")}
            hint="TND is normally 3"
          />
          <Field
            id="default_payment_terms_days"
            label="Payment terms (days)"
            type="number"
            value={form.default_payment_terms_days}
            onChange={set("default_payment_terms_days")}
          />
          <Field
            id="late_payment_grace_days"
            label="Grace period (days)"
            type="number"
            value={form.late_payment_grace_days}
            onChange={set("late_payment_grace_days")}
            hint="Before an instalment counts as late"
          />
          <Field
            id="invoice_number_format"
            label="Invoice numbering"
            value={form.invoice_number_format}
            onChange={set("invoice_number_format")}
            hint="{YYYY} and {SEQ:4} are substituted"
          />
        </div>
        <div className="space-y-2">
          <label className="flex items-center gap-2 text-sm text-text-2">
            <input type="checkbox" checked={!!form.vat_registered} onChange={check("vat_registered")} />
            Registered for VAT
          </label>
          <label className="flex items-center gap-2 text-sm text-text-2">
            <input type="checkbox" checked={!!form.stamp_duty_enabled} onChange={check("stamp_duty_enabled")} />
            Apply stamp duty on invoices
          </label>
          <Tooltip label="When off, unusual identifiers are reported as warnings but never block a save. Turn on only if your data is already clean.">
            <label className="flex items-center gap-2 text-sm text-text-2">
              <input
                type="checkbox"
                checked={!!form.enforce_legal_validation}
                onChange={check("enforce_legal_validation")}
              />
              Enforce identifier format checks (reject instead of warn)
            </label>
          </Tooltip>
        </div>
      </section>

      {error && <p className="text-sm text-danger">{error}</p>}
      <div className="flex items-center gap-3">
        <Button type="submit" disabled={mutation.isPending}>
          <Save className="h-4 w-4" /> {mutation.isPending ? "Saving…" : "Save settings"}
        </Button>
        {saved && <span className="text-sm text-positive">Saved.</span>}
      </div>
    </form>
  );
}

function Field({
  id,
  label: text,
  value,
  onChange,
  type = "text",
  hint,
}: {
  id: string;
  label: string;
  value: unknown;
  onChange: (e: { target: { value: string } }) => void;
  type?: string;
  hint?: string;
}) {
  return (
    <div className="space-y-1.5">
      <Label htmlFor={id}>{text}</Label>
      <Input id={id} type={type} value={(value as string) ?? ""} onChange={onChange} />
      {hint && <p className="text-xs text-text-3">{hint}</p>}
    </div>
  );
}

function MappingsTab() {
  const qc = useQueryClient();
  const [draft, setDraft] = useState<Record<string, string>>({});
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  const { data, isLoading } = useQuery({
    queryKey: ["account-mappings"],
    queryFn: listAccountMappings,
  });
  const { data: accounts } = useQuery({ queryKey: ["accounts"], queryFn: () => listAccounts() });

  const saveMutation = useMutation({
    mutationFn: () =>
      updateAccountMappings(
        Object.entries(draft).map(([key, account_code]) => ({ key, account_code }))
      ),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["account-mappings"] });
      setDraft({});
      setMessage("Mapping updated.");
      setError("");
    },
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not save the mapping."),
  });

  const templateMutation = useMutation({
    mutationFn: (template: "tunisia" | "default") => applyChartTemplate(template),
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: ["account-mappings"] });
      setMessage(res.detail);
      setError("");
    },
  });

  if (isLoading) return <TableSkeleton rows={8} />;

  const rows = data?.results ?? [];

  return (
    <div className="space-y-4">
      <div className="erp-card space-y-3 p-5">
        <h3 className="text-sm font-semibold">Chart of accounts template</h3>
        <p className="text-sm text-text-2">
          Every posting resolves its accounts through this table. Applying a template just
          re-points the keys — no account or history is deleted, and you can switch back.
        </p>
        <div className="flex gap-2">
          <Button size="sm" onClick={() => templateMutation.mutate("tunisia")}>
            <BookOpen className="h-4 w-4" /> Apply Tunisian chart
          </Button>
          <Button size="sm" variant="secondary" onClick={() => templateMutation.mutate("default")}>
            Restore generic chart
          </Button>
        </div>
        <p className="text-xs text-text-3">
          The Tunisian codes shipped here (411 Clients, 401 Fournisseurs, 5112 Chèques à
          encaisser…) are a practical starting point — confirm them with your accountant and
          edit any row below.
        </p>
      </div>

      {message && <p className="text-sm text-positive">{message}</p>}
      {error && <p className="text-sm text-danger">{error}</p>}

      <Table>
        <THead>
          <tr>
            <Th>Movement</Th>
            <Th>Meaning</Th>
            <Th>Account</Th>
          </tr>
        </THead>
        <TBody>
          {rows.map((m) => (
            <tr key={m.id}>
              <Td className="font-medium">{m.label || m.key}</Td>
              <Td className="text-text-3">{m.description || m.key}</Td>
              <Td>
                <Select
                  value={draft[m.key] ?? m.account_code}
                  onChange={(e) => setDraft((d) => ({ ...d, [m.key]: e.target.value }))}
                  className="max-w-xs"
                >
                  {!m.account_exists && (
                    <option value={m.account_code}>
                      {m.account_code} — missing!
                    </option>
                  )}
                  {(accounts ?? []).map((a) => (
                    <option key={a.id} value={a.code}>{a.code} · {a.name}</option>
                  ))}
                </Select>
              </Td>
            </tr>
          ))}
        </TBody>
      </Table>

      <Button
        disabled={Object.keys(draft).length === 0 || saveMutation.isPending}
        onClick={() => saveMutation.mutate()}
      >
        <Save className="h-4 w-4" />
        {saveMutation.isPending ? "Saving…" : `Save ${Object.keys(draft).length} change(s)`}
      </Button>
    </div>
  );
}

function JournalsTab() {
  const { data, isLoading } = useQuery({ queryKey: ["journals"], queryFn: listJournals });

  if (isLoading) return <TableSkeleton rows={6} />;

  return (
    <div className="space-y-3">
      <p className="text-sm text-text-2">
        Entries are filed by journal, the way a Tunisian accountant reads the books. Every
        posting made by the system picks its journal automatically.
      </p>
      <Table>
        <THead>
          <tr>
            <Th>Code</Th>
            <Th>Journal</Th>
            <Th>Libellé</Th>
            <Th>Type</Th>
          </tr>
        </THead>
        <TBody>
          {(data ?? []).map((j) => (
            <tr key={j.id}>
              <Td className="font-mono font-medium">{j.code}</Td>
              <Td>{j.name}</Td>
              <Td className="text-text-3">{j.name_fr}</Td>
              <Td>
                <Badge tone="employee">{j.type.replace(/_/g, " ")}</Badge>
              </Td>
            </tr>
          ))}
        </TBody>
      </Table>
    </div>
  );
}
