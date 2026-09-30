# TextLab

Detect how AI-like a piece of text reads, then rewrite it in a more natural voice.

## Run
```
pip install -r requirements.txt
export ANTHROPIC_API_KEY=...   # optional, enables the full LLM rewrite
uvicorn app:app --reload
```
Open http://localhost:8000. Tests: `python -m pytest tests`.

## How it works
- `detector.py`: feature-based score (sentence-rhythm variation, stock phrases, contractions,
  transitions, repeated openers, vocabulary). Also flags individual sentences.
- `humanizer.py`: asks Claude to rewrite with a plain-voice style guide, re-scores the result,
  and retries up to 3 times feeding back what still looks machine-written. Without an API key it
  falls back to mechanical clean-up (much weaker).

## Limits
The detector is a local proxy. Scores won't match Turnitin, GPTZero, Originality.ai and so on,
and those tools change constantly and disagree with each other, so "passes all checkers" can't be
guaranteed by any tool. Use it to edit toward a natural voice, and check final text in the
checkers you care about. Don't use it to pass off work where AI use is prohibited.
