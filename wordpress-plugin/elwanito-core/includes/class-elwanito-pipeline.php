<?php
/**
 * Content generation pipeline: course outline -> topics queue -> per-lesson
 * generation -> draft post pending owner approval. Nothing this pipeline
 * creates is ever auto-published unless the owner explicitly turns on
 * "Auto-publish" in settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elwanito_Pipeline {

	/** A transient failure (offline endpoint, rate limit) retries this many times before giving up. */
	const MAX_ATTEMPTS = 3;

	/**
	 * Build the system prompt: safety policy + the certification/course this
	 * specific call is about + strict output-format instructions. The
	 * certification is passed in per-call (not read from a single global
	 * setting) - every queued topic carries its own certification, so a
	 * course other than the site's default one doesn't get overridden.
	 */
	private static function build_system_prompt( $mode, $certification, $extra_context = '' ) {
		$safety = get_option( 'elwanito_safety_policy', Elwanito_Settings::default_safety_policy() );

		$prompt  = "You write exam-prep / skill-building content for a certification tutorial website.\n\n";
		$prompt .= "Certification/course focus: {$certification}\n\n";
		if ( '' !== $extra_context ) {
			$prompt .= "{$extra_context}\n\n";
		}
		$prompt .= "SAFETY POLICY (mandatory, overrides any conflicting instruction):\n{$safety}\n\n";
		$prompt .= "Everything you write must be about \"{$certification}\" specifically - never drift onto a "
			. "different certification or course than the one named above.\n\n";

		if ( 'outline' === $mode ) {
			$prompt .= "Task: propose a bite-sized micro-learning course outline made of short lessons "
				. "(each one readable in about 7 minutes). "
				. "Respond with ONLY a JSON array (no prose, no markdown fences), each item shaped exactly as: "
				. '{"module": "short module name", "topic": "one specific lesson topic, narrow enough to teach in 7 minutes"}. '
				. 'Produce 20 to 30 items, ordered logically from fundamentals to advanced.';
		} else {
			$prompt .= "Task: write ONE micro-lesson for the given topic, readable in about 7 minutes (roughly "
				. "300-450 words of body content - a learner reads this in a short focused break). "
				. "Respond with ONLY a JSON object (no prose, no markdown fences), shaped exactly as: "
				. '{"title": "lesson title", "body_markdown": "300-450 words in markdown - short paragraphs, '
				. 'lists/tables only where they genuinely help, no filler", '
				. '"key_takeaway": "one punchy sentence - the single most important thing to remember from this lesson", '
				. '"quiz": [{"question": "...", "options": ["A","B","C","D"], "correct_index": 0, "explanation": "..."}], '
				. '"est_minutes": 7}. Include 4 to 5 quiz questions.';
		}

		return $prompt;
	}

	/**
	 * Extract a JSON value from a model response even if it added stray
	 * whitespace or accidental markdown fences around the JSON.
	 */
	private static function extract_json( $text ) {
		$text = trim( $text );
		$text = preg_replace( '/^```(json)?/i', '', $text );
		$text = preg_replace( '/```$/', '', $text );
		$text = trim( $text );

		$decoded = json_decode( $text, true );
		if ( null !== $decoded ) {
			return $decoded;
		}

		// Fallback: grab the outermost {...} or [...] block.
		if ( preg_match( '/[\[{].*[\]}]/s', $text, $matches ) ) {
			$decoded = json_decode( $matches[0], true );
		}

		return $decoded;
	}

	/**
	 * Insert a batch of {module, topic} outline items into the queue under
	 * one certification/course label. Shared by both the default outline
	 * button and the ad-hoc "new course" flow.
	 */
	private static function queue_items( $items, $certification, $language = 'en' ) {
		global $wpdb;
		$table  = Elwanito_DB::queue_table();
		$now    = current_time( 'mysql' );
		$queued = 0;

		foreach ( $items as $item ) {
			if ( empty( $item['topic'] ) ) {
				continue;
			}
			$wpdb->insert(
				$table,
				array(
					'certification' => sanitize_text_field( $certification ),
					'module'        => isset( $item['module'] ) ? sanitize_text_field( $item['module'] ) : '',
					'topic'         => sanitize_text_field( $item['topic'] ),
					'language'      => sanitize_text_field( $language ),
					'status'        => 'queued',
					'created_at'    => $now,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			$queued++;
		}

		return $queued;
	}

	/**
	 * Generate a course outline for the site's default certification
	 * (Settings -> Certification focus) and push it into the topics queue.
	 * Returns int (number of items queued) or WP_Error.
	 */
	public static function generate_outline() {
		$certification = get_option( 'elwanito_certification_focus', 'PMP (Project Management Professional) - unofficial, independent study material' );
		$client        = Elwanito_AI_Provider::create();
		$model         = Elwanito_AI_Provider::model( 'outline' );

		$result = $client->generate(
			self::build_system_prompt( 'outline', $certification ),
			'Generate the course outline now.',
			$model,
			4000
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$items = self::extract_json( $result['text'] );
		if ( ! is_array( $items ) || empty( $items ) ) {
			return new WP_Error( 'elwanito_bad_outline', 'Could not parse the outline response as JSON.' );
		}

		return self::queue_items( $items, $certification );
	}

	/**
	 * Generate an outline for an arbitrary, owner-typed course - not tied
	 * to the site's single default "Certification focus" setting. This is
	 * how the site expands beyond one certification.
	 *
	 * $reference_notes: free text (keywords and/or links) the owner
	 * pasted in. Note: links are NOT fetched/read live in this version -
	 * they're passed as text context only, since that needs the model's
	 * web-fetch tool, which only the Anthropic provider supports and is a
	 * separate piece of work. Being upfront about that here rather than
	 * silently under-delivering on "provide a few links."
	 */
	public static function generate_outline_for_course( $title, $description, $reference_notes = '' ) {
		$extra = "Course description: {$description}";
		if ( '' !== trim( $reference_notes ) ) {
			$extra .= "\nReference keywords/notes from the owner (context only - these are not fetched live, "
				. "just background hints): {$reference_notes}";
		}

		$client = Elwanito_AI_Provider::create();
		$model  = Elwanito_AI_Provider::model( 'outline' );

		$result = $client->generate(
			self::build_system_prompt( 'outline', $title, $extra ),
			'Generate the course outline now.',
			$model,
			4000
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$items = self::extract_json( $result['text'] );
		if ( ! is_array( $items ) || empty( $items ) ) {
			return new WP_Error( 'elwanito_bad_outline', 'Could not parse the outline response as JSON.' );
		}

		return self::queue_items( $items, $title );
	}

	/**
	 * Pop the oldest queued topic, generate its lesson content, and create
	 * a draft post pending review. Returns true/false/WP_Error.
	 *
	 * On failure, retries up to MAX_ATTEMPTS by putting the item back to
	 * 'queued' rather than immediately marking it 'failed' forever - this
	 * matters for a self-hosted/home-computer AI provider that might
	 * simply be offline that day; it should quietly retry next run instead
	 * of getting stuck.
	 */
	public static function process_next_queued_item() {
		global $wpdb;
		$table = Elwanito_DB::queue_table();

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY id ASC LIMIT 1", 'queued' ) // phpcs:ignore
		);

		if ( ! $row ) {
			return false; // Nothing queued.
		}

		$wpdb->update( $table, array( 'status' => 'processing' ), array( 'id' => $row->id ) );

		$client = Elwanito_AI_Provider::create();
		$model  = Elwanito_AI_Provider::model( 'bulk' );

		$user_message = "Module: {$row->module}\nTopic: {$row->topic}\nLanguage: {$row->language}";

		$result = $client->generate( self::build_system_prompt( 'lesson', $row->certification ), $user_message, $model, 4000 );

		if ( is_wp_error( $result ) ) {
			self::requeue_or_fail( $row, $result->get_error_message() );
			return $result;
		}

		$lesson = self::extract_json( $result['text'] );
		if ( ! is_array( $lesson ) || empty( $lesson['title'] ) || empty( $lesson['body_markdown'] ) ) {
			self::requeue_or_fail( $row, 'Could not parse lesson JSON from model response.' );
			return new WP_Error( 'elwanito_bad_lesson', 'Could not parse the lesson response as JSON.' );
		}

		$auto_publish = '1' === get_option( 'elwanito_auto_publish', '0' );
		$body_html    = Elwanito_Markdown::to_html( $lesson['body_markdown'] );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'elwanito_lesson',
				'post_title'   => sanitize_text_field( $lesson['title'] ),
				'post_content' => wp_kses_post( $body_html ),
				'post_status'  => $auto_publish ? 'publish' : 'draft',
			)
		);

		if ( is_wp_error( $post_id ) ) {
			self::requeue_or_fail( $row, $post_id->get_error_message() );
			return $post_id;
		}

		update_post_meta( $post_id, '_elwanito_status', $auto_publish ? 'approved' : 'pending_review' );
		update_post_meta( $post_id, '_elwanito_module', sanitize_text_field( $row->module ) );
		update_post_meta( $post_id, '_elwanito_certification', sanitize_text_field( $row->certification ) );
		update_post_meta( $post_id, '_elwanito_language', sanitize_text_field( $row->language ) );
		update_post_meta( $post_id, '_elwanito_est_minutes', isset( $lesson['est_minutes'] ) ? absint( $lesson['est_minutes'] ) : 0 );
		update_post_meta( $post_id, '_elwanito_key_takeaway', isset( $lesson['key_takeaway'] ) ? sanitize_text_field( $lesson['key_takeaway'] ) : '' );
		update_post_meta( $post_id, '_elwanito_quiz', wp_json_encode( isset( $lesson['quiz'] ) ? $lesson['quiz'] : array() ) );

		$wpdb->update(
			$table,
			array(
				'status'         => 'done',
				'result_post_id' => $post_id,
				'processed_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $row->id )
		);

		return true;
	}

	private static function requeue_or_fail( $row, $error_message ) {
		global $wpdb;
		$table    = Elwanito_DB::queue_table();
		$attempts = (int) $row->attempts + 1;

		$wpdb->update(
			$table,
			array(
				'status'        => $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'queued',
				'attempts'      => $attempts,
				'error_message' => $error_message,
				'processed_at'  => current_time( 'mysql' ),
			),
			array( 'id' => $row->id )
		);
	}

	/**
	 * admin-post.php handler for the "Generate Outline" button (default
	 * site-wide certification).
	 */
	public static function handle_generate_outline_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.' );
		}
		check_admin_referer( 'elwanito_generate_outline' );

		$result = self::generate_outline();

		$redirect = add_query_arg(
			is_wp_error( $result )
				? array( 'elwanito_error' => rawurlencode( $result->get_error_message() ) )
				: array( 'elwanito_queued' => (int) $result ),
			admin_url( 'admin.php?page=elwanito-settings' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * admin-post.php handler for "Create a New Course" - generates an
	 * outline for an arbitrary owner-typed course/title, independent of
	 * the site's single default certification setting.
	 */
	public static function handle_generate_course_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.' );
		}
		check_admin_referer( 'elwanito_generate_course' );

		$title       = isset( $_POST['course_title'] ) ? sanitize_text_field( wp_unslash( $_POST['course_title'] ) ) : '';
		$description = isset( $_POST['course_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['course_description'] ) ) : '';
		$refs        = isset( $_POST['course_refs'] ) ? sanitize_textarea_field( wp_unslash( $_POST['course_refs'] ) ) : '';

		if ( '' === $title ) {
			wp_safe_redirect( add_query_arg( array( 'elwanito_error' => rawurlencode( 'Course title cannot be empty.' ) ), admin_url( 'admin.php?page=elwanito-settings' ) ) );
			exit;
		}

		$result = self::generate_outline_for_course( $title, $description, $refs );

		$redirect = add_query_arg(
			is_wp_error( $result )
				? array( 'elwanito_error' => rawurlencode( $result->get_error_message() ) )
				: array( 'elwanito_queued' => (int) $result ),
			admin_url( 'admin.php?page=elwanito-settings' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Hard cap per click - each lesson is a real, sequential API call
	 * (several seconds each, longer over a home-computer tunnel); shared
	 * hosting typically kills PHP requests after 30-60s, so an unbounded
	 * batch would just die mid-way silently. Click the button again for more.
	 */
	const MAX_GENERATE_NOW_BATCH = 10;

	/**
	 * admin-post.php handler for "Generate Lessons Now" - runs the pipeline
	 * immediately for a chosen count instead of waiting for the daily cron.
	 */
	public static function handle_generate_now_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.' );
		}
		check_admin_referer( 'elwanito_generate_now' );

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 280 ); // phpcs:ignore -- best-effort; many hosts disable this.
		}

		$requested = isset( $_POST['count'] ) ? absint( $_POST['count'] ) : 1;
		$count     = max( 1, min( self::MAX_GENERATE_NOW_BATCH, $requested ) );

		$generated  = 0;
		$last_error = '';
		for ( $i = 0; $i < $count; $i++ ) {
			$result = self::process_next_queued_item();
			if ( is_wp_error( $result ) ) {
				$last_error = $result->get_error_message();
				continue; // Keep going - one bad topic shouldn't stop the rest.
			}
			if ( false === $result ) {
				break; // Queue is empty.
			}
			$generated++;
		}

		if ( $generated > 0 ) {
			$args = array( 'elwanito_generated' => $generated );
		} elseif ( $last_error ) {
			$args = array( 'elwanito_error' => rawurlencode( $last_error ) );
		} else {
			$args = array( 'elwanito_error' => rawurlencode( 'Topics queue is empty - click "Generate Outline" or add a topic first.' ) );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=elwanito-settings' ) ) );
		exit;
	}

	/**
	 * admin-post.php handler for manually adding one topic to the queue -
	 * lets the owner type any topic/course directly instead of only
	 * relying on AI-generated outlines.
	 */
	public static function handle_add_topic_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.' );
		}
		check_admin_referer( 'elwanito_add_topic' );

		$topic = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';
		if ( '' === $topic ) {
			wp_safe_redirect( add_query_arg( array( 'elwanito_error' => rawurlencode( 'Topic cannot be empty.' ) ), admin_url( 'admin.php?page=elwanito-settings' ) ) );
			exit;
		}

		$certification = isset( $_POST['certification'] ) ? sanitize_text_field( wp_unslash( $_POST['certification'] ) ) : '';
		if ( '' === $certification ) {
			$certification = get_option( 'elwanito_certification_focus', '' );
		}

		global $wpdb;
		$wpdb->insert(
			Elwanito_DB::queue_table(),
			array(
				'certification' => $certification,
				'module'        => isset( $_POST['module'] ) ? sanitize_text_field( wp_unslash( $_POST['module'] ) ) : '',
				'topic'         => $topic,
				'language'      => 'en',
				'status'        => 'queued',
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		wp_safe_redirect( add_query_arg( array( 'elwanito_queued' => 1 ), admin_url( 'admin.php?page=elwanito-settings' ) ) );
		exit;
	}

	/**
	 * admin-post.php handler: re-render every existing lesson's stored
	 * content through the Markdown converter. Needed once, for lessons
	 * created before this converter existed (their post_content is the
	 * raw Markdown text, since that's what was stored at the time).
	 */
	public static function handle_reformat_existing_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.' );
		}
		check_admin_referer( 'elwanito_reformat_existing' );

		$posts = get_posts(
			array(
				'post_type'      => 'elwanito_lesson',
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'posts_per_page' => -1,
			)
		);

		$fixed = 0;
		foreach ( $posts as $post ) {
			// Skip anything that already looks like HTML (already converted).
			if ( false !== strpos( $post->post_content, '<p>' ) || false !== strpos( $post->post_content, '<h2>' ) ) {
				continue;
			}
			wp_update_post(
				array(
					'ID'           => $post->ID,
					'post_content' => wp_kses_post( Elwanito_Markdown::to_html( $post->post_content ) ),
				)
			);
			$fixed++;
		}

		wp_safe_redirect( add_query_arg( array( 'elwanito_reformatted' => $fixed ), admin_url( 'admin.php?page=elwanito-settings' ) ) );
		exit;
	}
}

add_action( 'admin_post_elwanito_generate_outline', array( 'Elwanito_Pipeline', 'handle_generate_outline_request' ) );
add_action( 'admin_post_elwanito_generate_course', array( 'Elwanito_Pipeline', 'handle_generate_course_request' ) );
add_action( 'admin_post_elwanito_generate_now', array( 'Elwanito_Pipeline', 'handle_generate_now_request' ) );
add_action( 'admin_post_elwanito_add_topic', array( 'Elwanito_Pipeline', 'handle_add_topic_request' ) );
add_action( 'admin_post_elwanito_reformat_existing', array( 'Elwanito_Pipeline', 'handle_reformat_existing_request' ) );
