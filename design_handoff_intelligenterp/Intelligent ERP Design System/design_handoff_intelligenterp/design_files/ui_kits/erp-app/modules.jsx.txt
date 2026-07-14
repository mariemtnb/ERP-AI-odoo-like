// Additional ERP modules: Customers/Suppliers (Partners), Purchases/Sales (Documents), Reports, Users.
const { Icon, Btn, Badge, PageHead } = window.ERP_UI;
const MD = window.ERP_DATA;

const DOC_STATUS = { draft: "neutral", pending_approval: "amber", confirmed: "sky", received: "emerald", cancelled: "rose" };
const ROLE_TONE = { admin: "violet", manager: "sky", employee: "neutral" };

function Toolbar({ children }) {
  return <div style={{ display: "flex", gap: 12, marginBottom: 18, flexWrap: "wrap", alignItems: "center" }}>{children}</div>;
}
function SearchField({ value, onChange, placeholder }) {
  return (
    <div style={{ position: "relative", maxWidth: 320, width: "100%" }}>
      <span style={{ position: "absolute", left: 12, top: "50%", transform: "translateY(-50%)", color: "var(--text-faint)", display: "flex" }}><Icon name="search" size={16} /></span>
      <input className="erp-field" style={{ paddingLeft: 38 }} placeholder={placeholder} value={value} onChange={(e) => onChange(e.target.value)} />
    </div>
  );
}
function Sheet({ children }) {
  return <div style={{ width: "100%", overflow: "hidden", borderRadius: "var(--radius-lg)", border: "1px solid var(--border-subtle)", background: "var(--surface-card)", boxShadow: "var(--shadow-sm), var(--hairline)" }}><table className="erp-table">{children}</table></div>;
}
function Money({ children, strong }) {
  return <span style={{ fontFamily: "var(--font-mono)", fontVariantNumeric: "tabular-nums", color: strong ? "var(--text-strong)" : "var(--text-body)" }}>{children}</span>;
}

// ---------- Customers / Suppliers ----------
function Partners({ kind }) {
  const isCust = kind === "customers";
  const list = isCust ? MD.customers : MD.suppliers;
  const [q, setQ] = React.useState("");
  const rows = list.filter((p) => (p.name + p.email + p.phone).toLowerCase().includes(q.toLowerCase()));
  const single = isCust ? "customer" : "supplier";
  return (
    <div>
      <PageHead title={isCust ? "Customers" : "Suppliers"} sub={`${list.length} ${single}s · ${list.filter((p) => p.active).length} active`}>
        <Btn variant="primary" size="md" icon="plus">New {single}</Btn>
      </PageHead>
      <Toolbar><SearchField value={q} onChange={setQ} placeholder="Search by name, email or phone…" /></Toolbar>
      <Sheet>
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>City</th><th style={{ textAlign: "right" }}>{isCust ? "Orders" : "POs"}</th><th>Status</th><th></th></tr></thead>
        <tbody>
          {rows.map((p) => (
            <tr key={p.id} style={{ opacity: p.active ? 1 : 0.5 }}>
              <td>
                <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                  <span style={{ width: 30, height: 30, borderRadius: 999, background: "var(--surface-hover)", display: "grid", placeItems: "center", font: "600 12px/1 var(--font-sans)", color: "var(--text-muted)", flex: "none" }}>{p.name.split(" ").map((w) => w[0]).slice(0, 2).join("")}</span>
                  <span style={{ color: "var(--text-strong)", fontWeight: 500 }}>{p.name}</span>
                </div>
              </td>
              <td style={{ color: "var(--text-muted)" }}>{p.email || "—"}</td>
              <td style={{ color: "var(--text-muted)", fontFamily: "var(--font-mono)", fontSize: 13, whiteSpace: "nowrap" }}>{p.phone}</td>
              <td style={{ color: "var(--text-muted)" }}>{p.city}</td>
              <td style={{ textAlign: "right", fontFamily: "var(--font-mono)" }}>{p.orders}</td>
              <td><Badge tone={p.active ? "emerald" : "neutral"} dot>{p.active ? "active" : "inactive"}</Badge></td>
              <td style={{ textAlign: "right" }}>
                <span style={{ display: "inline-flex", gap: 2 }}>
                  <button className="erp-iconbtn erp-iconbtn--sm" aria-label="edit"><Icon name="pencil" size={15} /></button>
                  <button className="erp-iconbtn erp-iconbtn--sm" aria-label="deactivate"><Icon name="trash-2" size={15} /></button>
                </span>
              </td>
            </tr>
          ))}
        </tbody>
      </Sheet>
    </div>
  );
}

// ---------- Purchases / Sales ----------
function Documents({ kind }) {
  const isPur = kind === "purchases";
  const list = isPur ? MD.purchases : MD.sales;
  const [open, setOpen] = React.useState(null);
  const [status, setStatus] = React.useState("all");
  const rows = list.filter((d) => status === "all" || d.status === status);
  const partnerLabel = isPur ? "Supplier" : "Customer";
  return (
    <div>
      <PageHead title={isPur ? "Purchases" : "Sales"} sub={`${list.length} ${isPur ? "purchase orders" : "sales orders"}`}>
        {isPur && <Btn variant="outline" size="md" icon="scan-line">Import from invoice</Btn>}
        <Btn variant="primary" size="md" icon="plus">New {isPur ? "purchase order" : "sale"}</Btn>
      </PageHead>
      <Toolbar>
        <div style={{ display: "flex", gap: 3, padding: 3, background: "var(--surface-inset)", border: "1px solid var(--border)", borderRadius: 999 }}>
          {["all", "draft", "pending_approval", "confirmed", "received", "cancelled"].map((s) => (
            <button key={s} onClick={() => setStatus(s)} style={{ height: 28, padding: "0 12px", borderRadius: 999, border: "none", cursor: "pointer", whiteSpace: "nowrap",
              font: "600 12px/1 var(--font-sans)", background: status === s ? "var(--surface-hover)" : "transparent", color: status === s ? "var(--text-strong)" : "var(--text-muted)", transition: "all var(--dur-1)" }}>{s === "pending_approval" ? "pending" : s}</button>
          ))}
        </div>
      </Toolbar>
      <Sheet>
        <thead><tr><th>Number</th><th>{partnerLabel}</th><th>Date</th><th>Status</th><th style={{ textAlign: "right" }}>Total</th><th>By</th></tr></thead>
        <tbody>
          {rows.map((d) => (
            <tr key={d.number} onClick={() => setOpen(d)} style={{ cursor: "pointer" }}>
              <td style={{ fontFamily: "var(--font-mono)", fontSize: 13, color: "var(--emerald-400)" }}>{d.number}</td>
              <td style={{ color: "var(--text-strong)" }}>{d.partner}</td>
              <td style={{ color: "var(--text-muted)", fontFamily: "var(--font-mono)", fontSize: 13 }}>{d.date}</td>
              <td><Badge tone={DOC_STATUS[d.status]} dot>{d.status === "pending_approval" ? "pending" : d.status}</Badge></td>
              <td style={{ textAlign: "right" }}><Money strong>{d.total}</Money></td>
              <td style={{ fontSize: 12, color: "var(--text-faint)", fontFamily: "var(--font-mono)" }}>{d.by}</td>
            </tr>
          ))}
        </tbody>
      </Sheet>
      {open && <DocDetail doc={open} isPur={isPur} onClose={() => setOpen(null)} />}
    </div>
  );
}

function DocDetail({ doc, isPur, onClose }) {
  return (
    <div className="erp-dialog-backdrop" onClick={onClose}>
      <div className="erp-dialog" style={{ maxWidth: 620 }} onClick={(e) => e.stopPropagation()}>
        <div style={{ display: "flex", alignItems: "flex-start", justifyContent: "space-between", padding: "22px 24px 16px", borderBottom: "1px solid var(--border-subtle)" }}>
          <div>
            <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
              <h2 style={{ margin: 0, font: "600 20px/1 var(--font-mono)", color: "var(--text-strong)" }}>{doc.number}</h2>
              <Badge tone={DOC_STATUS[doc.status]} dot>{doc.status === "pending_approval" ? "pending" : doc.status}</Badge>
            </div>
            <p style={{ margin: "8px 0 0", font: "400 13px/1 var(--font-sans)", color: "var(--text-muted)" }}>{doc.partner} · {doc.date} · by {doc.by}</p>
          </div>
          <button className="erp-iconbtn erp-iconbtn--sm" onClick={onClose} aria-label="close"><Icon name="x" size={16} /></button>
        </div>
        <div style={{ padding: "16px 24px 24px" }}>
          <div style={{ borderRadius: "var(--radius-md)", border: "1px solid var(--border-subtle)", overflow: "hidden", marginBottom: 16 }}>
            <table className="erp-table">
              <thead><tr><th>Product</th><th style={{ textAlign: "right" }}>Qty</th><th style={{ textAlign: "right" }}>Unit price</th><th style={{ textAlign: "right" }}>Subtotal</th></tr></thead>
              <tbody>
                {doc.lines.map((l, i) => (
                  <tr key={i}>
                    <td><span style={{ fontFamily: "var(--font-mono)", fontSize: 12, color: "var(--text-faint)" }}>{l[0]}</span> <span style={{ color: "var(--text-strong)" }}>{l[1]}</span></td>
                    <td style={{ textAlign: "right", fontFamily: "var(--font-mono)" }}>{l[2]}</td>
                    <td style={{ textAlign: "right", fontFamily: "var(--font-mono)" }}>{l[3]}</td>
                    <td style={{ textAlign: "right", fontFamily: "var(--font-mono)", color: "var(--text-strong)" }}>{(l[2] * parseFloat(l[3].replace(",", ""))).toFixed(2)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
            <span style={{ font: "400 13px/1 var(--font-sans)", color: "var(--text-muted)" }}>Total</span>
            <span style={{ font: "600 22px/1 var(--font-sans)", letterSpacing: "-.02em", color: "var(--text-strong)", fontVariantNumeric: "tabular-nums" }}>{doc.total} <span style={{ font: "500 13px/1 var(--font-sans)", color: "var(--text-muted)" }}>TND</span></span>
          </div>
          <div style={{ display: "flex", justifyContent: "flex-end", gap: 8, marginTop: 20 }}>
            {!isPur && doc.status === "confirmed" && <Btn variant="outline" size="md" icon="file-text">Invoice PDF</Btn>}
            {doc.status === "draft" && <Btn variant="primary" size="md" icon="check">Confirm</Btn>}
            {isPur && doc.status === "pending_approval" && <Btn variant="primary" size="md" icon="shield-check">Approve order</Btn>}
            {isPur && doc.status === "confirmed" && <Btn variant="primary" size="md" icon="package-check">Receive goods</Btn>}
            {(doc.status === "draft" || doc.status === "confirmed") && <Btn variant="danger" size="md">Cancel</Btn>}
          </div>
        </div>
      </div>
    </div>
  );
}

// ---------- Reports ----------
function Reports() {
  const [kind, setKind] = React.useState("sales");
  const dated = kind !== "stock";
  const rows = MD.reportRows[kind];
  const total = kind === "stock" ? "5,922.00" : (kind === "sales" ? "4,448.00" : "16,040.00");
  return (
    <div>
      <PageHead title="Reports" sub="Export sales, purchases and stock valuation">
        <Btn variant="primary" size="md" icon="download">Export PDF</Btn>
      </PageHead>
      <Toolbar>
        <div style={{ display: "flex", gap: 3, padding: 3, background: "var(--surface-inset)", border: "1px solid var(--border)", borderRadius: 12 }}>
          {[["sales", "Sales", "trending-up"], ["purchases", "Purchases", "shopping-cart"], ["stock", "Stock", "boxes"]].map(([k, l, ic]) => (
            <button key={k} onClick={() => setKind(k)} style={{ display: "inline-flex", alignItems: "center", gap: 7, height: 34, padding: "0 16px", borderRadius: 9, border: "none", cursor: "pointer",
              font: "600 13px/1 var(--font-sans)", background: kind === k ? "var(--surface-hover)" : "transparent", color: kind === k ? "var(--text-strong)" : "var(--text-muted)", boxShadow: kind === k ? "var(--shadow-xs), var(--hairline)" : "none", transition: "all var(--dur-1)" }}>
              <Icon name={ic} size={15} color={kind === k ? "var(--emerald-400)" : undefined} /> {l}
            </button>
          ))}
        </div>
        {dated && (
          <div style={{ display: "flex", alignItems: "center", gap: 8, marginLeft: "auto" }}>
            <input className="erp-field" type="date" defaultValue="2026-07-01" style={{ width: 160 }} />
            <span style={{ color: "var(--text-faint)" }}>→</span>
            <input className="erp-field" type="date" defaultValue="2026-07-12" style={{ width: 160 }} />
          </div>
        )}
      </Toolbar>
      <Sheet>
        {kind === "stock" ? (
          <>
            <thead><tr><th>SKU</th><th>Product</th><th>Category</th><th style={{ textAlign: "right" }}>Qty</th><th style={{ textAlign: "right" }}>Min</th><th style={{ textAlign: "right" }}>Stock value</th></tr></thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.sku}>
                  <td style={{ fontFamily: "var(--font-mono)", fontSize: 13, color: "var(--text-faint)" }}>{r.sku}</td>
                  <td style={{ color: r.low ? "var(--rose-400)" : "var(--text-strong)" }}>{r.name}</td>
                  <td style={{ color: "var(--text-muted)" }}>{r.cat}</td>
                  <td style={{ textAlign: "right", fontFamily: "var(--font-mono)", color: r.low ? "var(--rose-400)" : undefined }}>{r.qty}</td>
                  <td style={{ textAlign: "right", fontFamily: "var(--font-mono)", color: "var(--text-muted)" }}>{r.min}</td>
                  <td style={{ textAlign: "right" }}><Money strong>{r.value}</Money></td>
                </tr>
              ))}
            </tbody>
          </>
        ) : (
          <>
            <thead><tr><th>Number</th><th>Date</th><th>Partner</th><th>Status</th><th style={{ textAlign: "right" }}>Total</th></tr></thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.number} style={{ opacity: r.status === "cancelled" ? 0.5 : 1 }}>
                  <td style={{ fontFamily: "var(--font-mono)", fontSize: 13, color: "var(--emerald-400)" }}>{r.number}</td>
                  <td style={{ color: "var(--text-muted)", fontFamily: "var(--font-mono)", fontSize: 13 }}>{r.date}</td>
                  <td style={{ color: "var(--text-strong)" }}>{r.partner}</td>
                  <td><Badge tone={DOC_STATUS[r.status]}>{r.status === "pending_approval" ? "pending" : r.status}</Badge></td>
                  <td style={{ textAlign: "right" }}><Money strong>{r.total}</Money></td>
                </tr>
              ))}
            </tbody>
          </>
        )}
      </Sheet>
      <div style={{ display: "flex", justifyContent: "flex-end", gap: 8, marginTop: 14, font: "400 13px/1 var(--font-sans)", color: "var(--text-muted)" }}>
        {rows.length} {kind === "stock" ? "products" : "documents"} · {kind === "stock" ? "Total stock value" : "Total"}: <b style={{ color: "var(--text-strong)", fontFamily: "var(--font-mono)" }}>{total} TND</b>
      </div>
    </div>
  );
}

// ---------- Users ----------
function Users() {
  return (
    <div>
      <PageHead title="Users" sub={`${MD.users.length} team members`}>
        <Btn variant="primary" size="md" icon="user-plus">Invite user</Btn>
      </PageHead>
      <Sheet>
        <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Status</th></tr></thead>
        <tbody>
          {MD.users.map((u) => (
            <tr key={u.email} style={{ opacity: u.active ? 1 : 0.5 }}>
              <td>
                <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                  <span style={{ width: 32, height: 32, borderRadius: 999, background: "linear-gradient(135deg,var(--emerald-500),var(--emerald-700))", display: "grid", placeItems: "center", font: "600 12px/1 var(--font-sans)", color: "var(--text-on-accent)", flex: "none" }}>{u.first[0]}{u.last[0]}</span>
                  <span style={{ color: "var(--text-strong)", fontWeight: 500 }}>{u.first} {u.last}</span>
                </div>
              </td>
              <td style={{ color: "var(--text-muted)", fontFamily: "var(--font-mono)", fontSize: 13 }}>{u.email}</td>
              <td><Badge tone={ROLE_TONE[u.role]}>{u.role}</Badge></td>
              <td><Badge tone={u.active ? "emerald" : "neutral"} dot>{u.active ? "active" : "inactive"}</Badge></td>
            </tr>
          ))}
        </tbody>
      </Sheet>
    </div>
  );
}

window.ERP_MODULES = { Partners, Documents, Reports, Users };
