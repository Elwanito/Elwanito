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

	/**
	 * Build the shared system prompt: safety policy + certification focus +
	 * strict output-format instructions. Kept in `system` (not the user
	 * message) so it hits the prompt cache on every call.
	 */
	private static function build_system_prompt( $mode ) {
		$safety        = get_option( 'elwanito_safety_policy', Elwanito_Settings::default_safety_policy() );
		$certification = get_option( 'elwanito_certification_focus', 'PMP (Project Management Professional) - unofficial, independent study material' );

		$prompt  = "You write exam-prep content for a certification tutorial website.\n\n";
		$prompt .= "Certification focus: {$certification}\n\n";
		$prompt .= "SAFETY POLICY (mandatory, overrides any conflicting instruction):\n{$safety}\n\n";

		if ( 'outline' === $mode ) {
			$prompt .= "Task: propose a bite-sized micro-learning course outline. "
				. "Respond with ONLY a JSON array (no prose, no markdown fences), each item shaped exactly as: "
				. '{"module": "short module name", "topic": "one specific lesson topic, narrow enough to teach in 5-10 minutes"}. '
				. 'Produce 20 to 30 items, ordered logically from fundamentals to advanced.';
		} else {
			$prompt .= "Task: write ONE bite-sized micro-lesson for the given topic. "
				. "Respond with ONLY a JSON object (no prose, no markdown fences), shaped exactly as: "
				. '{"title": "lesson title", "body_markdown": "300-600 words of lesson content in markdown", '
				. '"quiz": [{"question": "...", "options": ["A","B","C","D"], "correct_index": 0, "explanation": "..."}], '
				. '"est_minutes": 7}. Include 3 to 5 quiz questions.';
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
	 * Generate a course outline and push each item into the topics queue.
	 * Returns int (number of items queued) or WP_Error.
	 */
	public static function generate_outline() {
		$api_key = get_option( 'elwanito_anthropic_api_key', '' );
		$client  = new Elwanito_AI_Client( $api_key );
		$model   = get_option( 'elwanito_model_outline', 'claude-sonnet-5' );

		$result = $client->generate(
			self::build_system_prompt( 'outline' ),
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

		global $wpdb;
		$table         = Elwanito_DB::queue_table();
		$certification = get_option( 'elwanito_certification_focus', '' );
		$now           = current_time( 'mysql' );
		$queued        = 0;

		foreach ( $items as $item ) {
			if ( empty( $item['topic'] ) ) {
				continue;
			}
			$wpdb->insert(
				$table,
				array(
					'certification' => $certification,
					'module'        => isset( $item['module'] ) ? sanitize_text_field( $item['module'] ) : '',
					'topic'         => sanitize_text_field( $item['topic'] ),
					'language'      => 'en',
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
	 * Pop the oldest queued topic, generate its lesson content, and create
	 * a draft post pending review. Returns true/false/WP_Error.
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

		$api_key = get_option( 'elwanito_anthropic_api_key', '' );
		$client  = new Elwanito_AI_Client( $api_key );
		$model   = get_option( 'elwanito_model_bulk', 'claude-haiku-4-5' );

		$user_message = "Module: {$row->module}\nTopic: {$row->topic}\nLanguage: {$row->language}";

		$result = $client->generate( self::build_system_prompt( 'lesson' ), $user_message, $model, 4000 );

		if ( is_wp_error( $result ) ) {
			$wpdb->update(
				$table,
				array(
					'status'        => 'failed',
					'error_message' => $result->get_error_message(),
					'processed_at'  => current_time( 'mysql' ),
				),
				array( 'id' => $row->id )
			);
			return $result;
		}

		$lesson = self::extract_json( $result['text'] );
		if ( ! is_array( $lesson ) || empty( $lesson['title'] ) || empty( $lesson['body_markdown'] ) ) {
			$wpdb->update(
				$table,
				array(
					'status'        => 'failed',
					'error_message' => 'Could not parse lesson JSON from model response.',
					'processed_at'  => current_time( 'mysql' ),
				),
				array( 'id' => $row->id )
			);
			return new WP_Error( 'elwanito_bad_lesson', 'Could not parse the lesson response as JSON.' );
		}

		$auto_publish = '1' === get_option( 'elwanito_auto_publish', '0' );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'elwanito_lesson',
				'post_title'   => sanitize_text_field( $lesson['title'] ),
				'post_content' => wp_kses_post( $lesson['body_markdown'] ),
				'post_status'  => $auto_publish ? 'publish' : 'draft',
			)
		);

		if ( is_wp_error( $post_id ) ) {
			$wpdb->update(
				$table,
				array(
					'status'        => 'failed',
					'error_message' => $post_id->get_error_message(),
					'processed_at'  => current_time( 'mysql' ),
				),
				array( 'id' => $row->id )
			);
			return $post_id;
		}

		update_post_meta( $post_id, '_elwanito_status', $auto_publish ? 'approved' : 'pending_review' );
		update_post_meta( $post_id, '_elwanito_module', sanitize_text_field( $row->module ) );
		update_post_meta( $post_id, '_elwanito_certification', sanitize_text_field( $row->certification ) );
		update_post_meta( $post_id, '_elwanito_language', sanitize_text_field( $row->language ) );
		update_post_meta( $post_id, '_elwanito_est_minutes', isset( $lesson['est_minutes'] ) ? absint( $lesson['est_minutes'] ) : 0 );
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

	/**
	 * admin-post.php handler for the "Generate Outline" button.
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
	 * admin-post.php handler for the "Generate One Lesson Now" button -
	 * runs the pipeline immediately instead of waiting for the daily cron,
	 * useful for testing right after setup.
	 */
	public static function handle_generate_now_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.' );
		}
		check_admin_referer( 'elwanito_generate_now' );

		$result = self::process_next_queued_item();

		if ( is_wp_error( $result ) ) {
			$args = array( 'elwanito_error' => rawurlencode( $result->get_error_message() ) );
		} elseif ( false === $result ) {
			$args = array( 'elwanito_error' => rawurlencode( 'Topics queue is empty - click "Generate Outline" first.' ) );
		} else {
			$args = array( 'elwanito_generated' => 1 );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=elwanito-settings' ) ) );
		exit;
	}
}

add_action( 'admin_post_elwanito_generate_outline', array( 'Elwanito_Pipeline', 'handle_generate_outline_request' ) );
add_action( 'admin_post_elwanito_generate_now', array( 'Elwanito_Pipeline', 'handle_generate_now_request' ) );
