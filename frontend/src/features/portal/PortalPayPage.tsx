import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";

const API_BASE = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000/api/v1";
const green = "#0e7c5a";

interface Intent {
  amount: string;
  status: string;
  sale_number: string | null;
  sale_token: string | null;
}

/**
 * Sandbox checkout page. A real gateway would host this and redirect back; the
 * built-in provider shows it here so the flow is complete without real money.
 */
export default function PortalPayPage() {
  const { token } = useParams<{ token: string }>();
  const navigate = useNavigate();
  const [intent, setIntent] = useState<Intent | null>(null);
  const [state, setState] = useState<"loading" | "ok" | "error">("loading");
  const [paying, setPaying] = useState(false);

  useEffect(() => {
    fetch(`${API_BASE}/portal/pay/${token}`)
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then((d) => { setIntent(d); setState("ok"); })
      .catch(() => setState("error"));
  }, [token]);

  async function confirm() {
    setPaying(true);
    try {
      const r = await fetch(`${API_BASE}/portal/pay/${token}/confirm`, { method: "POST" });
      const d = await r.json();
      if (r.ok && d.sale_token) navigate(`/portal/sales/${d.sale_token}`);
      else { alert(d.detail ?? "Payment failed."); setPaying(false); }
    } catch { alert("Payment failed."); setPaying(false); }
  }

  return (
    <div style={styles.page}>
      <div style={styles.card}>
        {state === "loading" && <p style={styles.muted}>Loading…</p>}
        {state === "error" && <p style={styles.muted}>This payment link is invalid or has expired.</p>}
        {state === "ok" && intent && (
          <>
            <div style={styles.badge}>Sandbox payment</div>
            <h1 style={styles.h1}>Pay {Number(intent.amount).toFixed(3)} TND</h1>
            <p style={styles.muted}>for {intent.sale_number}</p>
            {intent.status === "paid" ? (
              <p style={{ ...styles.muted, marginTop: 20 }}>This payment is already complete.</p>
            ) : (
              <>
                <button style={styles.btn} disabled={paying} onClick={confirm}>
                  {paying ? "Processing…" : "Pay now"}
                </button>
                <p style={styles.note}>This is a demo gateway — no real card is charged.</p>
              </>
            )}
          </>
        )}
      </div>
    </div>
  );
}

const styles: Record<string, React.CSSProperties> = {
  page: { minHeight: "100dvh", background: "#f5f8f6", display: "grid", placeItems: "center", padding: 16, fontFamily: "system-ui, Arial, sans-serif", color: "#13211c" },
  card: { width: 400, maxWidth: "100%", background: "#fff", border: "1px solid #e2e9e5", borderRadius: 16, padding: 32, textAlign: "center", boxShadow: "0 10px 40px -24px rgba(0,0,0,.3)" },
  badge: { display: "inline-block", background: "#f0f5f2", color: "#58665f", borderRadius: 999, padding: "4px 12px", fontSize: 11, textTransform: "uppercase", letterSpacing: ".08em", marginBottom: 16 },
  h1: { fontSize: 26, margin: "0 0 4px", color: green },
  muted: { color: "#58665f", fontSize: 14, margin: 0 },
  btn: { marginTop: 24, width: "100%", background: green, color: "#fff", border: "none", borderRadius: 10, padding: "13px 0", fontSize: 16, fontWeight: 600, cursor: "pointer" },
  note: { marginTop: 12, fontSize: 12, color: "#8695" + "8e" },
};
