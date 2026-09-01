import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { PageHead } from "@/components/ui/page-head";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { listCustomers } from "@/api/subscriptions";
import { useI18n } from "@/lib/i18n";
import * as sh from "@/api/shipping";

const STATUS_COLOR: Record<string, string> = {
  pending: "var(--text-muted)", shipped: "var(--amber-400,#d99a2b)",
  delivered: "var(--emerald-400)", cancelled: "var(--rose-400)",
};

export default function ShippingPage() {
  const { t } = useI18n();
  const qc = useQueryClient();
  const [status, setStatus] = useState("");
  const listQ = useQuery({ queryKey: ["shipments", status], queryFn: () => sh.listShipments(status ? { status } : {}) });
  const [creating, setCreating] = useState(false);
  const [shipping, setShipping] = useState<sh.Shipment | null>(null);
  const refresh = () => qc.invalidateQueries({ queryKey: ["shipments"] });

  const deliver = useMutation({ mutationFn: (id: number) => sh.deliverShipment(id), onSuccess: refresh });
  const cancel = useMutation({ mutationFn: (id: number) => sh.cancelShipment(id), onSuccess: refresh });

  return (
    <div>
      <PageHead title={t("nav.shipping")} sub={t("ship.sub")}>
        <Button onClick={() => setCreating((v) => !v)}>{creating ? t("common.close") : t("ship.new")}</Button>
      </PageHead>

      {creating && <NewShipment onDone={() => { setCreating(false); refresh(); }} onCancel={() => setCreating(false)} />}
      {shipping && <ShipDialog shipment={shipping} onDone={() => { setShipping(null); refresh(); }} onClose={() => setShipping(null)} />}

      <div style={{ display: "flex", gap: 6, marginBottom: 14 }}>
        {["", "pending", "shipped", "delivered", "cancelled"].map((s) => (
          <button key={s || "all"} onClick={() => setStatus(s)} style={{
            padding: "5px 12px", borderRadius: 8, cursor: "pointer", fontSize: 13, textTransform: "capitalize",
            border: "1px solid " + (status === s ? "var(--emerald-500)" : "var(--border)"),
            background: status === s ? "color-mix(in oklab, var(--emerald-500) 12%, transparent)" : "var(--surface)",
            color: status === s ? "var(--text-strong)" : "var(--text-muted)",
          }}>{s ? t(`status.${s}`) : t("common.all")}</button>
        ))}
      </div>

      <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, overflow: "hidden" }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 14 }}>
          <thead><tr style={{ background: "var(--surface-hover)", color: "var(--text-muted)", textAlign: "left" }}>
            <Th>{t("ship.col.shipment")}</Th><Th>{t("field.customer")}</Th><Th>{t("ship.carrier")}</Th><Th>{t("ship.tracking")}</Th><Th>{t("common.status")}</Th><Th></Th>
          </tr></thead>
          <tbody>
            {(listQ.data ?? []).map((s) => (
              <tr key={s.id} style={{ borderTop: "1px solid var(--border)" }}>
                <Td mono>{s.number}{s.sale_number && <span style={{ color: "var(--text-muted)" }}> · {s.sale_number}</span>}</Td>
                <Td>{s.customer_name ?? "—"}<div style={{ fontSize: 12, color: "var(--text-muted)" }}>{s.address}</div></Td>
                <Td>{s.carrier}</Td>
                <Td mono>{s.tracking_number ?? "—"}</Td>
                <Td><span style={{ fontSize: 12, fontWeight: 600, color: STATUS_COLOR[s.status] }}>{t(`status.${s.status}`)}</span></Td>
                <Td right>
                  <span style={{ display: "flex", gap: 6, justifyContent: "flex-end" }}>
                    {s.status === "pending" && <Button size="sm" onClick={() => setShipping(s)}>{t("ship.ship")}</Button>}
                    {s.status === "shipped" && <Button size="sm" onClick={() => deliver.mutate(s.id)}>{t("ship.markDelivered")}</Button>}
                    {(s.status === "pending" || s.status === "shipped") && <Button size="sm" variant="ghost" onClick={() => cancel.mutate(s.id)}>{t("common.cancel")}</Button>}
                  </span>
                </Td>
              </tr>
            ))}
            {listQ.data?.length === 0 && <tr><Td colSpan={6} muted>{t("ship.none")}</Td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function ShipDialog({ shipment, onDone, onClose }: { shipment: sh.Shipment; onDone: () => void; onClose: () => void }) {
  const { t } = useI18n();
  const [tracking, setTracking] = useState("");
  const ship = useMutation({ mutationFn: () => sh.shipShipment(shipment.id, tracking || undefined), onSuccess: onDone });
  return (
    <div role="dialog" style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,.45)", display: "grid", placeItems: "center", zIndex: 50 }} onClick={onClose}>
      <div onClick={(e) => e.stopPropagation()} style={{ width: 360, background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 22 }}>
        <h3 style={{ margin: "0 0 6px", color: "var(--text-strong)" }}>{t("ship.ship")} {shipment.number}</h3>
        <p style={{ fontSize: 13, color: "var(--text-muted)", marginTop: 0 }}>{t("ship.via")} {shipment.carrier}. {t("ship.viaHint")}</p>
        <Input value={tracking} onChange={(e) => setTracking(e.target.value)} placeholder={t("ship.trackingPlaceholder")} />
        <div style={{ display: "flex", gap: 8, marginTop: 14 }}>
          <Button variant="outline" style={{ flex: 1 }} onClick={onClose}>{t("common.cancel")}</Button>
          <Button style={{ flex: 1 }} loading={ship.isPending} onClick={() => ship.mutate()}>{t("ship.markShipped")}</Button>
        </div>
      </div>
    </div>
  );
}

function NewShipment({ onDone, onCancel }: { onDone: () => void; onCancel: () => void }) {
  const { t } = useI18n();
  const customersQ = useQuery({ queryKey: ["ship-customers"], queryFn: listCustomers });
  const [customer, setCustomer] = useState("");
  const [carrier, setCarrier] = useState("");
  const [address, setAddress] = useState("");
  const add = useMutation({ mutationFn: () => sh.createShipment({ customer: customer ? Number(customer) : null, carrier, address }), onSuccess: onDone });
  const ok = carrier.trim();
  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 18, marginBottom: 18, display: "grid", gridTemplateColumns: "repeat(auto-fit,minmax(150px,1fr))", gap: 12, alignItems: "end" }}>
      <Field label={t("field.customer")}>
        <select value={customer} onChange={(e) => setCustomer(e.target.value)} style={{ height: 38, width: "100%", background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, color: "var(--text-strong)", padding: "0 8px" }}>
          <option value="">{t("common.none")}</option>
          {(customersQ.data ?? []).map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
      </Field>
      <Field label={t("ship.carrier")}><Input value={carrier} onChange={(e) => setCarrier(e.target.value)} placeholder="Aramex" /></Field>
      <Field label={t("field.address")}><Input value={address} onChange={(e) => setAddress(e.target.value)} placeholder="Tunis" /></Field>
      <div style={{ display: "flex", gap: 8 }}>
        <Button variant="outline" onClick={onCancel}>{t("common.cancel")}</Button>
        <Button loading={add.isPending} disabled={!ok} onClick={() => add.mutate()}>{t("common.create")}</Button>
      </div>
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return <label style={{ display: "block" }}><span style={{ display: "block", fontSize: 12, color: "var(--text-muted)", marginBottom: 4 }}>{label}</span>{children}</label>;
}
function Th({ children, right }: { children?: React.ReactNode; right?: boolean }) {
  return <th style={{ padding: "10px 14px", fontSize: 11, letterSpacing: "0.08em", textTransform: "uppercase", textAlign: right ? "right" : "left" }}>{children}</th>;
}
function Td({ children, mono, right, muted, colSpan }: { children: React.ReactNode; mono?: boolean; right?: boolean; muted?: boolean; colSpan?: number }) {
  return <td colSpan={colSpan} style={{ padding: "10px 14px", textAlign: right ? "right" : "left", fontFamily: mono ? "var(--font-mono)" : undefined, color: muted ? "var(--text-muted)" : "var(--text-body)", fontVariantNumeric: mono ? "tabular-nums" : undefined }}>{children}</td>;
}
