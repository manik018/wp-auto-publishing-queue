<?php
/**
 * Queue database operations.
 *
 * @package WP_Auto_Publishing_Queue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queue operations for draft posts.
 */
class WPAPQ_Queue {

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
	 * Add a draft post to the queue.
	 *
	 * @param int $post_id Post ID.
	 * @return int|WP_Error
	 */
	public function add_post( $post_id ) {
		global $wpdb;

		$post_id = absint( $post_id );

		if ( 0 === $post_id ) {
			return new WP_Error( 'wpapq_invalid_post', __( 'Invalid post.', 'wp-auto-publishing-queue' ) );
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'wpapq_invalid_post', __( 'Invalid post.', 'wp-auto-publishing-queue' ) );
		}

		if ( 'post' !== $post->post_type ) {
			return new WP_Error( 'wpapq_invalid_post_type', __( 'Only standard posts can be added to the publishing queue.', 'wp-auto-publishing-queue' ) );
		}

		if ( 'draft' !== $post->post_status ) {
			return new WP_Error( 'wpapq_draft_only', __( 'Only draft posts can be added to the publishing queue.', 'wp-auto-publishing-queue' ) );
		}

		if ( $this->is_queued( $post_id ) ) {
			return new WP_Error( 'wpapq_already_queued', __( 'This post is already in the publishing queue.', 'wp-auto-publishing-queue' ) );
		}

		$now    = current_time( 'mysql' );
		$result = $wpdb->insert(
			$this->database->get_queue_table(),
			array(
				'post_id'        => $post_id,
				'queue_position' => $this->get_next_position(),
				'status'         => 'queued',
				'scheduled_at'   => null,
				'retry_count'    => 0,
				'last_error'     => null,
				'added_at'       => $now,
				'updated_at'     => $now,
			),
			array(
				'%d',
				'%d',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
			)
		);

		if ( false === $result ) {
			if ( $this->is_queued( $post_id ) ) {
				return new WP_Error( 'wpapq_already_queued', __( 'This post is already in the publishing queue.', 'wp-auto-publishing-queue' ) );
			}

			return new WP_Error( 'wpapq_insert_failed', __( 'The post could not be added to the publishing queue.', 'wp-auto-publishing-queue' ) );
		}

		update_option( 'wpapq_queue_empty_notified', 0 );
		$this->maybe_reset_low_queue_notification();

		return absint( $wpdb->insert_id );
	}

	/**
	 * Remove a post from the queue.
	 *
	 * @param int $post_id Post ID.
	 * @return bool|WP_Error
	 */
	public function remove_post( $post_id ) {
		global $wpdb;

		$post_id = absint( $post_id );

		if ( 0 === $post_id ) {
			return new WP_Error( 'wpapq_invalid_post', __( 'Invalid post.', 'wp-auto-publishing-queue' ) );
		}

		$result = $wpdb->delete(
			$this->database->get_queue_table(),
			array( 'post_id' => $post_id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'wpapq_remove_failed', __( 'The post could not be removed from the publishing queue.', 'wp-auto-publishing-queue' ) );
		}

		if ( 0 === $result ) {
			return false;
		}

		$this->normalize_positions();

		return true;
	}

	/**
	 * Determine whether a post is queued.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function is_queued( $post_id ) {
		return null !== $this->get_queue_item( $post_id );
	}

	/**
	 * Get a queue item by post ID.
	 *
	 * @param int $post_id Post ID.
	 * @return object|null
	 */
	public function get_queue_item( $post_id ) {
		global $wpdb;

		$post_id = absint( $post_id );

		if ( 0 === $post_id ) {
			return null;
		}

		$table = $this->database->get_queue_table();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE post_id = %d LIMIT 1",
				$post_id
			)
		);
	}

	/**
	 * Get queued posts.
	 *
	 * @param int $limit Result limit.
	 * @param int $offset Result offset.
	 * @return array
	 */
	public function get_queued_posts( $limit = 100, $offset = 0 ) {
		global $wpdb;

		$limit  = max( 1, absint( $limit ) );
		$offset = absint( $offset );
		$table  = $this->database->get_queue_table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT q.*, p.post_title, p.post_status
				FROM {$table} q
				INNER JOIN {$wpdb->posts} p ON p.ID = q.post_id
				WHERE q.status IN (%s, %s, %s, %s)
				ORDER BY
					CASE WHEN q.status = 'failed' THEN 2 WHEN q.scheduled_at IS NULL THEN 1 ELSE 0 END ASC,
					CASE WHEN q.scheduled_at IS NULL THEN 1 ELSE 0 END ASC,
					q.scheduled_at ASC,
					q.queue_position ASC,
					q.id ASC
				LIMIT %d OFFSET %d",
				'queued',
				'scheduled',
				'retrying',
				'failed',
				$limit,
				$offset
			)
		);
	}

	/**
	 * Get the active queue count.
	 *
	 * @return int
	 */
	public function get_queue_count() {
		return $this->get_active_queue_count();
	}

	/**
	 * Get the active queue count.
	 *
	 * @return int
	 */
	public function get_active_count() {
		return $this->get_active_queue_count();
	}

	/**
	 * Get the active queue count.
	 *
	 * @return int
	 */
	public function get_active_queue_count() {
		global $wpdb;

		$table = $this->database->get_queue_table();

		return absint(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE status IN (%s, %s, %s)",
					'queued',
					'scheduled',
					'retrying'
				)
			)
		);
	}

	/**
	 * Get failed queue row count.
	 *
	 * @return int
	 */
	public function get_failed_count() {
		global $wpdb;

		$table = $this->database->get_queue_table();

		return absint(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE status = %s",
					'failed'
				)
			)
		);
	}

	/**
	 * Get the next FIFO queue position.
	 *
	 * @return int
	 */
	public function get_next_position() {
		global $wpdb;

		$table = $this->database->get_queue_table();

		return absint(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(MAX(queue_position), 0) + 1 FROM {$table} WHERE status IN (%s, %s, %s)",
					'queued',
					'scheduled',
					'retrying'
				)
			)
		);
	}

	/**
	 * Normalize active queue positions to 1, 2, 3...
	 */
	public function normalize_positions() {
		global $wpdb;

		$table = $this->database->get_queue_table();

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE status IN (%s, %s, %s) ORDER BY queue_position ASC, id ASC",
				'queued',
				'scheduled',
				'retrying'
			)
		);

		$position = 1;

		foreach ( $items as $item ) {
			$wpdb->update(
				$table,
				array(
					'queue_position' => $position,
					'updated_at'     => current_time( 'mysql' ),
				),
				array( 'id' => absint( $item->id ) ),
				array( '%d', '%s' ),
				array( '%d' )
			);

			$position++;
		}
	}

	/**
	 * Reset low-queue notification state when the active count recovers.
	 */
	private function maybe_reset_low_queue_notification() {
		$settings  = get_option( 'wpapq_settings', array() );
		$threshold = is_array( $settings ) && isset( $settings['low_queue_threshold'] ) ? absint( $settings['low_queue_threshold'] ) : 0;

		if ( $threshold > 0 && $this->get_active_queue_count() >= $threshold ) {
			update_option( 'wpapq_low_queue_notified', 0 );
		}
	}
}
