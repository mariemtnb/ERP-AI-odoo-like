import { useState, type FormEvent } from "react";
import { Link, useNavigate } from "react-router-dom";
import { ArrowRight } from "lucide-react";
import { useAuth } from "./AuthContext";
import { AuthShell } from "./AuthShell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

export default function SignupPage() {
  const { register } = useAuth();
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
      setError("Password must be at least 8 characters.");
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
          ? "That email is already registered."
          : data
            ? Object.values(data.errors ?? { d: [data.detail] }).flat().join(" ")
            : "Unable to create your account. Is the server running?"
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
        Create your account
      </h2>
      <p style={{ margin: "8px 0 28px", font: "400 14px/1 var(--font-sans)", color: "var(--text-muted)" }}>
        Start with a workspace account in seconds.
      </p>

      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <div className="grid grid-cols-2 gap-3">
          <div className="flex flex-col gap-2">
            <Label htmlFor="first_name">First name</Label>
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
            <Label htmlFor="last_name">Last name</Label>
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
          <Label htmlFor="email">Email</Label>
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
          <Label htmlFor="password">Password</Label>
          <Input
            id="password"
            type="password"
            autoComplete="new-password"
            placeholder="At least 8 characters"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </div>
        {error && <p style={{ font: "500 13px/1.4 var(--font-sans)", color: "var(--rose-400)" }}>{error}</p>}
        <Button type="submit" size="lg" className="mt-1.5 w-full" loading={busy}>
          {busy ? "Creating account…" : "Create account"}
          {!busy && <ArrowRight size={16} />}
        </Button>
      </form>

      <p style={{ margin: "22px 0 0", font: "400 14px/1 var(--font-sans)", color: "var(--text-muted)" }}>
        Already have an account?{" "}
        <Link to="/login" style={{ color: "var(--emerald-400)", fontWeight: 600 }}>
          Sign in
        </Link>
      </p>
    </AuthShell>
  );
}
