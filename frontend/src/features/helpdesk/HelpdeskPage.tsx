import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { PageHead } from "@/components/ui/page-head";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useAuth } from "@/features/auth/AuthContext";
import * as hd from "@/api/helpdesk";

const PRIORITY_COLOR: Record<string, string> = {
  low: "var(--text-muted)", normal: "var(--emerald-400)",
  high: "var(--amber-400,#d99a2b)", urgent: "var(--rose-400)",
};
const STATUS_LABEL: Record<string, string> = { open: "Open", in_progress: "In progress", resolved: "Resolved", closed: "Closed" };
const NEXT: Record<string, string[]> = {
  open: ["in_progress", "closed"], in_progress: ["resolved", "closed"], resolved: ["closed", "in_progress"], closed: [],
};

export default function HelpdeskPage() {
  const qc = useQueryClient();
  const [status, setStatus] = useState("");
  const ticketsQ = useQuery({ queryKey: ["tickets", status], queryFn: () => hd.listTickets(status ? { status } : {}) });
  const [selected, setSelected] = useState<number | null>(null);
  const [creating, setCreating] = useState(false);

  return (
    <div>
      <PageHead title="Helpdesk" sub="Support tickets with priorities, assignment and a conversation thread.">
        <Button onClick={() => setCreating((v) => !v)}>{creating ? "Close" : "New ticket"}</Button>
      </PageHead>

      {creating && <NewTicket onDone={(id) => { setCreating(false); qc.invalidateQueries({ queryKey: ["tickets"] }); setSelected(id); }} onCancel={() => setCreating(false)} />}

      <div style={{ display: "flex", gap: 6, marginBottom: 14 }}>
        {["", "open", "in_progress", "resolved", "closed"].map((s) => (
          <button key={s || "all"} onClick={() => setStatus(s)} style={{
            padding: "5px 12px", borderRadius: 8, cursor: "pointer", fontSize: 13,
            border: "1px solid " + (status === s ? "var(--emerald-500)" : "var(--border)"),
            background: status === s ? "color-mix(in oklab, var(--emerald-500) 12%, transparent)" : "var(--surface)",
            color: status === s ? "var(--text-strong)" : "var(--text-muted)",
          }}>{s ? STATUS_LABEL[s] : "All"}</button>
        ))}
      </div>

      <div style={{ display: "grid", gridTemplateColumns: "320px 1fr", gap: 18, alignItems: "start" }}>
        <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, overflow: "hidden" }}>
          {(ticketsQ.data ?? []).map((t) => (
            <button key={t.id} onClick={() => setSelected(t.id)} style={{
              display: "block", width: "100%", textAlign: "left", padding: "12px 14px", cursor: "pointer",
              background: selected === t.id ? "color-mix(in oklab, var(--emerald-500) 12%, transparent)" : "transparent",
              border: 0, borderBottom: "1px solid var(--border)", color: "var(--text-strong)",
            }}>
              <div style={{ display: "flex", justifyContent: "space-between", gap: 8 }}>
                <span style={{ fontWeight: 600 }}>{t.subject}</span>
                <span style={{ fontSize: 11, fontWeight: 700, color: PRIORITY_COLOR[t.priority], textTransform: "uppercase" }}>{t.priority}</span>
              </div>
              <div style={{ fontSize: 12, color: "var(--text-muted)" }}>{t.number} · {STATUS_LABEL[t.status]} · {t.messages_count} msg</div>
            </button>
          ))}
          {ticketsQ.data?.length === 0 && <p style={{ padding: 14, color: "var(--text-muted)", fontSize: 13 }}>No tickets.</p>}
        </div>

        <div>{selected ? <TicketDetail ticketId={selected} /> : <p style={{ color: "var(--text-muted)" }}>Select a ticket.</p>}</div>
      </div>
    </div>
  );
}

function TicketDetail({ ticketId }: { ticketId: number }) {
  const { user } = useAuth();
  const isManager = user?.role === "admin" || user?.role === "manager";
  const qc = useQueryClient();
  const q = useQuery({ queryKey: ["ticket", ticketId], queryFn: () => hd.getTicket(ticketId) });
  const [body, setBody] = useState("");
  const refresh = () => { qc.invalidateQueries({ queryKey: ["ticket", ticketId] }); qc.invalidateQueries({ queryKey: ["tickets"] }); };

  const reply = useMutation({ mutationFn: () => hd.replyTicket(ticketId, body), onSuccess: () => { setBody(""); refresh(); } });
  const setStatus = useMutation({ mutationFn: (s: string) => hd.setTicketStatus(ticketId, s), onSuccess: refresh });

  const t = q.data;
  if (!t) return <p style={{ color: "var(--text-muted)" }}>Loading…</p>;

  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, overflow: "hidden" }}>
      <div style={{ padding: 16, borderBottom: "1px solid var(--border)" }}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
          <div>
            <div style={{ fontWeight: 700, color: "var(--text-strong)", fontSize: 16 }}>{t.subject}</div>
            <div style={{ fontSize: 12, color: "var(--text-muted)" }}>{t.number} · {t.customer_name ?? "no customer"} · priority <span style={{ color: PRIORITY_COLOR[t.priority] }}>{t.priority}</span></div>
          </div>
          <span style={{ fontSize: 12, fontWeight: 600, color: "var(--emerald-400)", border: "1px solid var(--emerald-500)", borderRadius: 999, padding: "3px 12px" }}>{STATUS_LABEL[t.status]}</span>
        </div>
        {isManager && NEXT[t.status].length > 0 && (
          <div style={{ display: "flex", gap: 6, marginTop: 12 }}>
            {NEXT[t.status].map((s) => (
              <Button key={s} size="sm" variant="outline" loading={setStatus.isPending} onClick={() => setStatus.mutate(s)}>Mark {STATUS_LABEL[s].toLowerCase()}</Button>
            ))}
          </div>
        )}
      </div>

      <div style={{ padding: 16, display: "flex", flexDirection: "column", gap: 12, maxHeight: 380, overflowY: "auto" }}>
        {t.messages.map((m) => (
          <div key={m.id} style={{ background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 14px" }}>
            <div style={{ fontSize: 12, color: "var(--text-muted)", marginBottom: 4 }}>{m.user_email} · {m.created_at ? new Date(m.created_at).toLocaleString() : ""}</div>
            <div style={{ color: "var(--text-body)", whiteSpace: "pre-wrap" }}>{m.body}</div>
          </div>
        ))}
        {t.messages.length === 0 && <p style={{ color: "var(--text-muted)", fontSize: 13 }}>No messages yet.</p>}
      </div>

      {t.status !== "closed" && (
        <div style={{ display: "flex", gap: 8, padding: 14, borderTop: "1px solid var(--border)" }}>
          <Input placeholder="Write a reply…" value={body} onChange={(e) => setBody(e.target.value)} style={{ flex: 1 }}
            onKeyDown={(e) => { if (e.key === "Enter" && body.trim()) reply.mutate(); }} />
          <Button loading={reply.isPending} disabled={!body.trim()} onClick={() => reply.mutate()}>Send</Button>
        </div>
      )}
    </div>
  );
}

function NewTicket({ onDone, onCancel }: { onDone: (id: number) => void; onCancel: () => void }) {
  const [subject, setSubject] = useState("");
  const [priority, setPriority] = useState("normal");
  const [message, setMessage] = useState("");
  const create = useMutation({ mutationFn: () => hd.createTicket({ subject, priority, message }), onSuccess: (t) => onDone(t.id) });
  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 18, marginBottom: 18 }}>
      <div style={{ display: "grid", gridTemplateColumns: "2fr 1fr", gap: 12, marginBottom: 12 }}>
        <Field label="Subject"><Input value={subject} onChange={(e) => setSubject(e.target.value)} placeholder="Describe the issue" /></Field>
        <Field label="Priority">
          <select value={priority} onChange={(e) => setPriority(e.target.value)} style={{ width: "100%", height: 38, background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, color: "var(--text-strong)", padding: "0 8px" }}>
            <option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option>
          </select>
        </Field>
      </div>
      <Field label="First message"><Input value={message} onChange={(e) => setMessage(e.target.value)} placeholder="What happened?" /></Field>
      <div style={{ display: "flex", gap: 8, marginTop: 14 }}>
        <Button variant="outline" onClick={onCancel}>Cancel</Button>
        <Button loading={create.isPending} disabled={!subject.trim()} onClick={() => create.mutate()}>Create ticket</Button>
      </div>
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return <label style={{ display: "block" }}><span style={{ display: "block", fontSize: 12, color: "var(--text-muted)", marginBottom: 4 }}>{label}</span>{children}</label>;
}
