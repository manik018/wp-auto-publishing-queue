<?php
/**
 * Admin queue management.
 *
 * @package WP_Auto_Publishing_Queue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queue admin UI and actions.
 */
class WPAPQ_Queue_Page {

	/**
	 * Queue service.
	 *
	 * @var WPAPQ_Queue
	 */
	private $queue;

	/**
	 * Scheduler service.
	 *
	 * @var WPAPQ_Scheduler
	 */
	private $scheduler;

	/**
	 * Allowed notice codes.
	 *
	 * @var array
	 */
	private $notice_codes = array(
		'added',
		'removed',
		'already_queued',
		'invalid_post',
		'draft_only',
		'permission_denied',
		'not_removed',
		'add_failed',
		'remove_failed',
		'bulk_added',
		'bulk_none',
		'schedule_generated',
		'schedule_full',
		'schedule_no_slots',
		'schedule_regenerated',
		'schedule_error',
		'processor_run',
		'processor_disabled',
		'processor_locked',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->queue     = new WPAPQ_Queue();
		$this->scheduler = new WPAPQ_Scheduler();
	}

	/**
	 * Register hooks.
	 */
	public function run() {
		add_action( 'admin_post_wpapq_add_to_queue', array( $this, 'handle_add_to_queue' ) );
		add_action( 'admin_post_wpapq_remove_from_queue', array( $this, 'handle_remove_from_queue' ) );
		add_action( 'admin_post_wpapq_generate_today_schedule', array( $this, 'handle_generate_today_schedule' ) );
		add_action( 'admin_post_wpapq_regenerate_today_schedule', array( $this, 'handle_regenerate_today_schedule' ) );
		add_action( 'admin_post_wpapq_run_queue_processor', array( $this, 'handle_run_queue_processor' ) );
		add_action( 'add_meta_boxes_post', array( $this, 'register_meta_box' ) );
		add_filter( 'post_row_actions', array( $this, 'filter_post_row_actions' ), 10, 2 );
		add_filter( 'bulk_actions-edit-post', array( $this, 'register_bulk_action' ) );
		add_filter( 'handle_bulk_actions-edit-post', array( $this, 'handle_bulk_action' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}

	/**
	 * Render the queue page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$items = $this->queue->get_queued_posts();
		$count = $this->queue->get_active_queue_count();
		$failed_count = $this->queue->get_failed_count();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Publishing Queue', 'wp-auto-publishing-queue' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %d: Number of queued posts. */
					esc_html__( 'Active Queue: %d', 'wp-auto-publishing-queue' ),
					absint( $count )
				);
				?>
				<br />
				<?php
				printf(
					/* translators: %d: Number of failed queue rows. */
					esc_html__( 'Failed: %d', 'wp-auto-publishing-queue' ),
					absint( $failed_count )
				);
				?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $this->get_schedule_action_url( 'wpapq_generate_today_schedule' ) ); ?>">
					<?php echo esc_html__( 'Generate Today\'s Schedule', 'wp-auto-publishing-queue' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( $this->get_schedule_action_url( 'wpapq_regenerate_today_schedule' ) ); ?>">
					<?php echo esc_html__( 'Regenerate Today\'s Schedule', 'wp-auto-publishing-queue' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( $this->get_schedule_action_url( 'wpapq_run_queue_processor' ) ); ?>">
					<?php echo esc_html__( 'Run Queue Processor Now', 'wp-auto-publishing-queue' ); ?>
				</a>
			</p>
			<p class="description"><?php echo esc_html__( 'Regenerating replaces today\'s active scheduled times.', 'wp-auto-publishing-queue' ); ?></p>

			<?php if ( empty( $items ) ) : ?>
				<p><?php echo esc_html__( 'No posts are currently in the publishing queue.', 'wp-auto-publishing-queue' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Position', 'wp-auto-publishing-queue' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Post Title', 'wp-auto-publishing-queue' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Post Status', 'wp-auto-publishing-queue' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Queue Status', 'wp-auto-publishing-queue' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Scheduled Time', 'wp-auto-publishing-queue' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Last Error', 'wp-auto-publishing-queue' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Added Date', 'wp-auto-publishing-queue' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Actions', 'wp-auto-publishing-queue' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $items as $item ) : ?>
							<tr>
								<td><?php echo esc_html( absint( $item->queue_position ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( get_edit_post_link( absint( $item->post_id ), 'raw' ) ); ?>">
										<?php echo esc_html( get_the_title( absint( $item->post_id ) ) ); ?>
									</a>
								</td>
								<td><?php echo esc_html( $this->get_post_status_label( $item->post_status ) ); ?></td>
								<td><?php echo esc_html( $this->get_queue_status_label( $item->status ) ); ?></td>
								<td><?php echo esc_html( $this->format_scheduled_time( $item->scheduled_at ) ); ?></td>
								<td><?php echo esc_html( $this->format_last_error( $item->last_error ) ); ?></td>
								<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item->added_at ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( get_edit_post_link( absint( $item->post_id ), 'raw' ) ); ?>">
										<?php echo esc_html__( 'Edit', 'wp-auto-publishing-queue' ); ?>
									</a>
									|
									<a href="<?php echo esc_url( $this->get_action_url( 'wpapq_remove_from_queue', absint( $item->post_id ), 'queue' ) ); ?>">
										<?php echo esc_html__( 'Remove', 'wp-auto-publishing-queue' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Register the editor meta box.
	 */
	public function register_meta_box() {
		add_meta_box(
			'wpapq_publishing_queue',
			__( 'Publishing Queue', 'wp-auto-publishing-queue' ),
			array( $this, 'render_meta_box' ),
			'post',
			'side',
			'high'
		);
	}

	/**
	 * Render the editor meta box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_meta_box( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( 'draft' !== $post->post_status ) {
			echo '<p>' . esc_html__( 'Only draft posts can be added to the publishing queue.', 'wp-auto-publishing-queue' ) . '</p>';
			return;
		}

		$item = $this->queue->get_queue_item( $post->ID );

		if ( null === $item ) {
			echo '<p><strong>' . esc_html__( 'Status:', 'wp-auto-publishing-queue' ) . '</strong> ' . esc_html__( 'Not in queue', 'wp-auto-publishing-queue' ) . '</p>';
			$this->render_meta_box_button( 'wpapq_add_to_queue', $post->ID, __( 'Add to Publishing Queue', 'wp-auto-publishing-queue' ) );
			return;
		}

		echo '<p><strong>' . esc_html__( 'Status:', 'wp-auto-publishing-queue' ) . '</strong> ' . esc_html__( 'Queued', 'wp-auto-publishing-queue' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Queue Position:', 'wp-auto-publishing-queue' ) . '</strong> ' . esc_html( absint( $item->queue_position ) ) . '</p>';
		$this->render_meta_box_button( 'wpapq_remove_from_queue', $post->ID, __( 'Remove from Publishing Queue', 'wp-auto-publishing-queue' ) );
	}

	/**
	 * Handle a single add action.
	 */
	public function handle_add_to_queue() {
		$post_id  = $this->get_request_post_id();
		$redirect = $this->get_safe_redirect_url( $post_id );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			$this->redirect_with_notice( $redirect, 'permission_denied' );
		}

		check_admin_referer( 'wpapq_add_to_queue_' . $post_id );

		$result = $this->queue->add_post( $post_id );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( $redirect, $this->map_error_to_notice( $result ) );
		}

		$this->redirect_with_notice( $redirect, 'added' );
	}

	/**
	 * Handle a single remove action.
	 */
	public function handle_remove_from_queue() {
		$post_id  = $this->get_request_post_id();
		$context  = $this->get_request_context();
		$redirect = $this->get_safe_redirect_url( $post_id, $context );

		if ( 'queue' === $context ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				$this->redirect_with_notice( $redirect, 'permission_denied' );
			}
		} elseif ( ! current_user_can( 'edit_post', $post_id ) ) {
			$this->redirect_with_notice( $redirect, 'permission_denied' );
		}

		check_admin_referer( 'wpapq_remove_from_queue_' . $post_id );

		$result = $this->queue->remove_post( $post_id );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( $redirect, $this->map_error_to_notice( $result ) );
		}

		if ( true !== $result ) {
			$this->redirect_with_notice( $redirect, 'not_removed' );
		}

		$this->redirect_with_notice( $redirect, 'removed' );
	}

	/**
	 * Handle manual schedule generation for today.
	 */
	public function handle_generate_today_schedule() {
		$this->handle_schedule_generation( false );
	}

	/**
	 * Handle manual forced schedule regeneration for today.
	 */
	public function handle_regenerate_today_schedule() {
		$this->handle_schedule_generation( true );
	}

	/**
	 * Handle manual queue processor run.
	 */
	public function handle_run_queue_processor() {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->redirect_with_notice( admin_url( 'admin.php?page=wpapq-queue' ), 'permission_denied' );
		}

		check_admin_referer( 'wpapq_run_queue_processor' );

		$cron   = new WPAPQ_Cron();
		$result = $cron->process_queue();
		$notice = 'processor_run';

		if ( ! empty( $result['disabled'] ) ) {
			$notice = 'processor_disabled';
		} elseif ( ! empty( $result['locked'] ) ) {
			$notice = 'processor_locked';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'wpapq_notice'    => $notice,
					'wpapq_processed' => absint( $result['processed'] ),
					'wpapq_published' => absint( $result['published'] ),
				),
				remove_query_arg( array( 'wpapq_notice', 'wpapq_added', 'wpapq_skipped', 'wpapq_scheduled', 'wpapq_processed', 'wpapq_published' ), admin_url( 'admin.php?page=wpapq-queue' ) )
			)
		);
		exit;
	}

	/**
	 * Add row action links for draft posts.
	 *
	 * @param array   $actions Existing actions.
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	public function filter_post_row_actions( $actions, $post ) {
		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || 'draft' !== $post->post_status || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		if ( $this->queue->is_queued( $post->ID ) ) {
			$actions['wpapq_queue'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->get_action_url( 'wpapq_remove_from_queue', $post->ID, 'list' ) ),
				esc_html__( 'Remove from Queue', 'wp-auto-publishing-queue' )
			);
		} else {
			$actions['wpapq_queue'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->get_action_url( 'wpapq_add_to_queue', $post->ID, 'list' ) ),
				esc_html__( 'Add to Queue', 'wp-auto-publishing-queue' )
			);
		}

		return $actions;
	}

	/**
	 * Register bulk action.
	 *
	 * @param array $actions Existing actions.
	 * @return array
	 */
	public function register_bulk_action( $actions ) {
		$actions['wpapq_add_to_queue'] = __( 'Add to Publishing Queue', 'wp-auto-publishing-queue' );

		return $actions;
	}

	/**
	 * Handle bulk add action.
	 *
	 * @param string $redirect_url Redirect URL.
	 * @param string $action Action key.
	 * @param array  $post_ids Post IDs.
	 * @return string
	 */
	public function handle_bulk_action( $redirect_url, $action, $post_ids ) {
		if ( 'wpapq_add_to_queue' !== $action ) {
			return $redirect_url;
		}

		$added   = 0;
		$skipped = 0;

		foreach ( (array) $post_ids as $post_id ) {
			$post_id = absint( $post_id );

			if ( 0 === $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
				$skipped++;
				continue;
			}

			$result = $this->queue->add_post( $post_id );

			if ( is_wp_error( $result ) ) {
				$skipped++;
				continue;
			}

			$added++;
		}

		$notice = $added > 0 ? 'bulk_added' : 'bulk_none';

		return add_query_arg(
			array(
				'wpapq_notice'  => $notice,
				'wpapq_added'   => $added,
				'wpapq_skipped' => $skipped,
			),
			remove_query_arg( array( 'wpapq_notice', 'wpapq_added', 'wpapq_skipped' ), $redirect_url )
		);
	}

	/**
	 * Render restricted admin notices.
	 */
	public function render_admin_notices() {
		$notice = isset( $_GET['wpapq_notice'] ) ? sanitize_key( wp_unslash( $_GET['wpapq_notice'] ) ) : '';

		if ( '' === $notice || ! in_array( $notice, $this->notice_codes, true ) ) {
			return;
		}

		$type    = in_array( $notice, array( 'added', 'removed', 'bulk_added', 'schedule_generated', 'schedule_full', 'schedule_no_slots', 'schedule_regenerated', 'processor_run' ), true ) ? 'success' : 'error';
		$message = $this->get_notice_message( $notice );

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/**
	 * Render a meta box action button.
	 *
	 * @param string $action Action name.
	 * @param int    $post_id Post ID.
	 * @param string $label Button label.
	 */
	private function render_meta_box_button( $action, $post_id, $label ) {
		$post_id = absint( $post_id );
		?>
		<a class="button button-secondary" href="<?php echo esc_url( $this->get_action_url( $action, $post_id, 'editor' ) ); ?>">
			<?php echo esc_html( $label ); ?>
		</a>
		<?php
	}

	/**
	 * Build a nonce-protected admin action URL.
	 *
	 * @param string $action Action name.
	 * @param int    $post_id Post ID.
	 * @param string $context Action context.
	 * @return string
	 */
	private function get_action_url( $action, $post_id, $context ) {
		$post_id = absint( $post_id );
		$url     = add_query_arg(
			array(
				'action'        => $action,
				'post_id'       => $post_id,
				'wpapq_context' => sanitize_key( $context ),
			),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, $action . '_' . $post_id );
	}

	/**
	 * Build a nonce-protected schedule action URL.
	 *
	 * @param string $action Action name.
	 * @return string
	 */
	private function get_schedule_action_url( $action ) {
		$url = add_query_arg(
			array( 'action' => $action ),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, $action );
	}

	/**
	 * Get a sanitized request post ID.
	 *
	 * @return int
	 */
	private function get_request_post_id() {
		if ( ! isset( $_REQUEST['post_id'] ) ) {
			return 0;
		}

		return absint( wp_unslash( $_REQUEST['post_id'] ) );
	}

	/**
	 * Get a restricted action context.
	 *
	 * @return string
	 */
	private function get_request_context() {
		$context = isset( $_REQUEST['wpapq_context'] ) ? sanitize_key( wp_unslash( $_REQUEST['wpapq_context'] ) ) : '';

		if ( ! in_array( $context, array( 'queue', 'editor', 'list' ), true ) ) {
			return '';
		}

		return $context;
	}

	/**
	 * Resolve a safe redirect URL.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $context Action context.
	 * @return string
	 */
	private function get_safe_redirect_url( $post_id, $context = '' ) {
		if ( 'queue' === $context ) {
			return admin_url( 'admin.php?page=wpapq-queue' );
		}

		$fallback = $post_id > 0 ? get_edit_post_link( $post_id, 'raw' ) : admin_url( 'edit.php' );

		if ( ! $fallback ) {
			$fallback = admin_url( 'edit.php' );
		}

		$referer = wp_get_referer();

		if ( $referer ) {
			return wp_validate_redirect( $referer, $fallback );
		}

		return $fallback;
	}

	/**
	 * Redirect with a restricted notice code.
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $notice Notice code.
	 */
	private function redirect_with_notice( $redirect, $notice ) {
		if ( ! in_array( $notice, $this->notice_codes, true ) ) {
			$notice = 'invalid_post';
		}

		wp_safe_redirect(
			add_query_arg(
				'wpapq_notice',
				$notice,
				remove_query_arg( array( 'wpapq_notice', 'wpapq_added', 'wpapq_skipped' ), $redirect )
			)
		);
		exit;
	}

	/**
	 * Map a queue error to a public notice code.
	 *
	 * @param WP_Error $error Error object.
	 * @return string
	 */
	private function map_error_to_notice( $error ) {
		$code = $error->get_error_code();

		if ( 'wpapq_already_queued' === $code ) {
			return 'already_queued';
		}

		if ( 'wpapq_draft_only' === $code ) {
			return 'draft_only';
		}

		if ( in_array( $code, array( 'wpapq_invalid_post', 'wpapq_invalid_post_type' ), true ) ) {
			return 'invalid_post';
		}

		if ( 'wpapq_remove_failed' === $code ) {
			return 'remove_failed';
		}

		return 'add_failed';
	}

	/**
	 * Get a notice message.
	 *
	 * @param string $notice Notice code.
	 * @return string
	 */
	private function get_notice_message( $notice ) {
		if ( in_array( $notice, array( 'schedule_generated', 'schedule_regenerated' ), true ) ) {
			$scheduled = isset( $_GET['wpapq_scheduled'] ) ? absint( wp_unslash( $_GET['wpapq_scheduled'] ) ) : 0;

			if ( 'schedule_regenerated' === $notice ) {
				return sprintf(
					/* translators: %d: Number of scheduled posts. */
					__( 'Today\'s schedule was regenerated. %d posts were scheduled for today.', 'wp-auto-publishing-queue' ),
					$scheduled
				);
			}

			return sprintf(
				/* translators: %d: Number of scheduled posts. */
				__( '%d posts were scheduled for today.', 'wp-auto-publishing-queue' ),
				$scheduled
			);
		}

		if ( 'processor_run' === $notice ) {
			$processed = isset( $_GET['wpapq_processed'] ) ? absint( wp_unslash( $_GET['wpapq_processed'] ) ) : 0;
			$published = isset( $_GET['wpapq_published'] ) ? absint( wp_unslash( $_GET['wpapq_published'] ) ) : 0;

			return sprintf(
				/* translators: 1: Processed count. 2: Published count. */
				__( 'Queue processor completed. %1$d items processed, %2$d posts published.', 'wp-auto-publishing-queue' ),
				$processed,
				$published
			);
		}

		if ( 'bulk_added' === $notice ) {
			$added   = isset( $_GET['wpapq_added'] ) ? absint( wp_unslash( $_GET['wpapq_added'] ) ) : 0;
			$skipped = isset( $_GET['wpapq_skipped'] ) ? absint( wp_unslash( $_GET['wpapq_skipped'] ) ) : 0;

			return sprintf(
				/* translators: 1: Added count. 2: Skipped count. */
				__( '%1$d posts added to the publishing queue. %2$d posts were skipped.', 'wp-auto-publishing-queue' ),
				$added,
				$skipped
			);
		}

		$messages = array(
			'added'             => __( 'Post added to the publishing queue.', 'wp-auto-publishing-queue' ),
			'removed'           => __( 'Post removed from the publishing queue.', 'wp-auto-publishing-queue' ),
			'already_queued'    => __( 'This post is already in the publishing queue.', 'wp-auto-publishing-queue' ),
			'invalid_post'      => __( 'Invalid post.', 'wp-auto-publishing-queue' ),
			'draft_only'        => __( 'Only draft posts can be added to the publishing queue.', 'wp-auto-publishing-queue' ),
			'permission_denied' => __( 'You do not have permission to manage this post.', 'wp-auto-publishing-queue' ),
			'not_removed'       => __( 'The post was not in the publishing queue.', 'wp-auto-publishing-queue' ),
			'add_failed'        => __( 'The post could not be added to the publishing queue.', 'wp-auto-publishing-queue' ),
			'remove_failed'     => __( 'The post could not be removed from the publishing queue.', 'wp-auto-publishing-queue' ),
			'bulk_none'         => __( 'No posts were added to the publishing queue.', 'wp-auto-publishing-queue' ),
			'schedule_full'     => __( 'Today\'s schedule already contains the configured number of posts.', 'wp-auto-publishing-queue' ),
			'schedule_no_slots' => __( 'No additional publishing slots are available today.', 'wp-auto-publishing-queue' ),
			'schedule_error'    => __( 'Today\'s schedule could not be generated.', 'wp-auto-publishing-queue' ),
			'processor_disabled' => __( 'Automatic publishing is disabled. Enable it from Settings before running the processor.', 'wp-auto-publishing-queue' ),
			'processor_locked'   => __( 'The queue processor is already running. Please try again shortly.', 'wp-auto-publishing-queue' ),
		);

		return $messages[ $notice ] ?? __( 'Queue action completed.', 'wp-auto-publishing-queue' );
	}

	/**
	 * Handle manual schedule generation.
	 *
	 * @param bool $force Whether to force regeneration.
	 */
	private function handle_schedule_generation( $force ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->redirect_with_notice( admin_url( 'admin.php?page=wpapq-queue' ), 'permission_denied' );
		}

		$action = $force ? 'wpapq_regenerate_today_schedule' : 'wpapq_generate_today_schedule';
		check_admin_referer( $action );

		$result = $this->scheduler->generate_schedule_for_date( null, $force );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( admin_url( 'admin.php?page=wpapq-queue' ), 'schedule_error' );
		}

		$notice = $this->get_schedule_notice_code( $result, $force );

		wp_safe_redirect(
			add_query_arg(
				array(
					'wpapq_notice'    => $notice,
					'wpapq_scheduled' => absint( $result['newly_scheduled'] ),
				),
				remove_query_arg( array( 'wpapq_notice', 'wpapq_added', 'wpapq_skipped', 'wpapq_scheduled' ), admin_url( 'admin.php?page=wpapq-queue' ) )
			)
		);
		exit;
	}

	/**
	 * Resolve a schedule notice code from a scheduler result.
	 *
	 * @param array $result Scheduler result.
	 * @param bool  $force Whether this was forced.
	 * @return string
	 */
	private function get_schedule_notice_code( $result, $force ) {
		if ( $force ) {
			return 'schedule_regenerated';
		}

		if ( ! empty( $result['newly_scheduled'] ) ) {
			return 'schedule_generated';
		}

		if ( isset( $result['daily_capacity'], $result['requested'] ) && $result['daily_capacity'] >= $result['requested'] ) {
			return 'schedule_full';
		}

		return 'schedule_no_slots';
	}

	/**
	 * Get a readable post status label.
	 *
	 * @param string $status Post status.
	 * @return string
	 */
	private function get_post_status_label( $status ) {
		$status_object = get_post_status_object( $status );

		if ( null === $status_object ) {
			return (string) $status;
		}

		return $status_object->label;
	}

	/**
	 * Get a readable queue status label.
	 *
	 * @param string $status Queue status.
	 * @return string
	 */
	private function get_queue_status_label( $status ) {
		$labels = array(
			'queued'    => __( 'Queued', 'wp-auto-publishing-queue' ),
			'scheduled' => __( 'Scheduled', 'wp-auto-publishing-queue' ),
			'retrying'  => __( 'Retrying', 'wp-auto-publishing-queue' ),
			'failed'    => __( 'Failed', 'wp-auto-publishing-queue' ),
		);

		return $labels[ $status ] ?? $status;
	}

	/**
	 * Format a scheduled timestamp for display.
	 *
	 * @param string|null $scheduled_at Scheduled timestamp.
	 * @return string
	 */
	private function format_scheduled_time( $scheduled_at ) {
		return WPAPQ_Helper::format_mysql_datetime( $scheduled_at, __( 'Not scheduled', 'wp-auto-publishing-queue' ) );
	}

	/**
	 * Format the last error column.
	 *
	 * @param string|null $last_error Last error.
	 * @return string
	 */
	private function format_last_error( $last_error ) {
		if ( empty( $last_error ) ) {
			return '';
		}

		return wp_trim_words( sanitize_text_field( (string) $last_error ), 20, '...' );
	}
}
