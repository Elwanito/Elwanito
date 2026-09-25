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
				<div class="notice notice-success"><p>Topic(s) added to the queue: <?php echo (int) $_GET['elwanito_queued']; ?>.</p></div>
			<?php elseif ( isset( $_GET['elwanito_generated'] ) ) : ?>
				<div class="notice notice-success"><p><?php echo (int) $_GET['elwanito_generated']; ?> lesson(s) generated - check your site or the <a href="<?php echo esc_url( admin_url( 'admin.php?page=elwanito-review' ) ); ?>">Review Queue</a>.</p></div>
			<?php elseif ( isset( $_GET['elwanito_reformatted'] ) ) : ?>
				<div class="notice notice-success"><p>Reformatted <?php echo (int) $_GET['elwanito_reformatted']; ?> existing lesson(s) to proper HTML.</p></div>
			<?php elseif ( isset( $_GET['elwanito_error'] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( wp_unslash( $_GET['elwanito_error'] ) ); ?></p></div>
			<?php endif; ?>

			<hr>
			<h2>Automation status</h2>
			<?php $status = Elwanito_Cron::status(); ?>
			<table class="widefat" style="max-width:420px;">
				<tr><td>Daily schedule registered?</td><td><?php echo $status['scheduled'] ? '✅ yes' : '❌ no'; ?></td></tr>
				<tr><td>Next scheduled run</td><td><?php echo esc_html( $status['next_run'] ?: '—' ); ?></td></tr>
				<tr><td>Last time wp-cron.php actually fired</td><td><?php echo esc_html( $status['last_check'] ?: 'never yet - your cPanel cron job is likely not reaching wp-cron.php' ); ?></td></tr>
				<tr><td>Last automated generation</td><td><?php echo esc_html( $status['last_generation'] ?: 'never yet' ); ?></td></tr>
			</table>
			<p class="description">If "last time wp-cron.php fired" stays "never" after 24 hours, your cPanel Cron Job isn't actually reaching the site - re-check the exact URL and command in DEPLOY.md. In the meantime, use "Generate Lessons Now" below whenever you like - no cron required.</p>

			<hr>
			<h2>Generate a course outline now</h2>
			<p>Uses the outline model once to propose ~20-30 bite-sized lesson topics for the certification above, and adds them to the topics queue.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'elwanito_generate_outline' ); ?>
				<input type="hidden" name="action" value="elwanito_generate_outline">
				<?php submit_button( 'Generate Outline', 'secondary' ); ?>
			</form>

			<h2>Generate lessons right now</h2>
			<p>Pulls the oldest queued topics and generates them immediately - no need to wait for cron. Max <?php echo (int) Elwanito_Pipeline::MAX_GENERATE_NOW_BATCH; ?> per click (each one is a real API call); click again for more.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'elwanito_generate_now' ); ?>
				<input type="hidden" name="action" value="elwanito_generate_now">
				<label>How many? <input type="number" name="count" min="1" max="<?php echo (int) Elwanito_Pipeline::MAX_GENERATE_NOW_BATCH; ?>" value="1" style="width:80px;"></label>
				<?php submit_button( 'Generate Lessons Now', 'secondary', 'submit', false ); ?>
			</form>

			<h2>Add a topic or course manually</h2>
			<p>Type any topic directly into the queue - doesn't have to come from an AI-generated outline.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'elwanito_add_topic' ); ?>
				<input type="hidden" name="action" value="elwanito_add_topic">
				<p>
					<label>Certification/course <input type="text" name="certification" value="<?php echo esc_attr( $certification ); ?>" style="width:100%;max-width:420px;"></label>
				</p>
				<p>
					<label>Module (optional) <input type="text" name="module" placeholder="e.g. Risk Management" style="width:100%;max-width:420px;"></label>
				</p>
				<p>
					<label>Topic <input type="text" name="topic" placeholder="e.g. How to calculate expected monetary value" required style="width:100%;max-width:420px;"></label>
				</p>
				<?php submit_button( 'Add to Queue', 'secondary' ); ?>
			</form>

			<h2>Fix formatting on already-published lessons</h2>
			<p>One-time cleanup: re-renders older lessons (created before the Markdown fix) into proper HTML. Safe to run more than once - already-fixed lessons are skipped.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'elwanito_reformat_existing' ); ?>
				<input type="hidden" name="action" value="elwanito_reformat_existing">
				<?php submit_button( 'Reformat Existing Lessons', 'secondary' ); ?>
			</form>
		</div>
		<?php
	}
}
