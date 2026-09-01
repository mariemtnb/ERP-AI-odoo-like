import { useEffect, useRef, useState, type FormEvent } from "react";
import { motion } from "framer-motion";
import { Check, Mic, MicOff, Send, Sparkles, Wrench, X } from "lucide-react";
import {
  sendApproval,
  sendMessage,
  type ChatMessage,
} from "@/api/assistant";
import { Button } from "@/components/ui/button";
import { Tooltip } from "@/components/ui/tooltip";
import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";
import { describeAction, detectLang, t } from "./approval";
import { useI18n } from "@/lib/i18n";

let nextLocalId = -1;

/** Voice input via the browser's Web Speech API (Chrome/Edge). */
function useSpeech(onResult: (text: string) => void) {
  const [listening, setListening] = useState(false);
  const recRef = useRef<any>(null);
  const supported =
    typeof window !== "undefined" &&
    ("SpeechRecognition" in window || "webkitSpeechRecognition" in window);

  function toggle() {
    if (!supported) return;
    if (listening) {
      recRef.current?.stop();
      return;
    }
    const SR = (window as any).SpeechRecognition ?? (window as any).webkitSpeechRecognition;
    const rec = new SR();
    rec.lang = navigator.language.startsWith("fr") ? "fr-FR" : "en-US";
    rec.interimResults = false;
    rec.maxAlternatives = 1;
    rec.onresult = (e: any) => onResult(e.results[0][0].transcript);
    rec.onend = () => setListening(false);
    rec.onerror = () => setListening(false);
    recRef.current = rec;
    setListening(true);
    rec.start();
  }

  return { supported, listening, toggle };
}

export default function AssistantPage() {
  const { t: tr } = useI18n();
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [conversationId, setConversationId] = useState<number | undefined>();
  const [input, setInput] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const bottomRef = useRef<HTMLDivElement>(null);
  const speech = useSpeech((text) => setInput((v) => (v ? v + " " : "") + text));

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, busy]);

  const pending = messages.at(-1)?.pending_action ?? null;
  // Speak the confirmation in the language the user actually wrote in.
  const lastUserText = [...messages]
    .reverse()
    .find((m) => m.role === "user" && m.content && !/^[✔✘]/.test(m.content))?.content;
  const lang = detectLang(lastUserText);

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
      setError(err?.response?.data?.detail ?? tr("asst.unavailable"));
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
      content: approve ? `✔ ${tr("asst.approved")}` : `✘ ${tr("asst.rejected")}`,
      tool_calls: null,
      pending_action: null,
      created_at: new Date().toISOString(),
    });
    void run(() => sendApproval(conversationId, approve));
  }

  const SUGGESTIONS = [
    tr("asst.sug1"),
    tr("asst.sug2"),
    tr("asst.sug3"),
    tr("asst.sug4"),
  ];

  function sendSuggestion(text: string) {
    if (busy) return;
    push({
      id: nextLocalId--, role: "user", content: text,
      tool_calls: null, pending_action: null, created_at: new Date().toISOString(),
    });
    void run(() => sendMessage(text, conversationId));
  }

  return (
    <div className="mx-auto flex h-[calc(100dvh-8rem)] w-full max-w-3xl flex-col">
      <div className="flex-1 space-y-5 overflow-y-auto pb-6 pr-1">
        {messages.length === 0 && (
          <motion.div
            initial={{ opacity: 0, y: 14 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, ease: [0.22, 1, 0.36, 1] }}
            className="flex flex-col items-center gap-6 pt-16 text-center"
          >
            <div className="relative">
              <div className="glow-accent absolute -inset-10" />
              <div className="relative flex h-16 w-16 items-center justify-center rounded-2xl bg-accent/15 shadow-[inset_0_0_0_1px_hsl(var(--accent)/0.35)]">
                <Sparkles className="h-7 w-7 text-accent-strong" />
              </div>
            </div>
            <div>
              <h2 className="text-xl font-semibold tracking-tight">
                {tr("asst.heading")}
              </h2>
              <p className="mt-1.5 max-w-md text-sm leading-relaxed text-text-3">
                {tr("asst.sub")}
              </p>
            </div>
            <div className="grid w-full max-w-lg gap-2 sm:grid-cols-2">
              {SUGGESTIONS.map((s) => (
                <button
                  key={s}
                  onClick={() => sendSuggestion(s)}
                  className="rounded-lg bg-surface px-4 py-3 text-left text-[13px] leading-snug text-text-2
                             ring-1 ring-inset ring-white/[0.05] transition-all duration-200
                             hover:-translate-y-0.5 hover:text-text hover:shadow-2 hover:ring-accent/25"
                >
                  {s}
                </button>
              ))}
            </div>
          </motion.div>
        )}
        {messages.map((m) => (
          <MessageBubble key={m.id} message={m} />
        ))}
        {pending && !busy && (
          <motion.div
            initial={{ opacity: 0, scale: 0.97, y: 8 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            transition={{ duration: 0.25, ease: [0.22, 1, 0.36, 1] }}
            className="ml-11 max-w-lg rounded-xl bg-surface p-5 shadow-2
                       ring-1 ring-inset ring-warning/30"
          >
            {(() => {
              const f = describeAction(pending.action, pending.details, lang);
              const rtl = lang === "ar";
              return (
                <div dir={rtl ? "rtl" : "ltr"} style={{ textAlign: rtl ? "right" : "left" }}>
                  <p className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-warning">
                    <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-warning" />
                    {t.approvalNeeded[lang]}
                  </p>
                  <p className="mt-2 text-base font-semibold text-text-1">{f.title}</p>
                  <p className="mt-0.5 text-sm text-text-3">{t.question[lang]}</p>
                  {f.rows.length > 0 && (
                    <div className="mt-3 divide-y divide-border rounded-lg bg-surface-2 px-3">
                      {f.rows.map((r, i) => (
                        <div key={i} className="flex justify-between gap-4 py-2 text-sm">
                          <span className="text-text-3">{r.label}</span>
                          <span className="font-medium text-text-1" style={{ direction: "ltr", textAlign: rtl ? "left" : "right" }}>
                            {r.value}
                          </span>
                        </div>
                      ))}
                    </div>
                  )}
                  <div className="mt-4 flex gap-2" style={{ flexDirection: rtl ? "row-reverse" : "row" }}>
                    <Button size="sm" onClick={() => answer(true)}>
                      <Check className="h-4 w-4" /> {t.approve[lang]}
                    </Button>
                    <Button size="sm" variant="destructive" onClick={() => answer(false)}>
                      <X className="h-4 w-4" /> {t.reject[lang]}
                    </Button>
                  </div>
                </div>
              );
            })()}
          </motion.div>
        )}
        {busy && (
          <div className="ml-11 flex items-center gap-2.5 text-sm text-text-3">
            <span className="flex gap-1">
              {[0, 1, 2].map((i) => (
                <motion.span
                  key={i}
                  className="h-1.5 w-1.5 rounded-full bg-accent-strong"
                  animate={{ opacity: [0.25, 1, 0.25] }}
                  transition={{ duration: 1.2, repeat: Infinity, delay: i * 0.18 }}
                />
              ))}
            </span>
            {tr("asst.thinking")}
          </div>
        )}
        {error && <p className="ml-11 text-sm text-danger">{error}</p>}
        <div ref={bottomRef} />
      </div>

      {/* composer */}
      <form
        onSubmit={submit}
        className="flex items-center gap-2 rounded-xl bg-surface p-2 shadow-2 ring-1 ring-inset ring-white/[0.06]
                   transition-shadow duration-200 focus-within:ring-accent/40"
      >
        <Input
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder={tr("asst.placeholder")}
          disabled={busy}
          className="border-0 bg-transparent shadow-none hover:shadow-none focus:shadow-none"
        />
        {speech.supported && (
          <Tooltip label={speech.listening ? tr("asst.stopListening") : tr("asst.talkTip")}>
            <Button
              type="button"
              size="icon"
              variant={speech.listening ? "destructive" : "ghost"}
              onClick={speech.toggle}
              aria-label={speech.listening ? tr("asst.stopListening") : tr("asst.speakAria")}
            >
              {speech.listening ? <MicOff className="h-4 w-4" /> : <Mic className="h-4 w-4" />}
            </Button>
          </Tooltip>
        )}
        <Tooltip label={tr("asst.sendTip")}>
          <Button type="submit" size="icon" disabled={busy || !input.trim()} aria-label={tr("asst.sendAria")}>
            <Send className="h-4 w-4" />
          </Button>
        </Tooltip>
      </form>
    </div>
  );
}

function MessageBubble({ message }: { message: ChatMessage }) {
  const isUser = message.role === "user";
  if (!message.content && !message.tool_calls) return null;
  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1] }}
      className={cn("flex gap-3", isUser && "justify-end")}
    >
      {!isUser && (
        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-accent/15 shadow-[inset_0_0_0_1px_hsl(var(--accent)/0.3)]">
          <Sparkles className="h-4 w-4 text-accent-strong" />
        </div>
      )}
      <div className={cn("max-w-[85%] space-y-2", isUser && "text-right")}>
        {message.tool_calls?.map((tc, i) => (
          <span
            key={i}
            className="mr-1 inline-flex items-center gap-1.5 rounded-full bg-accent/[0.08] px-2.5 py-1 text-[11px] font-medium text-accent-strong
                       shadow-[inset_0_0_0_1px_hsl(var(--accent)/0.2)]"
          >
            <Wrench className="h-3 w-3" /> {tc.name}
          </span>
        ))}
        {message.content && (
          <div
            className={cn(
              "whitespace-pre-wrap rounded-xl px-4 py-3 text-sm leading-relaxed",
              isUser
                ? "bg-accent font-medium text-bg shadow-[inset_0_1px_0_hsl(var(--accent-strong)/0.5)]"
                : "bg-surface text-text shadow-2 ring-1 ring-inset ring-white/[0.045]"
            )}
          >
            {message.content}
          </div>
        )}
      </div>
    </motion.div>
  );
}
