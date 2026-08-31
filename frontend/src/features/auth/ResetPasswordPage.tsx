import { useState, type FormEvent } from "react";
import { Link, useNavigate, useSearchParams } from "react-router-dom";
import { useMutation } from "@tanstack/react-query";
import { AuthShell } from "./AuthShell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { resetPassword } from "@/api/auth";

export default function ResetPasswordPage() {
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const email = params.get("email") ?? "";
  const token = params.get("token") ?? "";
  const [pw, setPw] = useState("");
  const [confirm, setConfirm] = useState("");
  const [error, setError] = useState("");
  const [done, setDone] = useState(false);

  const reset = useMutation({
    mutationFn: () => resetPassword(email, token, pw),
    onSuccess: () => { setDone(true); setTimeout(() => navigate("/login"), 2200); },
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not reset your password."),
  });

  function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError("");
    if (pw.length < 8) return setError("Password must be at least 8 characters.");
    if (pw !== confirm) return setError("Passwords do not match.");
    reset.mutate();
  }

  const invalidLink = !email || !token;

  return (
    <AuthShell>
      <h2 style={{ margin: 0, font: "600 26px/1 var(--font-sans)", letterSpacing: "-0.02em", color: "var(--text-strong)" }}>
        Set a new password
      </h2>
      {invalidLink ? (
        <>
          <p style={{ margin: "12px 0 20px", color: "var(--rose-400)", fontSize: 14 }}>
            This reset link is incomplete or invalid.
          </p>
          <Link to="/forgot-password" style={{ color: "var(--emerald-400)", fontWeight: 600 }}>Request a new link</Link>
        </>
      ) : done ? (
        <p style={{ margin: "16px 0", color: "var(--emerald-400)", fontSize: 15 }}>
          Password reset. Redirecting you to sign in…
        </p>
      ) : (
        <>
          <p style={{ margin: "8px 0 24px", font: "400 14px/1.5 var(--font-sans)", color: "var(--text-muted)" }}>
            for <b style={{ color: "var(--text-body)" }}>{email}</b>
          </p>
          <form onSubmit={onSubmit} className="flex flex-col gap-4">
            <div className="flex flex-col gap-2">
              <Label htmlFor="pw">New password</Label>
              <Input id="pw" type="password" value={pw} onChange={(e) => setPw(e.target.value)} required />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="c">Confirm new password</Label>
              <Input id="c" type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} required />
            </div>
            {error && <p style={{ font: "500 13px var(--font-sans)", color: "var(--rose-400)" }}>{error}</p>}
            <Button type="submit" size="lg" className="mt-1.5 w-full" loading={reset.isPending}>Reset password</Button>
          </form>
          <p style={{ margin: "22px 0 0", font: "400 14px/1 var(--font-sans)", color: "var(--text-muted)" }}>
            <Link to="/login" style={{ color: "var(--emerald-400)", fontWeight: 600 }}>← Back to sign in</Link>
          </p>
        </>
      )}
    </AuthShell>
  );
}
