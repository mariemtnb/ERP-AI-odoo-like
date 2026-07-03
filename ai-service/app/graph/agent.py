"""LangGraph ReAct agent over the ERP tools.

One graph invocation per chat turn. Conversations are checkpointed in
memory keyed by thread_id (= Django conversation id), which also lets a
paused interrupt (write-tool confirmation) resume on the next request.
"""
from langchain_ollama import ChatOllama
from langgraph.checkpoint.memory import MemorySaver
from langgraph.prebuilt import create_react_agent

from app.config import OLLAMA_BASE_URL, OLLAMA_MODEL
from app.tools.erp import build_tools

SYSTEM_PROMPT = """/no_think
You are the built-in AI assistant of an ERP system managing products, stock,
customers, suppliers, purchases and sales.

Today's date is {today}. Use it to resolve relative periods
("this month", "last week") into YYYY-MM-DD dates.

Rules:
- Use the provided tools to read data or perform actions. Never invent data:
  if a tool returns an error or nothing, say so.
- Before creating documents (sales, purchase orders), look up the exact ids
  with the search tools first.
- Optional tool arguments (email, phone, address…) may simply be omitted —
  do not ask the user for them; proceed with what you have.
- Quantities and prices come from the user or from tool results — never guess.
- If a tool returns a permission error (403), tell the user their role does
  not allow that action.
- Write actions pause for the user's confirmation automatically; after a
  rejection, do not retry the action.
- Answer in the user's language (French or English). Be concise and factual.
- Amounts are in the company currency; do not add a currency symbol.
"""

_checkpointer = MemorySaver()


def get_agent(token: str):
    llm = ChatOllama(
        model=OLLAMA_MODEL,
        base_url=OLLAMA_BASE_URL,
        temperature=0,
        num_ctx=8192,
    )
    from datetime import date

    return create_react_agent(
        llm,
        tools=build_tools(token),
        state_modifier=SYSTEM_PROMPT.format(today=date.today().isoformat()),
        checkpointer=_checkpointer,
    )
