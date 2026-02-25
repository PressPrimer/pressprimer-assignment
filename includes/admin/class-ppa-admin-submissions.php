<?php
/**
 * Submissions admin class
 *
 * Handles the submissions list and detail view.
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
 * Submissions admin class
 *
 * Manages the submissions list table and detail view.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Admin_Submissions {

	/**
	 * List table instance
	 *
	 * @since 1.0.0
	 * @var PressPrimer_Assignment_Submissions_List_Table
	 */
	private $list_table;

	/**
	 * Initialize submissions admin
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'admin_init', [ $this, 'handle_actions' ] );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'current_screen', [ $this, 'maybe_add_screen_options' ] );
		add_filter( 'set_screen_option_pressprimer_assignment_submissions_per_page', [ $this, 'set_screen_option' ], 10, 3 );
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
		if ( false !== strpos( $screen->id, 'pressprimer-assignment-submissions' ) ) {
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
		if ( 'view' === $action ) {
			return;
		}

		add_screen_option(
			'per_page',
			[
				'label'   => __( 'Submissions per page', 'pressprimer-assignment' ),
				'default' => 20,
				'option'  => 'pressprimer_assignment_submissions_per_page',
			]
		);

		$this->list_table = new PressPrimer_Assignment_Submissions_List_Table();

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
		if ( 'pressprimer_assignment_submissions_per_page' === $option ) {
			return $value;
		}

		return $status;
	}

	/**
	 * Handle actions
	 *
	 * @since 1.0.0
	 */
	public function handle_actions() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verified in individual handlers.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'pressprimer-assignment-submissions' !== $page ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'delete' === $action && isset( $_GET['submission'] ) ) {
			$this->handle_delete();
		}

		// Bulk delete.
		$bulk_action = $this->current_bulk_action();
		if ( 'delete' === $bulk_action && isset( $_GET['submissions'] ) ) {
			$this->handle_bulk_delete();
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Display admin notices
	 *
	 * @since 1.0.0
	 */
	public function admin_notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only notice flags from redirect.
		if ( ! isset( $_GET['page'] ) || 'pressprimer-assignment-submissions' !== $_GET['page'] ) {
			return;
		}

		if ( isset( $_GET['deleted'] ) && absint( wp_unslash( $_GET['deleted'] ) ) > 0 ) {
			$count = absint( wp_unslash( $_GET['deleted'] ) );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of submissions deleted */
						esc_html( _n( '%d submission deleted.', '%d submissions deleted.', $count, 'pressprimer-assignment' ) ),
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
	 * @since 1.0.0
	 */
	public function render() {
		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'pressprimer-assignment' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for display routing.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'view' === $action ) {
			$this->render_view();
		} else {
			$this->render_list();
		}
	}

	/**
	 * Render submissions list
	 *
	 * @since 1.0.0
	 */
	private function render_list() {
		if ( ! $this->list_table ) {
			$this->list_table = new PressPrimer_Assignment_Submissions_List_Table();
		}

		$this->list_table->prepare_items();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Submissions', 'pressprimer-assignment' ); ?></h1>
			<hr class="wp-header-end">

			<form method="get">
				<input type="hidden" name="page" value="pressprimer-assignment-submissions">
				<?php
				$this->list_table->search_box( __( 'Search Submissions', 'pressprimer-assignment' ), 'ppa-submission' );
				$this->list_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render submission detail view
	 *
	 * @since 1.0.0
	 */
	private function render_view() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only submission ID for detail display.
		$submission_id = isset( $_GET['submission'] ) ? absint( wp_unslash( $_GET['submission'] ) ) : 0;
		$submission    = PressPrimer_Assignment_Submission::get( $submission_id );

		if ( ! $submission ) {
			wp_die( esc_html__( 'Submission not found.', 'pressprimer-assignment' ) );
		}

		$assignment = $submission->get_assignment();
		$user       = get_userdata( $submission->user_id );
		$files      = $submission->get_files();

		$statuses = [
			'draft'     => __( 'Draft', 'pressprimer-assignment' ),
			'submitted' => __( 'Submitted', 'pressprimer-assignment' ),
			'grading'   => __( 'Grading', 'pressprimer-assignment' ),
			'graded'    => __( 'Graded', 'pressprimer-assignment' ),
			'returned'  => __( 'Returned', 'pressprimer-assignment' ),
		];

		$back_url = admin_url( 'admin.php?page=pressprimer-assignment-submissions' );
		?>
		<div class="wrap">
			<h1>
				<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action" style="margin-right: 10px;">
					&larr; <?php esc_html_e( 'Back to Submissions', 'pressprimer-assignment' ); ?>
				</a>
				<?php esc_html_e( 'Submission Details', 'pressprimer-assignment' ); ?>
			</h1>

			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-2">
					<div id="post-body-content">
						<div class="postbox">
							<h2 class="hndle"><?php esc_html_e( 'Submission Information', 'pressprimer-assignment' ); ?></h2>
							<div class="inside">
								<table class="form-table">
									<tr>
										<th scope="row"><?php esc_html_e( 'ID', 'pressprimer-assignment' ); ?></th>
										<td><?php echo esc_html( $submission->id ); ?></td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Assignment', 'pressprimer-assignment' ); ?></th>
										<td>
											<?php if ( $assignment ) : ?>
												<a href="<?php echo esc_url( admin_url( 'admin.php?page=pressprimer-assignment-assignments&action=edit&assignment=' . $assignment->id ) ); ?>">
													<?php echo esc_html( $assignment->title ); ?>
												</a>
											<?php else : ?>
												&#8212;
											<?php endif; ?>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Student', 'pressprimer-assignment' ); ?></th>
										<td>
											<?php if ( $user ) : ?>
												<?php echo esc_html( $user->display_name ); ?>
												(<?php echo esc_html( $user->user_email ); ?>)
											<?php else : ?>
												<?php esc_html_e( 'Unknown User', 'pressprimer-assignment' ); ?>
											<?php endif; ?>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Status', 'pressprimer-assignment' ); ?></th>
										<td><?php echo isset( $statuses[ $submission->status ] ) ? esc_html( $statuses[ $submission->status ] ) : esc_html( $submission->status ); ?></td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Submitted', 'pressprimer-assignment' ); ?></th>
										<td>
											<?php
											if ( $submission->submitted_at ) {
												echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $submission->submitted_at ) ) );
											} else {
												echo '&#8212;';
											}
											?>
										</td>
									</tr>
									<?php if ( null !== $submission->score ) : ?>
									<tr>
										<th scope="row"><?php esc_html_e( 'Score', 'pressprimer-assignment' ); ?></th>
										<td>
											<?php
											echo esc_html( $submission->score );
											if ( $assignment ) {
												echo ' / ' . esc_html( $assignment->max_points );
											}
											?>
										</td>
									</tr>
									<?php endif; ?>
								</table>
							</div>
						</div>

						<?php if ( $submission->student_notes ) : ?>
						<div class="postbox">
							<h2 class="hndle"><?php esc_html_e( 'Student Notes', 'pressprimer-assignment' ); ?></h2>
							<div class="inside">
								<?php echo wp_kses_post( wpautop( $submission->student_notes ) ); ?>
							</div>
						</div>
						<?php endif; ?>

						<?php if ( ! empty( $files ) ) : ?>
						<div class="postbox">
							<h2 class="hndle"><?php esc_html_e( 'Files', 'pressprimer-assignment' ); ?></h2>
							<div class="inside">
								<table class="widefat fixed striped">
									<thead>
										<tr>
											<th><?php esc_html_e( 'File Name', 'pressprimer-assignment' ); ?></th>
											<th><?php esc_html_e( 'Size', 'pressprimer-assignment' ); ?></th>
											<th><?php esc_html_e( 'Type', 'pressprimer-assignment' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $files as $file ) : ?>
										<tr>
											<td><?php echo esc_html( $file->original_filename ); ?></td>
											<td><?php echo esc_html( size_format( $file->file_size ) ); ?></td>
											<td><?php echo esc_html( $file->file_extension ); ?></td>
										</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
						<?php endif; ?>

						<?php if ( $submission->feedback ) : ?>
						<div class="postbox">
							<h2 class="hndle"><?php esc_html_e( 'Grader Feedback', 'pressprimer-assignment' ); ?></h2>
							<div class="inside">
								<?php echo wp_kses_post( wpautop( $submission->feedback ) ); ?>
							</div>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle single submission delete
	 *
	 * @since 1.0.0
	 */
	private function handle_delete() {
		if ( ! isset( $_GET['submission'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'pressprimer-assignment' ) );
		}

		$submission_id_raw = absint( wp_unslash( $_GET['submission'] ) );
		check_admin_referer( 'delete-submission_' . $submission_id_raw );

		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			wp_die( esc_html__( 'You do not have permission to delete submissions.', 'pressprimer-assignment' ) );
		}

		$submission = PressPrimer_Assignment_Submission::get( $submission_id_raw );

		if ( ! $submission ) {
			wp_die( esc_html__( 'Submission not found.', 'pressprimer-assignment' ) );
		}

		$result = $submission->delete();

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		wp_safe_redirect( add_query_arg( 'deleted', '1', admin_url( 'admin.php?page=pressprimer-assignment-submissions' ) ) );
		exit;
	}

	/**
	 * Get the current bulk action
	 *
	 * @since 1.0.0
	 *
	 * @return string|false The bulk action or false.
	 */
	private function current_bulk_action() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verified in individual handlers.
		$action = false;

		if ( isset( $_GET['action'] ) && -1 !== (int) $_GET['action'] && '-1' !== $_GET['action'] ) {
			$action = sanitize_key( wp_unslash( $_GET['action'] ) );
		}

		if ( isset( $_GET['action2'] ) && -1 !== (int) $_GET['action2'] && '-1' !== $_GET['action2'] ) {
			$action = sanitize_key( wp_unslash( $_GET['action2'] ) );
		}

		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return $action;
	}

	/**
	 * Handle bulk delete
	 *
	 * @since 1.0.0
	 */
	private function handle_bulk_delete() {
		check_admin_referer( 'bulk-submissions' );

		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			wp_die( esc_html__( 'You do not have permission to delete submissions.', 'pressprimer-assignment' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Validated above via check_admin_referer.
		$submission_ids = isset( $_GET['submissions'] ) ? array_map( 'absint', wp_unslash( $_GET['submissions'] ) ) : [];
		$deleted        = 0;

		foreach ( $submission_ids as $submission_id ) {
			$submission = PressPrimer_Assignment_Submission::get( $submission_id );

			if ( ! $submission ) {
				continue;
			}

			$result = $submission->delete();

			if ( ! is_wp_error( $result ) ) {
				++$deleted;
			}
		}

		wp_safe_redirect( add_query_arg( 'deleted', $deleted, admin_url( 'admin.php?page=pressprimer-assignment-submissions' ) ) );
		exit;
	}
}

/**
 * Submissions list table class
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Submissions_List_Table extends WP_List_Table {

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
			'files'        => __( 'Files', 'pressprimer-assignment' ),
			'score'        => __( 'Score', 'pressprimer-assignment' ),
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
			'submitted_at' => [ 'submitted_at', false ],
			'score'        => [ 'score', false ],
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
		return [
			'delete' => __( 'Delete', 'pressprimer-assignment' ),
		];
	}

	/**
	 * Prepare items for display
	 *
	 * @since 1.0.0
	 */
	public function prepare_items() {
		global $wpdb;

		$per_page     = $this->get_items_per_page( 'pressprimer_assignment_submissions_per_page', 20 );
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$sub_table = $wpdb->prefix . 'ppa_submissions';
		$asg_table = $wpdb->prefix . 'ppa_assignments';

		$where_clauses = [];
		$where_values  = [];

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters for list table display.

		// Filter by assignment.
		if ( isset( $_GET['assignment'] ) && '' !== $_GET['assignment'] ) {
			$where_clauses[] = 's.assignment_id = %d';
			$where_values[]  = absint( wp_unslash( $_GET['assignment'] ) );
		}

		// Filter by status.
		$get_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		if ( '' !== $get_status && 'all' !== $get_status ) {
			$where_clauses[] = 's.status = %s';
			$where_values[]  = $get_status;
		}

		// Filter by search (student name or email).
		if ( isset( $_GET['s'] ) && '' !== $_GET['s'] ) {
			$search_term     = sanitize_text_field( wp_unslash( $_GET['s'] ) );
			$like_term       = '%' . $wpdb->esc_like( $search_term ) . '%';
			$where_clauses[] = '(u.display_name LIKE %s OR u.user_email LIKE %s OR a.title LIKE %s)';
			$where_values[]  = $like_term;
			$where_values[]  = $like_term;
			$where_values[]  = $like_term;
		}

		// Build orderby and order.
		$allowed_orderby = [ 'id', 'status', 'submitted_at', 'score' ];
		$orderby         = isset( $_GET['orderby'] ) && '' !== $_GET['orderby'] ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'submitted_at';
		$orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'submitted_at';
		$order           = isset( $_GET['order'] ) && '' !== $_GET['order'] ? strtoupper( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'DESC';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
			$order = 'DESC';
		}

		// Build WHERE SQL.
		$where_sql = ! empty( $where_clauses )
			? 'WHERE ' . implode( ' AND ', $where_clauses )
			: '';

		// Prefix the orderby with s. for the submissions table.
		$order_sql = sanitize_sql_orderby( "s.{$orderby} {$order}" );
		$order_sql = $order_sql ? "ORDER BY {$order_sql}" : 'ORDER BY s.submitted_at DESC';

		// Get total count.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and validated clauses safely constructed.
		$count_query = "SELECT COUNT(*) FROM {$sub_table} s LEFT JOIN {$asg_table} a ON s.assignment_id = a.id LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID {$where_sql}";

		if ( ! empty( $where_values ) ) {
			$count_query = $wpdb->prepare( $count_query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_items = $wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Get items with joins for user and assignment data.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and validated clauses safely constructed.
		$items_query  = "SELECT s.*, a.title AS assignment_title, u.display_name AS student_name, u.user_email AS student_email FROM {$sub_table} s LEFT JOIN {$asg_table} a ON s.assignment_id = a.id LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID {$where_sql} {$order_sql} LIMIT %d OFFSET %d";
		$query_values = array_merge( $where_values, [ $per_page, $offset ] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$items = $wpdb->get_results(
			$wpdb->prepare( $items_query, $query_values ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		$this->items = $items ? $items : [];

		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			]
		);

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
	 * @since 1.0.0
	 *
	 * @param object $item Submission row object.
	 * @return string Column content.
	 */
	public function column_student( $item ) {
		if ( ! empty( $item->student_name ) ) {
			return sprintf(
				'<a href="%s">%s</a>',
				esc_url( get_edit_user_link( $item->user_id ) ),
				esc_html( $item->student_name )
			);
		}

		return esc_html__( 'Unknown User', 'pressprimer-assignment' );
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
			// Build row actions.
			$actions = [];

			$actions['view'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					add_query_arg(
						[
							'page'       => 'pressprimer-assignment-submissions',
							'action'     => 'view',
							'submission' => $item->id,
						],
						admin_url( 'admin.php' )
					)
				),
				esc_html__( 'View', 'pressprimer-assignment' )
			);

			$actions['delete'] = sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\')">%s</a>',
				esc_url(
					wp_nonce_url(
						add_query_arg(
							[
								'page'       => 'pressprimer-assignment-submissions',
								'action'     => 'delete',
								'submission' => $item->id,
							],
							admin_url( 'admin.php' )
						),
						'delete-submission_' . $item->id
					)
				),
				esc_js( __( 'Are you sure you want to delete this submission?', 'pressprimer-assignment' ) ),
				esc_html__( 'Delete', 'pressprimer-assignment' )
			);

			return sprintf(
				'<a href="%s">%s</a>%s',
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
				esc_html( $item->assignment_title ),
				$this->row_actions( $actions )
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
			'draft'     => __( 'Draft', 'pressprimer-assignment' ),
			'submitted' => __( 'Submitted', 'pressprimer-assignment' ),
			'grading'   => __( 'Grading', 'pressprimer-assignment' ),
			'graded'    => __( 'Graded', 'pressprimer-assignment' ),
			'returned'  => __( 'Returned', 'pressprimer-assignment' ),
		];

		return isset( $statuses[ $item->status ] ) ? esc_html( $statuses[ $item->status ] ) : '&#8212;';
	}

	/**
	 * Render files column
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Submission row object.
	 * @return string Column content.
	 */
	public function column_files( $item ) {
		return (int) $item->file_count;
	}

	/**
	 * Render score column
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Submission row object.
	 * @return string Column content.
	 */
	public function column_score( $item ) {
		if ( null === $item->score ) {
			return '&#8212;';
		}

		return esc_html( $item->score );
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
			'returned'  => __( 'Returned', 'pressprimer-assignment' ),
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

		// Get list of assignments.
		$assignments = PressPrimer_Assignment_Assignment::find(
			[
				'order_by' => 'title',
				'order'    => 'ASC',
			]
		);

		?>
		<label class="screen-reader-text" for="filter-by-assignment"><?php esc_html_e( 'Filter by assignment', 'pressprimer-assignment' ); ?></label>
		<select name="assignment" id="filter-by-assignment">
			<option value=""><?php esc_html_e( 'All Assignments', 'pressprimer-assignment' ); ?></option>
			<?php foreach ( $assignments as $assignment ) : ?>
				<option value="<?php echo esc_attr( $assignment->id ); ?>" <?php selected( $current_assignment, $assignment->id ); ?>>
					<?php echo esc_html( $assignment->title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}
}
