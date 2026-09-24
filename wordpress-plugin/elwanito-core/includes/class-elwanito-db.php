<?php
/**
 * Database table management: guest progress and the AI content topics queue.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elwanito_DB {

	public static function progress_table() {
		global $wpdb;
		return $wpdb->prefix . 'elwanito_progress';
	}

	public static function queue_table() {
		global $wpdb;
		return $wpdb->prefix . 'elwanito_topics_queue';
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$progress_table = self::progress_table();
		$sql_progress   = "CREATE TABLE {$progress_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			resume_code VARCHAR(12) NOT NULL,
			user_id BIGINT UNSIGNED NULL,
			progress_data LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY resume_code (resume_code)
		) {$charset_collate};";
		dbDelta( $sql_progress );

		$queue_table = self::queue_table();
		$sql_queue   = "CREATE TABLE {$queue_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			certification VARCHAR(191) NOT NULL,
			module VARCHAR(191) NOT NULL DEFAULT '',
			topic VARCHAR(191) NOT NULL,
			language VARCHAR(10) NOT NULL DEFAULT 'en',
			status VARCHAR(20) NOT NULL DEFAULT 'queued',
			result_post_id BIGINT UNSIGNED NULL,
			error_message TEXT NULL,
			created_at DATETIME NOT NULL,
			processed_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql_queue );

		update_option( 'elwanito_db_version', ELWANITO_VERSION );
	}
}
