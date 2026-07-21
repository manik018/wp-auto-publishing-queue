<?php
/**
 * General helper methods.
 *
 * @package WP_Auto_Publishing_Queue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared helper methods.
 */
class WPAPQ_Helper {

	/**
	 * Get the configured WordPress timezone object.
	 *
	 * @return DateTimeZone
	 */
	public static function get_timezone() {
		return wp_timezone();
	}

	/**
	 * Get the current DateTimeImmutable in the configured WordPress timezone.
	 *
	 * @return DateTimeImmutable
	 */
	public static function get_current_datetime() {
		return current_datetime();
	}

	/**
	 * Get the current timestamp using the configured WordPress timezone.
	 *
	 * @return int
	 */
	public static function get_current_timestamp() {
		return self::get_current_datetime()->getTimestamp();
	}

	/**
	 * Get the formatted current WordPress date and time.
	 *
	 * @return string
	 */
	public static function get_formatted_current_datetime() {
		return wp_date(
			'F j, Y g:i A',
			self::get_current_timestamp(),
			self::get_timezone()
		);
	}

	/**
	 * Format a stored WordPress-local MySQL datetime.
	 *
	 * @param string|null $mysql_datetime MySQL datetime.
	 * @param string      $fallback Fallback text.
	 * @return string
	 */
	public static function format_mysql_datetime( $mysql_datetime, $fallback = '' ) {
		if ( empty( $mysql_datetime ) || ! is_string( $mysql_datetime ) ) {
			return $fallback;
		}

		$datetime = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $mysql_datetime, self::get_timezone() );
		$errors   = DateTimeImmutable::getLastErrors();

		if ( false === $datetime || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ) {
			return $fallback;
		}

		return wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$datetime->getTimestamp(),
			self::get_timezone()
		);
	}

	/**
	 * Get the WordPress administration email address.
	 *
	 * @return string
	 */
	public static function get_admin_email() {
		$admin_email = get_option( 'admin_email' );

		if ( ! is_string( $admin_email ) ) {
			return '';
		}

		return sanitize_email( $admin_email );
	}
}
