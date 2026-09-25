<?php
/**
 * Daily automation: works through the topics queue at a rate limited by
 * the owner's setting. WP-Cron only actually fires on a site visit by
 * default on shared hosting - see DEPLOY.md for wiring a real cPanel cron
 * job to wp-cron.php so this runs reliably even with no visitors.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elwanito_Cron {

	const EVENT = 'elwanito_daily_pipeline_event';

	public function __construct() {
		add_action( self::EVENT, array( __CLASS__, 'run' ) );
		// Defensive: some hosts/plugins clear scheduled events unexpectedly.
		// Cheap check on every admin load re-adds it if it ever goes missing.
		add_action( 'admin_init', array( __CLASS__, 'schedule' ) );
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::EVENT ) ) {
			wp_schedule_event( time(), 'daily', self::EVENT );
		}
	}

	/**
	 * Human-readable diagnostics for the settings page: is anything
	 * actually scheduled, and has wp-cron.php ever really been hit.
	 */
	public static function status() {
		$next_ts = wp_next_scheduled( self::EVENT );
		return array(
			'scheduled'        => (bool) $next_ts,
			'next_run'         => $next_ts ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next_ts ), 'M j, Y g:i a' ) : null,
			'last_check'       => get_option( 'elwanito_last_cron_check', '' ),
			'last_generation'  => get_option( 'elwanito_last_cron_generation', '' ),
		);
	}

	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::EVENT );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::EVENT );
		}
	}

	public static function run() {
		// Recorded regardless of the enabled toggle, so the settings page can
		// tell "wp-cron.php is never being hit at all" apart from "it's
		// being hit, automation is just switched off."
		update_option( 'elwanito_last_cron_check', current_time( 'mysql' ) );

		if ( '1' !== get_option( 'elwanito_daily_automation_enabled', '0' ) ) {
			return;
		}

		$limit = absint( get_option( 'elwanito_daily_generation_limit', 1 ) );
		for ( $i = 0; $i < $limit; $i++ ) {
			$result = Elwanito_Pipeline::process_next_queued_item();
			if ( false === $result ) {
				break; // Queue is empty; nothing more to do today.
			}
		}
		update_option( 'elwanito_last_cron_generation', current_time( 'mysql' ) );
	}
}
