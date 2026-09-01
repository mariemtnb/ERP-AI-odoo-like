import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { PageHead } from "@/components/ui/page-head";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useAuth } from "@/features/auth/AuthContext";
import { useI18n } from "@/lib/i18n";
import * as api from "@/api/projects";

export default function ProjectsPage() {
  const { user } = useAuth();
  const { t } = useI18n();
  const isManager = user?.role === "admin" || user?.role === "manager";
  const qc = useQueryClient();
  const projectsQ = useQuery({ queryKey: ["projects"], queryFn: api.listProjectsApi });
  const [selected, setSelected] = useState<number | null>(null);
  const [adding, setAdding] = useState(false);

  return (
    <div>
      <PageHead title={t("nav.projects")} sub={t("prj.sub")}>
        {isManager && <Button onClick={() => setAdding((v) => !v)}>{adding ? t("common.close") : t("prj.new")}</Button>}
      </PageHead>

      {adding && <NewProject onDone={() => { setAdding(false); qc.invalidateQueries({ queryKey: ["projects"] }); }} onCancel={() => setAdding(false)} />}

      <div style={{ display: "grid", gridTemplateColumns: "280px 1fr", gap: 18, alignItems: "start" }}>
        <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, overflow: "hidden" }}>
          {(projectsQ.data ?? []).map((p) => (
            <button key={p.id} onClick={() => setSelected(p.id)} style={{
              display: "block", width: "100%", textAlign: "left", padding: "12px 14px", cursor: "pointer",
              background: selected === p.id ? "color-mix(in oklab, var(--emerald-500) 12%, transparent)" : "transparent",
              border: 0, borderBottom: "1px solid var(--border)", color: "var(--text-strong)",
            }}>
              <div style={{ fontWeight: 600 }}>{p.name}</div>
              <div style={{ fontSize: 12, color: "var(--text-muted)" }}>
                {p.customer_name ?? t("prj.internal")} · {p.logged_hours}{p.budget_hours ? `/${p.budget_hours}` : ""} h · {t(`prj.status.${p.status}`)}
              </div>
            </button>
          ))}
          {projectsQ.data?.length === 0 && <p style={{ padding: 14, color: "var(--text-muted)", fontSize: 13 }}>{t("prj.none")}</p>}
        </div>

        <div>{selected ? <ProjectDetail projectId={selected} isManager={isManager} /> : <p style={{ color: "var(--text-muted)" }}>{t("prj.select")}</p>}</div>
      </div>
    </div>
  );
}

function ProjectDetail({ projectId, isManager }: { projectId: number; isManager: boolean }) {
  const { t } = useI18n();
  const qc = useQueryClient();
  const detailQ = useQuery({ queryKey: ["project", projectId], queryFn: () => api.getProjectDetail(projectId) });
  const summaryQ = useQuery({ queryKey: ["project-sum", projectId], queryFn: () => api.getSummary(projectId) });
  const sheetsQ = useQuery({ queryKey: ["project-ts", projectId], queryFn: () => api.listTimesheets(projectId) });
  const refresh = () => {
    qc.invalidateQueries({ queryKey: ["project", projectId] });
    qc.invalidateQueries({ queryKey: ["project-sum", projectId] });
    qc.invalidateQueries({ queryKey: ["project-ts", projectId] });
    qc.invalidateQueries({ queryKey: ["projects"] });
  };

  const [task, setTask] = useState("");
  const [date, setDate] = useState("");
  const [hours, setHours] = useState("");
  const [billable, setBillable] = useState(true);
  const [note, setNote] = useState("");
  const [newTask, setNewTask] = useState("");

  const log = useMutation({
    mutationFn: () => api.logTime(projectId, { task: task ? Number(task) : null, work_date: date, hours: Number(hours), billable, note }),
    onSuccess: () => { setHours(""); setNote(""); refresh(); },
  });
  const addTaskM = useMutation({ mutationFn: () => api.addTask(projectId, newTask), onSuccess: () => { setNewTask(""); refresh(); } });

  const s = summaryQ.data;
  return (
    <>
      {s && (
        <div style={{ display: "flex", gap: 12, marginBottom: 16, flexWrap: "wrap" }}>
          <Stat label={t("prj.logged")} value={`${s.logged_hours} h`} />
          <Stat label={t("prj.billable")} value={`${s.billable_hours} h`} accent />
          <Stat label={t("prj.nonBillable")} value={`${s.non_billable_hours} h`} />
          {s.budget_hours != null && <Stat label={s.over_budget ? t("prj.overBudget") : t("prj.remaining")} value={`${s.remaining_hours} h`} danger={s.over_budget} />}
        </div>
      )}

      <Panel>
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit,minmax(120px,1fr))", gap: 10, alignItems: "end" }}>
          <Field label={t("prj.task")}>
            <select value={task} onChange={(e) => setTask(e.target.value)} style={selectStyle}>
              <option value="">{t("common.none")}</option>
              {(detailQ.data?.tasks ?? []).map((tk) => <option key={tk.id} value={tk.id}>{tk.name}</option>)}
            </select>
          </Field>
          <Field label={t("common.date")}><Input type="date" value={date} onChange={(e) => setDate(e.target.value)} /></Field>
          <Field label={t("prj.hours")}><Input type="number" min={0} step="0.25" value={hours} onChange={(e) => setHours(e.target.value)} /></Field>
          <Field label={t("prj.note")}><Input value={note} onChange={(e) => setNote(e.target.value)} placeholder={t("prj.optional")} /></Field>
          <label style={{ display: "flex", gap: 6, alignItems: "center", fontSize: 13, color: "var(--text-body)" }}>
            <input type="checkbox" checked={billable} onChange={(e) => setBillable(e.target.checked)} /> {t("prj.billableWord")}
          </label>
          <Button loading={log.isPending} disabled={!date || !(Number(hours) > 0)} onClick={() => log.mutate()}>{t("prj.logTime")}</Button>
        </div>
        {isManager && (
          <div style={{ display: "flex", gap: 8, marginTop: 14, alignItems: "center" }}>
            <Input placeholder={t("prj.addTaskPlaceholder")} value={newTask} onChange={(e) => setNewTask(e.target.value)} style={{ maxWidth: 240 }} />
            <Button size="sm" variant="outline" loading={addTaskM.isPending} disabled={!newTask.trim()} onClick={() => addTaskM.mutate()}>{t("prj.addTask")}</Button>
          </div>
        )}
      </Panel>

      <Table head={[t("common.date"), t("prj.task"), t("prj.hours"), t("prj.billableWord"), t("docs.col.by")]}>
        {(sheetsQ.data ?? []).map((e) => (
          <tr key={e.id} style={{ borderTop: "1px solid var(--border)" }}>
            <Td mono>{e.work_date}</Td><Td>{e.task_name ?? "—"}</Td><Td mono right>{e.hours}</Td>
            <Td>{e.billable ? t("common.yes") : t("common.no")}</Td><Td muted>{e.user_email}</Td>
          </tr>
        ))}
        {sheetsQ.data?.length === 0 && <tr><Td colSpan={5} muted>{t("prj.noTime")}</Td></tr>}
      </Table>
    </>
  );
}

function NewProject({ onDone, onCancel }: { onDone: () => void; onCancel: () => void }) {
  const { t } = useI18n();
  const [name, setName] = useState("");
  const [budget, setBudget] = useState("");
  const add = useMutation({ mutationFn: () => api.createProject({ name, budget_hours: Number(budget) || null }), onSuccess: onDone });
  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 18, marginBottom: 18, display: "flex", gap: 12, alignItems: "end" }}>
      <Field label={t("field.name")}><Input value={name} onChange={(e) => setName(e.target.value)} placeholder={t("prj.namePlaceholder")} /></Field>
      <Field label={t("prj.budgetHours")}><Input type="number" min={0} value={budget} onChange={(e) => setBudget(e.target.value)} /></Field>
      <Button variant="outline" onClick={onCancel}>{t("common.cancel")}</Button>
      <Button loading={add.isPending} disabled={!name.trim()} onClick={() => add.mutate()}>{t("common.create")}</Button>
    </div>
  );
}

const selectStyle: React.CSSProperties = { height: 38, width: "100%", background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, color: "var(--text-strong)", padding: "0 8px" };
function Stat({ label, value, accent, danger }: { label: string; value: string; accent?: boolean; danger?: boolean }) {
  return (
    <div style={{ flex: 1, minWidth: 120, background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 12, padding: "12px 16px" }}>
      <div style={{ fontSize: 12, color: "var(--text-muted)", textTransform: "uppercase", letterSpacing: "0.06em" }}>{label}</div>
      <div style={{ fontSize: 22, fontWeight: 700, color: danger ? "var(--rose-400)" : accent ? "var(--emerald-400)" : "var(--text-strong)", marginTop: 4 }}>{value}</div>
    </div>
  );
}
function Panel({ children }: { children: React.ReactNode }) {
  return <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 18, marginBottom: 18 }}>{children}</div>;
}
function Table({ head, children }: { head: string[]; children: React.ReactNode }) {
  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, overflow: "hidden" }}>
      <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 14 }}>
        <thead><tr style={{ background: "var(--surface-hover)", color: "var(--text-muted)", textAlign: "left" }}>
          {head.map((h, i) => <th key={i} style={{ padding: "10px 14px", fontSize: 11, letterSpacing: "0.08em", textTransform: "uppercase", textAlign: i === 2 ? "right" : "left" }}>{h}</th>)}
        </tr></thead>
        <tbody>{children}</tbody>
      </table>
    </div>
  );
}
function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return <label style={{ display: "block" }}><span style={{ display: "block", fontSize: 12, color: "var(--text-muted)", marginBottom: 4 }}>{label}</span>{children}</label>;
}
function Td({ children, mono, right, muted, colSpan }: { children: React.ReactNode; mono?: boolean; right?: boolean; muted?: boolean; colSpan?: number }) {
  return <td colSpan={colSpan} style={{ padding: "10px 14px", textAlign: right ? "right" : "left", fontFamily: mono ? "var(--font-mono)" : undefined, color: muted ? "var(--text-muted)" : "var(--text-body)", fontVariantNumeric: mono ? "tabular-nums" : undefined }}>{children}</td>;
}
