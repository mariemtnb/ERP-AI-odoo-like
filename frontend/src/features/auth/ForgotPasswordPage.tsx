import { useState, type FormEvent } from "react";
import { Link } from "react-router-dom";
import { useMutation } from "@tanstack/react-query";
import { AuthShell } from "./AuthShell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { forgotPassword } from "@/api/auth";
import { useI18n } from "@/lib/i18n";

export default function ForgotPasswordPage() {
  const { t } = useI18n();
  const [email, setEmail] = useState("");
  const [sent, setSent] = useState(false);
  const [devToken, setDevToken] = useState<string | null>(null);

  const req = useMutation({
    mutationFn: () => forgotPassword(email.trim()),
    onSuccess: (d) => { setSent(true); setDevToken(d.dev_token); },
  });

  function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (email.trim()) req.mutate();
  }

  return (
    <AuthShell>
      <h2 style={{ margin: 0, font: "600 26px/1 var(--font-sans)", letterSpacing: "-0.02em", color: "var(--text-strong)" }}>
        {t("auth.resetTitle")}
      </h2>
      <p style={{ margin: "8px 0 28px", font: "400 14px/1.5 var(--font-sans)", color: "var(--text-muted)" }}>
        {t("auth.resetSub")}
      </p>

      {!sent ? (
        <form onSubmit={onSubmit} className="flex flex-col gap-4">
          <div className="flex flex-col gap-2">
            <Label htmlFor="email">{t("field.email")}</Label>
            <Input id="email" type="email" placeholder="you@company.com" value={email} onChange={(e) => setEmail(e.target.value)} required />
          </div>
          <Button type="submit" size="lg" className="mt-1.5 w-full" loading={req.isPending}>{t("auth.sendLink")}</Button>
        </form>
      ) : (
        <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 12, padding: 16, fontSize: 14, color: "var(--text-body)" }}>
          {t("auth.sentIfExists")} <b>{email}</b>, {t("auth.sentSuffix")}
          {devToken && (
            <div style={{ marginTop: 12, paddingTop: 12, borderTop: "1px solid var(--border)" }}>
              <div style={{ fontSize: 12, color: "var(--amber-400,#d99a2b)", marginBottom: 6 }}>
                {t("auth.devMode")}
              </div>
              <Link
                to={`/reset-password?email=${encodeURIComponent(email)}&token=${encodeURIComponent(devToken)}`}
                style={{ color: "var(--emerald-400)", fontWeight: 600, wordBreak: "break-all" }}
              >
                {t("auth.resetMyPw")}
              </Link>
            </div>
          )}
        </div>
      )}

      <p style={{ margin: "22px 0 0", font: "400 14px/1 var(--font-sans)", color: "var(--text-muted)" }}>
        <Link to="/login" style={{ color: "var(--emerald-400)", fontWeight: 600 }}>{t("auth.backToSignIn")}</Link>
      </p>
    </AuthShell>
  );
}
