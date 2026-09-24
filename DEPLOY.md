# Deploying the Elwanito Core plugin

This covers getting the `elwanito-core` plugin (in `wordpress-plugin/elwanito-core/`,
also delivered to you as `elwanito-core.zip`) live on `quantaprojex.com/tutify/`.

## 0. Prerequisite

WordPress must already be installed at `quantaprojex.com/tutify/` (Softaculous,
"In Directory" = `tutify`). Nothing below works without that first.

## 1. Upload the plugin

1. In WordPress admin (`quantaprojex.com/tutify/wp-admin/`), go to **Plugins → Add New → Upload Plugin**.
2. Choose `elwanito-core.zip`, click **Install Now**, then **Activate**.
3. If upload size limits reject the zip (rare, it's ~17KB), use cPanel **File Manager** instead:
   extract the zip locally, upload the `elwanito-core` folder into
   `public_html/tutify/wp-content/plugins/` directly.

## 2. Configure

1. In wp-admin, open **Elwanito AI** in the left sidebar.
2. Paste in your Anthropic API key (from console.anthropic.com).
3. Leave the default models unless you want to change them:
   - Outline model: `claude-sonnet-5`
   - Bulk lesson model: `claude-haiku-4-5`
4. Review the safety policy text — edit it if you want to tighten anything.
5. Leave **Auto-publish** OFF for now (recommended) so every AI lesson waits in the Review Queue.
6. Save settings.

## 3. Generate your first content

1. Still on the Elwanito AI page, click **Generate Outline**. This makes one API call
   to propose ~20-30 PMP lesson topics and adds them to the internal topics queue.
2. Go to **Elwanito AI → Review Queue** — it will be empty until lessons are generated.
3. To generate lessons right now (rather than waiting for the daily cron), the
   simplest option for a PHP-only host is a **WP-CLI-free manual trigger**: visit
   `quantaprojex.com/tutify/wp-cron.php?doing_wp_cron` once with automation turned on
   (see step 4) — or ask me for a one-click "Generate Now" button if you'd rather not
   touch cron timing manually; that's a small follow-up addition.

## 4. Turn on daily automation + wire up a real cron job

WordPress's built-in "cron" only fires when someone visits the site, which is
unreliable for a low-traffic new site. Use cPanel's real cron instead:

1. In Elwanito AI settings, check **Enable daily automated generation**, set
   **Lessons per day** (start with 1-2 while validating quality/cost), save.
2. In cPanel, open **Cron Jobs**.
3. Add a new cron job:
   - **Common Settings**: Once Per Day (or a specific time you prefer)
   - **Command**:
     ```
     wget -q -O /dev/null "https://www.quantaprojex.com/tutify/wp-cron.php?doing_wp_cron"
     ```
   (If `wget` isn't available on your host, use `curl -s -o /dev/null "https://www.quantaprojex.com/tutify/wp-cron.php?doing_wp_cron"` instead — cPanel's Cron Jobs page usually shows which is available.)
4. Save. From now on, one lesson (or however many you set) generates automatically
   each day and lands in the Review Queue for you to approve from your phone.

## 5. Review from your phone

`quantaprojex.com/tutify/wp-admin/admin.php?page=elwanito-review` works in any mobile
browser — bookmark it. Each pending lesson shows a preview, an "Edit before deciding"
link, and Approve/Reject buttons.

## Not included in this first version (next iterations)

- Text-to-speech audio generation per lesson
- Slide-deck video generation (ffmpeg-based)
- PDF booklet export
- Object storage (Backblaze B2/Cloudflare R2) wiring for media offload
- A one-click "Generate Now" button (currently relies on the daily cron or the outline button)
- Multi-language generation
- A public-facing theme/skin (the plugin renders a plain default template; a real theme comes next)

These build directly on top of what's here — the pipeline, data model, and review
queue don't change shape when they're added.
