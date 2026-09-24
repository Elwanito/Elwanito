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

1. **Confirm the cPanel cron job is real** (not just the setting toggle) —
   otherwise "10/day" silently does nothing when no one visits the site.
2. **Spot-check 5-10 published lessons for quality** — accuracy, tone, no
   safety-policy violations, no PMI trademark/endorsement claims slipping
   through. Auto-publish means nothing is gating this anymore.
3. **Ship a real theme/skin** — a simple, clean, mobile-friendly design
   independent of content (goal: skin swappable without touching content).
4. **Add TTS audio per lesson** (Amazon Polly or Google Cloud TTS — cheap).
5. **Add PDF booklet export** per module/course.
6. **Object storage wiring** (Backblaze B2 or Cloudflare R2) once media
   generation starts producing real file volume.
7. **Legal/compliance basics**: privacy policy, cookie/consent banner,
   accessible design, before applying for AdSense.
8. **Apply for Google AdSense** once there's enough real content + the
   pages above exist.
9. **Second certification track** once PMP is validated and stable.
10. **Multi-language expansion** once the single-language pipeline is proven.
11. **SEO push + trending-topics agent** (goals #13/#14 from the original ask)
    — later-stage, once there's a content base worth promoting.

## For the next Claude session picking this up cold

Say hi, then: read this file, check in on where the owner actually is versus
this list, name the single next concrete action, and push for it — don't
wait to be asked "what's next." If the owner sounds unsure or stalled,
remind them how far this has already come (a real AI content pipeline
publishing live lessons on their own domain, built without them writing code)
and give them one small, doable next task rather than the whole list at once.
