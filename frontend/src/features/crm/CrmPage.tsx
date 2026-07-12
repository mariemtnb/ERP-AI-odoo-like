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
import { Dialog } from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import type { LeadStatus } from "@/types";

const COLUMNS: { status: LeadStatus; label: string; tone: string }[] = [
  { status: "new", label: "New", tone: "employee" },
  { status: "contacted", label: "Contacted", tone: "manager" },
  { status: "qualified", label: "Qualified", tone: "admin" },
  { status: "won", label: "Won", tone: "green" },
  { status: "lost", label: "Lost", tone: "red" },
];

const NEXT: Partial<Record<LeadStatus, LeadStatus>> = {
  new: "contacted",
  contacted: "qualified",
};

const emptyForm = { name: "", company: "", email: "", phone: "", source: "", notes: "" };

export default function CrmPage() {
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
      setError(JSON.stringify(e?.response?.data ?? "Request failed.")),
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
            Prospect pipeline · {leads.length} lead{leads.length === 1 ? "" : "s"} — advance
            deals, convert winners.
          </p>
        </div>
        <Button onClick={() => { setError(""); setCreateOpen(true); }}>
          <Plus className="h-4 w-4" /> New lead
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
          title="Your pipeline is empty"
          hint="Add your first lead — track calls and meetings, then convert winners into customers."
          action={
            <Button onClick={() => { setError(""); setCreateOpen(true); }}>
              <Plus className="h-4 w-4" /> New lead
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
                  <Badge tone={col.tone}>{col.label}</Badge>
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
                      <Button
                        variant="ghost"
                        size="sm"
                        className="mt-2 h-6 w-full text-xs"
                        onClick={(e) => {
                          e.stopPropagation();
                          statusMutation.mutate({ id: lead.id, status: NEXT[lead.status]! });
                        }}
                      >
                        <ArrowRight className="h-3 w-3" /> {NEXT[lead.status]}
                      </Button>
                    )}
                  </Card>
                ))}
              </div>
            );
          })}
        </div>
      )}

      {/* create dialog */}
      <Dialog open={createOpen} onClose={() => setCreateOpen(false)} title="New lead">
        <form onSubmit={submit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="l-name">Name</Label>
              <Input id="l-name" value={form.name} onChange={set("name")} required />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="l-company">Company</Label>
              <Input id="l-company" value={form.company} onChange={set("company")} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="l-email">Email</Label>
              <Input id="l-email" type="email" value={form.email} onChange={set("email")} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="l-phone">Phone</Label>
              <Input id="l-phone" value={form.phone} onChange={set("phone")} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="l-source">Source</Label>
              <Input id="l-source" placeholder="web, referral, event…" value={form.source} onChange={set("source")} />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="l-notes">Notes</Label>
            <Input id="l-notes" value={form.notes} onChange={set("notes")} />
          </div>
          {error && <p className="break-all text-sm text-danger">{error}</p>}
          <Button type="submit" className="w-full" disabled={createMutation.isPending}>
            {createMutation.isPending ? "Saving…" : "Create lead"}
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
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Conversion failed."),
  });

  const loseMutation = useMutation({
    mutationFn: () => updateLead(id!, { status: "lost" }),
    onSuccess: onChanged,
  });

  return (
    <Dialog
      open={id !== null}
      onClose={onClose}
      title={lead ? lead.name : "Lead"}
      className="max-w-2xl"
    >
      {lead && (
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-2 text-sm text-text-2">
            <p>Company: {lead.company || "—"}</p>
            <p>Source: {lead.source || "—"}</p>
            <p>Email: {lead.email || "—"}</p>
            <p>Phone: {lead.phone || "—"}</p>
            <p>Assigned to: {lead.assigned_to_email ?? "—"}</p>
            <p>
              Status: <Badge tone={lead.status === "won" ? "green" : lead.status === "lost" ? "red" : "manager"}>{lead.status}</Badge>
            </p>
          </div>
          {lead.notes && <p className="text-sm text-text-2">{lead.notes}</p>}

          {lead.status !== "won" && lead.status !== "lost" && (
            <div className="flex gap-2">
              <Button size="sm" onClick={() => convertMutation.mutate()} disabled={convertMutation.isPending}>
                <UserCheck className="h-4 w-4" /> Convert to customer
              </Button>
              <Button size="sm" variant="destructive" onClick={() => loseMutation.mutate()}>
                Mark lost
              </Button>
            </div>
          )}
          {lead.customer_id && (
            <p className="text-sm text-positive">
              Converted → customer #{lead.customer_id}
            </p>
          )}
          {error && <p className="text-sm text-danger">{error}</p>}

          <div className="space-y-2">
            <Label>Activity log</Label>
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
                <option value="call">Call</option>
                <option value="email">Email</option>
                <option value="meeting">Meeting</option>
                <option value="note">Note</option>
              </Select>
              <Input
                placeholder="What happened?"
                value={activity.summary}
                onChange={(e) => setActivity((a) => ({ ...a, summary: e.target.value }))}
              />
              <Button type="submit" size="sm" disabled={activityMutation.isPending}>
                Log
              </Button>
            </form>
            <ul className="max-h-48 space-y-2 overflow-y-auto text-sm">
              {(lead.activities ?? []).map((a) => (
                <li key={a.id} className="rounded-md bg-surface-2 p-2">
                  <span className="text-xs uppercase text-accent-strong">{a.type}</span>{" "}
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
