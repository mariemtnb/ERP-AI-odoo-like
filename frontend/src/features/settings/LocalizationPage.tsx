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
import { useI18n } from "@/lib/i18n";

export default function LocalizationPage() {
  const { t } = useI18n();
  const [tab, setTab] = useState("profile");

  return (
    <div className="space-y-6">
      <p className="text-sm text-text-3">
        {t("loc.sub")}
      </p>

      <Segmented
        id="localization-tab"
        value={tab}
        onChange={setTab}
        options={[
          { value: "profile", label: t("loc.tab.profile") },
          { value: "mappings", label: t("loc.tab.mappings") },
          { value: "journals", label: t("loc.tab.journals") },
        ]}
      />

      {tab === "profile" && <ProfileTab />}
      {tab === "mappings" && <MappingsTab />}
      {tab === "journals" && <JournalsTab />}
    </div>
  );
}

function ProfileTab() {
  const { t } = useI18n();
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
    onError: (e: any) => setError(e?.response?.data?.detail ?? t("adm.couldNotSave")),
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
        <h3 className="text-sm font-semibold">{t("loc.identity")}</h3>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field id="legal_name" label={t("loc.legalName")} value={form.legal_name} onChange={set("legal_name")} />
          <Field id="trade_name" label={t("loc.tradeName")} value={form.trade_name} onChange={set("trade_name")} />
          <Field id="address" label={t("field.address")} value={form.address} onChange={set("address")} />
          <Field id="city" label={t("adm.city")} value={form.city} onChange={set("city")} />
          <Field id="postal_code" label={t("loc.postalCode")} value={form.postal_code} onChange={set("postal_code")} />
          <Field id="phone" label={t("field.phone")} value={form.phone} onChange={set("phone")} />
          <Field id="email" label={t("field.email")} value={form.email} onChange={set("email")} />
        </div>
      </section>

      <section className="erp-card space-y-4 p-5">
        <div className="flex items-center justify-between">
          <h3 className="text-sm font-semibold">{t("loc.taxIds")}</h3>
          {profile?.full_tax_id && (
            <Badge tone="sky">{profile.full_tax_id}</Badge>
          )}
        </div>
        <div className="grid gap-4 sm:grid-cols-4">
          <Field
            id="tax_id"
            label={t("loc.matricule")}
            value={form.tax_id}
            onChange={set("tax_id")}
            hint={t("loc.mainTaxId")}
          />
          <Field id="vat_code" label={t("loc.vatCode")} value={form.vat_code} onChange={set("vat_code")} />
          <Field id="category_code" label={t("loc.category")} value={form.category_code} onChange={set("category_code")} />
          <Field
            id="establishment_code"
            label={t("loc.establishment")}
            value={form.establishment_code}
            onChange={set("establishment_code")}
          />
          <Field id="trade_register" label={t("loc.tradeRegister")} value={form.trade_register} onChange={set("trade_register")} />
          <Field id="customs_code" label={t("loc.customsCode")} value={form.customs_code} onChange={set("customs_code")} />
        </div>
        {(profile?.warnings ?? []).length > 0 && (
          <p className="text-sm text-warning">
            ⚠ {profile!.warnings!.join(" ")}
          </p>
        )}
      </section>

      <section className="erp-card space-y-4 p-5">
        <h3 className="text-sm font-semibold">{t("loc.fiscalSettings")}</h3>
        <div className="grid gap-4 sm:grid-cols-3">
          <div className="space-y-1.5">
            <Label htmlFor="fiscal_regime">{t("loc.regime")}</Label>
            <Select id="fiscal_regime" value={form.fiscal_regime ?? "reel"} onChange={set("fiscal_regime")}>
              {Object.entries(FISCAL_REGIME).map(([key, l]) => (
                <option key={key} value={key}>{l.en} · {l.fr}</option>
              ))}
            </Select>
          </div>
          <Field id="default_vat_rate" label={t("loc.defaultVat")} type="number" value={form.default_vat_rate} onChange={set("default_vat_rate")} />
          <Field id="withholding_rate" label={t("loc.withholding")} type="number" value={form.withholding_rate} onChange={set("withholding_rate")} />
          <Field
            id="stamp_duty_amount"
            label={t("loc.stampDuty")}
            type="number"
            value={form.stamp_duty_amount}
            onChange={set("stamp_duty_amount")}
          />
          <Field id="currency" label={t("bnk.currency")} value={form.currency} onChange={set("currency")} />
          <Field
            id="currency_decimals"
            label={t("loc.decimals")}
            type="number"
            value={form.currency_decimals}
            onChange={set("currency_decimals")}
            hint={t("loc.tndDecimals")}
          />
          <Field
            id="default_payment_terms_days"
            label={t("loc.paymentTerms")}
            type="number"
            value={form.default_payment_terms_days}
            onChange={set("default_payment_terms_days")}
          />
          <Field
            id="late_payment_grace_days"
            label={t("loc.grace")}
            type="number"
            value={form.late_payment_grace_days}
            onChange={set("late_payment_grace_days")}
            hint={t("loc.graceHint")}
          />
          <Field
            id="invoice_number_format"
            label={t("loc.invoiceNumbering")}
            value={form.invoice_number_format}
            onChange={set("invoice_number_format")}
            hint={t("loc.invoiceNumberingHint")}
          />
        </div>
        <div className="space-y-2">
          <label className="flex items-center gap-2 text-sm text-text-2">
            <input type="checkbox" checked={!!form.vat_registered} onChange={check("vat_registered")} />
            {t("loc.vatRegistered")}
          </label>
          <label className="flex items-center gap-2 text-sm text-text-2">
            <input type="checkbox" checked={!!form.stamp_duty_enabled} onChange={check("stamp_duty_enabled")} />
            {t("loc.applyStamp")}
          </label>
          <Tooltip label={t("loc.enforceTip")}>
            <label className="flex items-center gap-2 text-sm text-text-2">
              <input
                type="checkbox"
                checked={!!form.enforce_legal_validation}
                onChange={check("enforce_legal_validation")}
              />
              {t("loc.enforceChecks")}
            </label>
          </Tooltip>
        </div>
      </section>

      {error && <p className="text-sm text-danger">{error}</p>}
      <div className="flex items-center gap-3">
        <Button type="submit" disabled={mutation.isPending}>
          <Save className="h-4 w-4" /> {mutation.isPending ? t("common.saving") : t("loc.saveSettings")}
        </Button>
        {saved && <span className="text-sm text-positive">{t("loc.savedDot")}</span>}
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
  const { t } = useI18n();
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
      setMessage(t("loc.mappingUpdated"));
      setError("");
    },
    onError: (e: any) => setError(e?.response?.data?.detail ?? t("loc.couldNotSaveMapping")),
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
        <h3 className="text-sm font-semibold">{t("loc.chartTemplate")}</h3>
        <p className="text-sm text-text-2">
          {t("loc.chartNote")}
        </p>
        <div className="flex gap-2">
          <Button size="sm" onClick={() => templateMutation.mutate("tunisia")}>
            <BookOpen className="h-4 w-4" /> {t("loc.applyTunisian")}
          </Button>
          <Button size="sm" variant="secondary" onClick={() => templateMutation.mutate("default")}>
            {t("loc.restoreGeneric")}
          </Button>
        </div>
        <p className="text-xs text-text-3">
          {t("loc.chartHint")}
        </p>
      </div>

      {message && <p className="text-sm text-positive">{message}</p>}
      {error && <p className="text-sm text-danger">{error}</p>}

      <Table>
        <THead>
          <tr>
            <Th>{t("loc.movement")}</Th>
            <Th>{t("loc.meaning")}</Th>
            <Th>{t("acc.account")}</Th>
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
                      {m.account_code} - {t("loc.missing")}
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
        {saveMutation.isPending ? t("common.saving") : `${t("adm.savePrefix")} ${Object.keys(draft).length} ${t("loc.changesSuffix")}`}
      </Button>
    </div>
  );
}

function JournalsTab() {
  const { t } = useI18n();
  const { data, isLoading } = useQuery({ queryKey: ["journals"], queryFn: listJournals });

  if (isLoading) return <TableSkeleton rows={6} />;

  return (
    <div className="space-y-3">
      <p className="text-sm text-text-2">
        {t("loc.journalsNote")}
      </p>
      <Table>
        <THead>
          <tr>
            <Th>{t("adm.code")}</Th>
            <Th>{t("loc.journal")}</Th>
            <Th>{t("loc.libelle")}</Th>
            <Th>{t("adm.type")}</Th>
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
