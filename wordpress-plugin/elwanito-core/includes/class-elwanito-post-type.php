<?php
/**
 * The 'elwanito_lesson' post type: a self-contained micro-lesson (body +
 * quiz), independent of any specific theme or LMS plugin so the visual
 * skin can change freely without touching content (goal: design/content
 * separation). A theme can override the template by copying
 * single-elwanito_lesson.php into itself; otherwise this class renders a
 * sensible default.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elwanito_Post_Type {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_filter( 'the_content', array( $this, 'append_quiz' ) );
	}

	public function register() {
		register_post_type(
			'elwanito_lesson',
			array(
				'label'        => 'Lessons',
				'public'       => true,
				'show_in_rest' => true,
				'has_archive'  => 'lessons',
				'rewrite'      => array( 'slug' => 'lesson' ),
				'supports'     => array( 'title', 'editor' ),
				'menu_icon'    => 'dashicons-welcome-learn-more',
			)
		);

		register_post_meta(
			'elwanito_lesson',
			'_elwanito_module',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		register_post_meta(
			'elwanito_lesson',
			'_elwanito_quiz',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		register_post_meta(
			'elwanito_lesson',
			'_elwanito_status',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
	}

	/**
	 * Render the quiz beneath the lesson body on the frontend, and expose
	 * a "mark complete" button wired to the guest/registered progress API.
	 */
	public function append_quiz( $content ) {
		if ( ! is_singular( 'elwanito_lesson' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();
		$quiz    = json_decode( get_post_meta( $post_id, '_elwanito_quiz', true ), true );

		ob_start();
		?>
		<div class="elwanito-lesson-footer">
			<?php if ( is_array( $quiz ) && ! empty( $quiz ) ) : ?>
				<h3>Check your understanding</h3>
				<ol class="elwanito-quiz">
					<?php foreach ( $quiz as $q ) : ?>
						<li>
							<p><?php echo esc_html( $q['question'] ?? '' ); ?></p>
							<ul>
								<?php foreach ( (array) ( $q['options'] ?? array() ) as $option ) : ?>
									<li><?php echo esc_html( $option ); ?></li>
								<?php endforeach; ?>
							</ul>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
			<button type="button" class="elwanito-mark-complete" data-lesson-id="<?php echo esc_attr( $post_id ); ?>">
				Mark lesson complete
			</button>
		</div>
		<?php
		return $content . ob_get_clean();
	}
}
