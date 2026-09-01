import { useState, type FormEvent } from "react";
import { Link, useNavigate } from "react-router-dom";
import { ArrowRight } from "lucide-react";
import { useAuth } from "./AuthContext";
import { AuthShell } from "./AuthShell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useI18n } from "@/lib/i18n";

export default function SignupPage() {
  const { register } = useAuth();
  const { t } = useI18n();
  const navigate = useNavigate();
  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError("");
    if (password.length < 8) {
      setError(t("auth.min8"));
      return;
    }
    setBusy(true);
    try {
      await register({
        first_name: firstName,
        last_name: lastName,
        email,
        password,
      });
      navigate("/");
    } catch (err: any) {
      const data = err?.response?.data;
      setError(
        data?.errors?.email
          ? t("auth.emailTaken")
          : data
            ? Object.values(data.errors ?? { d: [data.detail] }).flat().join(" ")
            : t("auth.unableCreate")
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
        {t("auth.createTitle")}
      </h2>
      <p style={{ margin: "8px 0 28px", font: "400 14px/1 var(--font-sans)", color: "var(--text-muted)" }}>
        {t("auth.createSub")}
      </p>

      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <div className="grid grid-cols-2 gap-3">
          <div className="flex flex-col gap-2">
            <Label htmlFor="first_name">{t("field.firstName")}</Label>
            <Input
              id="first_name"
              autoComplete="given-name"
              placeholder="Amine"
              value={firstName}
              onChange={(e) => setFirstName(e.target.value)}
              required
            />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="last_name">{t("field.lastName")}</Label>
            <Input
              id="last_name"
              autoComplete="family-name"
              placeholder="Khelifi"
              value={lastName}
              onChange={(e) => setLastName(e.target.value)}
            />
          </div>
        </div>
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
          <Label htmlFor="password">{t("field.password")}</Label>
          <Input
            id="password"
            type="password"
            autoComplete="new-password"
            placeholder={t("auth.min8Placeholder")}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </div>
        {error && <p style={{ font: "500 13px/1.4 var(--font-sans)", color: "var(--rose-400)" }}>{error}</p>}
        <Button type="submit" size="lg" className="mt-1.5 w-full" loading={busy}>
          {busy ? t("auth.creating") : t("auth.createBtn")}
          {!busy && <ArrowRight size={16} />}
        </Button>
      </form>

      <p style={{ margin: "22px 0 0", font: "400 14px/1 var(--font-sans)", color: "var(--text-muted)" }}>
        {t("auth.haveAccount")}{" "}
        <Link to="/login" style={{ color: "var(--emerald-400)", fontWeight: 600 }}>
          {t("auth.signIn")}
        </Link>
      </p>
    </AuthShell>
  );
}
