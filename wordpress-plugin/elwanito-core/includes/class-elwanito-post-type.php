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
		add_shortcode( 'elwanito_lessons', array( $this, 'render_lesson_list' ) );
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

	/**
	 * [elwanito_lessons] - lists published lessons grouped by module, so an
	 * owner can drop this into any Page (including the homepage) without
	 * depending on the active theme knowing about this post type at all.
	 */
	public function render_lesson_list( $atts ) {
		$atts = shortcode_atts( array( 'limit' => 100 ), $atts );

		$lessons = get_posts(
			array(
				'post_type'      => 'elwanito_lesson',
				'post_status'    => 'publish',
				'posts_per_page' => absint( $atts['limit'] ),
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);

		if ( empty( $lessons ) ) {
			return '<p>No lessons published yet.</p>';
		}

		$grouped = array();
		foreach ( $lessons as $lesson ) {
			$module = get_post_meta( $lesson->ID, '_elwanito_module', true );
			$module = $module ? $module : 'General';
			$grouped[ $module ][] = $lesson;
		}

		ob_start();
		?>
		<div class="elwanito-lesson-list">
			<?php foreach ( $grouped as $module => $module_lessons ) : ?>
				<h3><?php echo esc_html( $module ); ?></h3>
				<ul>
					<?php foreach ( $module_lessons as $lesson ) : ?>
						<?php $minutes = get_post_meta( $lesson->ID, '_elwanito_est_minutes', true ); ?>
						<li>
							<a href="<?php echo esc_url( get_permalink( $lesson ) ); ?>"><?php echo esc_html( $lesson->post_title ); ?></a>
							<?php if ( $minutes ) : ?>
								<span class="elwanito-est-minutes"> - <?php echo esc_html( $minutes ); ?> min</span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
