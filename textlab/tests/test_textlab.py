import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from fastapi.testclient import TestClient
import app as appmod
import detector
import humanizer

AI = ("Moreover, it is important to note that leveraging robust solutions is crucial. "
      "Furthermore, organizations must navigate the evolving landscape with a comprehensive approach. "
      "Additionally, seamless integration fosters pivotal outcomes across every domain. "
      "Ultimately, a holistic strategy plays a vital role in sustainable growth for all teams. "
      "In conclusion, it is important to note that these factors are crucial for success today.")
HUMAN = ("I walked the site in March and the pump station was three weeks late. Nobody had told the "
         "crew. We'd already poured the slab, so the vendor's drawings didn't match what was in the ground, "
         "and that cost about $40 thousand to fix. Honestly, I'd catch it earlier now. Short RFI turnarounds "
         "help a lot, but only if somebody actually reads them. My last job never got there.")


def test_ai_scores_higher_than_human():
    assert detector.analyze(AI).score > detector.analyze(HUMAN).score + 25


def test_short_text_not_judged():
    assert detector.analyze("Too short.").score == 0


def test_rule_based_fallback(monkeypatch):
    monkeypatch.delenv("LLM_API_KEY", raising=False)
    r = humanizer.humanize(AI)
    assert "—" not in r["text"] and "moreover" not in r["text"].lower()
    assert r["after"] <= r["before"]


def test_api():
    c = TestClient(appmod.app)
    assert c.post("/api/detect", json={"text": AI}).json()["score"] > 40
    assert c.get("/").status_code == 200
