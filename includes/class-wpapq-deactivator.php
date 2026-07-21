<?php
/**
 * Plugin deactivation.
 *
 * @package WP_Auto_Publishing_Queue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivation routines.
 */
class WPAPQ_Deactivator {

	/**
	 * Run deactivation tasks.
	 */
	public static function deactivate() {
		WPAPQ_Cron::clear_event();
	}
}
