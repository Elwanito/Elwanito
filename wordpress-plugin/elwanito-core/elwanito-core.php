<?php
/**
 * Plugin Name: Elwanito Core
 * Description: AI content pipeline, guest progress tracking, and an owner approval queue for the certification tutorial platform. Generates unofficial exam-prep lessons and quizzes via the Anthropic API, holds everything in a draft review queue until approved.
 * Version: 0.2.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: elwanito-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'ELWANITO_VERSION', '0.2.0' );
define( 'ELWANITO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ELWANITO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ELWANITO_PLUGIN_DIR . 'includes/class-elwanito-db.php';
require_once ELWANITO_PLUGIN_DIR . 'includes/class-elwanito-settings.php';
require_once ELWANITO_PLUGIN_DIR . 'includes/class-elwanito-ai-client.php';
require_once ELWANITO_PLUGIN_DIR . 'includes/class-elwanito-pipeline.php';
require_once ELWANITO_PLUGIN_DIR . 'includes/class-elwanito-post-type.php';
require_once ELWANITO_PLUGIN_DIR . 'includes/class-elwanito-approval-queue.php';
require_once ELWANITO_PLUGIN_DIR . 'includes/class-elwanito-progress.php';
require_once ELWANITO_PLUGIN_DIR . 'includes/class-elwanito-cron.php';

/**
 * Activation: create tables, schedule the daily pipeline event, and flush
 * rewrite rules so the elwanito_lesson post type's URLs work immediately
 * instead of 404ing until something else happens to trigger a flush.
 */
function elwanito_activate() {
	Elwanito_DB::create_tables();
	Elwanito_Cron::schedule();

	$post_type = new Elwanito_Post_Type();
	$post_type->register();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'elwanito_activate' );

/**
 * Deactivation: stop the scheduled event and flush rewrite rules again so
 * no stale elwanito_lesson rules linger. Tables/data are kept (only
 * uninstall.php removes them, on explicit plugin deletion).
 */
function elwanito_deactivate() {
	Elwanito_Cron::unschedule();
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'elwanito_deactivate' );

/**
 * Boot the plugin.
 */
function elwanito_init() {
	new Elwanito_Settings();
	new Elwanito_Post_Type();
	new Elwanito_Approval_Queue();
	new Elwanito_Progress();
	new Elwanito_Cron();
}
add_action( 'plugins_loaded', 'elwanito_init' );
