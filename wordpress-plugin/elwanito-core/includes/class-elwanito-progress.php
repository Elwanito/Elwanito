<?php
/**
 * Progress tracking that works for guests (no account) via a short resume
 * code, and transparently for logged-in users via their user ID. A guest's
 * code is stored client-side (localStorage) and can be typed into any
 * other device/browser to pull the same progress back down.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elwanito_Progress {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_shortcode( 'elwanito_resume', array( $this, 'render_resume_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets() {
		wp_register_script( 'elwanito-progress', ELWANITO_PLUGIN_URL . 'assets/progress.js', array(), ELWANITO_VERSION, true );
		wp_localize_script(
			'elwanito-progress',
			'elwanitoProgress',
			array( 'restUrl' => esc_url_raw( rest_url( 'elwanito/v1/progress' ) ) )
		);
		wp_enqueue_script( 'elwanito-progress' );
	}

	public function register_routes() {
		register_rest_route(
			'elwanito/v1',
			'/progress/(?P<code>[A-Za-z0-9]{6,12})',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_progress' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'code' => array(
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && preg_match( '/^[A-Za-z0-9]{6,12}$/', $param );
						},
					),
				),
			)
		);

		register_rest_route(
			'elwanito/v1',
			'/progress',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_progress' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function generate_unique_code() {
		global $wpdb;
		$table = Elwanito_DB::progress_table();
		do {
			$code = strtoupper( substr( bin2hex( random_bytes( 5 ) ), 0, 8 ) );
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE resume_code = %s", $code ) ); // phpcs:ignore
		} while ( $exists );
		return $code;
	}

	public function get_progress( WP_REST_Request $request ) {
		global $wpdb;
		$table = Elwanito_DB::progress_table();
		$code  = strtoupper( $request['code'] );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE resume_code = %s", $code ) ); // phpcs:ignore

		if ( ! $row ) {
			return new WP_REST_Response( array( 'found' => false ), 200 );
		}

		return new WP_REST_Response(
			array(
				'found'    => true,
				'code'     => $row->resume_code,
				'progress' => json_decode( $row->progress_data, true ),
			),
			200
		);
	}

	/**
	 * Body: { code?: string, lesson_id: int, status: "complete" }
	 * If no code is supplied, a new resume code is generated and returned.
	 */
	public function save_progress( WP_REST_Request $request ) {
		global $wpdb;
		$table = Elwanito_DB::progress_table();

		$params    = $request->get_json_params();
		$code      = isset( $params['code'] ) ? strtoupper( sanitize_text_field( $params['code'] ) ) : '';
		$lesson_id = isset( $params['lesson_id'] ) ? absint( $params['lesson_id'] ) : 0;

		if ( ! $lesson_id ) {
			return new WP_REST_Response( array( 'error' => 'lesson_id is required' ), 400 );
		}

		$now = current_time( 'mysql' );
		$row = null;

		if ( $code && preg_match( '/^[A-Z0-9]{6,12}$/', $code ) ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE resume_code = %s", $code ) ); // phpcs:ignore
		}

		$progress_data = $row ? (array) json_decode( $row->progress_data, true ) : array();
		if ( ! isset( $progress_data['completed_lessons'] ) ) {
			$progress_data['completed_lessons'] = array();
		}
		if ( ! in_array( $lesson_id, $progress_data['completed_lessons'], true ) ) {
			$progress_data['completed_lessons'][] = $lesson_id;
		}

		$user_id = get_current_user_id() ?: null;

		if ( $row ) {
			$wpdb->update(
				$table,
				array( 'progress_data' => wp_json_encode( $progress_data ), 'updated_at' => $now, 'user_id' => $user_id ),
				array( 'id' => $row->id )
			);
			$final_code = $row->resume_code;
		} else {
			$final_code = self::generate_unique_code();
			$wpdb->insert(
				$table,
				array(
					'resume_code'   => $final_code,
					'user_id'       => $user_id,
					'progress_data' => wp_json_encode( $progress_data ),
					'created_at'    => $now,
					'updated_at'    => $now,
				)
			);
		}

		return new WP_REST_Response(
			array( 'code' => $final_code, 'progress' => $progress_data ),
			200
		);
	}

	public function render_resume_shortcode() {
		ob_start();
		?>
		<div class="elwanito-resume-widget">
			<p>Your progress is saved automatically to this device. To continue on another device, enter your resume code there:</p>
			<p>Your code: <strong class="elwanito-resume-code">—</strong></p>
			<form class="elwanito-resume-form">
				<input type="text" placeholder="Enter resume code" maxlength="12" class="elwanito-resume-input">
				<button type="submit">Resume</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}
}
