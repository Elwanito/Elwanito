# Elwanito Roadmap & Status

**Read this file first in any new session on this project.** It's the persistent
memory across conversations — status, decisions, and next steps live here, not
in chat history that gets lost when a session ends.

## The vision (why this exists)

A free, AI-built, ad-supported global tutorial platform for major professional
certifications (Business, Engineering, PM, IT/software), delivered as
bite-sized microlearning in major world languages, largely self-maintaining
once running. Owner (mo.elwanito@gmail.com) manages it from a phone in
spare time around a full-time job. See `PLAN.md` for the original full plan
and constraints (budget, legal, safety policy).

## Decisions log (don't re-litigate these without a reason)

- **Platform**: WordPress on existing cPanel shared hosting (PHP/MySQL only, no SSH).
- **Pilot cert**: PMP — unofficial/independent prep content only, no PMI trademark/logo use.
- **Pilot language**: English only, for now.
- **Target audience**: working professionals aged 18-40, globally, pursuing career
  advancement — not students or academics. Drives tone (practical/motivating,
  career-payoff framed, not textbook-dry), content pacing (short, mobile-scannable),
  and future design direction (modern/app-like, not "online course" aesthetic).
  Baked into the AI system prompt (`elwanito-core` safety policy default) as of
  this decision — existing owners must manually update their saved "Safety policy"
  field in Elwanito AI settings since WordPress options don't auto-update from
  a new plugin default once a value is already saved.
- **Content data model**: a custom post type (`elwanito_lesson`) owned entirely by our
  plugin, not integrated with a third-party LMS's internal schema (Tutor LMS etc.) —
  chosen because this coding environment cannot reach WordPress.org or the live
  site to verify a third-party plugin's internals, so a self-contained model is
  the correct/verifiable choice. Can layer an LMS on top later for UI polish.
- **AI client**: raw HTTP (wp_remote_post) to the Anthropic API, no SDK/Composer —
  required by PHP-only shared hosting with no SSH.
- **Auto-publish**: currently ON (owner's choice) — lessons publish immediately,
  no manual review step. The Review Queue code still exists and can be
  switched back on any time from Elwanito AI settings.
- **Budget**: lean, $50-150/month target. Current actual spend: near-zero
  (a few cents of testing). Anthropic key has $5 prepaid credit, 30-day expiry
  from whenever it was created — **check console.anthropic.com before that runs out.**
- **This coding session cannot reach the live site or any external domain
  except api.anthropic.com** — network policy of this sandbox, not a
  permissions issue. Every fix ships as a plugin file the owner uploads and
  tests themselves; there is no way around this, don't re-attempt it.

## Branding direction (new)

Owner's idea, adopted: lessons target **~3 minutes**, not 7. Working concept
name: **LRN3** ("learn in 3 [minutes]") - short, describes the mechanic
directly. "Tutify" is out (name collision found). See "Next steps" for the
concrete domain shortlist - not yet purchased, availability unverified from
this sandbox (can't reach domain registrars from here either).

## Status as of last session (2026-09-25)

Fixed in this session (all shipped as elwanito-core v0.3.0):
- **Markdown was never converted to HTML** - lessons displayed literal `**`/`-`/`|`
  characters instead of bold/lists/tables. Root cause found and fixed with a
  custom line-by-line Markdown-to-HTML converter (no Composer/SSH available
  to pull in a library) - tested against real captured API output, including
  the tricky "intro sentence immediately followed by a list, no blank line"
  case that a naive block-based converter gets wrong. A "Reformat Existing
  Lessons" button in settings re-renders lessons published before this fix.
- **Quizzes didn't grade** - only had a "mark complete" button. Now real
  radio-button questions with a "Check Answers" button that grades each
  question, shows correct/incorrect, reveals the explanation, and shows a
  score. Known limitation: this is client-side only (the correct answer sits
  in a data attribute in the page source) - fine for a free self-check quiz,
  not for anything higher-stakes; server-side grading would be the fix if
  that ever matters.
- **No mobile-friendly styling at all** - added `assets/style.css`: readable
  typography, horizontally-scrollable tables (mobile tables are otherwise a
  common breakage point), 44px+ touch targets on quiz options and buttons.
- **Cron reliability was unverifiable** - added an "Automation status" panel
  in settings showing whether the daily event is even scheduled, when it's
  next due, and the last time wp-cron.php actually fired vs. last time it
  actually generated something - lets the owner self-diagnose "cron isn't
  configured on cPanel" vs. "it's firing but automation is toggled off."
- **No way to generate more than one lesson on demand** - "Generate Lessons
  Now" takes a count (capped at 10/click - shared hosting PHP timeouts, each
  lesson is a real sequential API call - click again for more).
- **No way to type a topic directly** - "Add a topic or course manually" form
  in settings, independent of the AI-outline flow.
- Lessons retargeted to ~3 minutes (150-200 words, 3 quiz questions) instead
  of ~7 minutes, matching the LRN3 direction.

## Status as of last session

**Working end-to-end:** WordPress installed at `quantaprojex.com/tutify/` →
`elwanito-core` plugin v0.2 installed and active → Anthropic API key configured →
course outline generated → lessons auto-generating and auto-publishing →
confirmed visible/clickable on the live site via the `[elwanito_lessons]`
shortcode page. Daily automation is configured for up to 10 lessons/day
(confirm the real cPanel cron job hitting `wp-cron.php` is actually in place —
see `DEPLOY.md` step 4 — this was flagged but not explicitly re-confirmed).

**Known gaps / things to watch:**
- No real visual theme yet — site uses plain default WordPress styling.
- No TTS audio, no video, no PDF booklets yet (text lessons + quizzes only).
- No object storage wired up yet (fine at current tiny scale; matters once
  media generation starts).
- Content quality at volume hasn't been systematically reviewed — spot-check
  lessons periodically, especially now that auto-publish is on with no human
  gate.
- Only one certification (PMP), one language (English).
- No AdSense application yet, no privacy policy / consent banner yet, no
  legal pages yet — needed before real traffic/monetization.
- Anthropic API key is a personal key with $5 credit and a 30-day expiry —
  will need topping up / renewing.

## Next steps, in priority order

1. **Verify the cron fixes actually worked** — check the new "Automation
   status" panel after 24h; use "Generate Lessons Now" in the meantime,
   no need to wait on cron for content to flow.
2. **Domain decision** — pick a name/domain before building the real theme
   (theme branding depends on it). Shortlist: `lrn3.com`, `getlrn3.com`,
   `lrn3.io`, `tryLRN3.com`, `learn3x.com`, `skill3.io`. None verified
   available from this sandbox — check a registrar. LRN3 direction adopted
   from the owner's own idea (see Branding direction above).
3. **Custom "generate any course" builder** — a form: title + description +
   optional reference links/keywords → AI outline. Queued, not yet built:
   the reference-links part is worth doing properly with Claude's server-side
   web_fetch tool (lets the model actually read the provided links, not just
   see the URL text) rather than rushed — next session's first job.
4. **Images/infographics in lessons** — Claude's API doesn't generate images
   directly. Two-part plan: (a) simple inline-SVG "key takeaway" callouts
   generated from lesson content, no external API/cost — quick win; (b) real
   stock photos via a free-tier API (Unsplash or Pexels, openly-licensed,
   fits the "cite open source" requirement) — needs the owner to grab one
   more free API key, same pattern as the Anthropic key.
5. **Ship a real theme/skin** — modern, mobile-first, app-like (think career/skills
   app, not "online university course"), aimed at 18-40 working professionals,
   built around whatever domain/name gets picked — independent of content
   (goal: skin swappable without touching content).
6. **Spot-check lesson quality at the new 3-minute length** — shorter format
   is new, worth a read-through before it scales up.
7. **Add TTS audio per lesson** (Amazon Polly or Google Cloud TTS — cheap).
8. **Add PDF booklet export** per module/course.
9. **Object storage wiring** (Backblaze B2 or Cloudflare R2) once media
   generation starts producing real file volume.
10. **Legal/compliance basics**: privacy policy, cookie/consent banner,
    accessible design, before applying for AdSense.
11. **Apply for Google AdSense** once there's enough real content + the
    pages above exist.
12. **Second certification track** once PMP is validated and stable.
13. **Multi-language expansion** once the single-language pipeline is proven.
14. **SEO push + trending-topics agent** (goals #13/#14 from the original ask)
    — later-stage, once there's a content base worth promoting.

## For the next Claude session picking this up cold

Say hi, then: read this file, check in on where the owner actually is versus
this list, name the single next concrete action, and push for it — don't
wait to be asked "what's next." If the owner sounds unsure or stalled,
remind them how far this has already come (a real AI content pipeline
publishing live lessons on their own domain, built without them writing code)
and give them one small, doable next task rather than the whole list at once.
