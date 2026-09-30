"""Heuristic AI-text detector.

This is a transparent, feature-based proxy. It scores the same surface
signals many commercial detectors lean on (uniform sentence rhythm, stock
phrasing, no contractions, heavy transitions). It is NOT a copy of any
commercial detector and its score will not match theirs.
"""
import re
import statistics
from dataclasses import dataclass, field

STOCK_PHRASES = [
    "delve", "tapestry", "landscape", "leverage", "robust", "seamless",
    "crucial", "pivotal", "foster", "streamline", "game-changer", "realm",
    "in today's", "it is important to note", "it's important to note",
    "in conclusion", "ultimately", "moreover", "furthermore", "additionally",
    "in the ever-evolving", "navigate the", "at the end of the day",
    "plays a vital role", "plays a crucial role", "a testament to",
    "unlock the", "shed light on", "in summary", "underscores", "multifaceted",
    "comprehensive", "holistic", "paradigm", "synergy", "cutting-edge",
]
TRANSITIONS = ["however", "moreover", "furthermore", "additionally", "therefore",
               "consequently", "thus", "overall", "in addition", "as a result"]
CONTRACTION_RE = re.compile(r"\b\w+'(?:s|t|re|ve|ll|d|m)\b", re.I)
SENT_SPLIT = re.compile(r"(?<=[.!?])\s+(?=[A-Z\"'(])")
WORD_RE = re.compile(r"[A-Za-z']+")


@dataclass
class Report:
    score: int
    label: str
    signals: dict = field(default_factory=dict)
    sentences: list = field(default_factory=list)
    reasons: list = field(default_factory=list)


def split_sentences(text: str) -> list[str]:
    out = []
    for para in re.split(r"\n\s*\n", text.strip()):
        out.extend(s.strip() for s in SENT_SPLIT.split(para.strip()) if s.strip())
    return out


def _clamp(x, lo=0.0, hi=1.0):
    return max(lo, min(hi, x))


def sentence_flags(sentence: str) -> list[str]:
    low = sentence.lower()
    flags = [p for p in STOCK_PHRASES if p in low]
    if "—" in sentence or " -- " in sentence:
        flags.append("em dash")
    if re.search(r"\bnot (?:just|only|merely)\b.*\bbut\b", low):
        flags.append("'not just X but Y' pattern")
    return flags


def analyze(text: str) -> Report:
    sents = split_sentences(text)
    words = WORD_RE.findall(text)
    n_words = len(words)
    if n_words < 40 or len(sents) < 3:
        return Report(0, "Too short to judge (need ~40+ words)")

    lengths = [len(WORD_RE.findall(s)) for s in sents]
    mean = statistics.mean(lengths)
    cv = statistics.pstdev(lengths) / mean if mean else 0
    # Human text tends to have coefficient of variation ~0.55+; AI ~0.3-0.4.
    burst = _clamp((0.55 - cv) / 0.35)

    stock_hits = sum(1 for s in sents for _ in sentence_flags(s))
    stock = _clamp(stock_hits / max(1, len(sents)) / 0.35)

    contractions = len(CONTRACTION_RE.findall(text))
    contr_rate = contractions / n_words * 100
    no_contr = _clamp((1.5 - contr_rate) / 1.5)

    low = text.lower()
    trans_hits = sum(low.count(t) for t in TRANSITIONS)
    trans = _clamp(trans_hits / len(sents) / 0.3)

    openers = [s.split()[0].lower() for s in sents if s.split()]
    top_open = max((openers.count(o) for o in set(openers)), default=0)
    opener_rep = _clamp((top_open / len(sents) - 0.15) / 0.25)

    ttr = len({w.lower() for w in words}) / n_words
    # Longer texts naturally lower TTR, so compare on a fixed window.
    window = [w.lower() for w in words[:200]]
    ttr = len(set(window)) / len(window)
    uniform_vocab = _clamp((ttr - 0.62) / 0.2)

    signals = {
        "uniform_rhythm": burst, "stock_phrases": stock, "no_contractions": no_contr,
        "transition_heavy": trans, "repeated_openers": opener_rep,
        "polished_vocab": uniform_vocab,
    }
    weights = {"uniform_rhythm": .28, "stock_phrases": .25, "no_contractions": .15,
               "transition_heavy": .12, "repeated_openers": .10, "polished_vocab": .10}
    score = round(100 * sum(signals[k] * weights[k] for k in signals))

    labels = [(25, "Likely human"), (50, "Mixed / unclear"), (75, "Likely AI"), (101, "Very likely AI")]
    label = next(l for cap, l in labels if score < cap)

    reasons = []
    if burst > .5: reasons.append(f"Sentence lengths are very even (variation {cv:.2f}); human writing usually swings more.")
    if stock > .3: reasons.append(f"{stock_hits} stock AI phrase hit(s) found.")
    if no_contr > .6: reasons.append("Almost no contractions.")
    if trans > .4: reasons.append("Heavy use of formal transitions (however, moreover...).")
    if opener_rep > .3: reasons.append("Many sentences start with the same word.")

    per_sentence = [{"text": s, "flags": sentence_flags(s)} for s in sents]
    return Report(score, label, {k: round(v, 2) for k, v in signals.items()}, per_sentence, reasons)
