<?php
/**
 * Admin settings page: API key, model choice, safety policy, automation toggles.
 * Reachable from any phone browser at /wp-admin/admin.php?page=elwanito-settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elwanito_Settings {

	const OPTION_GROUP = 'elwanito_settings_group';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_menu() {
		add_menu_page(
			'Elwanito AI',
			'Elwanito AI',
			'manage_options',
			'elwanito-settings',
			array( $this, 'render_page' ),
			'dashicons-welcome-learn-more',
			58
		);
	}

	public static function default_safety_policy() {
		return "This site publishes independent, unofficial exam-preparation study material only.\n"
			. "Never claim official endorsement, affiliation, or certification-body authorship.\n"
			. "Never include political, religious, sexual, violent, or discriminatory content or framing.\n"
			. "Stay strictly on the certification exam subject matter.\n"
			. "If a fact is uncertain, say so rather than inventing it.\n"
			. "Write for a global audience: plain, simple English, no region-specific idioms or slang.\n"
			. "Primary audience: working professionals aged 18-40 building their careers. "
			. "Tone: practical, direct, motivating - connect concepts to real career payoff (promotions, "
			. "passing the exam, doing the job better) rather than academic or textbook-dry. "
			. "Keep paragraphs short and scannable for mobile reading in short bursts, not long-form reading.";
	}

	public function register_settings() {
		$fields = array(
			'elwanito_anthropic_api_key'         => 'sanitize_text_field',
			'elwanito_model_outline'             => 'sanitize_text_field',
			'elwanito_model_bulk'                => 'sanitize_text_field',
			'elwanito_certification_focus'       => 'sanitize_text_field',
			'elwanito_safety_policy'             => 'sanitize_textarea_field',
			'elwanito_auto_publish'              => array( $this, 'sanitize_checkbox' ),
			'elwanito_daily_automation_enabled'  => array( $this, 'sanitize_checkbox' ),
			'elwanito_daily_generation_limit'    => 'absint',
		);

		foreach ( $fields as $option => $sanitize ) {
			register_setting( self::OPTION_GROUP, $option, array( 'sanitize_callback' => $sanitize ) );
		}
	}

	public function sanitize_checkbox( $value ) {
		return $value ? '1' : '0';
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$api_key           = get_option( 'elwanito_anthropic_api_key', '' );
		$model_outline      = get_option( 'elwanito_model_outline', 'claude-sonnet-5' );
		$model_bulk         = get_option( 'elwanito_model_bulk', 'claude-haiku-4-5' );
		$certification      = get_option( 'elwanito_certification_focus', 'PMP (Project Management Professional) - unofficial, independent study material' );
		$safety_policy      = get_option( 'elwanito_safety_policy', self::default_safety_policy() );
		$auto_publish       = get_option( 'elwanito_auto_publish', '0' );
		$daily_enabled      = get_option( 'elwanito_daily_automation_enabled', '0' );
		$daily_limit        = get_option( 'elwanito_daily_generation_limit', 1 );
		?>
		<div class="wrap" style="max-width:640px;">
			<h1>Elwanito AI Settings</h1>
			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<h2>Anthropic API</h2>
				<p>
					<label for="elwanito_anthropic_api_key"><strong>API Key</strong></label><br>
					<input type="password" id="elwanito_anthropic_api_key" name="elwanito_anthropic_api_key"
						value="<?php echo esc_attr( $api_key ); ?>" style="width:100%;max-width:420px;" autocomplete="off">
					<br><span class="description">From console.anthropic.com. Stored in your WordPress database, never leaves this site.</span>
				</p>
				<p>
					<label for="elwanito_model_outline"><strong>Model for course outlines</strong> (structuring, higher quality)</label><br>
					<input type="text" id="elwanito_model_outline" name="elwanito_model_outline"
						value="<?php echo esc_attr( $model_outline ); ?>" style="width:100%;max-width:420px;">
				</p>
				<p>
					<label for="elwanito_model_bulk"><strong>Model for bulk lesson generation</strong> (cheaper, high volume)</label><br>
					<input type="text" id="elwanito_model_bulk" name="elwanito_model_bulk"
						value="<?php echo esc_attr( $model_bulk ); ?>" style="width:100%;max-width:420px;">
				</p>

				<h2>Content Focus &amp; Safety</h2>
				<p>
					<label for="elwanito_certification_focus"><strong>Certification focus</strong></label><br>
					<input type="text" id="elwanito_certification_focus" name="elwanito_certification_focus"
						value="<?php echo esc_attr( $certification ); ?>" style="width:100%;max-width:420px;">
				</p>
				<p>
					<label for="elwanito_safety_policy"><strong>Safety policy</strong> (sent with every AI request)</label><br>
					<textarea id="elwanito_safety_policy" name="elwanito_safety_policy" rows="7"
						style="width:100%;max-width:420px;"><?php echo esc_textarea( $safety_policy ); ?></textarea>
				</p>

				<h2>Automation</h2>
				<p>
					<label>
						<input type="checkbox" name="elwanito_auto_publish" value="1" <?php checked( $auto_publish, '1' ); ?>>
						Auto-publish AI-generated lessons (off = every lesson waits in the Review Queue for your approval)
					</label>
				</p>
				<p>
					<label>
						<input type="checkbox" name="elwanito_daily_automation_enabled" value="1" <?php checked( $daily_enabled, '1' ); ?>>
						Enable daily automated generation from the topics queue
					</label>
				</p>
				<p>
					<label for="elwanito_daily_generation_limit"><strong>Lessons to generate per day</strong></label><br>
					<input type="number" min="0" max="20" id="elwanito_daily_generation_limit" name="elwanito_daily_generation_limit"
						value="<?php echo esc_attr( $daily_limit ); ?>" style="width:100px;">
				</p>

				<?php submit_button( 'Save Settings' ); ?>
			</form>

			<?php if ( isset( $_GET['elwanito_queued'] ) ) : ?>
				<div class="notice notice-success"><p>Outline generated: <?php echo (int) $_GET['elwanito_queued']; ?> topics added to the queue.</p></div>
			<?php elseif ( isset( $_GET['elwanito_generated'] ) ) : ?>
				<div class="notice notice-success"><p>Lesson generated - check the <a href="<?php echo esc_url( admin_url( 'admin.php?page=elwanito-review' ) ); ?>">Review Queue</a>.</p></div>
			<?php elseif ( isset( $_GET['elwanito_error'] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( wp_unslash( $_GET['elwanito_error'] ) ); ?></p></div>
			<?php endif; ?>

			<hr>
			<h2>Generate a course outline now</h2>
			<p>Uses the outline model once to propose ~20-30 bite-sized lesson topics for the certification above, and adds them to the topics queue for the daily pipeline to work through.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'elwanito_generate_outline' ); ?>
				<input type="hidden" name="action" value="elwanito_generate_outline">
				<?php submit_button( 'Generate Outline', 'secondary' ); ?>
			</form>

			<h2>Generate one lesson right now</h2>
			<p>Pulls the oldest queued topic and generates its lesson immediately, instead of waiting for the daily cron. Good for testing.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'elwanito_generate_now' ); ?>
				<input type="hidden" name="action" value="elwanito_generate_now">
				<?php submit_button( 'Generate One Lesson Now', 'secondary' ); ?>
			</form>
		</div>
		<?php
	}
}
