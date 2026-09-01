import { useState, type FormEvent } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowRight, Contact, Phone, Plus, UserCheck } from "lucide-react";
import {
  addLeadActivity,
  convertLead,
  createLead,
  getLead,
  listLeads,
  updateLead,
} from "@/api/crm";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Tooltip } from "@/components/ui/tooltip";
import { Dialog } from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { useI18n } from "@/lib/i18n";
import type { LeadStatus } from "@/types";

const COLUMNS: { status: LeadStatus; tone: string }[] = [
  { status: "new", tone: "employee" },
  { status: "contacted", tone: "manager" },
  { status: "qualified", tone: "admin" },
  { status: "won", tone: "green" },
  { status: "lost", tone: "red" },
];

const NEXT: Partial<Record<LeadStatus, LeadStatus>> = {
  new: "contacted",
  contacted: "qualified",
};

const emptyForm = { name: "", company: "", email: "", phone: "", source: "", notes: "" };

export default function CrmPage() {
  const { t } = useI18n();
  const qc = useQueryClient();
  const [createOpen, setCreateOpen] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [detailId, setDetailId] = useState<number | null>(null);
  const [error, setError] = useState("");

  const { data, isLoading } = useQuery({ queryKey: ["leads"], queryFn: () => listLeads() });
  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ["leads"] });
    if (detailId) qc.invalidateQueries({ queryKey: ["lead", detailId] });
  };

  const createMutation = useMutation({
    mutationFn: () => createLead(form),
    onSuccess: () => {
      invalidate();
      setCreateOpen(false);
      setForm(emptyForm);
      setError("");
    },
    onError: (e: any) =>
      setError(JSON.stringify(e?.response?.data ?? t("common.requestFailed"))),
  });

  const statusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: LeadStatus }) =>
      updateLead(id, { status }),
    onSuccess: invalidate,
  });

  const set = (k: keyof typeof emptyForm) => (e: { target: { value: string } }) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  function submit(e: FormEvent) {
    e.preventDefault();
    createMutation.mutate();
  }

  const leads = data?.results ?? [];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm text-text-3">
            {t("crm.pipeline1")} {leads.length} {t("crm.leadWord")} {t("crm.pipeline2")}
          </p>
        </div>
        <Button onClick={() => { setError(""); setCreateOpen(true); }}>
          <Plus className="h-4 w-4" /> {t("crm.new")}
        </Button>
      </div>

      {isLoading ? (
        <div className="grid gap-4 lg:grid-cols-5">
          {Array.from({ length: 5 }).map((_, i) => (
            <div key={i} className="space-y-3">
              <Skeleton className="h-6 w-20 rounded-full" />
              <Skeleton className="h-24" />
            </div>
          ))}
        </div>
      ) : leads.length === 0 ? (
        <EmptyState
          icon={Contact}
          title={t("crm.emptyTitle")}
          hint={t("crm.emptyHint")}
          action={
            <Button onClick={() => { setError(""); setCreateOpen(true); }}>
              <Plus className="h-4 w-4" /> {t("crm.new")}
            </Button>
          }
        />
      ) : (
        <div className="grid gap-4 lg:grid-cols-5">
          {COLUMNS.map((col) => {
            const items = leads.filter((l) => l.status === col.status);
            return (
              <div key={col.status} className="space-y-3">
                <div className="flex items-center justify-between px-1">
                  <Badge tone={col.tone}>{t(`crm.status.${col.status}`)}</Badge>
                  <span className="text-xs text-text-3">{items.length}</span>
                </div>
                {items.map((lead) => (
                  <Card
                    key={lead.id}
                    className="cursor-pointer p-3 transition-transform duration-200 hover:-translate-y-0.5 hover:shadow-3"
                    onClick={() => setDetailId(lead.id)}
                  >
                    <p className="text-sm font-medium">{lead.name}</p>
                    {lead.company && (
                      <p className="text-xs text-text-2">{lead.company}</p>
                    )}
                    {lead.phone && (
                      <p className="mt-1 flex items-center gap-1 text-xs text-text-3">
                        <Phone className="h-3 w-3" /> {lead.phone}
                      </p>
                    )}
                    {NEXT[lead.status] && (
                      <Tooltip label={t("crm.moveTip")} className="w-full">
                      <Button
                        variant="ghost"
                        size="sm"
                        className="mt-2 h-6 w-full text-xs"
                        onClick={(e) => {
                          e.stopPropagation();
                          statusMutation.mutate({ id: lead.id, status: NEXT[lead.status]! });
                        }}
                      >
                        <ArrowRight className="h-3 w-3" /> {t(`crm.status.${NEXT[lead.status]}`)}
                      </Button>
                      </Tooltip>
                    )}
                  </Card>
                ))}
              </div>
            );
          })}
        </div>
      )}

      {/* create dialog */}
      <Dialog open={createOpen} onClose={() => setCreateOpen(false)} title={t("crm.new")}>
        <form onSubmit={submit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="l-name">{t("field.name")}</Label>
              <Input id="l-name" value={form.name} onChange={set("name")} required />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="l-company">{t("crm.company")}</Label>
              <Input id="l-company" value={form.company} onChange={set("company")} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="l-email">{t("field.email")}</Label>
              <Input id="l-email" type="email" value={form.email} onChange={set("email")} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="l-phone">{t("field.phone")}</Label>
              <Input id="l-phone" value={form.phone} onChange={set("phone")} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="l-source">{t("crm.source")}</Label>
              <Input id="l-source" placeholder={t("crm.sourcePlaceholder")} value={form.source} onChange={set("source")} />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="l-notes">{t("field.notes")}</Label>
            <Input id="l-notes" value={form.notes} onChange={set("notes")} />
          </div>
          {error && <p className="break-all text-sm text-danger">{error}</p>}
          <Button type="submit" className="w-full" disabled={createMutation.isPending}>
            {createMutation.isPending ? t("common.saving") : t("crm.createLead")}
          </Button>
        </form>
      </Dialog>

      <LeadDetail id={detailId} onClose={() => setDetailId(null)} onChanged={invalidate} />
    </div>
  );
}

function LeadDetail({
  id,
  onClose,
  onChanged,
}: {
  id: number | null;
  onClose: () => void;
  onChanged: () => void;
}) {
  const { t } = useI18n();
  const [activity, setActivity] = useState({ type: "call", summary: "" });
  const [error, setError] = useState("");

  const { data: lead } = useQuery({
    queryKey: ["lead", id],
    queryFn: () => getLead(id!),
    enabled: id !== null,
  });

  const activityMutation = useMutation({
    mutationFn: () =>
      addLeadActivity(id!, { type: activity.type as any, summary: activity.summary }),
    onSuccess: () => {
      setActivity((a) => ({ ...a, summary: "" }));
      onChanged();
    },
  });

  const convertMutation = useMutation({
    mutationFn: () => convertLead(id!),
    onSuccess: onChanged,
    onError: (e: any) => setError(e?.response?.data?.detail ?? t("crm.conversionFailed")),
  });

  const loseMutation = useMutation({
    mutationFn: () => updateLead(id!, { status: "lost" }),
    onSuccess: onChanged,
  });

  return (
    <Dialog
      open={id !== null}
      onClose={onClose}
      title={lead ? lead.name : t("crm.lead")}
      className="max-w-2xl"
    >
      {lead && (
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-2 text-sm text-text-2">
            <p>{t("crm.companyLabel")} {lead.company || "—"}</p>
            <p>{t("crm.sourceLabel")} {lead.source || "—"}</p>
            <p>{t("crm.emailLabel")} {lead.email || "—"}</p>
            <p>{t("crm.phoneLabel")} {lead.phone || "—"}</p>
            <p>{t("crm.assignedTo")} {lead.assigned_to_email ?? "—"}</p>
            <p>
              {t("crm.statusLabel")} <Badge tone={lead.status === "won" ? "green" : lead.status === "lost" ? "red" : "manager"}>{t(`crm.status.${lead.status}`)}</Badge>
            </p>
          </div>
          {lead.notes && <p className="text-sm text-text-2">{lead.notes}</p>}

          {lead.status !== "won" && lead.status !== "lost" && (
            <div className="flex gap-2">
              <Button size="sm" onClick={() => convertMutation.mutate()} disabled={convertMutation.isPending}>
                <UserCheck className="h-4 w-4" /> {t("crm.convert")}
              </Button>
              <Button size="sm" variant="destructive" onClick={() => loseMutation.mutate()}>
                {t("crm.markLost")}
              </Button>
            </div>
          )}
          {lead.customer_id && (
            <p className="text-sm text-positive">
              {t("crm.converted")}{lead.customer_id}
            </p>
          )}
          {error && <p className="text-sm text-danger">{error}</p>}

          <div className="space-y-2">
            <Label>{t("crm.activityLog")}</Label>
            <form
              className="flex gap-2"
              onSubmit={(e) => {
                e.preventDefault();
                if (activity.summary.trim()) activityMutation.mutate();
              }}
            >
              <Select
                value={activity.type}
                onChange={(e) => setActivity((a) => ({ ...a, type: e.target.value }))}
                className="w-32"
              >
                <option value="call">{t("crm.act.call")}</option>
                <option value="email">{t("crm.act.email")}</option>
                <option value="meeting">{t("crm.act.meeting")}</option>
                <option value="note">{t("crm.act.note")}</option>
              </Select>
              <Input
                placeholder={t("crm.whatHappened")}
                value={activity.summary}
                onChange={(e) => setActivity((a) => ({ ...a, summary: e.target.value }))}
              />
              <Button type="submit" size="sm" disabled={activityMutation.isPending}>
                {t("crm.log")}
              </Button>
            </form>
            <ul className="max-h-48 space-y-2 overflow-y-auto text-sm">
              {(lead.activities ?? []).map((a) => (
                <li key={a.id} className="rounded-md bg-surface-2 p-2">
                  <span className="text-xs uppercase text-accent-strong">{t(`crm.act.${a.type}`)}</span>{" "}
                  {a.summary}
                  <span className="ml-2 text-xs text-text-3">
                    {new Date(a.created_at).toLocaleString()} · {a.created_by_email}
                  </span>
                </li>
              ))}
            </ul>
          </div>
        </div>
      )}
    </Dialog>
  );
}
