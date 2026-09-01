import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";

const API_BASE = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000/api/v1";

interface PortalLine {
  product_name: string | null;
  quantity: string;
  unit_price: string;
  discount_pct?: string;
  subtotal: string;
}
interface PortalSale {
  number: string;
  status: string;
  sale_date: string | null;
  total_amount: string;
  is_invoice: boolean;
  invoice_number: string | null;
  invoice_date: string | null;
  customer: { name: string | null; email: string | null; address: string | null };
  company: { name: string };
  paid_online: boolean;
  lines: PortalLine[];
}

/** Public, no-login view of a sale shared with the customer via a token. */
export default function PortalSalePage() {
  const { token } = useParams<{ token: string }>();
  const [sale, setSale] = useState<PortalSale | null>(null);
  const [state, setState] = useState<"loading" | "ok" | "error">("loading");
  const [paying, setPaying] = useState(false);

  useEffect(() => {
    fetch(`${API_BASE}/portal/sales/${token}`)
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then((d) => { setSale(d); setState("ok"); })
      .catch(() => setState("error"));
  }, [token]);

  async function pay() {
    setPaying(true);
    try {
      const r = await fetch(`${API_BASE}/portal/sales/${token}/pay`, { method: "POST" });
      const d = await r.json();
      if (r.ok && d.checkout_url) {
        window.location.href = d.checkout_url;
      } else {
        alert(d.detail ?? "Could not start the payment.");
        setPaying(false);
      }
    } catch {
      alert("Could not start the payment.");
      setPaying(false);
    }
  }

  return (
    <div style={styles.page}>
      <div style={styles.card}>
        {state === "loading" && <p style={styles.muted}>Loading…</p>}
        {state === "error" && (
          <div style={{ textAlign: "center", padding: "40px 0" }}>
            <h1 style={styles.h1}>Document not found</h1>
            <p style={styles.muted}>This link is invalid or has expired. Please ask the sender for a new one.</p>
          </div>
        )}
        {state === "ok" && sale && (
          <>
            <div style={styles.head}>
              <div>
                <div style={styles.brand}>{sale.company.name}</div>
                <div style={styles.docType}>{sale.is_invoice ? "Invoice" : "Quote"}</div>
              </div>
              <div style={{ textAlign: "right" }}>
                <div style={styles.number}>{sale.is_invoice ? sale.invoice_number : sale.number}</div>
                <div style={styles.muted}>{sale.is_invoice ? sale.invoice_date : sale.sale_date}</div>
              </div>
            </div>

            <div style={styles.billTo}>
              <div style={styles.label}>Billed to</div>
              <div style={{ fontWeight: 600 }}>{sale.customer.name}</div>
              {sale.customer.address && <div style={styles.muted}>{sale.customer.address}</div>}
              {sale.customer.email && <div style={styles.muted}>{sale.customer.email}</div>}
            </div>

            <table style={styles.table}>
              <thead>
                <tr>
                  <th style={styles.th}>Item</th>
                  <th style={{ ...styles.th, textAlign: "right" }}>Qty</th>
                  <th style={{ ...styles.th, textAlign: "right" }}>Unit</th>
                  <th style={{ ...styles.th, textAlign: "right" }}>Disc.</th>
                  <th style={{ ...styles.th, textAlign: "right" }}>Subtotal</th>
                </tr>
              </thead>
              <tbody>
                {sale.lines.map((l, i) => (
                  <tr key={i}>
                    <td style={styles.td}>{l.product_name}</td>
                    <td style={{ ...styles.td, textAlign: "right" }}>{Number(l.quantity)}</td>
                    <td style={{ ...styles.td, textAlign: "right" }}>{Number(l.unit_price).toFixed(2)}</td>
                    <td style={{ ...styles.td, textAlign: "right" }}>{Number(l.discount_pct ?? 0) > 0 ? `${Number(l.discount_pct)}%` : "—"}</td>
                    <td style={{ ...styles.td, textAlign: "right" }}>{Number(l.subtotal).toFixed(2)}</td>
                  </tr>
                ))}
              </tbody>
            </table>

            <div style={styles.totalRow}>
              <span style={styles.label}>Total</span>
              <span style={styles.total}>{Number(sale.total_amount).toFixed(3)} TND</span>
            </div>

            {sale.paid_online ? (
              <div style={styles.paidBadge}>✓ Paid</div>
            ) : Number(sale.total_amount) > 0 ? (
              <button style={styles.payBtn} disabled={paying} onClick={pay}>
                {paying ? "Redirecting…" : "Pay online"}
              </button>
            ) : null}

            <p style={styles.footer}>Thank you for your business.</p>
          </>
        )}
      </div>
    </div>
  );
}

const green = "#0e7c5a";
const styles: Record<string, React.CSSProperties> = {
  page: { minHeight: "100dvh", background: "#f5f8f6", display: "grid", placeItems: "start center", padding: "40px 16px", fontFamily: "system-ui, Arial, sans-serif", color: "#13211c" },
  card: { width: 640, maxWidth: "100%", background: "#fff", border: "1px solid #e2e9e5", borderRadius: 16, padding: 32, boxShadow: "0 10px 40px -24px rgba(0,0,0,.3)" },
  head: { display: "flex", justifyContent: "space-between", alignItems: "flex-start", borderBottom: `2px solid ${green}`, paddingBottom: 16, marginBottom: 20 },
  brand: { fontSize: 20, fontWeight: 700, color: green },
  docType: { fontSize: 13, textTransform: "uppercase", letterSpacing: ".08em", color: "#58665f", marginTop: 2 },
  number: { fontSize: 18, fontWeight: 700 },
  billTo: { marginBottom: 20 },
  label: { fontSize: 11, textTransform: "uppercase", letterSpacing: ".08em", color: "#8695" + "8e", marginBottom: 4 },
  table: { width: "100%", borderCollapse: "collapse", fontSize: 14 },
  th: { textAlign: "left", padding: "8px 6px", borderBottom: "1px solid #e2e9e5", fontSize: 12, color: "#58665f", textTransform: "uppercase", letterSpacing: ".04em" },
  td: { padding: "8px 6px", borderBottom: "1px solid #eff5f1" },
  totalRow: { display: "flex", justifyContent: "space-between", alignItems: "baseline", marginTop: 18, paddingTop: 12, borderTop: `2px solid ${green}` },
  total: { fontSize: 22, fontWeight: 700, color: green },
  muted: { color: "#58665f", fontSize: 13 },
  h1: { fontSize: 22, margin: "0 0 8px" },
  footer: { marginTop: 24, textAlign: "center", color: "#8695" + "8e", fontSize: 13 },
  payBtn: { marginTop: 20, width: "100%", background: green, color: "#fff", border: "none", borderRadius: 10, padding: "13px 0", fontSize: 16, fontWeight: 600, cursor: "pointer" },
  paidBadge: { marginTop: 20, textAlign: "center", background: "#dcece4", color: "#0a5c43", borderRadius: 10, padding: "12px 0", fontSize: 16, fontWeight: 700 },
};
