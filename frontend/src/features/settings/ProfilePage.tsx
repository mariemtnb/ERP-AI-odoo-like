import { useState } from "react";
import { useMutation } from "@tanstack/react-query";
import { PageHead } from "@/components/ui/page-head";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useAuth } from "@/features/auth/AuthContext";
import * as authApi from "@/api/auth";

function firstError(e: any, fallback: string): string {
  const d = e?.response?.data;
  if (typeof d?.detail === "string") return d.detail;
  const firstKey = d && Object.keys(d).find((k) => Array.isArray(d[k]));
  if (firstKey) return d[firstKey][0];
  return fallback;
}

export default function ProfilePage() {
  const { user, setUser } = useAuth();

  return (
    <div style={{ maxWidth: 640 }}>
      <PageHead title="My Account" sub="Update your name, sign-in email and password." />
      <NameCard user={user} onSaved={setUser} />
      <EmailCard user={user} onSaved={setUser} />
      <PasswordCard />
    </div>
  );
}

function Card({ title, desc, children }: { title: string; desc?: string; children: React.ReactNode }) {
  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 20, marginBottom: 16 }}>
      <div style={{ fontWeight: 600, color: "var(--text-strong)", fontSize: 15 }}>{title}</div>
      {desc && <div style={{ fontSize: 13, color: "var(--text-muted)", marginTop: 2, marginBottom: 12 }}>{desc}</div>}
      <div style={{ marginTop: desc ? 0 : 12 }}>{children}</div>
    </div>
  );
}
function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return <label style={{ display: "block", marginBottom: 12 }}><span style={{ display: "block", fontSize: 12, color: "var(--text-muted)", marginBottom: 4 }}>{label}</span>{children}</label>;
}
function Msg({ ok, text }: { ok?: boolean; text: string }) {
  return <p style={{ fontSize: 13, marginTop: 8, color: ok ? "var(--emerald-400)" : "var(--rose-400)" }}>{text}</p>;
}

function NameCard({ user, onSaved }: { user: any; onSaved: (u: any) => void }) {
  const [first, setFirst] = useState(user?.first_name ?? "");
  const [last, setLast] = useState(user?.last_name ?? "");
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const save = useMutation({
    mutationFn: () => authApi.updateProfile({ first_name: first, last_name: last }),
    onSuccess: (u) => { onSaved(u); setMsg({ ok: true, text: "Name updated." }); },
    onError: (e) => setMsg({ ok: false, text: firstError(e, "Could not update your name.") }),
  });
  return (
    <Card title="Name">
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
        <Field label="First name"><Input value={first} onChange={(e) => setFirst(e.target.value)} /></Field>
        <Field label="Last name"><Input value={last} onChange={(e) => setLast(e.target.value)} /></Field>
      </div>
      <Button loading={save.isPending} disabled={!first.trim()} onClick={() => save.mutate()}>Save name</Button>
      {msg && <Msg ok={msg.ok} text={msg.text} />}
    </Card>
  );
}

function EmailCard({ user, onSaved }: { user: any; onSaved: (u: any) => void }) {
  const [email, setEmail] = useState(user?.email ?? "");
  const [password, setPassword] = useState("");
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const save = useMutation({
    mutationFn: () => authApi.updateProfile({ email, current_password: password }),
    onSuccess: (u) => { onSaved(u); setPassword(""); setMsg({ ok: true, text: "Email updated." }); },
    onError: (e) => setMsg({ ok: false, text: firstError(e, "Could not update your email.") }),
  });
  const changed = email.trim() && email !== user?.email;
  return (
    <Card title="Sign-in email" desc="Changing your email requires your current password.">
      <Field label="Email"><Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} /></Field>
      {changed && <Field label="Current password"><Input type="password" value={password} onChange={(e) => setPassword(e.target.value)} /></Field>}
      <Button loading={save.isPending} disabled={!changed || !password} onClick={() => save.mutate()}>Update email</Button>
      {msg && <Msg ok={msg.ok} text={msg.text} />}
    </Card>
  );
}

function PasswordCard() {
  const [current, setCurrent] = useState("");
  const [next, setNext] = useState("");
  const [confirm, setConfirm] = useState("");
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const save = useMutation({
    mutationFn: () => authApi.changePassword(current, next),
    onSuccess: () => { setCurrent(""); setNext(""); setConfirm(""); setMsg({ ok: true, text: "Password changed." }); },
    onError: (e) => setMsg({ ok: false, text: firstError(e, "Could not change your password.") }),
  });
  const mismatch = next !== "" && confirm !== "" && next !== confirm;
  const ok = current && next.length >= 8 && next === confirm;
  return (
    <Card title="Password" desc="At least 8 characters.">
      <Field label="Current password"><Input type="password" value={current} onChange={(e) => setCurrent(e.target.value)} /></Field>
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
        <Field label="New password"><Input type="password" value={next} onChange={(e) => setNext(e.target.value)} /></Field>
        <Field label="Confirm new password"><Input type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} /></Field>
      </div>
      {mismatch && <Msg text="Passwords do not match." />}
      <Button loading={save.isPending} disabled={!ok} onClick={() => save.mutate()}>Change password</Button>
      {msg && <Msg ok={msg.ok} text={msg.text} />}
    </Card>
  );
}
