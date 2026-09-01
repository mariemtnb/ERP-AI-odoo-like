import { useState, type FormEvent } from "react";
import { Link, useNavigate } from "react-router-dom";
import { ArrowRight } from "lucide-react";
import { useAuth } from "./AuthContext";
import { AuthShell } from "./AuthShell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useI18n } from "@/lib/i18n";

export default function LoginPage() {
  const { login } = useAuth();
  const { t } = useI18n();
  const navigate = useNavigate();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError("");
    setBusy(true);
    try {
      await login(email, password);
      navigate("/");
    } catch (err: any) {
      setError(
        err?.response?.status === 401
          ? t("auth.invalidCreds")
          : err?.response?.status === 429
            ? t("auth.tooMany")
            : t("auth.unable")
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <AuthShell>
      <h2
        className="font-semibold"
        style={{ margin: 0, font: "600 26px/1 var(--font-sans)", letterSpacing: "-0.02em", color: "var(--text-strong)" }}
      >
        {t("auth.signIn")}
      </h2>
      <p style={{ margin: "8px 0 28px", font: "400 14px/1 var(--font-sans)", color: "var(--text-muted)" }}>
        {t("auth.welcomeBack")}
      </p>

      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <div className="flex flex-col gap-2">
          <Label htmlFor="email">{t("field.email")}</Label>
          <Input
            id="email"
            type="email"
            autoComplete="email"
            placeholder="you@company.com"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
        </div>
        <div className="flex flex-col gap-2">
          <div className="flex items-center justify-between">
            <Label htmlFor="password">{t("field.password")}</Label>
            <Link to="/forgot-password" style={{ font: "500 12px/1 var(--font-sans)", color: "var(--emerald-400)" }}>
              {t("auth.forgot")}
            </Link>
          </div>
          <Input
            id="password"
            type="password"
            autoComplete="current-password"
            placeholder="••••••••"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </div>
        {error && <p style={{ font: "500 13px/1.4 var(--font-sans)", color: "var(--rose-400)" }}>{error}</p>}
        <Button type="submit" size="lg" className="mt-1.5 w-full" loading={busy}>
          {busy ? t("auth.signingIn") : t("auth.signIn")}
          {!busy && <ArrowRight size={16} />}
        </Button>
      </form>

      <p style={{ margin: "22px 0 0", font: "400 14px/1 var(--font-sans)", color: "var(--text-muted)" }}>
        {t("auth.newHere")}{" "}
        <Link to="/signup" style={{ color: "var(--emerald-400)", fontWeight: 600 }}>
          {t("auth.createAccount")}
        </Link>
      </p>
      <p style={{ margin: "16px 0 0", font: "400 12px/1.5 var(--font-mono)", color: "var(--text-faint)" }}>
        Demo · admin@erp.local / Admin123!
      </p>
    </AuthShell>
  );
}
