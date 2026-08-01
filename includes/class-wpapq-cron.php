<?php
/**
 * Cron coordination.
 *
 * @package WP_Auto_Publishing_Queue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and processes WP-Cron queue execution.
 */
class WPAPQ_Cron {

	const SCHEDULE = 'wpapq_every_five_minutes';
	const HOOK     = 'wpapq_process_publishing_queue';
	const LOCK     = 'wpapq_processing_lock';

	/**
	 * Scheduler.
	 *
	 * @var WPAPQ_Scheduler
	 */
	private $scheduler;

	/**
	 * Publisher.
	 *
	 * @var WPAPQ_Publisher
	 */
	private $publisher;

	/**
	 * Queue.
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
	 */
	public function __construct() {
		$this->logger    = new WPAPQ_Logger();
		$this->notifier  = new WPAPQ_Notifier( $this->logger );
		$this->scheduler = new WPAPQ_Scheduler();
		$this->publisher = new WPAPQ_Publisher( $this->logger, $this->notifier );
		$this->queue     = new WPAPQ_Queue();
	}

	/**
	 * Register runtime hooks.
	 */
	public function run() {
		add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );
		add_action( self::HOOK, array( $this, 'process_queue' ) );
		add_action( 'init', array( __CLASS__, 'schedule_event' ) );
	}

	/**
	 * Add custom interval.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function add_cron_interval( $schedules ) {
		$schedules[ self::SCHEDULE ] = array(
			'interval' => 300,
			'display'  => __( 'Every five minutes', 'wp-auto-publishing-queue' ),
		);

		return $schedules;
	}

	/**
	 * Schedule the recurring event if missing.
	 */
	public static function schedule_event() {
		if ( ! self::is_enabled_setting() ) {
			wp_clear_scheduled_hook( self::HOOK );
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( WPAPQ_Helper::get_current_timestamp() + MINUTE_IN_SECONDS, self::SCHEDULE, self::HOOK );
		}
	}

	/**
	 * Clear the recurring event.
	 */
	public static function clear_event() {
		wp_clear_scheduled_hook( self::HOOK );
		delete_transient( self::LOCK );
	}

	/**
	 * Process due queue items.
	 *
	 * @return array
	 */
	public function process_queue() {
		$result = array(
			'processed' => 0,
			'published' => 0,
			'retrying'  => 0,
			'failed'    => 0,
			'skipped'   => 0,
			'disabled'  => false,
			'locked'    => false,
			'blocked'   => false,
			'released'  => 0,
		);

		if ( ! $this->is_enabled() ) {
			$result['disabled'] = true;
			return $result;
		}

		$settings = get_option( 'wpapq_settings', array() );
		$settings = is_array( $settings ) ? array_merge( WPAPQ_Activator::get_default_settings(), $settings ) : WPAPQ_Activator::get_default_settings();
		$today    = WPAPQ_Helper::get_current_datetime()->format( 'Y-m-d' );
		$released = $this->scheduler->release_blocked_schedule( $settings );

		$result['released'] = $released;

		if ( get_transient( self::LOCK ) ) {
			$result['locked'] = true;
			return $result;
		}

		if ( $this->scheduler->is_date_blocked( $today, $settings ) ) {
			$result['blocked'] = true;
			return $result;
		}

		$lock_token = wp_generate_password( 32, false, false );
		set_transient( self::LOCK, $lock_token, 4 * MINUTE_IN_SECONDS );

		if ( get_transient( self::LOCK ) !== $lock_token ) {
			$result['locked'] = true;
			return $result;
		}

		try {
			$schedule_result = $this->scheduler->generate_schedule_for_date( null, false );

			if ( is_wp_error( $schedule_result ) ) {
				$this->logger->log( null, 'cron', 'failed', 'Automatic schedule generation failed.' );
			}

			$items = $this->scheduler->get_due_items( 10 );

			foreach ( $items as $item ) {
				$result['processed']++;

				try {
					$outcome = $this->publisher->process_item( $item );
				} catch ( Throwable $exception ) {
					$this->logger->log( isset( $item->post_id ) ? absint( $item->post_id ) : null, 'cron', 'failed', 'Queue item processing failed unexpectedly.' );
					$result['failed']++;
					continue;
				}

				if ( isset( $result[ $outcome ] ) ) {
					$result[ $outcome ]++;
				} else {
					$result['skipped']++;
				}
			}

			$this->check_queue_notifications();
		} finally {
			if ( get_transient( self::LOCK ) === $lock_token ) {
				delete_transient( self::LOCK );
			}
		}

		return $result;
	}

	/**
	 * Check queue empty and low queue notifications.
	 */
	public function check_queue_notifications() {
		$active_count = $this->queue->get_active_queue_count();
		$settings     = get_option( 'wpapq_settings', array() );
		$threshold    = is_array( $settings ) && isset( $settings['low_queue_threshold'] ) ? absint( $settings['low_queue_threshold'] ) : 0;

		if ( 0 === $active_count ) {
			if ( 1 !== absint( get_option( 'wpapq_queue_empty_notified', 0 ) ) ) {
				$this->notifier->send_queue_empty_notification();
				update_option( 'wpapq_queue_empty_notified', 1 );
			}

			return;
		}

		update_option( 'wpapq_queue_empty_notified', 0 );

		if ( 0 === $threshold ) {
			update_option( 'wpapq_low_queue_notified', 0 );
			return;
		}

		if ( $active_count >= $threshold ) {
			update_option( 'wpapq_low_queue_notified', 0 );
			return;
		}

		if ( 1 !== absint( get_option( 'wpapq_low_queue_notified', 0 ) ) ) {
			$this->notifier->send_low_queue_notification( $active_count, $threshold );
			update_option( 'wpapq_low_queue_notified', 1 );
		}
	}

	/**
	 * Whether automatic publishing is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled() {
		return self::is_enabled_setting();
	}

	/**
	 * Whether automatic publishing is enabled in settings.
	 *
	 * @return bool
	 */
	private static function is_enabled_setting() {
		$settings = get_option( 'wpapq_settings', array() );

		return is_array( $settings ) && ! empty( $settings['enabled'] );
	}
}
