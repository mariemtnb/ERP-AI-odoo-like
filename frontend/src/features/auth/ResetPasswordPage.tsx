import { useState, type FormEvent } from "react";
import { Link, useNavigate, useSearchParams } from "react-router-dom";
import { useMutation } from "@tanstack/react-query";
import { AuthShell } from "./AuthShell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { resetPassword } from "@/api/auth";
import { useI18n } from "@/lib/i18n";

export default function ResetPasswordPage() {
  const { t } = useI18n();
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
    onError: (e: any) => setError(e?.response?.data?.detail ?? t("auth.couldNotReset")),
  });

  function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError("");
    if (pw.length < 8) return setError(t("auth.min8"));
    if (pw !== confirm) return setError(t("auth.mismatch"));
    reset.mutate();
  }

  const invalidLink = !email || !token;

  return (
    <AuthShell>
      <h2 style={{ margin: 0, font: "600 26px/1 var(--font-sans)", letterSpacing: "-0.02em", color: "var(--text-strong)" }}>
        {t("auth.setNewTitle")}
      </h2>
      {invalidLink ? (
        <>
          <p style={{ margin: "12px 0 20px", color: "var(--rose-400)", fontSize: 14 }}>
            {t("auth.invalidLink")}
          </p>
          <Link to="/forgot-password" style={{ color: "var(--emerald-400)", fontWeight: 600 }}>{t("auth.requestNew")}</Link>
        </>
      ) : done ? (
        <p style={{ margin: "16px 0", color: "var(--emerald-400)", fontSize: 15 }}>
          {t("auth.doneMsg")}
        </p>
      ) : (
        <>
          <p style={{ margin: "8px 0 24px", font: "400 14px/1.5 var(--font-sans)", color: "var(--text-muted)" }}>
            {t("auth.forLabel")} <b style={{ color: "var(--text-body)" }}>{email}</b>
          </p>
          <form onSubmit={onSubmit} className="flex flex-col gap-4">
            <div className="flex flex-col gap-2">
              <Label htmlFor="pw">{t("auth.newPassword")}</Label>
              <Input id="pw" type="password" value={pw} onChange={(e) => setPw(e.target.value)} required />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="c">{t("auth.confirmPassword")}</Label>
              <Input id="c" type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} required />
            </div>
            {error && <p style={{ font: "500 13px var(--font-sans)", color: "var(--rose-400)" }}>{error}</p>}
            <Button type="submit" size="lg" className="mt-1.5 w-full" loading={reset.isPending}>{t("auth.resetBtn")}</Button>
          </form>
          <p style={{ margin: "22px 0 0", font: "400 14px/1 var(--font-sans)", color: "var(--text-muted)" }}>
            <Link to="/login" style={{ color: "var(--emerald-400)", fontWeight: 600 }}>{t("auth.backToSignIn")}</Link>
          </p>
        </>
      )}
    </AuthShell>
  );
}
