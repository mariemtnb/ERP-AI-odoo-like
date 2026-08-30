import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { PageHead } from "@/components/ui/page-head";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import * as mk from "@/api/marketing";

export default function MarketingPage() {
  const qc = useQueryClient();
  const listQ = useQuery({ queryKey: ["campaigns"], queryFn: mk.listCampaigns });
  const [selected, setSelected] = useState<number | null>(null);
  const [creating, setCreating] = useState(false);

  return (
    <div>
      <PageHead title="Marketing" sub="Email and SMS campaigns to your customers.">
        <Button onClick={() => setCreating((v) => !v)}>{creating ? "Close" : "New campaign"}</Button>
      </PageHead>

      <div style={{ background: "color-mix(in oklab, #d99a2b 12%, transparent)", border: "1px solid #d99a2b", borderRadius: 12, padding: "10px 16px", marginBottom: 16, fontSize: 13, color: "var(--text-body)" }}>
        No email/SMS provider is connected yet — sending records the audience and logs each message, but nothing leaves the server. Wire a provider into <code>MarketingService::deliver()</code> to go live.
      </div>

      {creating && <NewCampaign onDone={(id) => { setCreating(false); qc.invalidateQueries({ queryKey: ["campaigns"] }); setSelected(id); }} onCancel={() => setCreating(false)} />}

      <div style={{ display: "grid", gridTemplateColumns: "320px 1fr", gap: 18, alignItems: "start" }}>
        <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, overflow: "hidden" }}>
          {(listQ.data ?? []).map((c) => (
            <button key={c.id} onClick={() => setSelected(c.id)} style={{
              display: "block", width: "100%", textAlign: "left", padding: "12px 14px", cursor: "pointer",
              background: selected === c.id ? "color-mix(in oklab, var(--emerald-500) 12%, transparent)" : "transparent",
              border: 0, borderBottom: "1px solid var(--border)", color: "var(--text-strong)",
            }}>
              <div style={{ fontWeight: 600 }}>{c.name}</div>
              <div style={{ fontSize: 12, color: "var(--text-muted)", textTransform: "uppercase" }}>{c.channel} · {c.status}{c.status === "sent" ? ` · ${c.sent_count} sent` : ""}</div>
            </button>
          ))}
          {listQ.data?.length === 0 && <p style={{ padding: 14, color: "var(--text-muted)", fontSize: 13 }}>No campaigns yet.</p>}
        </div>

        <div>{selected ? <CampaignDetail campaignId={selected} /> : <p style={{ color: "var(--text-muted)" }}>Select a campaign.</p>}</div>
      </div>
    </div>
  );
}

function CampaignDetail({ campaignId }: { campaignId: number }) {
  const qc = useQueryClient();
  const q = useQuery({ queryKey: ["campaign", campaignId], queryFn: () => mk.getCampaign(campaignId) });
  const send = useMutation({
    mutationFn: () => mk.sendCampaign(campaignId),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["campaign", campaignId] }); qc.invalidateQueries({ queryKey: ["campaigns"] }); },
  });
  const c = q.data;
  if (!c) return <p style={{ color: "var(--text-muted)" }}>Loading…</p>;
  const draft = c.status === "draft";

  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 20 }}>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "start", marginBottom: 12 }}>
        <div>
          <div style={{ fontWeight: 700, fontSize: 18, color: "var(--text-strong)" }}>{c.name}</div>
          <div style={{ fontSize: 12, color: "var(--text-muted)", textTransform: "uppercase" }}>{c.channel} · {c.status}</div>
        </div>
        {draft
          ? <Button loading={send.isPending} onClick={() => send.mutate()}>Send to {c.audience_size} customer{c.audience_size !== 1 ? "s" : ""}</Button>
          : <span style={{ fontSize: 13, color: "var(--emerald-400)", fontWeight: 600 }}>Sent to {c.sent_count} · {c.sent_at ? new Date(c.sent_at).toLocaleString() : ""}</span>}
      </div>

      {c.channel === "email" && c.subject && <div style={{ marginBottom: 8, color: "var(--text-body)" }}><span style={{ color: "var(--text-muted)" }}>Subject:</span> {c.subject}</div>}
      <div style={{ background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 10, padding: 14, whiteSpace: "pre-wrap", color: "var(--text-body)" }}>{c.body}</div>

      {c.recipients.length > 0 && (
        <>
          <h3 style={{ font: "600 14px var(--font-sans)", color: "var(--text-strong)", margin: "18px 0 8px" }}>Recipients ({c.recipients.length})</h3>
          <div style={{ maxHeight: 220, overflowY: "auto", border: "1px solid var(--border)", borderRadius: 10 }}>
            <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13 }}>
              <tbody>
                {c.recipients.map((r, i) => (
                  <tr key={i} style={{ borderTop: i ? "1px solid var(--border)" : undefined }}>
                    <td style={{ padding: "8px 12px", color: "var(--text-body)" }}>{r.customer_name}</td>
                    <td style={{ padding: "8px 12px", fontFamily: "var(--font-mono)", color: "var(--text-muted)" }}>{r.contact}</td>
                    <td style={{ padding: "8px 12px", textAlign: "right", color: "var(--emerald-400)" }}>{r.status}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}

function NewCampaign({ onDone, onCancel }: { onDone: (id: number) => void; onCancel: () => void }) {
  const [name, setName] = useState("");
  const [channel, setChannel] = useState("email");
  const [subject, setSubject] = useState("");
  const [body, setBody] = useState("");
  const [error, setError] = useState<string | null>(null);
  const add = useMutation({
    mutationFn: () => mk.createCampaign({ name, channel, subject, body }),
    onSuccess: (c) => onDone(c.id),
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not create the campaign."),
  });
  const ok = name.trim() && body.trim();
  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 18, marginBottom: 18 }}>
      <div style={{ display: "grid", gridTemplateColumns: "2fr 1fr", gap: 12, marginBottom: 12 }}>
        <Field label="Campaign name"><Input value={name} onChange={(e) => setName(e.target.value)} placeholder="Eid promotion" /></Field>
        <Field label="Channel">
          <select value={channel} onChange={(e) => setChannel(e.target.value)} style={{ width: "100%", height: 38, background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, color: "var(--text-strong)", padding: "0 8px" }}>
            <option value="email">Email</option><option value="sms">SMS</option>
          </select>
        </Field>
      </div>
      {channel === "email" && <Field label="Subject"><Input value={subject} onChange={(e) => setSubject(e.target.value)} placeholder="Big Eid discounts" /></Field>}
      <div style={{ marginTop: 12 }}>
        <span style={{ display: "block", fontSize: 12, color: "var(--text-muted)", marginBottom: 4 }}>Message</span>
        <textarea value={body} onChange={(e) => setBody(e.target.value)} rows={4} placeholder="Write your message…"
          style={{ width: "100%", background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, color: "var(--text-strong)", padding: "10px 12px", resize: "vertical", fontFamily: "inherit" }} />
      </div>
      <div style={{ display: "flex", gap: 8, marginTop: 14 }}>
        <Button variant="outline" onClick={onCancel}>Cancel</Button>
        <Button loading={add.isPending} disabled={!ok} onClick={() => add.mutate()}>Create draft</Button>
      </div>
      {error && <p style={{ color: "var(--rose-400)", fontSize: 13, marginTop: 8 }}>{error}</p>}
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return <label style={{ display: "block" }}><span style={{ display: "block", fontSize: 12, color: "var(--text-muted)", marginBottom: 4 }}>{label}</span>{children}</label>;
}
