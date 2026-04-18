<?php
/**
 * Grading admin class
 *
 * Handles the grading queue list and routing to the grading form.
 *
 * @package PressPrimer_Assignment
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load WP_List_Table if not already loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Grading admin class
 *
 * Manages the grading queue list table. Shows submissions awaiting
 * grading (status: submitted, grading) across all assignments.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Admin_Grading {

	/**
	 * List table instance
	 *
	 * @since 1.0.0
	 * @var PressPrimer_Assignment_Grading_List_Table
	 */
	private $list_table;

	/**
	 * Initialize grading admin
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'current_screen', [ $this, 'maybe_add_screen_options' ] );
		add_filter( 'set_screen_option_pressprimer_assignment_grading_per_page', [ $this, 'set_screen_option' ], 10, 3 );
	}

	/**
	 * Maybe add screen options based on current screen
	 *
	 * @since 1.0.0
	 */
	public function maybe_add_screen_options() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		// Match the screen ID by the submenu slug suffix.
		// WordPress may convert hyphens to underscores in the parent slug portion
		// of the screen ID, so we check for the unique submenu slug instead.
		if ( false !== strpos( $screen->id, 'pressprimer-assignment-grading' ) ) {
			$this->screen_options();
		}
	}

	/**
	 * Set up screen options
	 *
	 * @since 1.0.0
	 */
	public function screen_options() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for display routing.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( 'grade' === $action ) {
			return;
		}

		add_screen_option(
			'per_page',
			[
				'label'   => __( 'Submissions per page', 'pressprimer-assignment' ),
				'default' => 20,
				'option'  => 'pressprimer_assignment_grading_per_page',
			]
		);

		$this->list_table = new PressPrimer_Assignment_Grading_List_Table();

		// Register columns with the screen for Screen Options column toggles.
		$screen = get_current_screen();
		if ( $screen ) {
			$columns = $this->list_table->get_columns();

			add_filter(
				"manage_{$screen->id}_columns",
				function () use ( $columns ) {
					return $columns;
				}
			);
		}

		add_filter( 'set-screen-option', [ $this, 'set_screen_option' ], 10, 3 );
	}

	/**
	 * Set screen option
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $status Screen option value.
	 * @param string $option The option name.
	 * @param mixed  $value  The option value.
	 * @return mixed Screen option value.
	 */
	public function set_screen_option( $status, $option, $value ) {
		if ( 'pressprimer_assignment_grading_per_page' === $option ) {
			return $value;
		}

		return $status;
	}

	/**
	 * Display admin notices
	 *
	 * @since 1.0.0
	 */
	public function admin_notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only notice flags from redirect.
		if ( ! isset( $_GET['page'] ) || 'pressprimer-assignment-grading' !== $_GET['page'] ) {
			return;
		}

		if ( isset( $_GET['returned'] ) && absint( wp_unslash( $_GET['returned'] ) ) > 0 ) {
			$count = absint( wp_unslash( $_GET['returned'] ) );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of submissions returned */
						esc_html( _n( '%d submission returned to student.', '%d submissions returned to students.', $count, 'pressprimer-assignment' ) ),
						(int) $count
					);
					?>
				</p>
			</div>
			<?php
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Render admin page
	 *
	 * Routes to list view or grading form based on action parameter.
	 *
	 * @since 1.0.0
	 */
	public function render() {
		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_OWN ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'pressprimer-assignment' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for display routing.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'grade' === $action ) {
			$this->render_grade();
		} else {
			$this->render_list();
		}
	}

	/**
	 * Render grading queue list
	 *
	 * @since 1.0.0
	 */
	private function render_list() {
		if ( ! $this->list_table ) {
			$this->list_table = new PressPrimer_Assignment_Grading_List_Table();
		}

		$this->list_table->prepare_items();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Grading Queue', 'pressprimer-assignment' ); ?></h1>
			<hr class="wp-header-end">

			<form method="get">
				<input type="hidden" name="page" value="pressprimer-assignment-grading">
				<?php
				$this->list_table->search_box( __( 'Search Submissions', 'pressprimer-assignment' ), 'ppa-grading' );
				$this->list_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render grading form for a single submission
	 *
	 * Loads the React grading interface with document viewers,
	 * score input, feedback editor, and save/return controls.
	 *
	 * @since 1.0.0
	 */
	private function render_grade() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only submission ID for display routing.
		$submission_id = isset( $_GET['submission'] ) ? absint( wp_unslash( $_GET['submission'] ) ) : 0;

		if ( ! $submission_id ) {
			wp_die( esc_html__( 'Invalid submission.', 'pressprimer-assignment' ) );
		}

		$submission = PressPrimer_Assignment_Submission::get( $submission_id );

		if ( ! $submission ) {
			wp_die( esc_html__( 'Submission not found.', 'pressprimer-assignment' ) );
		}

		// Enqueue the React grading interface bundle.
		$this->enqueue_grading_interface( $submission_id );

		?>
		<!-- React Grading Interface Root -->
		<div class="wrap">
			<div id="ppa-grading-interface-root"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue React grading interface assets
	 *
	 * Loads the compiled grading-interface bundle and passes
	 * the submission ID to JavaScript via wp_localize_script.
	 *
	 * @since 1.0.0
	 *
	 * @param int $submission_id Submission ID.
	 */
	private function enqueue_grading_interface( int $submission_id ) {
		// Enqueue Ant Design CSS.
		$antd_css = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'assets/css/vendor/antd-reset.css';
		if ( file_exists( $antd_css ) ) {
			wp_enqueue_style(
				'antd',
				PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/css/vendor/antd-reset.css',
				[],
				'5.12.0'
			);
		}

		// Enqueue grading CSS.
		wp_enqueue_style(
			'ppa-grading',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/css/grading.css',
			[ 'ppa-admin' ],
			PRESSPRIMER_ASSIGNMENT_VERSION
		);

		// Enqueue the built React bundle.
		$asset_file = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'build/grading-interface.asset.php';
		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;

			wp_enqueue_script(
				'ppa-grading-interface',
				PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'build/grading-interface.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);

			$style_file = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'build/style-grading-interface.css';
			if ( file_exists( $style_file ) ) {
				wp_enqueue_style(
					'ppa-grading-interface',
					PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'build/style-grading-interface.css',
					[],
					$asset['version']
				);
			}
		} else {
			// Fallback: use wp-element, wp-i18n, wp-api-fetch as dependencies.
			wp_enqueue_script(
				'ppa-grading-interface',
				PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'build/grading-interface.js',
				[ 'wp-element', 'wp-i18n', 'wp-api-fetch' ],
				PRESSPRIMER_ASSIGNMENT_VERSION,
				true
			);
		}

		// Localize script with grading data.
		wp_localize_script(
			'ppa-grading-interface',
			'pressprimerAssignmentGradingData',
			[
				'submissionId' => $submission_id,
				'adminUrl'     => admin_url(),
				'buildUrl'     => PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'build/',
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'addons'       => [
					'educator'   => PressPrimer_Assignment_Addon_Manager::is_educator_active(),
					'school'     => PressPrimer_Assignment_Addon_Manager::is_school_active(),
					'enterprise' => PressPrimer_Assignment_Addon_Manager::is_enterprise_active(),
				],
			]
		);
	}
}

/**
 * Grading queue list table class
 *
 * Displays submissions awaiting grading across all assignments.
 * Only shows submissions with status "submitted" or "grading".
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Grading_List_Table extends WP_List_Table {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'submission',
				'plural'   => 'submissions',
				'ajax'     => false,
			]
		);
	}

	/**
	 * Get columns
	 *
	 * @since 1.0.0
	 *
	 * @return array Column definitions.
	 */
	public function get_columns() {
		return [
			'cb'           => '<input type="checkbox" />',
			'id'           => __( 'ID', 'pressprimer-assignment' ),
			'student'      => __( 'Student', 'pressprimer-assignment' ),
			'assignment'   => __( 'Assignment', 'pressprimer-assignment' ),
			'status'       => __( 'Status', 'pressprimer-assignment' ),
			'submitted_at' => __( 'Submitted', 'pressprimer-assignment' ),
		];
	}

	/**
	 * Get sortable columns
	 *
	 * @since 1.0.0
	 *
	 * @return array Sortable columns.
	 */
	public function get_sortable_columns() {
		return [
			'status'       => [ 'status', false ],
			'submitted_at' => [ 'submitted_at', true ],
		];
	}

	/**
	 * Get bulk actions
	 *
	 * @since 1.0.0
	 *
	 * @return array Bulk actions.
	 */
	public function get_bulk_actions() {
		return [];
	}

	/**
	 * Display message when no items are found
	 *
	 * @since 1.0.0
	 */
	public function no_items() {
		esc_html_e( 'No submissions awaiting grading.', 'pressprimer-assignment' );
	}

	/**
	 * Prepare items for display
	 *
	 * Uses the grading queue service to fetch pending submissions.
	 *
	 * @since 1.0.0
	 */
	public function prepare_items() {
		$per_page     = $this->get_items_per_page( 'pressprimer_assignment_grading_per_page', 20 );
		$current_page = $this->get_pagenum();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters for list table display.

		// Build args for the grading queue service.
		$args = [
			'per_page' => $per_page,
			'page'     => $current_page,
			'status'   => [ 'submitted', 'grading', 'graded' ],
		];

		// Filter by assignment.
		if ( isset( $_GET['assignment'] ) && '' !== $_GET['assignment'] ) {
			$args['assignment_id'] = absint( wp_unslash( $_GET['assignment'] ) );
		}

		// Filter by status.
		$get_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		if ( '' !== $get_status && 'all' !== $get_status ) {
			$valid = [ 'submitted', 'grading', 'graded' ];
			if ( in_array( $get_status, $valid, true ) ) {
				$args['status'] = [ $get_status ];
			}
		}

		// Search.
		if ( isset( $_GET['s'] ) && '' !== $_GET['s'] ) {
			$args['search'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
		}

		// Orderby and order.
		$allowed_orderby = [ 'status', 'submitted_at', 'assignment_title' ];
		$orderby         = isset( $_GET['orderby'] ) && '' !== $_GET['orderby'] ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'submitted_at';
		$orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'submitted_at';
		$order           = isset( $_GET['order'] ) && '' !== $_GET['order'] ? strtoupper( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'ASC';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
			$order = 'ASC';
		}

		$args['orderby'] = $orderby;
		$args['order']   = $order;

		// Fetch from the grading queue service.
		$result = PressPrimer_Assignment_Grading_Queue_Service::get_queue( $args );

		$this->items = $result['items'];

		// Set pagination.
		$this->set_pagination_args(
			[
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => $result['pages'],
			]
		);

		// Set columns.
		$columns               = $this->get_columns();
		$hidden                = get_hidden_columns( $this->screen );
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = [ $columns, $hidden, $sortable ];
	}

	/**
	 * Render checkbox column
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Submission row object.
	 * @return string Column content.
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="submissions[]" value="%d" />', $item->id );
	}

	/**
	 * Render ID column
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Submission row object.
	 * @return string Column content.
	 */
	public function column_id( $item ) {
		return sprintf( '<strong>%d</strong>', $item->id );
	}

	/**
	 * Render student column
	 *
	 * Shows student name with row actions (Grade, View).
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Submission row object.
	 * @return string Column content.
	 */
	public function column_student( $item ) {
		$name = ! empty( $item->student_name )
			? esc_html( $item->student_name )
			: esc_html__( 'Unknown User', 'pressprimer-assignment' );

		// Build row actions.
		$actions = [];

		$actions['grade'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url(
				add_query_arg(
					[
						'page'       => 'pressprimer-assignment-grading',
						'action'     => 'grade',
						'submission' => $item->id,
					],
					admin_url( 'admin.php' )
				)
			),
			esc_html__( 'Grade', 'pressprimer-assignment' )
		);

		return sprintf( '<strong>%s</strong>%s', $name, $this->row_actions( $actions ) );
	}

	/**
	 * Render assignment column
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Submission row object.
	 * @return string Column content.
	 */
	public function column_assignment( $item ) {
		if ( ! empty( $item->assignment_title ) ) {
			return sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					add_query_arg(
						[
							'page'       => 'pressprimer-assignment-assignments',
							'action'     => 'edit',
							'assignment' => $item->assignment_id,
						],
						admin_url( 'admin.php' )
					)
				),
				esc_html( $item->assignment_title )
			);
		}

		return '&#8212;';
	}

	/**
	 * Render status column
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Submission row object.
	 * @return string Column content.
	 */
	public function column_status( $item ) {
		$statuses = [
			'submitted' => __( 'Submitted', 'pressprimer-assignment' ),
			'grading'   => __( 'Grading', 'pressprimer-assignment' ),
			'graded'    => __( 'Graded', 'pressprimer-assignment' ),
		];

		return isset( $statuses[ $item->status ] ) ? esc_html( $statuses[ $item->status ] ) : esc_html( $item->status );
	}

	/**
	 * Render submitted_at column
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Submission row object.
	 * @return string Column content.
	 */
	public function column_submitted_at( $item ) {
		if ( empty( $item->submitted_at ) ) {
			return '&#8212;';
		}

		$timestamp = strtotime( $item->submitted_at );
		$time_diff = time() - $timestamp;

		if ( $time_diff < DAY_IN_SECONDS ) {
			return sprintf(
				'<abbr title="%s">%s</abbr>',
				esc_attr( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ),
				/* translators: %s: human-readable time difference */
				sprintf( esc_html__( '%s ago', 'pressprimer-assignment' ), human_time_diff( $timestamp ) )
			);
		}

		return wp_date( get_option( 'date_format' ), $timestamp );
	}

	/**
	 * Render filters above table
	 *
	 * @since 1.0.0
	 *
	 * @param string $which Top or bottom of table.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		?>
		<div class="alignleft actions">
			<?php
			$this->render_status_filter();
			$this->render_assignment_filter();
			submit_button( __( 'Filter', 'pressprimer-assignment' ), '', 'filter_action', false );
			?>
		</div>
		<?php
	}

	/**
	 * Render status filter dropdown
	 *
	 * @since 1.0.0
	 */
	private function render_status_filter() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state.
		$current_status = isset( $_GET['status'] ) && '' !== $_GET['status'] ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';

		$statuses = [
			'all'       => __( 'All Statuses', 'pressprimer-assignment' ),
			'submitted' => __( 'Submitted', 'pressprimer-assignment' ),
			'grading'   => __( 'Grading', 'pressprimer-assignment' ),
			'graded'    => __( 'Graded', 'pressprimer-assignment' ),
		];

		?>
		<label class="screen-reader-text" for="filter-by-status"><?php esc_html_e( 'Filter by status', 'pressprimer-assignment' ); ?></label>
		<select name="status" id="filter-by-status">
			<?php foreach ( $statuses as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_status, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render assignment filter dropdown
	 *
	 * @since 1.0.0
	 */
	private function render_assignment_filter() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state.
		$current_assignment = isset( $_GET['assignment'] ) ? absint( wp_unslash( $_GET['assignment'] ) ) : 0;

		$assignments = PressPrimer_Assignment_Grading_Queue_Service::get_assignments_for_filter();

		?>
		<label class="screen-reader-text" for="filter-by-assignment"><?php esc_html_e( 'Filter by assignment', 'pressprimer-assignment' ); ?></label>
		<select name="assignment" id="filter-by-assignment">
			<option value=""><?php esc_html_e( 'All Assignments', 'pressprimer-assignment' ); ?></option>
			<?php foreach ( $assignments as $assignment ) : ?>
				<option value="<?php echo esc_attr( $assignment->id ); ?>" <?php selected( $current_assignment, (int) $assignment->id ); ?>>
					<?php echo esc_html( $assignment->title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}
}
