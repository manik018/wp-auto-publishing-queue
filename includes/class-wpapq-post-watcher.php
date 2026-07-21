<?php
/**
 * Post lifecycle watcher.
 *
 * @package WP_Auto_Publishing_Queue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Watches post status, trash, and delete events for queue cleanup.
 */
class WPAPQ_Post_Watcher {

	/**
	 * Queue service.
	 *
	 * @var WPAPQ_Queue
	 */
	private $queue;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->queue = new WPAPQ_Queue();
	}

	/**
	 * Register post lifecycle hooks.
	 */
	public function run() {
		add_action( 'transition_post_status', array( $this, 'cleanup_on_status_change' ), 10, 3 );
		add_action( 'trashed_post', array( $this, 'cleanup_post' ) );
		add_action( 'before_delete_post', array( $this, 'cleanup_post' ) );
	}

	/**
	 * Remove queued posts when they leave draft status.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post Post object.
	 */
	public function cleanup_on_status_change( $new_status, $old_status, $post ) {
		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			return;
		}

		if ( $new_status === $old_status || 'draft' === $new_status ) {
			return;
		}

		$this->queue->remove_post( $post->ID );
	}

	/**
	 * Remove a post from the queue during trash/delete events.
	 *
	 * @param int $post_id Post ID.
	 */
	public function cleanup_post( $post_id ) {
		$this->queue->remove_post( absint( $post_id ) );
	}
}
