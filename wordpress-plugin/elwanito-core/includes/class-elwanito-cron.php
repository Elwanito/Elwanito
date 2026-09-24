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
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::EVENT ) ) {
			wp_schedule_event( time(), 'daily', self::EVENT );
		}
	}

	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::EVENT );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::EVENT );
		}
	}

	public static function run() {
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
	}
}
