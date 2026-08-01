<?php
/**
 * Admin publishing logs page.
 *
 * @package WP_Auto_Publishing_Queue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Publishing logs admin page.
 */
class WPAPQ_Logs_Page {

	/**
	 * Logger.
	 *
	 * @var WPAPQ_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger = new WPAPQ_Logger();
	}

	/**
	 * Register hooks.
	 */
	public function run() {
		add_action( 'admin_post_wpapq_clear_logs', array( $this, 'handle_clear_logs' ) );
	}

	/**
	 * Handle clearing all logs.
	 */
	public function handle_clear_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect(
				add_query_arg(
					'wpapq_notice',
					'permission_denied',
					remove_query_arg( 'wpapq_notice', admin_url( 'admin.php?page=wpapq-logs' ) )
				)
			);
			exit;
		}

		check_admin_referer( 'wpapq_clear_logs' );

		$this->logger->clear_all_logs();

		wp_safe_redirect(
			add_query_arg(
				'wpapq_notice',
				'logs_cleared',
				remove_query_arg( 'wpapq_notice', admin_url( 'admin.php?page=wpapq-logs' ) )
			)
		);
		exit;
	}

	/**
	 * Render the logs page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$filters      = $this->get_filters();
		$current_page = $this->get_current_page();
		$per_page     = 20;
		$total        = $this->logger->get_log_count( $filters );
		$total_pages  = max( 1, (int) ceil( $total / $per_page ) );

		if ( $current_page > $total_pages ) {
			$current_page = $total_pages;
		}

		$query_args = array_merge(
			$filters,
			array(
				'limit'  => $per_page,
				'offset' => ( $current_page - 1 ) * $per_page,
			)
		);
		$logs       = $this->logger->get_logs( $query_args );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Publishing Logs', 'wp-auto-publishing-queue' ); ?></h1>
			<?php $this->render_notices(); ?>
			<?php $this->render_filters( $filters, $this->logger->get_log_count() ); ?>
			<p>
				<?php
				printf(
					/* translators: %d: Number of matching log rows. */
					esc_html__( 'Total Matching Logs: %d', 'wp-auto-publishing-queue' ),
					absint( $total )
				);
				?>
			</p>
			<?php if ( empty( $logs ) ) : ?>
				<p><?php echo esc_html__( 'No publishing logs were found.', 'wp-auto-publishing-queue' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Date / Time', 'wp-auto-publishing-queue' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Post', 'wp-auto-publishing-queue' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Event', 'wp-auto-publishing-queue' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Status', 'wp-auto-publishing-queue' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Scheduled Time', 'wp-auto-publishing-queue' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Executed Time', 'wp-auto-publishing-queue' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Message', 'wp-auto-publishing-queue' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( WPAPQ_Helper::format_mysql_datetime( $log->created_at, __( 'Not available', 'wp-auto-publishing-queue' ) ) ); ?></td>
								<td><?php $this->render_post_column( $log ); ?></td>
								<td><?php echo esc_html( $this->get_event_label( $log->event_type ) ); ?></td>
								<td><?php echo esc_html( $this->get_status_label( $log->status ) ); ?></td>
								<td><?php echo esc_html( WPAPQ_Helper::format_mysql_datetime( $log->scheduled_at, __( 'Not available', 'wp-auto-publishing-queue' ) ) ); ?></td>
								<td><?php echo esc_html( WPAPQ_Helper::format_mysql_datetime( $log->executed_at, __( 'Not available', 'wp-auto-publishing-queue' ) ) ); ?></td>
								<td><?php echo esc_html( (string) $log->message ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php $this->render_pagination( $filters, $current_page, $total_pages ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render filter controls.
	 *
	 * @param array $filters Filters.
	 * @param int   $log_count Log count.
	 */
	private function render_filters( $filters, $log_count ) {
		?>
		<form method="get">
			<input type="hidden" name="page" value="wpapq-logs" />
			<select name="wpapq_event_type">
				<option value=""><?php echo esc_html__( 'All events', 'wp-auto-publishing-queue' ); ?></option>
				<?php foreach ( $this->get_event_labels() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['event_type'], $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<select name="wpapq_status">
				<option value=""><?php echo esc_html__( 'All statuses', 'wp-auto-publishing-queue' ); ?></option>
				<?php foreach ( $this->get_status_labels() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['status'], $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<input type="date" name="wpapq_date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" />
			<input type="date" name="wpapq_date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" />
			<?php submit_button( __( 'Filter', 'wp-auto-publishing-queue' ), 'secondary', '', false ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpapq-logs' ) ); ?>">
				<?php echo esc_html__( 'Reset Filters', 'wp-auto-publishing-queue' ); ?>
			</a>
			<?php if ( $log_count > 0 ) : ?>
				<a
					class="button button-secondary"
					href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpapq_clear_logs' ), 'wpapq_clear_logs' ) ); ?>"
					onclick="return confirm( '<?php echo esc_js( __( 'This will permanently delete all publishing logs. This cannot be undone. Continue?', 'wp-auto-publishing-queue' ) ); ?>' );"
				>
					<?php echo esc_html__( 'Clear All Logs', 'wp-auto-publishing-queue' ); ?>
				</a>
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * Render page notices.
	 */
	private function render_notices() {
		$notice = isset( $_GET['wpapq_notice'] ) ? sanitize_key( wp_unslash( $_GET['wpapq_notice'] ) ) : '';

		if ( 'logs_cleared' === $notice ) {
			$message = __( 'All publishing logs have been cleared.', 'wp-auto-publishing-queue' );
		} elseif ( 'permission_denied' === $notice ) {
			$message = __( 'You do not have permission to do this.', 'wp-auto-publishing-queue' );
		} else {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( $message )
		);
	}

	/**
	 * Render the post column.
	 *
	 * @param object $log Log row.
	 */
	private function render_post_column( $log ) {
		$post_id = absint( $log->post_id );

		if ( 0 === $post_id ) {
			echo esc_html__( 'System', 'wp-auto-publishing-queue' );
			return;
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			printf(
				/* translators: %d: Deleted post ID. */
				esc_html__( 'Deleted post (ID: %d)', 'wp-auto-publishing-queue' ),
				$post_id
			);
			return;
		}

		$title = get_the_title( $post );

		if ( current_user_can( 'edit_post', $post_id ) ) {
			printf(
				'<a href="%1$s">%2$s</a>',
				esc_url( get_edit_post_link( $post_id, 'raw' ) ),
				esc_html( $title )
			);
			return;
		}

		echo esc_html( $title );
	}

	/**
	 * Render pagination links.
	 *
	 * @param array $filters Filters.
	 * @param int   $current_page Current page.
	 * @param int   $total_pages Total pages.
	 */
	private function render_pagination( $filters, $current_page, $total_pages ) {
		if ( $total_pages <= 1 ) {
			return;
		}

		$args = array( 'page' => 'wpapq-logs' );

		if ( '' !== $filters['event_type'] ) {
			$args['wpapq_event_type'] = $filters['event_type'];
		}

		if ( '' !== $filters['status'] ) {
			$args['wpapq_status'] = $filters['status'];
		}

		if ( '' !== $filters['date_from'] ) {
			$args['wpapq_date_from'] = $filters['date_from'];
		}

		if ( '' !== $filters['date_to'] ) {
			$args['wpapq_date_to'] = $filters['date_to'];
		}

		$base = str_replace(
			999999999,
			'%#%',
			esc_url(
				add_query_arg(
					array_merge( $args, array( 'wpapq_log_page' => 999999999 ) ),
					admin_url( 'admin.php' )
				)
			)
		);

		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo wp_kses_post(
			paginate_links(
				array(
					'base'      => $base,
					'format'    => '',
					'current'   => max( 1, absint( $current_page ) ),
					'total'     => max( 1, absint( $total_pages ) ),
					'prev_text' => __( '&laquo;', 'wp-auto-publishing-queue' ),
					'next_text' => __( '&raquo;', 'wp-auto-publishing-queue' ),
				)
			)
		);
		echo '</div></div>';
	}

	/**
	 * Get sanitized filters from GET parameters.
	 *
	 * @return array
	 */
	private function get_filters() {
		$event_type = isset( $_GET['wpapq_event_type'] ) ? sanitize_key( wp_unslash( $_GET['wpapq_event_type'] ) ) : '';
		$status     = isset( $_GET['wpapq_status'] ) ? sanitize_key( wp_unslash( $_GET['wpapq_status'] ) ) : '';
		$date_from  = isset( $_GET['wpapq_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['wpapq_date_from'] ) ) : '';
		$date_to    = isset( $_GET['wpapq_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['wpapq_date_to'] ) ) : '';

		return array(
			'event_type' => array_key_exists( $event_type, $this->get_event_labels() ) ? $event_type : '',
			'status'     => array_key_exists( $status, $this->get_status_labels() ) ? $status : '',
			'date_from'  => $this->is_valid_date( $date_from ) ? $date_from : '',
			'date_to'    => $this->is_valid_date( $date_to ) ? $date_to : '',
		);
	}

	/**
	 * Get current page.
	 *
	 * @return int
	 */
	private function get_current_page() {
		return isset( $_GET['wpapq_log_page'] ) ? max( 1, absint( wp_unslash( $_GET['wpapq_log_page'] ) ) ) : 1;
	}

	/**
	 * Validate exact Y-m-d date.
	 *
	 * @param string $date Date.
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

	/**
	 * Get event labels.
	 *
	 * @return array
	 */
	private function get_event_labels() {
		return array(
			'publish'      => __( 'Publish', 'wp-auto-publishing-queue' ),
			'retry'        => __( 'Retry', 'wp-auto-publishing-queue' ),
			'cleanup'      => __( 'Cleanup', 'wp-auto-publishing-queue' ),
			'notification' => __( 'Notification', 'wp-auto-publishing-queue' ),
			'cron'         => __( 'Cron', 'wp-auto-publishing-queue' ),
		);
	}

	/**
	 * Get status labels.
	 *
	 * @return array
	 */
	private function get_status_labels() {
		return array(
			'success'   => __( 'Success', 'wp-auto-publishing-queue' ),
			'failed'    => __( 'Failed', 'wp-auto-publishing-queue' ),
			'scheduled' => __( 'Scheduled', 'wp-auto-publishing-queue' ),
			'skipped'   => __( 'Skipped', 'wp-auto-publishing-queue' ),
			'info'      => __( 'Info', 'wp-auto-publishing-queue' ),
		);
	}

	/**
	 * Get readable event label.
	 *
	 * @param string $event_type Event type.
	 * @return string
	 */
	private function get_event_label( $event_type ) {
		$labels = $this->get_event_labels();

		return $labels[ $event_type ] ?? __( 'Unknown', 'wp-auto-publishing-queue' );
	}

	/**
	 * Get readable status label.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private function get_status_label( $status ) {
		$labels = $this->get_status_labels();

		return $labels[ $status ] ?? __( 'Unknown', 'wp-auto-publishing-queue' );
	}
}
