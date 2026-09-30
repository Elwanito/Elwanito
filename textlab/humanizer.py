"""Rewrite text so it reads like a person wrote it, checked against the local detector."""
import os
import re

from detector import analyze, STOCK_PHRASES

MODEL = os.environ.get("TEXTLAB_MODEL", "claude-sonnet-5-5")

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


def _client():
    import anthropic
    return anthropic.Anthropic()


def _rewrite(text: str, feedback: str = "", extra: str = "") -> str:
    prompt = text
    if feedback:
        prompt = (f"{feedback}\n\nRewrite this again, fixing those problems:\n\n{text}")
    if extra:
        prompt = f"Extra style guidance from the user:\n{extra}\n\n{prompt}"
    msg = _client().messages.create(
        model=MODEL, max_tokens=4096, system=SYSTEM,
        messages=[{"role": "user", "content": prompt}])
    return "".join(b.text for b in msg.content if b.type == "text").strip()


def humanize(text: str, target: int = 25, max_passes: int = 3, style: str = "") -> dict:
    before = analyze(text)
    if not os.environ.get("ANTHROPIC_API_KEY"):
        out = rule_based(text)
        after = analyze(out)
        return {"text": out, "mode": "rule-based (set ANTHROPIC_API_KEY for full rewrite)",
                "before": before.score, "after": after.score, "passes": 0, "reasons": after.reasons}

    current, best, best_score, passes = text, text, before.score, 0
    feedback = ""
    for passes in range(1, max_passes + 1):
        current = _rewrite(text if passes == 1 else current, feedback, style)
        rep = analyze(current)
        if rep.score < best_score or best is text:
            best, best_score = current, rep.score
        if rep.score <= target:
            break
        feedback = "A detector still flags this text: " + " ".join(rep.reasons)
    final = analyze(best)
    return {"text": best, "mode": "llm", "before": before.score, "after": final.score,
            "passes": passes, "reasons": final.reasons}
