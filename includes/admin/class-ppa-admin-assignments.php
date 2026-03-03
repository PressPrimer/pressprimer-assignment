<?php
/**
 * Assignments admin class
 *
 * Handles the assignments list and management interface.
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
 * Assignments admin class
 *
 * Manages the assignments list table and edit interface.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Admin_Assignments {

	/**
	 * List table instance
	 *
	 * @since 1.0.0
	 * @var PressPrimer_Assignment_Assignments_List_Table
	 */
	private $list_table;

	/**
	 * Initialize assignments admin
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'admin_init', [ $this, 'handle_actions' ] );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );

		// Add screen options on the right hook.
		add_action( 'current_screen', [ $this, 'maybe_add_screen_options' ] );

		// Save screen options.
		add_filter( 'set_screen_option_pressprimer_assignment_assignments_per_page', [ $this, 'set_screen_option' ], 10, 3 );
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
		if ( false !== strpos( $screen->id, 'pressprimer-assignment-assignments' ) ) {
			$this->screen_options();
		}
	}

	/**
	 * Set up screen options
	 *
	 * @since 1.0.0
	 */
	public function screen_options() {
		// Only on list view.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for display routing.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( in_array( $action, [ 'new', 'edit' ], true ) ) {
			return;
		}

		// Add per page option.
		add_screen_option(
			'per_page',
			[
				'label'   => __( 'Assignments per page', 'pressprimer-assignment' ),
				'default' => 20,
				'option'  => 'pressprimer_assignment_assignments_per_page',
			]
		);

		// Instantiate the table and store it.
		$this->list_table = new PressPrimer_Assignment_Assignments_List_Table();

		// Get screen and register columns with it.
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

		// Set up filter for saving screen option.
		add_filter( 'set-screen-option', [ $this, 'set_screen_option' ], 10, 3 );
	}

	/**
	 * Set screen option
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $status Screen option value. Default false to skip.
	 * @param string $option The option name.
	 * @param mixed  $value  The option value.
	 * @return mixed Screen option value.
	 */
	public function set_screen_option( $status, $option, $value ) {
		if ( 'pressprimer_assignment_assignments_per_page' === $option ) {
			return $value;
		}

		return $status;
	}

	/**
	 * Handle actions
	 *
	 * Processes delete, bulk actions, etc. on admin_init.
	 *
	 * @since 1.0.0
	 */
	public function handle_actions() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verified in individual handlers.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		// Single delete.
		if ( 'delete' === $action && isset( $_GET['assignment'] ) ) {
			$this->handle_delete();
		}

		// Bulk actions.
		$bulk_action = $this->current_bulk_action();

		if ( 'delete' === $bulk_action && isset( $_GET['assignments'] ) ) {
			$this->handle_bulk_delete();
		}

		if ( 'publish' === $bulk_action && isset( $_GET['assignments'] ) ) {
			$this->handle_bulk_publish();
		}

		if ( 'draft' === $bulk_action && isset( $_GET['assignments'] ) ) {
			$this->handle_bulk_draft();
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
		if ( ! isset( $_GET['page'] ) || 'pressprimer-assignment-assignments' !== $_GET['page'] ) {
			return;
		}

		if ( isset( $_GET['deleted'] ) && absint( wp_unslash( $_GET['deleted'] ) ) > 0 ) {
			$count = absint( wp_unslash( $_GET['deleted'] ) );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of assignments deleted */
						esc_html( _n( '%d assignment deleted.', '%d assignments deleted.', $count, 'pressprimer-assignment' ) ),
						(int) $count
					);
					?>
				</p>
			</div>
			<?php
		}

		if ( isset( $_GET['published'] ) && absint( wp_unslash( $_GET['published'] ) ) > 0 ) {
			$count = absint( wp_unslash( $_GET['published'] ) );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of assignments published */
						esc_html( _n( '%d assignment published.', '%d assignments published.', $count, 'pressprimer-assignment' ) ),
						(int) $count
					);
					?>
				</p>
			</div>
			<?php
		}

		if ( isset( $_GET['drafted'] ) && absint( wp_unslash( $_GET['drafted'] ) ) > 0 ) {
			$count = absint( wp_unslash( $_GET['drafted'] ) );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of assignments moved to draft */
						esc_html( _n( '%d assignment moved to draft.', '%d assignments moved to draft.', $count, 'pressprimer-assignment' ) ),
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
	 * Routes to list view or edit view based on action parameter.
	 *
	 * @since 1.0.0
	 */
	public function render() {
		// Check user permissions.
		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'pressprimer-assignment' ) );
		}

		// Get action.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for display routing.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		// Route to appropriate view.
		if ( 'edit' === $action || 'new' === $action ) {
			$this->render_edit();
		} else {
			$this->render_list();
		}
	}

	/**
	 * Render assignments list
	 *
	 * @since 1.0.0
	 */
	private function render_list() {
		// Reuse the list table instance if it exists, otherwise create new one.
		if ( ! $this->list_table ) {
			$this->list_table = new PressPrimer_Assignment_Assignments_List_Table();
		}

		$this->list_table->prepare_items();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Assignments', 'pressprimer-assignment' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=pressprimer-assignment-assignments&action=new' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'pressprimer-assignment' ); ?>
			</a>
			<hr class="wp-header-end">

			<form method="get">
				<input type="hidden" name="page" value="pressprimer-assignment-assignments">
				<?php
				$this->list_table->search_box( __( 'Search Assignments', 'pressprimer-assignment' ), 'ppa-assignment' );
				$this->list_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render assignment editor (React)
	 *
	 * @since 1.0.0
	 */
	private function render_edit() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only assignment ID for editor display.
		$assignment_id = isset( $_GET['assignment'] ) ? absint( wp_unslash( $_GET['assignment'] ) ) : 0;
		$assignment    = null;

		// Load assignment if editing.
		if ( $assignment_id ) {
			$assignment = PressPrimer_Assignment_Assignment::get( $assignment_id );

			if ( ! $assignment ) {
				wp_die( esc_html__( 'Assignment not found.', 'pressprimer-assignment' ) );
			}
		}

		// Enqueue React editor.
		$this->enqueue_react_editor( $assignment_id );

		?>
		<!-- React Editor Root -->
		<div id="ppa-assignment-editor-root"></div>
		<?php
	}

	/**
	 * Enqueue React assignment editor
	 *
	 * @since 1.0.0
	 *
	 * @param int $assignment_id Assignment ID (0 for new).
	 */
	private function enqueue_react_editor( int $assignment_id ) {
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

		// Enqueue the built React bundle.
		$asset_file = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'build/assignment-editor.asset.php';
		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;

			wp_enqueue_script(
				'ppa-assignment-editor',
				PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'build/assignment-editor.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);

			$style_file = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'build/style-assignment-editor.css';
			if ( file_exists( $style_file ) ) {
				wp_enqueue_style(
					'ppa-assignment-editor',
					PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'build/style-assignment-editor.css',
					[],
					$asset['version']
				);
			}
		} else {
			// Fallback: use wp-element, wp-i18n, wp-api-fetch as dependencies.
			wp_enqueue_script(
				'ppa-assignment-editor',
				PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'build/assignment-editor.js',
				[ 'wp-element', 'wp-i18n', 'wp-api-fetch' ],
				PRESSPRIMER_ASSIGNMENT_VERSION,
				true
			);
		}

		// Prepare assignment data for JavaScript.
		$assignment_data = [];
		$plugin_settings = get_option( 'pressprimer_assignment_settings', [] );

		if ( 0 === $assignment_id ) {
			// New assignment: populate defaults from plugin settings.
			$default_file_size_mb = isset( $plugin_settings['default_max_file_size'] )
				? absint( $plugin_settings['default_max_file_size'] )
				: 5;

			$assignment_data['defaults'] = [
				'passing_score' => isset( $plugin_settings['default_passing_score'] )
					? absint( $plugin_settings['default_passing_score'] )
					: 60,
				'max_file_size' => $default_file_size_mb * 1048576,
				'max_files'     => isset( $plugin_settings['default_max_files'] )
					? absint( $plugin_settings['default_max_files'] )
					: 5,
			];
		}

		if ( $assignment_id > 0 ) {
			$assignment = PressPrimer_Assignment_Assignment::get( $assignment_id );

			if ( $assignment ) {
				// Get assigned category IDs.
				$category_ids = [];
				if ( class_exists( 'PressPrimer_Assignment_Category' ) ) {
					$assigned_cats = $assignment->get_categories();
					foreach ( $assigned_cats as $cat ) {
						$category_ids[] = (int) $cat->id;
					}
				}

				$assignment_data = [
					'id'                 => (int) $assignment->id,
					'title'              => $assignment->title,
					'description'        => $assignment->description,
					'instructions'       => $assignment->instructions,
					'grading_guidelines' => $assignment->grading_guidelines,
					'max_points'         => (float) $assignment->max_points,
					'passing_score'      => (float) $assignment->passing_score,
					'allow_resubmission' => (int) $assignment->allow_resubmission,
					'max_resubmissions'  => (int) $assignment->max_resubmissions,
					'allowed_file_types' => $assignment->allowed_file_types,
					'max_file_size'      => (int) $assignment->max_file_size,
					'max_files'          => (int) $assignment->max_files,
					'submission_type'    => $assignment->submission_type,
					'status'             => $assignment->status,
					'categories'         => $category_ids,
				];
			}
		}

		// Get all available categories and tags for the picker.
		$available_categories = [];
		$available_tags       = [];
		if ( class_exists( 'PressPrimer_Assignment_Category' ) ) {
			$all_categories = PressPrimer_Assignment_Category::get_categories();
			foreach ( $all_categories as $cat ) {
				$available_categories[] = [
					'id'        => (int) $cat->id,
					'name'      => $cat->name,
					'slug'      => $cat->slug,
					'parent_id' => $cat->parent_id ? (int) $cat->parent_id : null,
				];
			}

			$all_tags = PressPrimer_Assignment_Category::get_tags();
			foreach ( $all_tags as $tag ) {
				$available_tags[] = [
					'id'   => (int) $tag->id,
					'name' => $tag->name,
					'slug' => $tag->slug,
				];
			}
		}

		$assignment_data['availableCategories'] = $available_categories;
		$assignment_data['availableTags']       = $available_tags;

		/**
		 * Filters assignment editor data before passing to React.
		 *
		 * Allows addons to inject additional data into the assignment editor.
		 *
		 * @since 1.0.0
		 *
		 * @param array $assignment_data Assignment data array.
		 * @param int   $assignment_id   Assignment ID (0 for new assignment).
		 */
		$assignment_data = apply_filters( 'pressprimer_assignment_editor_data', $assignment_data, $assignment_id );

		// Localize script with assignment data.
		wp_localize_script(
			'ppa-assignment-editor',
			'pressprimerAssignmentEditorData',
			$assignment_data
		);

		// Also pass admin URL and nonce.
		wp_localize_script(
			'ppa-assignment-editor',
			'pressprimerAssignmentAdmin',
			[
				'adminUrl' => admin_url(),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'listUrl'  => admin_url( 'admin.php?page=pressprimer-assignment-assignments' ),
			]
		);
	}

	/**
	 * Handle single assignment delete
	 *
	 * @since 1.0.0
	 */
	private function handle_delete() {
		if ( ! isset( $_GET['assignment'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'pressprimer-assignment' ) );
		}

		$assignment_id_raw = absint( wp_unslash( $_GET['assignment'] ) );
		check_admin_referer( 'delete-assignment_' . $assignment_id_raw );

		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			wp_die( esc_html__( 'You do not have permission to delete assignments.', 'pressprimer-assignment' ) );
		}

		$assignment_id = absint( wp_unslash( $_GET['assignment'] ) );
		$assignment    = PressPrimer_Assignment_Assignment::get( $assignment_id );

		if ( ! $assignment ) {
			wp_die( esc_html__( 'Assignment not found.', 'pressprimer-assignment' ) );
		}

		$result = $assignment->delete();

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		wp_safe_redirect( add_query_arg( 'deleted', '1', admin_url( 'admin.php?page=pressprimer-assignment-assignments' ) ) );
		exit;
	}

	/**
	 * Get the current bulk action being performed
	 *
	 * Mimics WP_List_Table::current_action() logic.
	 *
	 * @since 1.0.0
	 *
	 * @return string|false The bulk action or false if none.
	 */
	private function current_bulk_action() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verified in individual handlers.
		$action = false;

		// Check top bulk action dropdown (action).
		if ( isset( $_GET['action'] ) && -1 !== (int) $_GET['action'] && '-1' !== $_GET['action'] ) {
			$action = sanitize_key( wp_unslash( $_GET['action'] ) );
		}

		// Check bottom bulk action dropdown (action2) - takes precedence if set.
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
		check_admin_referer( 'bulk-assignments' );

		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			wp_die( esc_html__( 'You do not have permission to delete assignments.', 'pressprimer-assignment' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Validated above via check_admin_referer.
		$assignment_ids = isset( $_GET['assignments'] ) ? array_map( 'absint', wp_unslash( $_GET['assignments'] ) ) : [];
		$deleted        = 0;

		foreach ( $assignment_ids as $assignment_id ) {
			$assignment = PressPrimer_Assignment_Assignment::get( $assignment_id );

			if ( ! $assignment ) {
				continue;
			}

			$result = $assignment->delete();

			if ( ! is_wp_error( $result ) ) {
				++$deleted;
			}
		}

		wp_safe_redirect( add_query_arg( 'deleted', $deleted, admin_url( 'admin.php?page=pressprimer-assignment-assignments' ) ) );
		exit;
	}

	/**
	 * Handle bulk publish
	 *
	 * @since 1.0.0
	 */
	private function handle_bulk_publish() {
		check_admin_referer( 'bulk-assignments' );

		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			wp_die( esc_html__( 'You do not have permission to publish assignments.', 'pressprimer-assignment' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Validated above via check_admin_referer.
		$assignment_ids = isset( $_GET['assignments'] ) ? array_map( 'absint', wp_unslash( $_GET['assignments'] ) ) : [];
		$published      = 0;

		foreach ( $assignment_ids as $assignment_id ) {
			$assignment = PressPrimer_Assignment_Assignment::get( $assignment_id );

			if ( ! $assignment ) {
				continue;
			}

			$assignment->status = 'published';
			$result             = $assignment->save();

			if ( ! is_wp_error( $result ) ) {
				++$published;
			}
		}

		wp_safe_redirect( add_query_arg( 'published', $published, admin_url( 'admin.php?page=pressprimer-assignment-assignments' ) ) );
		exit;
	}

	/**
	 * Handle bulk draft
	 *
	 * @since 1.0.0
	 */
	private function handle_bulk_draft() {
		check_admin_referer( 'bulk-assignments' );

		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			wp_die( esc_html__( 'You do not have permission to change assignment status.', 'pressprimer-assignment' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Validated above via check_admin_referer.
		$assignment_ids = isset( $_GET['assignments'] ) ? array_map( 'absint', wp_unslash( $_GET['assignments'] ) ) : [];
		$drafted        = 0;

		foreach ( $assignment_ids as $assignment_id ) {
			$assignment = PressPrimer_Assignment_Assignment::get( $assignment_id );

			if ( ! $assignment ) {
				continue;
			}

			$assignment->status = 'draft';
			$result             = $assignment->save();

			if ( ! is_wp_error( $result ) ) {
				++$drafted;
			}
		}

		wp_safe_redirect( add_query_arg( 'drafted', $drafted, admin_url( 'admin.php?page=pressprimer-assignment-assignments' ) ) );
		exit;
	}
}

/**
 * Assignments list table class
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Assignments_List_Table extends WP_List_Table {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'assignment',
				'plural'   => 'assignments',
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
			'cb'          => '<input type="checkbox" />',
			'id'          => __( 'ID', 'pressprimer-assignment' ),
			'title'       => __( 'Title', 'pressprimer-assignment' ),
			'submissions' => __( 'Submissions', 'pressprimer-assignment' ),
			'status'      => __( 'Status', 'pressprimer-assignment' ),
			'date'        => __( 'Date', 'pressprimer-assignment' ),
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
			'title'  => [ 'title', true ],
			'status' => [ 'status', false ],
			'date'   => [ 'created_at', false ],
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
			'publish' => __( 'Publish', 'pressprimer-assignment' ),
			'draft'   => __( 'Move to Draft', 'pressprimer-assignment' ),
			'delete'  => __( 'Delete', 'pressprimer-assignment' ),
		];
	}

	/**
	 * Prepare items for display
	 *
	 * @since 1.0.0
	 */
	public function prepare_items() {
		global $wpdb;

		$per_page     = $this->get_items_per_page( 'pressprimer_assignment_assignments_per_page', 20 );
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		// Build query.
		$table         = $wpdb->prefix . 'ppa_assignments';
		$where_clauses = [];
		$where_values  = [];

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters for list table display.
		// Filter by search.
		if ( isset( $_GET['s'] ) && '' !== $_GET['s'] ) {
			$where_clauses[] = 'title LIKE %s';
			$where_values[]  = '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) . '%';
		}

		// Filter by status.
		$get_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		if ( '' !== $get_status && 'all' !== $get_status ) {
			$where_clauses[] = 'status = %s';
			$where_values[]  = $get_status;
		}

		// Build WHERE clause.
		$where_sql = ! empty( $where_clauses )
			? 'WHERE ' . implode( ' AND ', $where_clauses )
			: '';

		// Get orderby and order - validate against allowed fields.
		$allowed_orderby = [ 'id', 'title', 'status', 'created_at', 'updated_at' ];
		$orderby         = isset( $_GET['orderby'] ) && '' !== $_GET['orderby'] ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
		$orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'created_at';
		$order           = isset( $_GET['order'] ) && '' !== $_GET['order'] ? strtoupper( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'DESC';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Validate order.
		if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
			$order = 'DESC';
		}

		// Build ORDER BY with sanitize_sql_orderby.
		$order_sql = sanitize_sql_orderby( "{$orderby} {$order}" );
		$order_sql = $order_sql ? "ORDER BY {$order_sql}" : 'ORDER BY created_at DESC';

		// Get total count.
		$total_query = "SELECT COUNT(*) FROM {$table} {$where_sql}";

		if ( ! empty( $where_values ) ) {
			$total_query = $wpdb->prepare( $total_query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- List table pagination, not suitable for caching.
		$total_items = $wpdb->get_var( $total_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Get items.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and validated clauses safely constructed.
		$items_query  = "SELECT * FROM {$table} {$where_sql} {$order_sql} LIMIT %d OFFSET %d";
		$query_values = array_merge( $where_values, [ $per_page, $offset ] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- List table pagination, not suitable for caching.
		$items = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared with placeholders.
			$wpdb->prepare( $items_query, $query_values )
		);

		// Convert to model instances.
		$this->items = [];
		if ( $items ) {
			foreach ( $items as $item ) {
				$this->items[] = PressPrimer_Assignment_Assignment::from_row( $item );
			}
		}

		// Set pagination.
		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
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
	 * @param PressPrimer_Assignment_Assignment $item Assignment object.
	 * @return string Column content.
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="assignments[]" value="%d" />', $item->id );
	}

	/**
	 * Render ID column
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Assignment $item Assignment object.
	 * @return string Column content.
	 */
	public function column_id( $item ) {
		return sprintf( '<strong>%d</strong>', $item->id );
	}

	/**
	 * Render title column
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Assignment $item Assignment object.
	 * @return string Column content.
	 */
	public function column_title( $item ) {
		// Build row actions.
		$actions = [];

		// Edit action.
		$actions['edit'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url(
				add_query_arg(
					[
						'page'       => 'pressprimer-assignment-assignments',
						'action'     => 'edit',
						'assignment' => $item->id,
					],
					admin_url( 'admin.php' )
				)
			),
			esc_html__( 'Edit', 'pressprimer-assignment' )
		);

		// Delete action.
		$actions['delete'] = sprintf(
			'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\')">%s</a>',
			esc_url(
				wp_nonce_url(
					add_query_arg(
						[
							'page'       => 'pressprimer-assignment-assignments',
							'action'     => 'delete',
							'assignment' => $item->id,
						],
						admin_url( 'admin.php' )
					),
					'delete-assignment_' . $item->id
				)
			),
			esc_js( __( 'Are you sure you want to delete this assignment?', 'pressprimer-assignment' ) ),
			esc_html__( 'Delete', 'pressprimer-assignment' )
		);

		// Build output.
		$title = ! empty( $item->title ) ? esc_html( $item->title ) : '<em>' . esc_html__( '(no title)', 'pressprimer-assignment' ) . '</em>';

		return sprintf( '<strong>%s</strong>%s', $title, $this->row_actions( $actions ) );
	}

	/**
	 * Render submissions column
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Assignment $item Assignment object.
	 * @return string Column content.
	 */
	public function column_submissions( $item ) {
		$count = (int) $item->submission_count;

		if ( $count > 0 ) {
			return sprintf(
				'<a href="%s">%d</a>',
				esc_url(
					add_query_arg(
						[
							'page'       => 'pressprimer-assignment-submissions',
							'assignment' => $item->id,
						],
						admin_url( 'admin.php' )
					)
				),
				$count
			);
		}

		return '0';
	}

	/**
	 * Render status column
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Assignment $item Assignment object.
	 * @return string Column content.
	 */
	public function column_status( $item ) {
		$statuses = [
			'draft'     => __( 'Draft', 'pressprimer-assignment' ),
			'published' => __( 'Published', 'pressprimer-assignment' ),
			'archived'  => __( 'Archived', 'pressprimer-assignment' ),
		];

		return isset( $statuses[ $item->status ] ) ? esc_html( $statuses[ $item->status ] ) : '&#8212;';
	}

	/**
	 * Render date column
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Assignment $item Assignment object.
	 * @return string Column content.
	 */
	public function column_date( $item ) {
		$timestamp = strtotime( $item->created_at );

		if ( ! $timestamp ) {
			return '&#8212;';
		}

		$time_diff = time() - $timestamp;

		// Show relative time if less than 24 hours.
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state for selected().
		$current_status = isset( $_GET['status'] ) && '' !== $_GET['status'] ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';

		$statuses = [
			'all'       => __( 'All Statuses', 'pressprimer-assignment' ),
			'draft'     => __( 'Draft', 'pressprimer-assignment' ),
			'published' => __( 'Published', 'pressprimer-assignment' ),
			'archived'  => __( 'Archived', 'pressprimer-assignment' ),
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
}
