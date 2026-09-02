import { useState, type FormEvent } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CalendarClock, Check, MessageSquare, Send } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import * as chatter from "@/api/chatter";

/**
 * Comments and follow-up activities for any record. Drop it into a detail view
 * with the record's type and id — <Chatter type="sales" id={sale.id} />.
 */
export function Chatter({ type, id }: { type: string; id: number }) {
  const qc = useQueryClient();
  const key = ["chatter", type, id];
  const q = useQuery({ queryKey: key, queryFn: () => chatter.getChatter(type, id) });
  const refresh = () => qc.invalidateQueries({ queryKey: key });

  const [body, setBody] = useState("");
  const [actTitle, setActTitle] = useState("");
  const [actDue, setActDue] = useState("");
  const [showAct, setShowAct] = useState(false);

  const post = useMutation({ mutationFn: () => chatter.postMessage(type, id, body), onSuccess: () => { setBody(""); refresh(); } });
  const schedule = useMutation({
    mutationFn: () => chatter.scheduleActivity(type, id, { title: actTitle, due_date: actDue || null }),
    onSuccess: () => { setActTitle(""); setActDue(""); setShowAct(false); refresh(); },
  });
  const toggle = useMutation({ mutationFn: (a: chatter.ChatterActivity) => chatter.toggleActivity(a.id, !a.done), onSuccess: refresh });

  const data = q.data;

  return (
    <div className="mt-4 border-t border-border pt-4">
      <div className="mb-3 flex items-center gap-2 text-sm font-medium text-text-2">
        <MessageSquare className="h-4 w-4" /> Activity & notes
      </div>

      {/* activities */}
      <div className="mb-3 space-y-1.5">
        {(data?.activities ?? []).map((a) => (
          <div key={a.id} className="flex items-center gap-2 text-sm">
            <button
              onClick={() => toggle.mutate(a)}
              title={a.done ? "Re-open" : "Mark done"}
              className="grid h-4 w-4 place-items-center rounded border"
              style={{ borderColor: a.done ? "var(--emerald-400)" : "var(--border)", background: a.done ? "var(--emerald-400)" : "transparent" }}
            >
              {a.done && <Check className="h-3 w-3" style={{ color: "var(--text-on-accent)" }} />}
            </button>
            <span className={a.done ? "text-text-3 line-through" : ""}>{a.title}</span>
            {a.due_date && (
              <span className="inline-flex items-center gap-1 text-xs" style={{ color: a.overdue ? "var(--rose-400)" : "var(--text-3)" }}>
                <CalendarClock className="h-3 w-3" /> {a.due_date}
              </span>
            )}
            {a.assignee && <span className="text-xs text-text-3">· {a.assignee}</span>}
          </div>
        ))}
      </div>

      {/* schedule an activity */}
      {showAct ? (
        <form onSubmit={(e: FormEvent) => { e.preventDefault(); schedule.mutate(); }} className="mb-3 flex flex-wrap items-end gap-2">
          <Input value={actTitle} onChange={(e) => setActTitle(e.target.value)} placeholder="Follow up on…" className="flex-1" required />
          <Input type="date" value={actDue} onChange={(e) => setActDue(e.target.value)} className="w-40" />
          <Button size="sm" type="submit" disabled={!actTitle || schedule.isPending}>Add</Button>
          <Button size="sm" type="button" variant="ghost" onClick={() => setShowAct(false)}>Cancel</Button>
        </form>
      ) : (
        <button className="mb-3 inline-flex items-center gap-1 text-xs text-accent" onClick={() => setShowAct(true)}>
          <CalendarClock className="h-3.5 w-3.5" /> Schedule an activity
        </button>
      )}

      {/* comment box */}
      <form onSubmit={(e: FormEvent) => { e.preventDefault(); if (body.trim()) post.mutate(); }} className="mb-3 flex items-center gap-2">
        <Input value={body} onChange={(e) => setBody(e.target.value)} placeholder="Write a note…" className="flex-1" />
        <Button size="sm" type="submit" disabled={!body.trim() || post.isPending}><Send className="h-3.5 w-3.5" /></Button>
      </form>

      {/* messages timeline */}
      <div className="space-y-2">
        {(data?.messages ?? []).map((m) => (
          <div key={m.id} className="rounded-md bg-surface-2 p-2.5 text-sm">
            <div className="mb-0.5 flex items-center justify-between text-xs text-text-3">
              <span className="font-medium text-text-2">{m.author}</span>
              <span>{new Date(m.created_at).toLocaleString()}</span>
            </div>
            <div className="whitespace-pre-wrap text-text-body">{m.body}</div>
          </div>
        ))}
        {data && data.messages.length === 0 && <p className="text-xs text-text-3">No notes yet.</p>}
      </div>
    </div>
  );
}
