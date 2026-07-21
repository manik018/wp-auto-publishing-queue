<?php
/**
 * Plugin Name: WP Auto Publishing Queue
 * Plugin URI: https://github.com/manik018/wp-auto-publishing-queue
 * Description: Automatically publish queued draft posts on a controlled daily schedule using WordPress Cron.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Md. Fakharuddin (Manik)
 * Author URI: https://bloggingshout.com
 * Text Domain: wp-auto-publishing-queue
 *
 * @package WP_Auto_Publishing_Queue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WPAPQ_VERSION' ) ) {
	define( 'WPAPQ_VERSION', '1.0.0' );
}

if ( ! defined( 'WPAPQ_DB_VERSION' ) ) {
	define( 'WPAPQ_DB_VERSION', '1.0.0' );
}

if ( ! defined( 'WPAPQ_PLUGIN_FILE' ) ) {
	define( 'WPAPQ_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'WPAPQ_PLUGIN_DIR' ) ) {
	define( 'WPAPQ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WPAPQ_PLUGIN_URL' ) ) {
	define( 'WPAPQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WPAPQ_PLUGIN_BASENAME' ) ) {
	define( 'WPAPQ_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

require_once WPAPQ_PLUGIN_DIR . 'includes/class-wpapq-database.php';
require_once WPAPQ_PLUGIN_DIR . 'includes/class-wpapq-helper.php';
require_once WPAPQ_PLUGIN_DIR . 'includes/class-wpapq-queue.php';
require_once WPAPQ_PLUGIN_DIR . 'includes/class-wpapq-scheduler.php';
require_once WPAPQ_PLUGIN_DIR . 'includes/class-wpapq-logger.php';
require_once WPAPQ_PLUGIN_DIR . 'includes/class-wpapq-notifier.php';
require_once WPAPQ_PLUGIN_DIR . 'includes/class-wpapq-publisher.php';
require_once WPAPQ_PLUGIN_DIR . 'includes/class-wpapq-cron.php';
require_once WPAPQ_PLUGIN_DIR . 'includes/class-wpapq-post-watcher.php';
require_once WPAPQ_PLUGIN_DIR . 'includes/class-wpapq-activator.php';
require_once WPAPQ_PLUGIN_DIR . 'includes/class-wpapq-deactivator.php';
require_once WPAPQ_PLUGIN_DIR . 'includes/class-wpapq-plugin.php';

if ( ! function_exists( 'wpapq_activate' ) ) {
	/**
	 * Run plugin activation tasks.
	 */
	function wpapq_activate() {
		WPAPQ_Activator::activate();
	}
}

if ( ! function_exists( 'wpapq_deactivate' ) ) {
	/**
	 * Run plugin deactivation tasks.
	 */
	function wpapq_deactivate() {
		WPAPQ_Deactivator::deactivate();
	}
}

register_activation_hook( __FILE__, 'wpapq_activate' );
register_deactivation_hook( __FILE__, 'wpapq_deactivate' );

if ( ! function_exists( 'wpapq_run' ) ) {
	/**
	 * Start the plugin.
	 */
	function wpapq_run() {
		WPAPQ_Plugin::instance()->run();
	}
}

wpapq_run();
