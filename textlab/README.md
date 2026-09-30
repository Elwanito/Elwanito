# TextLab

Detect how AI-like a piece of text reads, then rewrite it in a more natural voice.

## Run
```
pip install -r requirements.txt
export LLM_API_KEY=...   # free key from Groq, OpenRouter or Gemini (see below)
uvicorn app:app --reload
```
Open http://localhost:8000. Tests: `python -m pytest tests`.

## How it works
- `detector.py`: feature-based score (sentence-rhythm variation, stock phrases, contractions,
  transitions, repeated openers, vocabulary). Also flags individual sentences.
- `humanizer.py`: asks an LLM to rewrite with a plain-voice style guide, re-scores the result,
  and retries up to 3 times feeding back what still looks machine-written. Without an API key it
  falls back to mechanical clean-up (much weaker).

## Limits
The detector is a local proxy. Scores won't match Turnitin, GPTZero, Originality.ai and so on,
and those tools change constantly and disagree with each other, so "passes all checkers" can't be
guaranteed by any tool. Use it to edit toward a natural voice, and check final text in the
checkers you care about. Don't use it to pass off work where AI use is prohibited.

## Deploy on cPanel
1. Upload the `textlab/` folder contents to e.g. `~/textlab` (File Manager or git).
2. cPanel > Setup Python App > Create Application: Python 3.9+, app root `textlab`,
   startup file `passenger_wsgi.py`, entry point `application`.
3. In that screen run `pip install -r requirements.txt` (or use the command shown at the top of the page).
4. Add environment variable `LLM_API_KEY`, then Restart.

## Free LLM providers
Set `LLM_API_KEY`, and optionally `LLM_BASE_URL` and `LLM_MODEL`:

| Provider | LLM_BASE_URL | Example LLM_MODEL |
|---|---|---|
| Groq (default) | https://api.groq.com/openai/v1 | llama-3.3-70b-versatile |
| OpenRouter | https://openrouter.ai/api/v1 | a model ending in `:free` |
| Google Gemini | https://generativelanguage.googleapis.com/v1beta/openai | gemini-2.0-flash |

Free tiers have rate limits, and free models follow the style rules less closely than large paid ones.
If the provider errors, the app falls back to the weak rule-based clean-up and says so.
