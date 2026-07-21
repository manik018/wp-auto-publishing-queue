<?php
/**
 * Daily schedule generation.
 *
 * @package WP_Auto_Publishing_Queue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates publishing schedules for queued draft posts.
 */
class WPAPQ_Scheduler {

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
	 * Logger service.
	 *
	 * @var WPAPQ_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->database = new WPAPQ_Database();
		$this->queue    = new WPAPQ_Queue();
		$this->logger   = new WPAPQ_Logger();
	}

	/**
	 * Generate a schedule for a date.
	 *
	 * @param string|null $date Date in Y-m-d format, or null for today.
	 * @param bool        $force Whether to clear and regenerate the selected date.
	 * @return array|WP_Error
	 */
	public function generate_schedule_for_date( $date = null, $force = false ) {
		global $wpdb;

		$date = $this->parse_date( $date );

		if ( is_wp_error( $date ) ) {
			return $date;
		}

		if ( $this->is_past_date( $date ) && ! $force ) {
			return new WP_Error( 'wpapq_past_date', __( 'Past dates cannot be scheduled unless force regeneration is requested.', 'wp-auto-publishing-queue' ) );
		}

		$settings = $this->get_settings();

		if ( is_wp_error( $settings ) ) {
			return $settings;
		}

		if ( $force ) {
			$cleared = $this->clear_schedule_for_date( $date );

			if ( is_wp_error( $cleared ) ) {
				return $cleared;
			}
		}

		$already_scheduled = $this->count_active_scheduled_for_date( $date );
		$already_published = $this->logger->count_published_success_for_date( $date );
		$daily_capacity    = $already_scheduled + $already_published;
		$remaining_daily   = max( 0, $settings['posts_per_day'] - $daily_capacity );
		$result            = array(
			'date'              => $date,
			'requested'         => $settings['posts_per_day'],
			'already_scheduled' => $already_scheduled,
			'already_published' => $already_published,
			'daily_capacity'    => $daily_capacity,
			'newly_scheduled'   => 0,
			'total_scheduled'   => $already_scheduled,
			'slots'             => array(),
		);

		if ( 0 === $remaining_daily ) {
			return $result;
		}

		$eligible_items = $this->get_eligible_queue_items( $remaining_daily );

		if ( empty( $eligible_items ) ) {
			return $result;
		}

		$window     = $this->get_effective_window( $date, $settings );
		$max_fit    = $this->get_max_slots_that_fit( $window['start'], $window['end'], $settings['minimum_gap_minutes'] );
		$slot_count = min( $remaining_daily, count( $eligible_items ), $max_fit );

		if ( 0 === $slot_count ) {
			return $result;
		}

		$slot_minutes = $this->generate_random_slot_minutes(
			$window['start'],
			$window['end'],
			$settings['minimum_gap_minutes'],
			$slot_count
		);

		$table = $this->database->get_queue_table();
		$now   = current_time( 'mysql' );

		foreach ( $slot_minutes as $index => $minute ) {
			if ( ! isset( $eligible_items[ $index ] ) ) {
				break;
			}

			$scheduled_at = $date . ' ' . $this->minutes_to_time( $minute ) . ':00';
			$item         = $eligible_items[ $index ];
			$updated      = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table}
					SET status = %s, scheduled_at = %s, retry_count = 0, last_error = NULL, updated_at = %s
					WHERE id = %d AND status = %s AND scheduled_at IS NULL",
					'scheduled',
					$scheduled_at,
					$now,
					absint( $item->id ),
					'queued'
				)
			);

			if ( false === $updated || 1 !== $updated ) {
				continue;
			}

			$result['newly_scheduled']++;
			$result['slots'][] = $scheduled_at;
		}

		$result['total_scheduled'] = $this->count_active_scheduled_for_date( $date );
		$result['daily_capacity']  = $result['total_scheduled'] + $already_published;

		return $result;
	}

	/**
	 * Get active scheduled queue rows for a date.
	 *
	 * @param string|null $date Date in Y-m-d format, or null for today.
	 * @return array|WP_Error
	 */
	public function get_scheduled_posts_for_date( $date = null ) {
		global $wpdb;

		$date = $this->parse_date( $date );

		if ( is_wp_error( $date ) ) {
			return $date;
		}

		$table = $this->database->get_queue_table();
		$range = $this->get_date_range( $date );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT q.*, p.post_title, p.post_status
				FROM {$table} q
				INNER JOIN {$wpdb->posts} p ON p.ID = q.post_id
				WHERE q.status IN (%s, %s)
					AND q.scheduled_at >= %s
					AND q.scheduled_at <= %s
				ORDER BY q.scheduled_at ASC, q.id ASC",
				'scheduled',
				'retrying',
				$range['start'],
				$range['end']
			)
		);
	}

	/**
	 * Count active scheduled rows for a date.
	 *
	 * @param string|null $date Date in Y-m-d format, or null for today.
	 * @return int
	 */
	public function count_scheduled_posts_for_date( $date = null ) {
		$date = $this->parse_date( $date );

		if ( is_wp_error( $date ) ) {
			return 0;
		}

		return $this->count_active_scheduled_for_date( $date );
	}

	/**
	 * Clear a future active schedule for a post without deleting the queue row.
	 *
	 * @param int $post_id Post ID.
	 * @return bool|WP_Error
	 */
	public function clear_future_schedule_for_post( $post_id ) {
		global $wpdb;

		$post_id = absint( $post_id );

		if ( 0 === $post_id ) {
			return new WP_Error( 'wpapq_invalid_post', __( 'Invalid post.', 'wp-auto-publishing-queue' ) );
		}

		$table = $this->database->get_queue_table();
		$now   = current_time( 'mysql' );

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET status = %s, scheduled_at = NULL, retry_count = 0, last_error = NULL, updated_at = %s
				WHERE post_id = %d
					AND status IN (%s, %s)
					AND scheduled_at IS NOT NULL
					AND scheduled_at >= %s",
				'queued',
				$now,
				$post_id,
				'scheduled',
				'retrying',
				$now
			)
		);

		if ( false === $result ) {
			return new WP_Error( 'wpapq_clear_failed', __( 'The schedule could not be cleared.', 'wp-auto-publishing-queue' ) );
		}

		return $result > 0;
	}

	/**
	 * Clear active scheduled timestamps for a date.
	 *
	 * @param string|null $date Date in Y-m-d format, or null for today.
	 * @return int|WP_Error
	 */
	public function clear_schedule_for_date( $date = null ) {
		global $wpdb;

		$date = $this->parse_date( $date );

		if ( is_wp_error( $date ) ) {
			return $date;
		}

		$table = $this->database->get_queue_table();
		$range = $this->get_date_range( $date );
		$now   = current_time( 'mysql' );

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET status = %s, scheduled_at = NULL, retry_count = 0, last_error = NULL, updated_at = %s
				WHERE status IN (%s, %s)
					AND scheduled_at >= %s
					AND scheduled_at <= %s",
				'queued',
				$now,
				'scheduled',
				'retrying',
				$range['start'],
				$range['end']
			)
		);

		if ( false === $result ) {
			return new WP_Error( 'wpapq_clear_failed', __( 'The schedule could not be cleared.', 'wp-auto-publishing-queue' ) );
		}

		return absint( $result );
	}

	/**
	 * Get the earliest active scheduled item.
	 *
	 * @return object|null
	 */
	public function get_next_scheduled_item() {
		global $wpdb;

		$table = $this->database->get_queue_table();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT q.*, p.post_title, p.post_status
				FROM {$table} q
				INNER JOIN {$wpdb->posts} p ON p.ID = q.post_id
				WHERE q.status IN (%s, %s)
					AND q.scheduled_at IS NOT NULL
					AND p.post_type = %s
					AND p.post_status = %s
				ORDER BY q.scheduled_at ASC, q.id ASC
				LIMIT 1",
				'scheduled',
				'retrying',
				'post',
				'draft'
			)
		);
	}

	/**
	 * Get due active scheduled or retrying items.
	 *
	 * @param int $limit Maximum items.
	 * @return array
	 */
	public function get_due_items( $limit = 10 ) {
		global $wpdb;

		$limit = min( max( absint( $limit ), 1 ), 10 );
		$table = $this->database->get_queue_table();
		$now   = current_time( 'mysql' );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT q.*, p.post_title, p.post_status
				FROM {$table} q
				LEFT JOIN {$wpdb->posts} p ON p.ID = q.post_id
				WHERE q.status IN (%s, %s)
					AND q.scheduled_at IS NOT NULL
					AND q.scheduled_at <= %s
				ORDER BY q.scheduled_at ASC, q.id ASC
				LIMIT %d",
				'scheduled',
				'retrying',
				$now,
				$limit
			)
		);
	}

	/**
	 * Parse and validate an exact Y-m-d date.
	 *
	 * @param string|null $date Date input.
	 * @return string|WP_Error
	 */
	private function parse_date( $date ) {
		if ( null === $date ) {
			return WPAPQ_Helper::get_current_datetime()->format( 'Y-m-d' );
		}

		if ( ! is_string( $date ) || 1 !== preg_match( '/\A\d{4}-\d{2}-\d{2}\z/', $date ) ) {
			return new WP_Error( 'wpapq_invalid_date', __( 'Invalid schedule date.', 'wp-auto-publishing-queue' ) );
		}

		$datetime = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, WPAPQ_Helper::get_timezone() );
		$errors   = DateTimeImmutable::getLastErrors();

		if ( false === $datetime || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) || $datetime->format( 'Y-m-d' ) !== $date ) {
			return new WP_Error( 'wpapq_invalid_date', __( 'Invalid schedule date.', 'wp-auto-publishing-queue' ) );
		}

		return $date;
	}

	/**
	 * Get normalized scheduler settings.
	 *
	 * @return array|WP_Error
	 */
	private function get_settings() {
		$defaults = WPAPQ_Activator::get_default_settings();
		$settings = get_option( 'wpapq_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings = array_merge( $defaults, $settings );

		$start = $this->time_to_minutes( $settings['publishing_start'] );
		$end   = $this->time_to_minutes( $settings['publishing_end'] );

		if ( null === $start || null === $end || $start >= $end ) {
			return new WP_Error( 'wpapq_invalid_schedule_settings', __( 'Publishing schedule settings are invalid.', 'wp-auto-publishing-queue' ) );
		}

		$posts_per_day       = min( max( absint( $settings['posts_per_day'] ), 1 ), 100 );
		$minimum_gap_minutes = min( max( absint( $settings['minimum_gap_minutes'] ), 1 ), 1440 );

		if ( ( ( $posts_per_day - 1 ) * $minimum_gap_minutes ) > ( $end - $start ) ) {
			return new WP_Error( 'wpapq_impossible_schedule', __( 'Publishing schedule settings cannot fit the configured number of posts.', 'wp-auto-publishing-queue' ) );
		}

		return array(
			'publishing_start'    => $settings['publishing_start'],
			'publishing_end'      => $settings['publishing_end'],
			'posts_per_day'       => $posts_per_day,
			'minimum_gap_minutes' => $minimum_gap_minutes,
			'start_minutes'       => $start,
			'end_minutes'         => $end,
		);
	}

	/**
	 * Convert a strict HH:MM time to minutes after midnight.
	 *
	 * @param mixed $time Time.
	 * @return int|null
	 */
	private function time_to_minutes( $time ) {
		if ( ! is_string( $time ) || 1 !== preg_match( '/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $time ) ) {
			return null;
		}

		$parts = explode( ':', $time );

		return ( absint( $parts[0] ) * 60 ) + absint( $parts[1] );
	}

	/**
	 * Convert minutes after midnight to HH:MM.
	 *
	 * @param int $minutes Minutes.
	 * @return string
	 */
	private function minutes_to_time( $minutes ) {
		$hours   = (int) floor( $minutes / 60 );
		$minutes = $minutes % 60;

		return sprintf( '%02d:%02d', $hours, $minutes );
	}

	/**
	 * Get the selected date's effective scheduling window.
	 *
	 * @param string $date Date.
	 * @param array  $settings Settings.
	 * @return array
	 */
	private function get_effective_window( $date, $settings ) {
		$start = $settings['start_minutes'];
		$end   = $settings['end_minutes'];

		if ( $date === WPAPQ_Helper::get_current_datetime()->format( 'Y-m-d' ) ) {
			$current_minute = $this->get_current_rounded_minute();
			$start          = max( $start, $current_minute );
		}

		return array(
			'start' => $start,
			'end'   => $end,
		);
	}

	/**
	 * Get current WordPress time rounded up to the next full minute.
	 *
	 * @return int
	 */
	private function get_current_rounded_minute() {
		$now     = WPAPQ_Helper::get_current_datetime();
		$minutes = ( absint( $now->format( 'H' ) ) * 60 ) + absint( $now->format( 'i' ) );

		if ( absint( $now->format( 's' ) ) > 0 ) {
			$minutes++;
		}

		return $minutes;
	}

	/**
	 * Check whether a date is before today in WordPress timezone.
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	private function is_past_date( $date ) {
		return $date < WPAPQ_Helper::get_current_datetime()->format( 'Y-m-d' );
	}

	/**
	 * Get day start/end MySQL strings.
	 *
	 * @param string $date Date.
	 * @return array
	 */
	private function get_date_range( $date ) {
		return array(
			'start' => $date . ' 00:00:00',
			'end'   => $date . ' 23:59:59',
		);
	}

	/**
	 * Count active scheduled rows for a date.
	 *
	 * @param string $date Date.
	 * @return int
	 */
	private function count_active_scheduled_for_date( $date ) {
		global $wpdb;

		$table = $this->database->get_queue_table();
		$range = $this->get_date_range( $date );

		return absint(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*)
					FROM {$table}
					WHERE status IN (%s, %s)
						AND scheduled_at >= %s
						AND scheduled_at <= %s",
					'scheduled',
					'retrying',
					$range['start'],
					$range['end']
				)
			)
		);
	}

	/**
	 * Get eligible queued draft posts, cleaning invalid queue rows.
	 *
	 * @param int $limit Needed item count.
	 * @return array
	 */
	private function get_eligible_queue_items( $limit ) {
		global $wpdb;

		$table = $this->database->get_queue_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				FROM {$table}
				WHERE status = %s
					AND scheduled_at IS NULL
				ORDER BY queue_position ASC, id ASC
				LIMIT %d",
				'queued',
				1000
			)
		);

		$eligible = array();

		foreach ( $rows as $row ) {
			$post = get_post( absint( $row->post_id ) );

			if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || 'draft' !== $post->post_status ) {
				$this->queue->remove_post( absint( $row->post_id ) );
				continue;
			}

			$eligible[] = $row;

			if ( count( $eligible ) >= $limit ) {
				break;
			}
		}

		return $eligible;
	}

	/**
	 * Get the maximum number of slots that can fit in a window.
	 *
	 * @param int $start Start minute.
	 * @param int $end End minute.
	 * @param int $gap Minimum gap.
	 * @return int
	 */
	private function get_max_slots_that_fit( $start, $end, $gap ) {
		if ( $start > $end ) {
			return 0;
		}

		return (int) floor( ( $end - $start ) / $gap ) + 1;
	}

	/**
	 * Generate sorted random slot minutes.
	 *
	 * @param int $start Start minute.
	 * @param int $end End minute.
	 * @param int $gap Minimum gap.
	 * @param int $count Slot count.
	 * @return array
	 */
	private function generate_random_slot_minutes( $start, $end, $gap, $count ) {
		if ( $count <= 0 ) {
			return array();
		}

		$minimum_span = ( $count - 1 ) * $gap;
		$extra       = max( 0, ( $end - $start ) - $minimum_span );
		$buckets     = array_fill( 0, $count + 1, 0 );

		for ( $minute = 0; $minute < $extra; $minute++ ) {
			$buckets[ wp_rand( 0, $count ) ]++;
		}

		$slots   = array();
		$current = $start + $buckets[0];

		for ( $index = 0; $index < $count; $index++ ) {
			if ( $index > 0 ) {
				$current += $gap + $buckets[ $index ];
			}

			$slots[] = $current;
		}

		sort( $slots, SORT_NUMERIC );

		return $slots;
	}
}
