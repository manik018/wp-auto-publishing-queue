<?php
/**
 * Admin notifications.
 *
 * @package WP_Auto_Publishing_Queue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends admin notification emails.
 */
class WPAPQ_Notifier {

	/**
	 * Logger.
	 *
	 * @var WPAPQ_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param WPAPQ_Logger|null $logger Logger.
	 */
	public function __construct( $logger = null ) {
		$this->logger = $logger instanceof WPAPQ_Logger ? $logger : new WPAPQ_Logger();
	}

	/**
	 * Send permanent publishing failure email.
	 *
	 * @param WP_Post $post Post.
	 * @param object  $item Queue item.
	 * @param string  $reason Sanitized failure reason.
	 * @param int     $attempts Total attempts.
	 * @return bool
	 */
	public function send_failure_notification( $post, $item, $reason, $attempts ) {
		$subject = sprintf(
			/* translators: %s: Site name. */
			__( '[%s] Auto Publishing Failed', 'wp-auto-publishing-queue' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$body = sprintf(
			"%s\n\n%s\n%s\n%s\n%s\n%s\n%s\n\n%s",
			sprintf(
				/* translators: %s: Site name. */
				__( 'Site: %s', 'wp-auto-publishing-queue' ),
				wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
			),
			sprintf(
				/* translators: %s: Post title. */
				__( 'Post: %s', 'wp-auto-publishing-queue' ),
				get_the_title( $post )
			),
			sprintf(
				/* translators: %s: Post edit URL. */
				__( 'Edit URL: %s', 'wp-auto-publishing-queue' ),
				get_edit_post_link( $post->ID, 'raw' )
			),
			sprintf(
				/* translators: %s: Scheduled time. */
				__( 'Scheduled time: %s', 'wp-auto-publishing-queue' ),
				$item->scheduled_at
			),
			sprintf(
				/* translators: %d: Total attempts. */
				__( 'Total attempts: %d', 'wp-auto-publishing-queue' ),
				absint( $attempts )
			),
			sprintf(
				/* translators: %s: Failure reason. */
				__( 'Failure reason: %s', 'wp-auto-publishing-queue' ),
				$reason
			),
			__( 'Current queue status: failed', 'wp-auto-publishing-queue' ),
			__( 'Later queued posts will continue processing.', 'wp-auto-publishing-queue' )
		);

		return $this->send( $subject, $body, 'failure' );
	}

	/**
	 * Send queue empty notification.
	 *
	 * @return bool
	 */
	public function send_queue_empty_notification() {
		$subject = sprintf(
			/* translators: %s: Site name. */
			__( '[%s] Publishing Queue Is Empty', 'wp-auto-publishing-queue' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$body    = __( 'The active publishing queue is empty.', 'wp-auto-publishing-queue' );

		return $this->send( $subject, $body, 'queue_empty' );
	}

	/**
	 * Send low queue notification.
	 *
	 * @param int $active_count Active count.
	 * @param int $threshold Threshold.
	 * @return bool
	 */
	public function send_low_queue_notification( $active_count, $threshold ) {
		$subject = sprintf(
			/* translators: %s: Site name. */
			__( '[%s] Publishing Queue Is Running Low', 'wp-auto-publishing-queue' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$body    = sprintf(
			"%s\n%s\n%s",
			sprintf(
				/* translators: %d: Active queue count. */
				__( 'Active queue count: %d', 'wp-auto-publishing-queue' ),
				absint( $active_count )
			),
			sprintf(
				/* translators: %d: Queue threshold. */
				__( 'Configured threshold: %d', 'wp-auto-publishing-queue' ),
				absint( $threshold )
			),
			admin_url( 'admin.php?page=wpapq-queue' )
		);

		return $this->send( $subject, $body, 'low_queue' );
	}

	/**
	 * Send an email to the admin address.
	 *
	 * @param string $subject Subject.
	 * @param string $body Body.
	 * @param string $context Notification context.
	 * @return bool
	 */
	private function send( $subject, $body, $context ) {
		$admin_email = WPAPQ_Helper::get_admin_email();
		$subject     = str_replace( array( "\r", "\n" ), '', sanitize_text_field( (string) $subject ) );
		$body        = str_replace( array( "\r\n", "\r" ), "\n", (string) $body );

		if ( ! is_email( $admin_email ) ) {
			$this->logger->log( null, 'notification', 'skipped', 'No valid admin email was available for ' . sanitize_key( $context ) . ' notification.' );
			return false;
		}

		$sent = wp_mail( $admin_email, $subject, $body );
		$this->logger->log( null, 'notification', $sent ? 'success' : 'failed', sanitize_key( $context ) . ' notification attempted.' );

		return (bool) $sent;
	}
}
