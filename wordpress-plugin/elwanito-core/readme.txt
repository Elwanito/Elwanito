=== Elwanito Core ===
Contributors: elwanito
Tags: education, ai, lms, microlearning
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later

AI content pipeline, owner review queue, and guest/registered progress tracking for the Elwanito certification tutorial platform.

== Description ==

* Generates unofficial, bite-sized certification exam-prep lessons and quizzes via the Anthropic API.
* Every AI-generated lesson lands in a Review Queue (Elwanito AI -> Review Queue) for owner approval before it goes live, unless auto-publish is turned on.
* Guests can track progress without an account via a short resume code that works across devices; logged-in users are tracked by account automatically.
* Daily automation works through an admin-managed topics queue at a rate you control.

== Installation ==

1. Upload this folder to `wp-content/plugins/elwanito-core`.
2. Activate the plugin in wp-admin -> Plugins.
3. Go to Elwanito AI in the admin menu, paste in your Anthropic API key, and review the safety policy text.
4. Click "Generate Outline" once to seed the topics queue.
5. Turn on daily automation when you're ready, and set up a real server cron job hitting wp-cron.php (see DEPLOY.md in the project repository) so generation runs reliably.

== Changelog ==

= 0.1.0 =
Initial release: settings, AI client, pipeline, review queue, guest progress, daily cron.
