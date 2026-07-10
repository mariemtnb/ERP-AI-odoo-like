"""Invoice OCR via a local vision model (Ollama).

The image never leaves the machine. The model returns structured JSON which
the frontend uses to prefill a draft purchase order.
"""
import base64
import json
import os
import re

import httpx

from app.config import OLLAMA_BASE_URL

VISION_MODEL = os.environ.get("OLLAMA_VISION_MODEL", "qwen2.5vl:7b")

PROMPT = """You are an invoice-extraction engine. Look at this invoice image
and return ONLY a JSON object (no prose, no markdown fences) with this shape:
{
  "supplier_name": string or null,
  "invoice_number": string or null,
  "date": "YYYY-MM-DD" or null,
  "currency": string or null,
  "lines": [
    {"description": string, "quantity": number, "unit_price": number}
  ],
  "total": number or null
}
Rules: numbers use dot as decimal separator; quantity defaults to 1 when
absent; skip tax/shipping summary rows (only product lines); if the image is
not an invoice, return {"error": "not an invoice"}."""


def extract_invoice(image_bytes: bytes, timeout: float = 300) -> dict:
    payload = {
        "model": VISION_MODEL,
        "stream": False,
        "messages": [
            {
                "role": "user",
                "content": PROMPT,
                "images": [base64.b64encode(image_bytes).decode()],
            }
        ],
        "options": {"temperature": 0},
    }
    r = httpx.post(f"{OLLAMA_BASE_URL}/api/chat", json=payload, timeout=timeout)
    r.raise_for_status()
    content = r.json()["message"]["content"]

    # Tolerate markdown fences or stray prose around the JSON.
    match = re.search(r"\{.*\}", content, flags=re.DOTALL)
    if not match:
        return {"error": "model returned no JSON", "raw": content[:500]}
    try:
        return json.loads(match.group(0))
    except json.JSONDecodeError:
        return {"error": "invalid JSON from model", "raw": content[:500]}
