<?php
/**
 * Plugin activation.
 *
 * @package WP_Auto_Publishing_Queue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activation routines.
 */
class WPAPQ_Activator {

	/**
	 * Minimum supported PHP version.
	 *
	 * @var string
	 */
	const MINIMUM_PHP_VERSION = '7.4';

	/**
	 * Run activation tasks.
	 */
	public static function activate() {
		if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {
			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			deactivate_plugins( WPAPQ_PLUGIN_BASENAME );

			wp_die(
				esc_html__( 'WP Auto Publishing Queue requires PHP 7.4 or newer.', 'wp-auto-publishing-queue' ),
				esc_html__( 'Plugin Activation Error', 'wp-auto-publishing-queue' ),
				array( 'back_link' => true )
			);
		}

		$database = new WPAPQ_Database();
		$database->create_tables();

		self::add_default_settings();
		self::add_notification_state_options();

		update_option( 'wpapq_db_version', WPAPQ_DB_VERSION );

		$cron = new WPAPQ_Cron();
		add_filter( 'cron_schedules', array( $cron, 'add_cron_interval' ) );
		WPAPQ_Cron::schedule_event();
	}

	/**
	 * Get default plugin settings.
	 *
	 * @return array
	 */
	public static function get_default_settings() {
		return array(
			'enabled'             => 0,
			'publishing_start'    => '09:00',
			'publishing_end'      => '23:59',
			'posts_per_day'       => 3,
			'posts_per_day_mode'  => 'fixed',
			'posts_per_day_min'   => 1,
			'posts_per_day_max'   => 5,
			'minimum_gap_minutes' => 60,
			'maximum_retries'     => 3,
			'retry_interval'      => 10,
			'low_queue_threshold' => 5,
			'blocked_weekdays'    => array(),
			'blocked_dates'       => array(),
		);
	}

	/**
	 * Add default settings without overwriting existing values.
	 */
	private static function add_default_settings() {
		if ( false !== get_option( 'wpapq_settings', false ) ) {
			return;
		}

		add_option( 'wpapq_settings', self::get_default_settings() );
	}

	/**
	 * Add internal notification state options.
	 */
	private static function add_notification_state_options() {
		add_option( 'wpapq_queue_empty_notified', 0 );
		add_option( 'wpapq_low_queue_notified', 0 );
	}
}
