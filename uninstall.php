<?php
/**
 * Plugin uninstall handling.
 *
 * @package WP_Auto_Publishing_Queue
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'wpapq_process_publishing_queue' );
delete_transient( 'wpapq_processing_lock' );

if ( ! defined( 'WPAPQ_DELETE_DATA_ON_UNINSTALL' ) || true !== WPAPQ_DELETE_DATA_ON_UNINSTALL ) {
	return;
}

global $wpdb;

delete_option( 'wpapq_settings' );
delete_option( 'wpapq_db_version' );
delete_option( 'wpapq_queue_empty_notified' );
delete_option( 'wpapq_low_queue_notified' );

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpapq_queue" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpapq_logs" );
