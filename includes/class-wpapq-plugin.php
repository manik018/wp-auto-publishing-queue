<?php
/**
 * Main plugin bootstrap.
 *
 * @package WP_Auto_Publishing_Queue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
class WPAPQ_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var WPAPQ_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return WPAPQ_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {}

	/**
	 * Register plugin hooks.
	 */
	public function run() {
		$cron = new WPAPQ_Cron();
		$cron->run();

		$post_watcher = new WPAPQ_Post_Watcher();
		$post_watcher->run();

		if ( is_admin() ) {
			require_once WPAPQ_PLUGIN_DIR . 'admin/class-wpapq-queue-page.php';
			require_once WPAPQ_PLUGIN_DIR . 'admin/class-wpapq-logs-page.php';
			require_once WPAPQ_PLUGIN_DIR . 'admin/class-wpapq-admin.php';

			$admin = new WPAPQ_Admin();
			$admin->run();
		}
	}
}
