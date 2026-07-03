import { useEffect, useRef, useState, type FormEvent } from "react";
import { Bot, Check, Send, Wrench, X } from "lucide-react";
import {
  sendApproval,
  sendMessage,
  type ChatMessage,
} from "@/api/assistant";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";

let nextLocalId = -1;

export default function AssistantPage() {
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [conversationId, setConversationId] = useState<number | undefined>();
  const [input, setInput] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const bottomRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, busy]);

  const pending = messages.at(-1)?.pending_action ?? null;

  function push(msg: ChatMessage) {
    setMessages((ms) => [...ms, msg]);
  }

  async function run(fn: () => Promise<Awaited<ReturnType<typeof sendMessage>>>) {
    setBusy(true);
    setError("");
    try {
      const res = await fn();
      setConversationId(res.conversation_id);
      push(res.message);
    } catch (err: any) {
      setError(err?.response?.data?.detail ?? "The assistant is unavailable.");
    } finally {
      setBusy(false);
    }
  }

  function submit(e: FormEvent) {
    e.preventDefault();
    const text = input.trim();
    if (!text || busy) return;
    push({
      id: nextLocalId--,
      role: "user",
      content: text,
      tool_calls: null,
      pending_action: null,
      created_at: new Date().toISOString(),
    });
    setInput("");
    void run(() => sendMessage(text, conversationId));
  }

  function answer(approve: boolean) {
    if (!conversationId || busy) return;
    push({
      id: nextLocalId--,
      role: "user",
      content: approve ? "✔ Approved" : "✘ Rejected",
      tool_calls: null,
      pending_action: null,
      created_at: new Date().toISOString(),
    });
    void run(() => sendApproval(conversationId, approve));
  }

  return (
    <div className="flex h-[calc(100vh-4rem)] flex-col space-y-4">
      <div>
        <h1 className="text-2xl font-bold">AI Assistant</h1>
        <p className="text-sm text-slate-400">
          Ask about your data or ask it to act — every action needs your approval
          and respects your role.
        </p>
      </div>

      <div className="flex-1 space-y-4 overflow-y-auto pr-2">
        {messages.length === 0 && (
          <div className="mt-12 space-y-2 text-center text-sm text-slate-500">
            <Bot className="mx-auto h-10 w-10" />
            <p>Try: “Which products are low on stock?”</p>
            <p>“Create a customer named Ahmed Ben Ali”</p>
            <p>“Quel est le chiffre d'affaires de ce mois ?”</p>
          </div>
        )}
        {messages.map((m) => (
          <MessageBubble key={m.id} message={m} />
        ))}
        {pending && !busy && (
          <Card className="ml-11 max-w-lg border-amber-500/40 p-4">
            <p className="mb-1 text-sm font-semibold text-amber-400">
              Confirmation required — {pending.action}
            </p>
            <pre className="mb-3 overflow-x-auto rounded bg-slate-950 p-2 text-xs text-slate-300">
              {JSON.stringify(pending.details, null, 2)}
            </pre>
            <div className="flex gap-2">
              <Button size="sm" onClick={() => answer(true)}>
                <Check className="h-4 w-4" /> Approve
              </Button>
              <Button size="sm" variant="destructive" onClick={() => answer(false)}>
                <X className="h-4 w-4" /> Reject
              </Button>
            </div>
          </Card>
        )}
        {busy && (
          <div className="ml-11 flex items-center gap-2 text-sm text-slate-400">
            <span className="h-2 w-2 animate-pulse rounded-full bg-indigo-400" />
            Thinking… (local model, this can take a while)
          </div>
        )}
        {error && <p className="ml-11 text-sm text-red-400">{error}</p>}
        <div ref={bottomRef} />
      </div>

      <form onSubmit={submit} className="flex gap-2">
        <Input
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder="Ask the assistant…"
          disabled={busy}
        />
        <Button type="submit" disabled={busy || !input.trim()}>
          <Send className="h-4 w-4" />
        </Button>
      </form>
    </div>
  );
}

function MessageBubble({ message }: { message: ChatMessage }) {
  const isUser = message.role === "user";
  if (!message.content && !message.tool_calls) return null;
  return (
    <div className={cn("flex gap-3", isUser && "justify-end")}>
      {!isUser && (
        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-600">
          <Bot className="h-4 w-4" />
        </div>
      )}
      <div className={cn("max-w-2xl space-y-2", isUser && "text-right")}>
        {message.tool_calls?.map((tc, i) => (
          <span
            key={i}
            className="mr-1 inline-flex items-center gap-1 rounded-full bg-slate-800 px-2.5 py-1 text-xs text-slate-300"
          >
            <Wrench className="h-3 w-3 text-indigo-400" /> {tc.name}
          </span>
        ))}
        {message.content && (
          <div
            className={cn(
              "whitespace-pre-wrap rounded-xl px-4 py-2.5 text-sm",
              isUser ? "bg-indigo-600 text-white" : "bg-slate-800 text-slate-100"
            )}
          >
            {message.content}
          </div>
        )}
      </div>
    </div>
  );
}
