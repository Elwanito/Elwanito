<?php
/**
 * Runs only on explicit plugin deletion (not deactivation) - removes
 * custom tables and options so nothing orphaned is left behind.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}elwanito_progress" ); // phpcs:ignore
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}elwanito_topics_queue" ); // phpcs:ignore

$options = array(
	'elwanito_anthropic_api_key',
	'elwanito_model_outline',
	'elwanito_model_bulk',
	'elwanito_certification_focus',
	'elwanito_safety_policy',
	'elwanito_auto_publish',
	'elwanito_daily_automation_enabled',
	'elwanito_daily_generation_limit',
	'elwanito_db_version',
);
foreach ( $options as $option ) {
	delete_option( $option );
}
