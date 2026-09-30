"""Rewrite text so it reads like a person wrote it, checked against the local detector."""
import json
import os
import re
import urllib.request

from detector import analyze, STOCK_PHRASES

# Any OpenAI-compatible chat endpoint works. Defaults to Groq's free tier.
BASE_URL = os.environ.get("LLM_BASE_URL", "https://api.groq.com/openai/v1").rstrip("/")
MODEL = os.environ.get("LLM_MODEL", "llama-3.3-70b-versatile")

SYSTEM = """You rewrite text so it reads like a real person wrote it quickly and knows the subject.
Keep every fact, number, name and claim exactly as given. Do not add new claims or examples.
Style rules:
- Mix sentence lengths on purpose: some under 8 words, some over 30.
- Use contractions where they sound natural.
- Prefer plain everyday words. Repeat a key term instead of rotating synonyms.
- Vary how sentences and paragraphs open. Occasionally start with 'And' or 'But'.
- No em dashes, no semicolons, no rhetorical questions, no lists of exactly three.
- Avoid stock phrases: delve, landscape, leverage, robust, seamless, crucial, pivotal, foster,
  streamline, moreover, furthermore, ultimately, in conclusion, it's important to note.
- Don't summarize at the end. Stop when the point is made.
Return only the rewritten text."""

_CONTRACT = [
    (r"\bdo not\b", "don't"), (r"\bdoes not\b", "doesn't"), (r"\bcannot\b", "can't"),
    (r"\bit is\b", "it's"), (r"\bthat is\b", "that's"), (r"\bwe are\b", "we're"),
    (r"\bthey are\b", "they're"), (r"\byou are\b", "you're"), (r"\bwill not\b", "won't"),
    (r"\bis not\b", "isn't"), (r"\bare not\b", "aren't"), (r"\bI am\b", "I'm"),
]
_SWAPS = {
    "moreover": "Also", "furthermore": "And", "additionally": "Also", "ultimately": "",
    "leverage": "use", "utilize": "use", "robust": "solid", "seamless": "smooth",
    "crucial": "important", "pivotal": "key", "foster": "encourage", "streamline": "simplify",
    "delve into": "look at", "comprehensive": "full",
}


def rule_based(text: str) -> str:
    """Offline fallback: mechanical clean-up only. Much weaker than the LLM path."""
    out = text.replace("—", ", ").replace(";", ",")
    for pat, rep in _CONTRACT:
        out = re.sub(pat, rep, out, flags=re.I)
    for k, v in _SWAPS.items():
        out = re.sub(rf"\b{k}\b,?\s*", (v + " " if v else ""), out, flags=re.I)
    return re.sub(r"[ \t]{2,}", " ", out).strip()


def _rewrite(text: str, feedback: str = "", extra: str = "") -> str:
    prompt = text
    if feedback:
        prompt = f"{feedback}\n\nRewrite this again, fixing those problems:\n\n{text}"
    if extra:
        prompt = f"Extra style guidance from the user:\n{extra}\n\n{prompt}"
    body = json.dumps({
        "model": MODEL, "temperature": 0.9, "max_tokens": 4096,
        "messages": [{"role": "system", "content": SYSTEM}, {"role": "user", "content": prompt}],
    }).encode()
    req = urllib.request.Request(
        f"{BASE_URL}/chat/completions", body,
        {"Content-Type": "application/json", "Authorization": f"Bearer {os.environ['LLM_API_KEY']}"})
    with urllib.request.urlopen(req, timeout=90) as r:
        return json.load(r)["choices"][0]["message"]["content"].strip()


def humanize(text: str, target: int = 25, max_passes: int = 3, style: str = "") -> dict:
    before = analyze(text)
    if not os.environ.get("LLM_API_KEY"):
        out = rule_based(text)
        after = analyze(out)
        return {"text": out, "mode": "rule-based (set LLM_API_KEY for full rewrite)",
                "before": before.score, "after": after.score, "passes": 0, "reasons": after.reasons}

    current, best, best_score, passes = text, text, before.score, 0
    feedback = ""
    for passes in range(1, max_passes + 1):
        try:
            current = _rewrite(text if passes == 1 else current, feedback, style)
        except Exception as e:  # rate limit, bad key, network
            if passes == 1:
                out = rule_based(text)
                return {"text": out, "mode": f"rule-based fallback (LLM error: {e})",
                        "before": before.score, "after": analyze(out).score, "passes": 0,
                        "reasons": []}
            break
        rep = analyze(current)
        if rep.score < best_score or best is text:
            best, best_score = current, rep.score
        if rep.score <= target:
            break
        feedback = "A detector still flags this text: " + " ".join(rep.reasons)
    final = analyze(best)
    return {"text": best, "mode": "llm", "before": before.score, "after": final.score,
            "passes": passes, "reasons": final.reasons}
