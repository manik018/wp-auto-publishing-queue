<?php
/**
 * Admin foundation and settings page.
 *
 * @package WP_Auto_Publishing_Queue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu and settings registration.
 */
class WPAPQ_Admin {

	/**
	 * Queue page admin controller.
	 *
	 * @var WPAPQ_Queue_Page
	 */
	private $queue_page;

	/**
	 * Logs page admin controller.
	 *
	 * @var WPAPQ_Logs_Page
	 */
	private $logs_page;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->queue_page = new WPAPQ_Queue_Page();
		$this->logs_page  = new WPAPQ_Logs_Page();
	}

	/**
	 * Register admin hooks.
	 */
	public function run() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
		$this->queue_page->run();
		$this->logs_page->run();
	}

	/**
	 * Register the top-level admin menu and settings submenu.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Auto Publishing Queue', 'wp-auto-publishing-queue' ),
			__( 'Auto Publisher', 'wp-auto-publishing-queue' ),
			'manage_options',
			'wpapq',
			array( $this, 'render_overview_page' ),
			'dashicons-schedule',
			26
		);

		add_submenu_page(
			'wpapq',
			__( 'Auto Publishing Queue', 'wp-auto-publishing-queue' ),
			__( 'Overview', 'wp-auto-publishing-queue' ),
			'manage_options',
			'wpapq',
			array( $this, 'render_overview_page' )
		);

		add_submenu_page(
			'wpapq',
			__( 'Publishing Queue', 'wp-auto-publishing-queue' ),
			__( 'Publishing Queue', 'wp-auto-publishing-queue' ),
			'manage_options',
			'wpapq-queue',
			array( $this->queue_page, 'render_page' )
		);

		add_submenu_page(
			'wpapq',
			__( 'Publishing Logs', 'wp-auto-publishing-queue' ),
			__( 'Publishing Logs', 'wp-auto-publishing-queue' ),
			'manage_options',
			'wpapq-logs',
			array( $this->logs_page, 'render_page' )
		);

		add_submenu_page(
			'wpapq',
			__( 'Settings', 'wp-auto-publishing-queue' ),
			__( 'Settings', 'wp-auto-publishing-queue' ),
			'manage_options',
			'wpapq-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register the Settings API fields.
	 */
	public function register_settings() {
		register_setting(
			'wpapq_settings_group',
			'wpapq_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => WPAPQ_Activator::get_default_settings(),
			)
		);

		add_settings_section(
			'wpapq_general_section',
			__( 'General', 'wp-auto-publishing-queue' ),
			'__return_false',
			'wpapq-settings'
		);

		add_settings_section(
			'wpapq_schedule_section',
			__( 'Publishing Schedule', 'wp-auto-publishing-queue' ),
			'__return_false',
			'wpapq-settings'
		);

		add_settings_section(
			'wpapq_retry_section',
			__( 'Retry Settings', 'wp-auto-publishing-queue' ),
			'__return_false',
			'wpapq-settings'
		);

		add_settings_section(
			'wpapq_blocking_section',
			__( 'Blocking', 'wp-auto-publishing-queue' ),
			'__return_false',
			'wpapq-settings'
		);

		add_settings_section(
			'wpapq_notification_section',
			__( 'Notification Settings', 'wp-auto-publishing-queue' ),
			array( $this, 'render_notification_section' ),
			'wpapq-settings'
		);

		add_settings_section(
			'wpapq_timezone_section',
			__( 'WordPress Timezone Information', 'wp-auto-publishing-queue' ),
			array( $this, 'render_timezone_section' ),
			'wpapq-settings'
		);

		$this->add_checkbox_field(
			'wpapq_enabled',
			__( 'Enable Auto Publishing', 'wp-auto-publishing-queue' ),
			'enabled',
			'wpapq_general_section',
			__( 'Enable automatic publishing from the post queue.', 'wp-auto-publishing-queue' )
		);

		$this->add_time_field(
			'wpapq_publishing_start',
			__( 'Publishing Start Time', 'wp-auto-publishing-queue' ),
			'publishing_start',
			'wpapq_schedule_section'
		);

		$this->add_time_field(
			'wpapq_publishing_end',
			__( 'Publishing End Time', 'wp-auto-publishing-queue' ),
			'publishing_end',
			'wpapq_schedule_section'
		);

		add_settings_field(
			'wpapq_posts_per_day',
			__( 'Posts Per Day', 'wp-auto-publishing-queue' ),
			array( $this, 'render_posts_per_day_field' ),
			'wpapq-settings',
			'wpapq_schedule_section',
			array(
				'label_for' => 'wpapq_posts_per_day',
			)
		);

		$this->add_number_field(
			'wpapq_minimum_gap_minutes',
			__( 'Minimum Gap Between Posts', 'wp-auto-publishing-queue' ),
			'minimum_gap_minutes',
			'wpapq_schedule_section',
			1,
			1440,
			__( 'minutes', 'wp-auto-publishing-queue' )
		);

		$this->add_number_field(
			'wpapq_maximum_retries',
			__( 'Maximum Retry Attempts', 'wp-auto-publishing-queue' ),
			'maximum_retries',
			'wpapq_retry_section',
			1,
			10
		);

		$this->add_number_field(
			'wpapq_retry_interval',
			__( 'Retry Interval', 'wp-auto-publishing-queue' ),
			'retry_interval',
			'wpapq_retry_section',
			1,
			1440,
			__( 'minutes', 'wp-auto-publishing-queue' )
		);

		$this->add_number_field(
			'wpapq_low_queue_threshold',
			__( 'Low Queue Alert Threshold', 'wp-auto-publishing-queue' ),
			'low_queue_threshold',
			'wpapq_notification_section',
			0,
			1000,
			'',
			__( 'Send an admin notification when the number of queued posts drops below this value. Set to 0 to disable the low queue alert.', 'wp-auto-publishing-queue' )
		);

		add_settings_field(
			'wpapq_blocked_weekdays',
			__( 'Blocked Weekdays', 'wp-auto-publishing-queue' ),
			array( $this, 'render_blocked_weekdays_field' ),
			'wpapq-settings',
			'wpapq_blocking_section'
		);

		add_settings_field(
			'wpapq_blocked_dates',
			__( 'Blocked Dates', 'wp-auto-publishing-queue' ),
			array( $this, 'render_blocked_dates_field' ),
			'wpapq-settings',
			'wpapq_blocking_section'
		);
	}

	/**
	 * Render the plugin overview page.
	 */
	public function render_overview_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$metrics      = $this->get_status_metrics();
		$settings     = $this->get_settings();
		$scheduler    = new WPAPQ_Scheduler();
		$today        = WPAPQ_Helper::get_current_datetime()->format( 'Y-m-d' );
		$queue_url    = admin_url( 'admin.php?page=wpapq-queue' );
		$logs_url     = admin_url( 'admin.php?page=wpapq-logs' );
		$settings_url = admin_url( 'admin.php?page=wpapq-settings' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'WP Auto Publishing Queue', 'wp-auto-publishing-queue' ); ?></h1>
			<div class="notice notice-info">
				<p><?php echo esc_html__( 'Automatic publishing relies on WP-Cron, which only runs when your site receives traffic. On low-traffic or heavily cached sites, posts may publish later than scheduled. For exact timing, set up a real server cron job that calls wp-cron.php every 5 minutes.', 'wp-auto-publishing-queue' ); ?></p>
			</div>
			<?php if ( $scheduler->is_date_blocked( $today, $settings ) ) : ?>
				<div class="notice notice-warning">
					<p><?php echo esc_html__( 'Publishing is blocked today due to your weekday or date blocking rules in Settings. No posts will be published today.', 'wp-auto-publishing-queue' ); ?></p>
				</div>
			<?php endif; ?>
			<table class="widefat striped">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Automatic Publishing', 'wp-auto-publishing-queue' ); ?></th>
						<td><?php echo esc_html( $metrics['enabled'] ? __( 'Enabled', 'wp-auto-publishing-queue' ) : __( 'Disabled', 'wp-auto-publishing-queue' ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Active Queue', 'wp-auto-publishing-queue' ); ?></th>
						<td><?php echo esc_html( absint( $metrics['active_count'] ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Failed Posts', 'wp-auto-publishing-queue' ); ?></th>
						<td><?php echo esc_html( absint( $metrics['failed_count'] ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Scheduled Today', 'wp-auto-publishing-queue' ); ?></th>
						<td><?php echo esc_html( absint( $metrics['scheduled_today'] ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Published Today', 'wp-auto-publishing-queue' ); ?></th>
						<td><?php echo esc_html( absint( $metrics['published_today'] ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Next Publish', 'wp-auto-publishing-queue' ); ?></th>
						<td><?php echo esc_html( $metrics['next_publish'] ); ?></td>
					</tr>
				</tbody>
			</table>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $queue_url ); ?>">
					<?php echo esc_html__( 'View Publishing Queue', 'wp-auto-publishing-queue' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( $logs_url ); ?>">
					<?php echo esc_html__( 'View Publishing Logs', 'wp-auto-publishing-queue' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( $settings_url ); ?>">
					<?php echo esc_html__( 'Open Settings', 'wp-auto-publishing-queue' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Settings', 'wp-auto-publishing-queue' ); ?></h1>
			<div class="notice notice-info">
				<p><?php echo esc_html__( 'Automatic publishing relies on WP-Cron, which only runs when your site receives traffic. On low-traffic or heavily cached sites, posts may publish later than scheduled. For exact timing, set up a real server cron job that calls wp-cron.php every 5 minutes.', 'wp-auto-publishing-queue' ); ?></p>
			</div>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'wpapq_settings_group' );
				do_settings_sections( 'wpapq-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register dashboard widget for administrators.
	 */
	public function register_dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'wpapq_dashboard_widget',
			__( 'Auto Publishing Queue', 'wp-auto-publishing-queue' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render dashboard widget.
	 */
	public function render_dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$metrics      = $this->get_status_metrics();
		$queue_url    = admin_url( 'admin.php?page=wpapq-queue' );
		$logs_url     = admin_url( 'admin.php?page=wpapq-logs' );
		$settings_url = admin_url( 'admin.php?page=wpapq-settings' );
		?>
		<?php if ( ! $metrics['enabled'] ) : ?>
			<p><?php echo esc_html__( 'Automatic publishing is currently disabled.', 'wp-auto-publishing-queue' ); ?></p>
		<?php endif; ?>
		<ul>
			<li><?php printf( esc_html__( 'Active Queue: %d', 'wp-auto-publishing-queue' ), absint( $metrics['active_count'] ) ); ?></li>
			<li><?php printf( esc_html__( 'Failed: %d', 'wp-auto-publishing-queue' ), absint( $metrics['failed_count'] ) ); ?></li>
			<li><?php printf( esc_html__( 'Scheduled Today: %d', 'wp-auto-publishing-queue' ), absint( $metrics['scheduled_today'] ) ); ?></li>
			<li><?php printf( esc_html__( 'Published Today: %d', 'wp-auto-publishing-queue' ), absint( $metrics['published_today'] ) ); ?></li>
			<li><?php printf( esc_html__( 'Next Publish: %s', 'wp-auto-publishing-queue' ), esc_html( $metrics['next_publish'] ) ); ?></li>
		</ul>
		<?php if ( '' !== $metrics['next_post_title'] ) : ?>
			<p><?php echo esc_html( $metrics['next_post_title'] ); ?></p>
		<?php endif; ?>
		<p>
			<a href="<?php echo esc_url( $queue_url ); ?>"><?php echo esc_html__( 'View Queue', 'wp-auto-publishing-queue' ); ?></a>
			|
			<a href="<?php echo esc_url( $logs_url ); ?>"><?php echo esc_html__( 'View Logs', 'wp-auto-publishing-queue' ); ?></a>
			|
			<a href="<?php echo esc_url( $settings_url ); ?>"><?php echo esc_html__( 'Settings', 'wp-auto-publishing-queue' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Sanitize the full settings array.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$defaults = WPAPQ_Activator::get_default_settings();
		$current  = get_option( 'wpapq_settings', array() );

		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$base  = $this->normalize_settings( array_merge( $defaults, $current ) );
		$input = is_array( $input ) ? $input : array();
		$posts_per_day_mode = isset( $input['posts_per_day_mode'] ) && is_scalar( $input['posts_per_day_mode'] ) ? sanitize_key( wp_unslash( $input['posts_per_day_mode'] ) ) : '';

		$sanitized = array(
			'enabled'             => isset( $input['enabled'] ) ? 1 : 0,
			'publishing_start'    => $this->sanitize_time_value( $input, 'publishing_start', $base['publishing_start'] ),
			'publishing_end'      => $this->sanitize_time_value( $input, 'publishing_end', $base['publishing_end'] ),
			'posts_per_day'       => $this->sanitize_integer_value( $input, 'posts_per_day', 1, 100, $base['posts_per_day'] ),
			'posts_per_day_mode'  => in_array( $posts_per_day_mode, array( 'fixed', 'random' ), true ) ? $posts_per_day_mode : 'fixed',
			'posts_per_day_min'   => $this->sanitize_integer_value( $input, 'posts_per_day_min', 1, 100, $base['posts_per_day_min'] ),
			'posts_per_day_max'   => $this->sanitize_integer_value( $input, 'posts_per_day_max', 1, 100, $base['posts_per_day_max'] ),
			'minimum_gap_minutes' => $this->sanitize_integer_value( $input, 'minimum_gap_minutes', 1, 1440, $base['minimum_gap_minutes'] ),
			'maximum_retries'     => $this->sanitize_integer_value( $input, 'maximum_retries', 1, 10, $base['maximum_retries'] ),
			'retry_interval'      => $this->sanitize_integer_value( $input, 'retry_interval', 1, 1440, $base['retry_interval'] ),
			'low_queue_threshold' => $this->sanitize_integer_value( $input, 'low_queue_threshold', 0, 1000, $base['low_queue_threshold'] ),
		);

		if ( $sanitized['posts_per_day_min'] > $sanitized['posts_per_day_max'] ) {
			$sanitized['posts_per_day_max'] = $sanitized['posts_per_day_min'];
		}

		$blocked_weekdays = isset( $input['blocked_weekdays'] ) && is_array( $input['blocked_weekdays'] ) ? $input['blocked_weekdays'] : array();
		$blocked_weekdays = array_map( 'absint', $blocked_weekdays );
		$blocked_weekdays = array_filter(
			$blocked_weekdays,
			function ( $day ) {
				return $day >= 0 && $day <= 6;
			}
		);
		$blocked_weekdays = array_unique( $blocked_weekdays );
		sort( $blocked_weekdays, SORT_NUMERIC );

		$blocked_dates_raw = isset( $input['blocked_dates'] ) && is_scalar( $input['blocked_dates'] ) ? sanitize_textarea_field( wp_unslash( $input['blocked_dates'] ) ) : '';
		$blocked_dates     = preg_split( '/[\r\n,]+/', $blocked_dates_raw );
		$blocked_dates     = array_map( 'trim', is_array( $blocked_dates ) ? $blocked_dates : array() );
		$blocked_dates     = array_filter(
			$blocked_dates,
			function ( $date ) {
				if ( 1 !== preg_match( '/\A\d{4}-\d{2}-\d{2}\z/', $date ) ) {
					return false;
				}

				$parts = explode( '-', $date );

				return checkdate( absint( $parts[1] ), absint( $parts[2] ), absint( $parts[0] ) );
			}
		);
		$blocked_dates     = array_unique( $blocked_dates );
		sort( $blocked_dates, SORT_STRING );

		$sanitized['blocked_weekdays'] = array_values( $blocked_weekdays );
		$sanitized['blocked_dates']    = array_slice( array_values( $blocked_dates ), 0, 366 );

		$start_minutes = $this->time_to_minutes( $sanitized['publishing_start'] );
		$end_minutes   = $this->time_to_minutes( $sanitized['publishing_end'] );

		if ( $start_minutes >= $end_minutes ) {
			$this->restore_schedule_settings( $sanitized, $base );
			add_settings_error(
				'wpapq_settings',
				'wpapq_invalid_time_window',
				__( 'Publishing start time must be earlier than publishing end time. Overnight publishing windows are not supported in this version.', 'wp-auto-publishing-queue' ),
				'error'
			);

			return $sanitized;
		}

		$available_window_minutes = $end_minutes - $start_minutes;
		$effective_daily_count    = ( 'random' === $sanitized['posts_per_day_mode'] ) ? $sanitized['posts_per_day_max'] : $sanitized['posts_per_day'];
		$required_gap_minutes     = ( $effective_daily_count - 1 ) * $sanitized['minimum_gap_minutes'];

		if ( $required_gap_minutes > $available_window_minutes ) {
			$this->restore_schedule_settings( $sanitized, $base );
			add_settings_error(
				'wpapq_settings',
				'wpapq_impossible_schedule',
				__( 'The publishing schedule is not possible. Increase the publishing time window, reduce posts per day, or reduce the minimum gap.', 'wp-auto-publishing-queue' ),
				'error'
			);
		}

		return $sanitized;
	}

	/**
	 * Render the notification information.
	 */
	public function render_notification_section() {
		$admin_email = WPAPQ_Helper::get_admin_email();

		printf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: WordPress administration email address. */
					__( 'Notifications will be sent to: %s', 'wp-auto-publishing-queue' ),
					$admin_email
				)
			)
		);
	}

	/**
	 * Render the WordPress timezone information.
	 */
	public function render_timezone_section() {
		$timezone = WPAPQ_Helper::get_timezone();
		?>
		<p>
			<?php
			printf(
				/* translators: %s: WordPress timezone name. */
				esc_html__( 'WordPress Timezone: %s', 'wp-auto-publishing-queue' ),
				esc_html( $timezone->getName() )
			);
			?>
		</p>
		<p>
			<?php
			printf(
				/* translators: %s: Current WordPress date and time. */
				esc_html__( 'Current WordPress Time: %s', 'wp-auto-publishing-queue' ),
				esc_html( WPAPQ_Helper::get_formatted_current_datetime() )
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render a checkbox field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_checkbox_field( $args ) {
		$settings = $this->get_settings();
		$key      = $args['key'];
		?>
		<label for="<?php echo esc_attr( $args['label_for'] ); ?>">
			<input
				type="checkbox"
				id="<?php echo esc_attr( $args['label_for'] ); ?>"
				name="wpapq_settings[<?php echo esc_attr( $key ); ?>]"
				value="1"
				<?php checked( 1, $settings[ $key ] ); ?>
			/>
			<?php echo esc_html( $args['description'] ); ?>
		</label>
		<?php
	}

	/**
	 * Render a time field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_time_field( $args ) {
		$settings = $this->get_settings();
		$key      = $args['key'];
		?>
		<input
			type="time"
			id="<?php echo esc_attr( $args['label_for'] ); ?>"
			name="wpapq_settings[<?php echo esc_attr( $key ); ?>]"
			value="<?php echo esc_attr( $settings[ $key ] ); ?>"
			required
		/>
		<?php
	}

	/**
	 * Render a number field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_number_field( $args ) {
		$settings = $this->get_settings();
		$key      = $args['key'];
		?>
		<input
			type="number"
			id="<?php echo esc_attr( $args['label_for'] ); ?>"
			name="wpapq_settings[<?php echo esc_attr( $key ); ?>]"
			value="<?php echo esc_attr( $settings[ $key ] ); ?>"
			min="<?php echo esc_attr( $args['min'] ); ?>"
			max="<?php echo esc_attr( $args['max'] ); ?>"
			step="1"
		/>
		<?php if ( '' !== $args['unit'] ) : ?>
			<span><?php echo esc_html( $args['unit'] ); ?></span>
		<?php endif; ?>
		<?php if ( '' !== $args['description'] ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render the posts per day mode and values.
	 */
	public function render_posts_per_day_field() {
		$settings = $this->get_settings();
		?>
		<p>
			<label>
				<input type="radio" name="wpapq_settings[posts_per_day_mode]" value="fixed" <?php checked( 'fixed', $settings['posts_per_day_mode'] ); ?> />
				<?php echo esc_html__( 'Fixed amount', 'wp-auto-publishing-queue' ); ?>
			</label>
			<br />
			<label>
				<input type="radio" name="wpapq_settings[posts_per_day_mode]" value="random" <?php checked( 'random', $settings['posts_per_day_mode'] ); ?> />
				<?php echo esc_html__( 'Random amount (pick a range)', 'wp-auto-publishing-queue' ); ?>
			</label>
		</p>
		<div id="wpapq-fixed-per-day-row">
			<input
				type="number"
				id="wpapq_posts_per_day"
				name="wpapq_settings[posts_per_day]"
				value="<?php echo esc_attr( $settings['posts_per_day'] ); ?>"
				min="1"
				max="100"
				step="1"
			/>
		</div>
		<div id="wpapq-random-per-day-row">
			<p>
				<label for="wpapq_posts_per_day_min"><?php echo esc_html__( 'Minimum Posts Per Day', 'wp-auto-publishing-queue' ); ?></label>
				<br />
				<input
					type="number"
					id="wpapq_posts_per_day_min"
					name="wpapq_settings[posts_per_day_min]"
					value="<?php echo esc_attr( $settings['posts_per_day_min'] ); ?>"
					min="1"
					max="100"
					step="1"
				/>
			</p>
			<p>
				<label for="wpapq_posts_per_day_max"><?php echo esc_html__( 'Maximum Posts Per Day', 'wp-auto-publishing-queue' ); ?></label>
				<br />
				<input
					type="number"
					id="wpapq_posts_per_day_max"
					name="wpapq_settings[posts_per_day_max]"
					value="<?php echo esc_attr( $settings['posts_per_day_max'] ); ?>"
					min="1"
					max="100"
					step="1"
				/>
			</p>
		</div>
		<script>
			(function() {
				var fixedRow = document.getElementById('wpapq-fixed-per-day-row');
				var randomRow = document.getElementById('wpapq-random-per-day-row');
				var radios = document.querySelectorAll('input[name="wpapq_settings[posts_per_day_mode]"]');
				var toggleRows = function() {
					var selected = document.querySelector('input[name="wpapq_settings[posts_per_day_mode]"]:checked');
					var isRandom = selected && 'random' === selected.value;

					if (fixedRow) {
						fixedRow.style.display = isRandom ? 'none' : '';
					}

					if (randomRow) {
						randomRow.style.display = isRandom ? '' : 'none';
					}
				};

				for (var index = 0; index < radios.length; index++) {
					radios[index].addEventListener('change', toggleRows);
				}

				toggleRows();
			}());
		</script>
		<?php
	}

	/**
	 * Render blocked weekdays.
	 */
	public function render_blocked_weekdays_field() {
		$settings = $this->get_settings();
		$days     = array(
			0 => __( 'Sunday', 'wp-auto-publishing-queue' ),
			1 => __( 'Monday', 'wp-auto-publishing-queue' ),
			2 => __( 'Tuesday', 'wp-auto-publishing-queue' ),
			3 => __( 'Wednesday', 'wp-auto-publishing-queue' ),
			4 => __( 'Thursday', 'wp-auto-publishing-queue' ),
			5 => __( 'Friday', 'wp-auto-publishing-queue' ),
			6 => __( 'Saturday', 'wp-auto-publishing-queue' ),
		);
		?>
		<?php foreach ( $days as $day => $label ) : ?>
			<label>
				<input
					type="checkbox"
					name="wpapq_settings[blocked_weekdays][]"
					value="<?php echo esc_attr( $day ); ?>"
					<?php checked( in_array( $day, $settings['blocked_weekdays'], true ) ); ?>
				/>
				<?php echo esc_html( $label ); ?>
			</label>
			<br />
		<?php endforeach; ?>
		<?php
	}

	/**
	 * Render blocked dates.
	 */
	public function render_blocked_dates_field() {
		$settings = $this->get_settings();
		?>
		<textarea name="wpapq_settings[blocked_dates]" rows="4" class="large-text"><?php echo esc_textarea( implode( "\n", $settings['blocked_dates'] ) ); ?></textarea>
		<p class="description"><?php echo esc_html__( 'One date per line, format YYYY-MM-DD. Publishing will be skipped entirely on these dates.', 'wp-auto-publishing-queue' ); ?></p>
		<?php
	}

	/**
	 * Get live status metrics for overview and dashboard displays.
	 *
	 * @return array
	 */
	private function get_status_metrics() {
		$queue     = new WPAPQ_Queue();
		$scheduler = new WPAPQ_Scheduler();
		$logger    = new WPAPQ_Logger();
		$settings  = $this->get_settings();
		$next_item = $scheduler->get_next_scheduled_item();

		$next_publish    = __( 'Not scheduled', 'wp-auto-publishing-queue' );
		$next_post_title = '';

		if ( null !== $next_item && ! empty( $next_item->scheduled_at ) ) {
			$next_publish = WPAPQ_Helper::format_mysql_datetime( $next_item->scheduled_at, __( 'Not scheduled', 'wp-auto-publishing-queue' ) );

			if ( ! empty( $next_item->post_id ) && get_post( absint( $next_item->post_id ) ) ) {
				$next_post_title = get_the_title( absint( $next_item->post_id ) );
			}
		}

		return array(
			'enabled'         => ! empty( $settings['enabled'] ),
			'active_count'    => $queue->get_active_count(),
			'failed_count'    => $queue->get_failed_count(),
			'scheduled_today' => $scheduler->count_scheduled_posts_for_date(),
			'published_today' => $logger->count_published_success_for_date(),
			'next_publish'    => $next_publish,
			'next_post_title' => $next_post_title,
		);
	}

	/**
	 * Add a checkbox setting field.
	 *
	 * @param string $id Field ID.
	 * @param string $title Field title.
	 * @param string $key Settings key.
	 * @param string $section Settings section.
	 * @param string $description Field description.
	 */
	private function add_checkbox_field( $id, $title, $key, $section, $description ) {
		add_settings_field(
			$id,
			$title,
			array( $this, 'render_checkbox_field' ),
			'wpapq-settings',
			$section,
			array(
				'label_for'   => $id,
				'key'         => $key,
				'description' => $description,
			)
		);
	}

	/**
	 * Add a time setting field.
	 *
	 * @param string $id Field ID.
	 * @param string $title Field title.
	 * @param string $key Settings key.
	 * @param string $section Settings section.
	 */
	private function add_time_field( $id, $title, $key, $section ) {
		add_settings_field(
			$id,
			$title,
			array( $this, 'render_time_field' ),
			'wpapq-settings',
			$section,
			array(
				'label_for' => $id,
				'key'       => $key,
			)
		);
	}

	/**
	 * Add a number setting field.
	 *
	 * @param string $id Field ID.
	 * @param string $title Field title.
	 * @param string $key Settings key.
	 * @param string $section Settings section.
	 * @param int    $min Minimum value.
	 * @param int    $max Maximum value.
	 * @param string $unit Optional unit label.
	 * @param string $description Optional description.
	 */
	private function add_number_field( $id, $title, $key, $section, $min, $max, $unit = '', $description = '' ) {
		add_settings_field(
			$id,
			$title,
			array( $this, 'render_number_field' ),
			'wpapq-settings',
			$section,
			array(
				'label_for'   => $id,
				'key'         => $key,
				'min'         => $min,
				'max'         => $max,
				'unit'        => $unit,
				'description' => $description,
			)
		);
	}

	/**
	 * Get normalized settings for rendering.
	 *
	 * @return array
	 */
	private function get_settings() {
		$settings = get_option( 'wpapq_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return $this->normalize_settings( array_merge( WPAPQ_Activator::get_default_settings(), $settings ) );
	}

	/**
	 * Return only known settings keys with normalized scalar values.
	 *
	 * @param array $settings Settings array.
	 * @return array
	 */
	private function normalize_settings( $settings ) {
		$defaults = WPAPQ_Activator::get_default_settings();

		return array(
			'enabled'             => empty( $settings['enabled'] ) ? 0 : 1,
			'publishing_start'    => $this->is_valid_time( $settings['publishing_start'] ) ? $settings['publishing_start'] : $defaults['publishing_start'],
			'publishing_end'      => $this->is_valid_time( $settings['publishing_end'] ) ? $settings['publishing_end'] : $defaults['publishing_end'],
			'posts_per_day'       => $this->clamp_integer( absint( $settings['posts_per_day'] ), 1, 100 ),
			'posts_per_day_mode'  => in_array( $settings['posts_per_day_mode'] ?? '', array( 'fixed', 'random' ), true ) ? $settings['posts_per_day_mode'] : 'fixed',
			'posts_per_day_min'   => $this->clamp_integer( absint( $settings['posts_per_day_min'] ?? 1 ), 1, 100 ),
			'posts_per_day_max'   => $this->clamp_integer( absint( $settings['posts_per_day_max'] ?? 5 ), 1, 100 ),
			'minimum_gap_minutes' => $this->clamp_integer( absint( $settings['minimum_gap_minutes'] ), 1, 1440 ),
			'maximum_retries'     => $this->clamp_integer( absint( $settings['maximum_retries'] ), 1, 10 ),
			'retry_interval'      => $this->clamp_integer( absint( $settings['retry_interval'] ), 1, 1440 ),
			'low_queue_threshold' => $this->clamp_integer( absint( $settings['low_queue_threshold'] ), 0, 1000 ),
			'blocked_weekdays'    => is_array( $settings['blocked_weekdays'] ?? null ) ? $settings['blocked_weekdays'] : array(),
			'blocked_dates'       => is_array( $settings['blocked_dates'] ?? null ) ? $settings['blocked_dates'] : array(),
		);
	}

	/**
	 * Sanitize a time field, preserving fallback on invalid input.
	 *
	 * @param array  $input Submitted settings.
	 * @param string $key Setting key.
	 * @param string $fallback Fallback time.
	 * @return string
	 */
	private function sanitize_time_value( $input, $key, $fallback ) {
		if ( ! isset( $input[ $key ] ) ) {
			return $fallback;
		}

		if ( ! is_scalar( $input[ $key ] ) ) {
			return $fallback;
		}

		$value = sanitize_text_field( wp_unslash( $input[ $key ] ) );

		if ( ! $this->is_valid_time( $value ) ) {
			add_settings_error(
				'wpapq_settings',
				'wpapq_invalid_' . $key,
				__( 'Invalid time format submitted. Please use HH:MM in 24-hour format.', 'wp-auto-publishing-queue' ),
				'error'
			);

			return $fallback;
		}

		return $value;
	}

	/**
	 * Sanitize and clamp an integer field.
	 *
	 * @param array  $input Submitted settings.
	 * @param string $key Setting key.
	 * @param int    $min Minimum value.
	 * @param int    $max Maximum value.
	 * @param int    $fallback Fallback value.
	 * @return int
	 */
	private function sanitize_integer_value( $input, $key, $min, $max, $fallback ) {
		if ( ! isset( $input[ $key ] ) ) {
			return $fallback;
		}

		if ( ! is_scalar( $input[ $key ] ) ) {
			return $fallback;
		}

		return $this->clamp_integer( absint( wp_unslash( $input[ $key ] ) ), $min, $max );
	}

	/**
	 * Clamp an integer to a minimum and maximum.
	 *
	 * @param int $value Value.
	 * @param int $min Minimum value.
	 * @param int $max Maximum value.
	 * @return int
	 */
	private function clamp_integer( $value, $min, $max ) {
		return min( max( $value, $min ), $max );
	}

	/**
	 * Check for strict HH:MM 24-hour time.
	 *
	 * @param mixed $value Time value.
	 * @return bool
	 */
	private function is_valid_time( $value ) {
		return is_string( $value ) && 1 === preg_match( '/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $value );
	}

	/**
	 * Convert an HH:MM time to minutes after midnight.
	 *
	 * @param string $time Time value.
	 * @return int
	 */
	private function time_to_minutes( $time ) {
		$parts = explode( ':', $time );

		return ( absint( $parts[0] ) * 60 ) + absint( $parts[1] );
	}

	/**
	 * Restore schedule-related settings from the previous valid values.
	 *
	 * @param array $sanitized Sanitized settings.
	 * @param array $base Existing settings.
	 */
	private function restore_schedule_settings( &$sanitized, $base ) {
		$sanitized['publishing_start']    = $base['publishing_start'];
		$sanitized['publishing_end']      = $base['publishing_end'];
		$sanitized['posts_per_day']       = $base['posts_per_day'];
		$sanitized['posts_per_day_mode']  = $base['posts_per_day_mode'];
		$sanitized['posts_per_day_min']   = $base['posts_per_day_min'];
		$sanitized['posts_per_day_max']   = $base['posts_per_day_max'];
		$sanitized['minimum_gap_minutes'] = $base['minimum_gap_minutes'];
	}
}
