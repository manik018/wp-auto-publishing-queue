<?php
/**
 * Publishing logs.
 *
 * @package WP_Auto_Publishing_Queue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes entries to the plugin logs table.
 */
class WPAPQ_Logger {

	/**
	 * Allowed event types.
	 *
	 * @var array
	 */
	private $allowed_event_types = array( 'publish', 'retry', 'cleanup', 'notification', 'cron' );

	/**
	 * Allowed statuses.
	 *
	 * @var array
	 */
	private $allowed_statuses = array( 'success', 'failed', 'scheduled', 'skipped', 'info' );

	/**
	 * Database helper.
	 *
	 * @var WPAPQ_Database
	 */
	private $database;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->database = new WPAPQ_Database();
	}

	/**
	 * Write a log entry.
	 *
	 * @param int|null    $post_id Post ID.
	 * @param string      $event_type Event type.
	 * @param string      $status Status.
	 * @param string      $message Message.
	 * @param string|null $scheduled_at Scheduled timestamp.
	 * @param string|null $executed_at Execution timestamp.
	 * @return bool
	 */
	public function log( $post_id, $event_type, $status, $message = '', $scheduled_at = null, $executed_at = null ) {
		global $wpdb;

		$event_type = $this->sanitize_limited_key( $event_type, 50 );
		$status     = $this->sanitize_limited_key( $status, 20 );
		$message    = wp_trim_words( sanitize_textarea_field( (string) $message ), 80, '...' );

		if ( '' === $event_type || '' === $status ) {
			return false;
		}

		$result = $wpdb->insert(
			$this->database->get_logs_table(),
			array(
				'post_id'      => null === $post_id ? null : absint( $post_id ),
				'event_type'   => $event_type,
				'status'       => $status,
				'message'      => $message,
				'scheduled_at' => $scheduled_at,
				'executed_at'  => $executed_at,
				'created_at'   => current_time( 'mysql' ),
			),
			array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		return false !== $result;
	}

	/**
	 * Get logs with optional filters.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_logs( $args = array() ) {
		global $wpdb;

		$args   = $this->sanitize_query_args( $args );
		$where  = $this->build_where_clause( $args );
		$table  = $this->database->get_logs_table();
		$limit  = $args['limit'];
		$offset = $args['offset'];

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				FROM {$table}
				{$where['sql']}
				ORDER BY created_at DESC, id DESC
				LIMIT %d OFFSET %d",
				array_merge( $where['values'], array( $limit, $offset ) )
			)
		);
	}

	/**
	 * Count logs with optional filters.
	 *
	 * @param array $args Query arguments.
	 * @return int
	 */
	public function get_log_count( $args = array() ) {
		global $wpdb;

		$args  = $this->sanitize_query_args( $args );
		$where = $this->build_where_clause( $args );
		$table = $this->database->get_logs_table();

		return absint(
			empty( $where['values'] )
				? $wpdb->get_var(
					"SELECT COUNT(*)
					FROM {$table}"
				)
				: $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*)
						FROM {$table}
						{$where['sql']}",
						$where['values']
					)
				)
		);
	}

	/**
	 * Get recent logs.
	 *
	 * @param int $limit Result limit.
	 * @return array
	 */
	public function get_recent_logs( $limit = 10 ) {
		return $this->get_logs(
			array(
				'limit'  => min( max( absint( $limit ), 1 ), 20 ),
				'offset' => 0,
			)
		);
	}

	/**
	 * Count successful publish logs for a date.
	 *
	 * @param string|null $date Date in Y-m-d format, or null for today.
	 * @return int
	 */
	public function count_published_success_for_date( $date = null ) {
		if ( null === $date ) {
			$date = WPAPQ_Helper::get_current_datetime()->format( 'Y-m-d' );
		}

		if ( ! $this->is_valid_date( $date ) ) {
			return 0;
		}

		return $this->get_log_count(
			array(
				'event_type' => 'publish',
				'status'     => 'success',
				'date_from'  => $date,
				'date_to'    => $date,
			)
		);
	}

	/**
	 * Get allowed event types.
	 *
	 * @return array
	 */
	public function get_allowed_event_types() {
		return $this->allowed_event_types;
	}

	/**
	 * Get allowed statuses.
	 *
	 * @return array
	 */
	public function get_allowed_statuses() {
		return $this->allowed_statuses;
	}

	/**
	 * Sanitize a small key-like value.
	 *
	 * @param string $value Value.
	 * @param int    $limit Character limit.
	 * @return string
	 */
	private function sanitize_limited_key( $value, $limit ) {
		return substr( sanitize_key( $value ), 0, absint( $limit ) );
	}

	/**
	 * Sanitize log query arguments.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	private function sanitize_query_args( $args ) {
		$args = is_array( $args ) ? $args : array();

		$event_type = isset( $args['event_type'] ) ? sanitize_key( $args['event_type'] ) : '';
		$status     = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '';
		$date_from  = isset( $args['date_from'] ) && $this->is_valid_date( $args['date_from'] ) ? $args['date_from'] : '';
		$date_to    = isset( $args['date_to'] ) && $this->is_valid_date( $args['date_to'] ) ? $args['date_to'] : '';

		return array(
			'event_type' => in_array( $event_type, $this->allowed_event_types, true ) ? $event_type : '',
			'status'     => in_array( $status, $this->allowed_statuses, true ) ? $status : '',
			'post_id'    => isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0,
			'date_from'  => $date_from,
			'date_to'    => $date_to,
			'limit'      => isset( $args['limit'] ) ? min( max( absint( $args['limit'] ), 1 ), 100 ) : 20,
			'offset'     => isset( $args['offset'] ) ? max( absint( $args['offset'] ), 0 ) : 0,
		);
	}

	/**
	 * Build a safe WHERE clause from sanitized arguments.
	 *
	 * @param array $args Sanitized query arguments.
	 * @return array
	 */
	private function build_where_clause( $args ) {
		$where  = array();
		$values = array();

		if ( '' !== $args['event_type'] ) {
			$where[]  = 'event_type = %s';
			$values[] = $args['event_type'];
		}

		if ( '' !== $args['status'] ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( $args['post_id'] > 0 ) {
			$where[]  = 'post_id = %d';
			$values[] = $args['post_id'];
		}

		if ( '' !== $args['date_from'] ) {
			$where[]  = 'created_at >= %s';
			$values[] = $args['date_from'] . ' 00:00:00';
		}

		if ( '' !== $args['date_to'] ) {
			$where[]  = 'created_at <= %s';
			$values[] = $args['date_to'] . ' 23:59:59';
		}

		return array(
			'sql'    => empty( $where ) ? '' : 'WHERE ' . implode( ' AND ', $where ),
			'values' => $values,
		);
	}

	/**
	 * Validate an exact Y-m-d date.
	 *
	 * @param mixed $date Date.
	 * @return bool
	 */
	private function is_valid_date( $date ) {
		if ( ! is_string( $date ) || 1 !== preg_match( '/\A\d{4}-\d{2}-\d{2}\z/', $date ) ) {
			return false;
		}

		$datetime = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, WPAPQ_Helper::get_timezone() );
		$errors   = DateTimeImmutable::getLastErrors();

		return false !== $datetime && ( ! is_array( $errors ) || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) && $datetime->format( 'Y-m-d' ) === $date;
	}
}
