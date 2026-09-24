# Elwanito — Global Certification Tutorial Platform: Phase 1 Plan

Status: **Draft — pending confirmation before any build work starts.**

## Decisions locked in so far

| Area | Decision |
|---|---|
| Budget | Lean: $50–150/month |
| Owner time | 2–5 hrs/week (review/approve, not hands-on building) |
| Hosting | Existing cPanel account, PHP/MySQL only (no SSH, no Node/Python app support) |
| Legal structure | Individual/sole proprietor for now (LPR, USA) |
| Platform | WordPress-based |
| Pilot content | PMP (Project Management Professional) — unofficial prep content, no PMI trademark/logo use, clearly labeled independent study material |
| Pilot language | English only |
| Pilot scope | One certification, fully built end-to-end, before replicating to others |

## Why WordPress for this specific case

Given PHP/MySQL-only hosting + lean budget + 2-5 hrs/week: WordPress gets a real, monetizable, SEO-capable site live in weeks instead of months, with a mature plugin ecosystem covering LMS/microlearning, ads, SEO, privacy/consent, and media offloading — all things we'd otherwise hand-build. The tradeoff (goal #6, skin fully independent from content) is managed by strict discipline: **all custom logic lives in a small custom plugin, never in the theme**, and content is modeled so a future theme swap — or eventual migration to a headless setup — never touches content or pipeline code.

## Architecture

```
┌─────────────────────────────────────────────┐
│ WordPress (theme = skin only, swappable)      │
│  ├─ LMS plugin (Tutor LMS, free tier)         │
│  │   → courses/lessons/quizzes = "content"    │
│  ├─ Custom plugin "elwanito-core" (ours)      │
│  │   ├─ AI content pipeline hooks             │
│  │   ├─ Guest resume-code progress system     │
│  │   ├─ Owner mobile approval queue           │
│  │   └─ SEO/trending-topic agent hooks        │
│  ├─ Media → offloaded to S3-compatible bucket │
│  │   (Backblaze B2 / Cloudflare R2)           │
│  ├─ Ads (AdSense via Ad Inserter)             │
│  ├─ SEO (RankMath) + sitemap auto-ping        │
│  └─ Consent/privacy (Complianz or CookieYes)  │
└─────────────────────────────────────────────┘
             ▲
             │ scheduled jobs (real server cron → wp-cron.php,
             │ not WP's unreliable pseudo-cron)
             │
┌─────────────────────────────────────────────┐
│ Content pipeline (runs off-server or via     │
│ cPanel cron + PHP/CLI script)                │
│  1. Claude API (Haiku bulk / Sonnet QA /      │
│     Opus for curriculum + hard judgment)      │
│     — prompt caching + Batch API for cost     │
│  2. TTS (Amazon Polly / Google TTS, cheap)    │
│  3. ffmpeg — slide + voiceover → MP4          │
│  4. PDF booklet generation (mPDF/TCPDF)       │
│  5. Publish via WP REST API → draft status    │
│     → owner approval queue → publish          │
└─────────────────────────────────────────────┘
```

Everything the AI pipeline produces lands as a **draft** in an approval queue — nothing publishes unpublished/unreviewed while trust in the pipeline is still being established. This satisfies "owner can manage from phone" (approve/reject from a simple mobile-friendly admin page) without requiring 2-5 hrs/week of active building.

## Content safety guardrails (built into the pipeline prompts, not bolted on after)

- System prompt for every generation call includes the site's safety policy: no political, religious, sexual, violent, or discriminatory content or framing; certification content only; no claims of official endorsement by any certifying body; cite sources for any scraped factual material and only use openly-licensed (public domain, CC-BY, government/official-body public exam outlines) source data.
- A "disputed content" flag + removal workflow is part of the same custom plugin from day one, since goal #13 requires it.

## Monetization

Google AdSense to start (its own content policies already align with your safety rules, so approval is more likely, not less). Revisit Ezoic/Mediavine once there's enough traffic to qualify (typically 10k+ sessions/month) for better rates.

## Migration-readiness (goal #5 and #10)

- Media never stored on local cPanel disk — object storage from day one, so "server full" never happens and moving hosts never means re-uploading gigabytes of audio/video.
- Database: standard WordPress/MySQL — portable via any standard WP migration plugin (e.g. All-in-One WP Migration) to a VPS/cloud host later.
- Migration trigger points: cPanel disk fill-up, CPU throttling under load, or crossing ~50-100k monthly visitors → move to a VPS (DigitalOcean/Hetzner) or managed WP host, at which point SSH access unlocks the option to run the content pipeline server-side on a schedule instead of via cPanel cron.

## Budget breakdown (fits the $50–150/mo target)

| Item | Cost |
|---|---|
| Hosting (already owned) | $0 incremental |
| Object storage (Backblaze B2/Cloudflare R2) | ~$5-10/mo at MVP scale |
| Cloudflare (CDN/DNS/security) | $0 (free tier) |
| Tutor LMS | $0 (free tier) |
| RankMath SEO | $0 (free tier) |
| Complianz/CookieYes | $0 (free tier) |
| TTS (Amazon Polly/Google TTS) | ~$5-15/mo at pilot volume |
| Claude API (Haiku-heavy, cached, batched) | ~$30-60/mo at pilot volume |
| Domain (already owned) | $0 incremental |
| **Total** | **~$40-85/mo** — comfortably inside budget, leaves headroom |

## Phase 1 timeline (2–5 hrs/week pace)

- **Weeks 1-2**: WordPress + Tutor LMS + core plugins installed and configured; custom `elwanito-core` plugin skeleton (approval queue, resume-code progress); object storage wired up.
- **Weeks 3-4**: Content pipeline built and run once, manually reviewed, to generate the full PMP pilot course (lessons, quizzes, TTS audio, PDF booklet) end-to-end.
- **Week 5**: AdSense application, SEO setup, consent/privacy pages, soft launch.
- **Week 6+**: First scheduled automation run (one new lesson/page per week or trending-topic scan), owner reviews from phone via the approval queue.

## Open items before build work starts

See the accompanying message for the specific questions (domain name, WordPress install status, API key availability).
