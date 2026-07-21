<?php
/**
 * Publishing execution.
 *
 * @package WP_Auto_Publishing_Queue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Publishes due queued posts and manages retries.
 */
class WPAPQ_Publisher {

	/**
	 * Database helper.
	 *
	 * @var WPAPQ_Database
	 */
	private $database;

	/**
	 * Queue service.
	 *
	 * @var WPAPQ_Queue
	 */
	private $queue;

	/**
	 * Logger.
	 *
	 * @var WPAPQ_Logger
	 */
	private $logger;

	/**
	 * Notifier.
	 *
	 * @var WPAPQ_Notifier
	 */
	private $notifier;

	/**
	 * Constructor.
	 *
	 * @param WPAPQ_Logger|null   $logger Logger.
	 * @param WPAPQ_Notifier|null $notifier Notifier.
	 */
	public function __construct( $logger = null, $notifier = null ) {
		$this->database = new WPAPQ_Database();
		$this->queue    = new WPAPQ_Queue();
		$this->logger   = $logger instanceof WPAPQ_Logger ? $logger : new WPAPQ_Logger();
		$this->notifier = $notifier instanceof WPAPQ_Notifier ? $notifier : new WPAPQ_Notifier( $this->logger );
	}

	/**
	 * Process one due item.
	 *
	 * @param object $item Queue item.
	 * @return string Result code.
	 */
	public function process_item( $item ) {
		global $wpdb;

		$item_id      = absint( $item->id ?? 0 );
		$post_id      = absint( $item->post_id ?? 0 );
		$scheduled_at = isset( $item->scheduled_at ) ? (string) $item->scheduled_at : null;
		$executed_at  = current_time( 'mysql' );

		if ( 0 === $item_id || 0 === $post_id ) {
			return 'skipped';
		}

		$table        = $this->database->get_queue_table();
		$current_item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$item_id
			)
		);

		if ( null === $current_item || ! in_array( $current_item->status, array( 'scheduled', 'retrying' ), true ) || empty( $current_item->scheduled_at ) || $current_item->scheduled_at > $executed_at ) {
			return 'skipped';
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || 'draft' !== $post->post_status ) {
			$this->queue->remove_post( $post_id );
			$this->logger->log( $post_id, 'cleanup', 'skipped', 'Queued item was removed because the post is no longer an eligible draft.', $scheduled_at, $executed_at );
			return 'cleanup';
		}

		$published = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $published ) ) {
			return $this->handle_failure( $current_item, $post, $this->sanitize_error_message( $published->get_error_message() ), $executed_at );
		}

		if ( 'publish' !== get_post_status( $post_id ) ) {
			return $this->handle_failure( $current_item, $post, __( 'WordPress did not confirm the post was published.', 'wp-auto-publishing-queue' ), $executed_at );
		}

		if ( $this->queue->get_queue_item( $post_id ) ) {
			$this->queue->remove_post( $post_id );
		}

		$this->logger->log( $post_id, 'publish', 'success', 'Post published successfully.', $scheduled_at, $executed_at );

		return 'published';
	}

	/**
	 * Handle a failed publish attempt.
	 *
	 * maximum_retries is interpreted as retries after the original attempt.
	 * Example: maximum_retries = 3 allows 1 original attempt + 3 retries = 4 total attempts.
	 *
	 * @param object  $item Queue item.
	 * @param WP_Post $post Post.
	 * @param string  $error Error summary.
	 * @param string  $executed_at Execution time.
	 * @return string Result code.
	 */
	private function handle_failure( $item, $post, $error, $executed_at ) {
		global $wpdb;

		$settings        = $this->get_settings();
		$retry_count     = absint( $item->retry_count ) + 1;
		$maximum_retries = $settings['maximum_retries'];
		$table           = $this->database->get_queue_table();
		$error           = $this->sanitize_error_message( $error );

		if ( $retry_count <= $maximum_retries ) {
			$retry_at = $this->get_retry_time( $settings['retry_interval'] );

			$wpdb->update(
				$table,
				array(
					'status'       => 'retrying',
					'scheduled_at' => $retry_at,
					'retry_count'  => $retry_count,
					'last_error'   => $error,
					'updated_at'   => current_time( 'mysql' ),
				),
				array( 'id' => absint( $item->id ) ),
				array( '%s', '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);

			$this->logger->log( absint( $item->post_id ), 'retry', 'failed', 'Publishing failed. Retry scheduled.', $item->scheduled_at, $executed_at );

			return 'retrying';
		}

		$wpdb->update(
			$table,
			array(
				'status'       => 'failed',
				'scheduled_at' => null,
				'retry_count'  => $retry_count,
				'last_error'   => $error,
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => absint( $item->id ) ),
			array( '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		$this->logger->log( absint( $item->post_id ), 'publish', 'failed', 'Post publishing permanently failed.', $item->scheduled_at, $executed_at );
		$this->notifier->send_failure_notification( $post, $item, $error, $retry_count );

		return 'failed';
	}

	/**
	 * Get sanitized retry settings.
	 *
	 * @return array
	 */
	private function get_settings() {
		$defaults = WPAPQ_Activator::get_default_settings();
		$settings = get_option( 'wpapq_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings = array_merge( $defaults, $settings );

		return array(
			'maximum_retries' => min( max( absint( $settings['maximum_retries'] ), 1 ), 10 ),
			'retry_interval'  => min( max( absint( $settings['retry_interval'] ), 1 ), 1440 ),
		);
	}

	/**
	 * Get a WordPress-local retry timestamp.
	 *
	 * @param int $retry_interval Retry interval in minutes.
	 * @return string
	 */
	private function get_retry_time( $retry_interval ) {
		$retry_time = WPAPQ_Helper::get_current_datetime()->modify( '+' . absint( $retry_interval ) . ' minutes' );

		return $retry_time->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Sanitize a failure message.
	 *
	 * @param string $message Message.
	 * @return string
	 */
	private function sanitize_error_message( $message ) {
		$message = sanitize_text_field( (string) $message );

		if ( '' === $message ) {
			return __( 'Publishing failed.', 'wp-auto-publishing-queue' );
		}

		return substr( $message, 0, 500 );
	}
}
