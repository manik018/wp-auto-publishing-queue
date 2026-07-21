<?php
/**
 * Database table management.
 *
 * @package WP_Auto_Publishing_Queue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database helper for custom plugin tables.
 */
class WPAPQ_Database {

	/**
	 * Get the queue table name.
	 *
	 * @return string
	 */
	public function get_queue_table() {
		global $wpdb;

		return $wpdb->prefix . 'wpapq_queue';
	}

	/**
	 * Get the logs table name.
	 *
	 * @return string
	 */
	public function get_logs_table() {
		global $wpdb;

		return $wpdb->prefix . 'wpapq_logs';
	}

	/**
	 * Create custom database tables.
	 */
	public function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$queue_table     = $this->get_queue_table();
		$logs_table      = $this->get_logs_table();

		$sql = "CREATE TABLE {$queue_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			queue_position bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'queued',
			scheduled_at datetime DEFAULT NULL,
			retry_count smallint(5) unsigned NOT NULL DEFAULT 0,
			last_error text NULL,
			added_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY post_id (post_id),
			KEY status (status),
			KEY queue_position (queue_position),
			KEY scheduled_at (scheduled_at)
		) {$charset_collate};

		CREATE TABLE {$logs_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned DEFAULT NULL,
			event_type varchar(50) NOT NULL,
			status varchar(20) NOT NULL,
			message text NULL,
			scheduled_at datetime DEFAULT NULL,
			executed_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY event_type (event_type),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Get the stored plugin database version.
	 *
	 * @return string
	 */
	public function get_database_version() {
		return (string) get_option( 'wpapq_db_version', '' );
	}
}
