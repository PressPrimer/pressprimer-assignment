<?php
/**
 * Admin categories page
 *
 * Handles the categories management interface with two-column layout:
 * add form on left, list table on right. Matches PressPrimer Quiz pattern.
 *
 * @package PressPrimer_Assignment
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin categories class
 *
 * Manages the categories admin page with WP_List_Table and add/edit forms.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Admin_Categories {

	/**
	 * Taxonomy type: 'category' or 'tag'
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $taxonomy;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 *
	 * @param string $taxonomy Taxonomy type ('category' or 'tag'). Default 'category'.
	 */
	public function __construct( $taxonomy = 'category' ) {
		$this->taxonomy = in_array( $taxonomy, [ 'category', 'tag' ], true ) ? $taxonomy : 'category';
	}

	/**
	 * Initialize categories admin
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'admin_post_ppa_save_category', [ $this, 'handle_save' ] );
		add_action( 'admin_post_ppa_delete_category', [ $this, 'handle_delete' ] );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
	}

	/**
	 * Check if this instance manages tags
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if taxonomy is 'tag'.
	 */
	private function is_tag() {
		return 'tag' === $this->taxonomy;
	}

	/**
	 * Get the admin page slug for this taxonomy
	 *
	 * @since 1.0.0
	 *
	 * @return string Page slug.
	 */
	private function get_page_slug() {
		return $this->is_tag() ? 'pressprimer-assignment-tags' : 'pressprimer-assignment-categories';
	}

	/**
	 * Render categories page
	 *
	 * Routes to edit form or list view based on action parameter.
	 *
	 * @since 1.0.0
	 */
	public function render() {
		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'pressprimer-assignment' ),
				esc_html__( 'Permission Denied', 'pressprimer-assignment' ),
				[ 'response' => 403 ]
			);
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing parameters.
		$action   = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$id       = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Allow taxonomy override from URL (used by shared save/delete handlers).
		if ( in_array( $taxonomy, [ 'category', 'tag' ], true ) ) {
			$this->taxonomy = $taxonomy;
		}

		// Handle edit action.
		if ( 'edit' === $action && $id ) {
			$this->render_edit_form( $id );
			return;
		}

		// Show list with add form.
		$this->render_list_with_form();
	}

	/**
	 * Render list with add form
	 *
	 * Two-column layout: Add form on left, list table on right.
	 *
	 * @since 1.0.0
	 */
	private function render_list_with_form() {
		$is_tag     = $this->is_tag();
		$page_title = $is_tag
			? __( 'Tags', 'pressprimer-assignment' )
			: __( 'Categories', 'pressprimer-assignment' );
		$add_title  = $is_tag
			? __( 'Add New Tag', 'pressprimer-assignment' )
			: __( 'Add New Category', 'pressprimer-assignment' );

		?>
		<div class="wrap">
			<h1><?php echo esc_html( $page_title ); ?></h1>

			<div id="col-container" class="wp-clearfix">
				<!-- Add Form Column -->
				<div id="col-left">
					<div class="col-wrap">
						<div class="form-wrap">
							<h2><?php echo esc_html( $add_title ); ?></h2>

							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ppa-category-form">
								<?php wp_nonce_field( 'pressprimer_assignment_save_category', 'pressprimer_assignment_category_nonce' ); ?>
								<input type="hidden" name="action" value="ppa_save_category">
								<input type="hidden" name="taxonomy" value="<?php echo esc_attr( $this->taxonomy ); ?>">
								<input type="hidden" name="return_url" value="<?php echo esc_url( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ); ?>">

								<!-- Name -->
								<div class="form-field form-required term-name-wrap">
									<label for="category_name"><?php esc_html_e( 'Name', 'pressprimer-assignment' ); ?> <span class="required">*</span></label>
									<input
										type="text"
										id="category_name"
										name="category_name"
										required
										maxlength="100"
										aria-required="true"
									>
									<p class="description">
										<?php esc_html_e( 'The name is how it appears on your site.', 'pressprimer-assignment' ); ?>
									</p>
								</div>

								<!-- Slug -->
								<div class="form-field term-slug-wrap">
									<label for="category_slug"><?php esc_html_e( 'Slug', 'pressprimer-assignment' ); ?></label>
									<input
										type="text"
										id="category_slug"
										name="category_slug"
										maxlength="100"
									>
									<p class="description">
										<?php esc_html_e( 'The "slug" is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.', 'pressprimer-assignment' ); ?>
									</p>
								</div>

								<?php if ( ! $is_tag ) : ?>
								<!-- Parent (categories only) -->
								<div class="form-field term-parent-wrap">
									<label for="category_parent"><?php esc_html_e( 'Parent Category', 'pressprimer-assignment' ); ?></label>
									<select name="category_parent" id="category_parent">
										<option value="0"><?php esc_html_e( 'None', 'pressprimer-assignment' ); ?></option>
										<?php
										echo wp_kses(
											$this->get_category_options(),
											[
												'option' => [
													'value'    => [],
													'selected' => [],
												],
											]
										);
										?>
									</select>
									<p class="description">
										<?php esc_html_e( 'Categories can have a hierarchy. You might have a Writing category, and under that have children for Essays and Reports. Optional.', 'pressprimer-assignment' ); ?>
									</p>
								</div>
								<?php endif; ?>

								<!-- Description -->
								<div class="form-field term-description-wrap">
									<label for="category_description"><?php esc_html_e( 'Description', 'pressprimer-assignment' ); ?></label>
									<textarea
										id="category_description"
										name="category_description"
										rows="5"
										maxlength="500"
									></textarea>
									<p class="description">
										<?php esc_html_e( 'The description is not prominent by default; however, some themes may show it.', 'pressprimer-assignment' ); ?>
									</p>
								</div>

								<p class="submit">
									<button type="submit" class="button button-primary">
										<?php echo esc_html( $add_title ); ?>
									</button>
								</p>
							</form>
						</div>
					</div>
				</div>

				<!-- List Table Column -->
				<div id="col-right">
					<div class="col-wrap">
						<?php
						$list_table = new PressPrimer_Assignment_Categories_List_Table( $this->taxonomy );
						$list_table->prepare_items();
						$list_table->display();
						?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render edit form
	 *
	 * Full-width edit form for an existing category.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Category ID.
	 */
	private function render_edit_form( $id ) {
		$category = PressPrimer_Assignment_Category::get( $id );

		if ( ! $category || $this->taxonomy !== $category->taxonomy ) {
			$not_found = $this->is_tag()
				? __( 'Tag not found.', 'pressprimer-assignment' )
				: __( 'Category not found.', 'pressprimer-assignment' );
			wp_die(
				esc_html( $not_found ),
				esc_html__( 'Error', 'pressprimer-assignment' ),
				[ 'response' => 404 ]
			);
		}

		$is_tag     = $this->is_tag();
		$back_url   = admin_url( 'admin.php?page=' . $this->get_page_slug() );
		$edit_title = $is_tag
			? __( 'Edit Tag', 'pressprimer-assignment' )
			: __( 'Edit Category', 'pressprimer-assignment' );

		?>
		<div class="wrap">
			<h1><?php echo esc_html( $edit_title ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ppa-category-edit-form">
				<?php wp_nonce_field( 'pressprimer_assignment_save_category', 'pressprimer_assignment_category_nonce' ); ?>
				<input type="hidden" name="action" value="ppa_save_category">
				<input type="hidden" name="category_id" value="<?php echo esc_attr( $category->id ); ?>">
				<input type="hidden" name="taxonomy" value="<?php echo esc_attr( $this->taxonomy ); ?>">
				<input type="hidden" name="return_url" value="<?php echo esc_url( $back_url ); ?>">

				<table class="form-table ppa-form-table">
					<tbody>
						<!-- Name -->
						<tr>
							<th scope="row">
								<label for="category_name"><?php esc_html_e( 'Name', 'pressprimer-assignment' ); ?> <span class="required">*</span></label>
							</th>
							<td>
								<input
									type="text"
									id="category_name"
									name="category_name"
									value="<?php echo esc_attr( $category->name ); ?>"
									class="regular-text"
									required
									maxlength="100"
								>
							</td>
						</tr>

						<!-- Slug -->
						<tr>
							<th scope="row">
								<label for="category_slug"><?php esc_html_e( 'Slug', 'pressprimer-assignment' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="category_slug"
									name="category_slug"
									value="<?php echo esc_attr( $category->slug ); ?>"
									class="regular-text"
									maxlength="100"
								>
							</td>
						</tr>

						<?php if ( ! $is_tag ) : ?>
						<!-- Parent (categories only) -->
						<tr>
							<th scope="row">
								<label for="category_parent"><?php esc_html_e( 'Parent Category', 'pressprimer-assignment' ); ?></label>
							</th>
							<td>
								<select name="category_parent" id="category_parent">
									<option value="0"><?php esc_html_e( 'None', 'pressprimer-assignment' ); ?></option>
									<?php
									echo wp_kses(
										$this->get_category_options( $category->parent_id, $category->id ),
										[
											'option' => [
												'value'    => [],
												'selected' => [],
											],
										]
									);
									?>
								</select>
							</td>
						</tr>
						<?php endif; ?>

						<!-- Description -->
						<tr>
							<th scope="row">
								<label for="category_description"><?php esc_html_e( 'Description', 'pressprimer-assignment' ); ?></label>
							</th>
							<td>
								<textarea
									id="category_description"
									name="category_description"
									rows="5"
									class="large-text"
									maxlength="500"
								><?php echo esc_textarea( $category->description ); ?></textarea>
							</td>
						</tr>

						<!-- Assignment Count (read-only) -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Assignments', 'pressprimer-assignment' ); ?>
							</th>
							<td>
								<strong><?php echo absint( $category->assignment_count ); ?></strong>
								<?php esc_html_e( 'assignments', 'pressprimer-assignment' ); ?>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Update', 'pressprimer-assignment' ); ?>
					</button>
					<a href="<?php echo esc_url( $back_url ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Cancel', 'pressprimer-assignment' ); ?>
					</a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Get category options for select dropdown
	 *
	 * Builds hierarchical option elements for parent category selection.
	 *
	 * @since 1.0.0
	 *
	 * @param int $selected   Selected category ID.
	 * @param int $exclude_id Category ID to exclude (for edit form).
	 * @param int $parent_id  Parent ID for recursion.
	 * @param int $level      Indentation level.
	 * @return string HTML options.
	 */
	private function get_category_options( $selected = 0, $exclude_id = 0, $parent_id = null, $level = 0 ) {
		$args = [
			'order_by' => 'name',
			'order'    => 'ASC',
			'where'    => [
				'taxonomy' => 'category',
			],
		];

		if ( null === $parent_id ) {
			$args['where']['parent_id'] = null;
		} else {
			$args['where']['parent_id'] = $parent_id;
		}

		$categories = PressPrimer_Assignment_Category::find( $args );
		$output     = '';

		foreach ( $categories as $category ) {
			// Skip if this is the category being edited.
			if ( $category->id === $exclude_id ) {
				continue;
			}

			$indent        = str_repeat( '&nbsp;&nbsp;&nbsp;', $level );
			$selected_attr = ( $category->id === $selected ) ? ' selected' : '';

			$output .= sprintf(
				'<option value="%d"%s>%s%s</option>',
				intval( $category->id ),
				esc_attr( $selected_attr ),
				$indent,
				esc_html( $category->name )
			);

			// Recursively get children.
			$output .= $this->get_category_options( $selected, $exclude_id, $category->id, $level + 1 );
		}

		return $output;
	}

	/**
	 * Handle category save
	 *
	 * Processes create and update requests via admin-post.php.
	 *
	 * @since 1.0.0
	 */
	public function handle_save() {
		// Verify nonce.
		if ( ! isset( $_POST['pressprimer_assignment_category_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['pressprimer_assignment_category_nonce'] ) ),
				'pressprimer_assignment_save_category'
			)
		) {
			wp_die( esc_html__( 'Security check failed.', 'pressprimer-assignment' ) );
		}

		// Check capability.
		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'pressprimer-assignment' ) );
		}

		$category_id = isset( $_POST['category_id'] ) ? absint( wp_unslash( $_POST['category_id'] ) ) : 0;

		// Get taxonomy from form (defaults to 'category').
		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : 'category';
		if ( ! in_array( $taxonomy, [ 'category', 'tag' ], true ) ) {
			$taxonomy = 'category';
		}

		$default_page = 'tag' === $taxonomy ? 'pressprimer-assignment-tags' : 'pressprimer-assignment-categories';
		$return_url   = isset( $_POST['return_url'] )
			? esc_url_raw( wp_unslash( $_POST['return_url'] ) )
			: admin_url( 'admin.php?page=' . $default_page );

		$data = [
			'name'        => isset( $_POST['category_name'] )
				? sanitize_text_field( wp_unslash( $_POST['category_name'] ) )
				: '',
			'slug'        => isset( $_POST['category_slug'] )
				? sanitize_title( wp_unslash( $_POST['category_slug'] ) )
				: '',
			'description' => isset( $_POST['category_description'] )
				? sanitize_textarea_field( wp_unslash( $_POST['category_description'] ) )
				: '',
			'taxonomy'    => $taxonomy,
		];

		// Handle parent category (categories only, not tags).
		if ( 'category' === $taxonomy && isset( $_POST['category_parent'] ) ) {
			$parent_id         = absint( wp_unslash( $_POST['category_parent'] ) );
			$data['parent_id'] = $parent_id > 0 ? $parent_id : null;
		}

		if ( $category_id > 0 ) {
			// Update existing.
			$category = PressPrimer_Assignment_Category::get( $category_id );

			if ( ! $category ) {
				wp_die( esc_html__( 'Category not found.', 'pressprimer-assignment' ) );
			}

			foreach ( $data as $key => $value ) {
				$category->$key = $value;
			}

			$result  = $category->save();
			$message = 'updated';
		} else {
			// Create new.
			$data['created_by'] = get_current_user_id();
			$result             = PressPrimer_Assignment_Category::create( $data );
			$message            = 'added';
		}

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		// Redirect with success message.
		wp_safe_redirect(
			add_query_arg(
				[ 'message' => $message ],
				$return_url
			)
		);
		exit;
	}

	/**
	 * Handle category delete
	 *
	 * Processes single delete requests via admin-post.php.
	 *
	 * @since 1.0.0
	 */
	public function handle_delete() {
		// Verify id is set.
		if ( ! isset( $_GET['id'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'pressprimer-assignment' ) );
		}

		$id = absint( wp_unslash( $_GET['id'] ) );

		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ),
				'pressprimer_assignment_delete_category_' . $id
			)
		) {
			wp_die( esc_html__( 'Security check failed.', 'pressprimer-assignment' ) );
		}

		// Check capability.
		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'pressprimer-assignment' ) );
		}

		if ( ! $id ) {
			wp_die( esc_html__( 'Invalid ID.', 'pressprimer-assignment' ) );
		}

		$category = PressPrimer_Assignment_Category::get( $id );

		if ( ! $category ) {
			wp_die( esc_html__( 'Category not found.', 'pressprimer-assignment' ) );
		}

		// Determine redirect page based on taxonomy.
		$redirect_page = 'tag' === $category->taxonomy
			? 'pressprimer-assignment-tags'
			: 'pressprimer-assignment-categories';

		// Delete category.
		$result = $category->delete();

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		// Redirect with success message.
		wp_safe_redirect(
			add_query_arg(
				[
					'page'    => $redirect_page,
					'message' => 'deleted',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Display admin notices
	 *
	 * Shows success messages after create, update, and delete operations.
	 *
	 * @since 1.0.0
	 */
	public function admin_notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only notice flags from redirect.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		$valid_pages = [ 'pressprimer-assignment-categories', 'pressprimer-assignment-tags' ];
		if ( ! in_array( $page, $valid_pages, true ) ) {
			return;
		}

		if ( ! isset( $_GET['message'] ) ) {
			return;
		}

		$message_key = sanitize_key( wp_unslash( $_GET['message'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$is_tag = 'pressprimer-assignment-tags' === $page;
		$class  = 'notice notice-success is-dismissible';
		$text   = '';

		switch ( $message_key ) {
			case 'added':
				$text = $is_tag
					? __( 'Tag added successfully.', 'pressprimer-assignment' )
					: __( 'Category added successfully.', 'pressprimer-assignment' );
				break;
			case 'updated':
				$text = $is_tag
					? __( 'Tag updated successfully.', 'pressprimer-assignment' )
					: __( 'Category updated successfully.', 'pressprimer-assignment' );
				break;
			case 'deleted':
				$text = $is_tag
					? __( 'Tag deleted successfully.', 'pressprimer-assignment' )
					: __( 'Category deleted successfully.', 'pressprimer-assignment' );
				break;
		}

		if ( $text ) {
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $text ) );
		}
	}
}

// Load WP_List_Table if not loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Categories List Table class
 *
 * Extends WP_List_Table to display assignment categories.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Categories_List_Table extends WP_List_Table {

	/**
	 * Taxonomy type: 'category' or 'tag'
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $taxonomy;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 *
	 * @param string $taxonomy Taxonomy type ('category' or 'tag'). Default 'category'.
	 */
	public function __construct( $taxonomy = 'category' ) {
		$this->taxonomy = in_array( $taxonomy, [ 'category', 'tag' ], true ) ? $taxonomy : 'category';

		$singular = 'tag' === $this->taxonomy ? 'ppa-tag' : 'ppa-category';
		$plural   = 'tag' === $this->taxonomy ? 'ppa-tags' : 'ppa-categories';

		parent::__construct(
			[
				'singular' => $singular,
				'plural'   => $plural,
				'ajax'     => false,
			]
		);
	}

	/**
	 * Check if this table manages tags
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if taxonomy is 'tag'.
	 */
	private function is_tag() {
		return 'tag' === $this->taxonomy;
	}

	/**
	 * Get the admin page slug for this taxonomy
	 *
	 * @since 1.0.0
	 *
	 * @return string Page slug.
	 */
	private function get_page_slug() {
		return $this->is_tag() ? 'pressprimer-assignment-tags' : 'pressprimer-assignment-categories';
	}

	/**
	 * Get table columns
	 *
	 * @since 1.0.0
	 *
	 * @return array Column headers.
	 */
	public function get_columns() {
		$columns = [
			'cb'   => '<input type="checkbox" />',
			'name' => __( 'Name', 'pressprimer-assignment' ),
		];

		// Only categories have a parent column (tags are flat).
		if ( ! $this->is_tag() ) {
			$columns['parent'] = __( 'Parent', 'pressprimer-assignment' );
		}

		$columns['description'] = __( 'Description', 'pressprimer-assignment' );
		$columns['slug']        = __( 'Slug', 'pressprimer-assignment' );
		$columns['assignments'] = __( 'Assignments', 'pressprimer-assignment' );

		return $columns;
	}

	/**
	 * Get sortable columns
	 *
	 * @since 1.0.0
	 *
	 * @return array Sortable columns.
	 */
	protected function get_sortable_columns() {
		return [
			'name'        => [ 'name', false ],
			'slug'        => [ 'slug', false ],
			'assignments' => [ 'assignment_count', false ],
		];
	}

	/**
	 * Get bulk actions
	 *
	 * @since 1.0.0
	 *
	 * @return array Bulk actions.
	 */
	protected function get_bulk_actions() {
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
		// Process bulk actions.
		$this->process_bulk_action();

		// Columns.
		$columns               = $this->get_columns();
		$hidden                = [];
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = [ $columns, $hidden, $sortable ];

		// Build query args.
		$args = [
			'where' => [ 'taxonomy' => $this->taxonomy ],
		];

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- List table filter params, not form processing.
		// Search.
		if ( isset( $_REQUEST['s'] ) && '' !== $_REQUEST['s'] ) {
			$search = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
			global $wpdb;
			$args['where_raw'] = $wpdb->prepare(
				'(name LIKE %s OR description LIKE %s)',
				'%' . $wpdb->esc_like( $search ) . '%',
				'%' . $wpdb->esc_like( $search ) . '%'
			);
		}

		// Ordering.
		$valid_orderby     = [ 'name', 'slug', 'assignment_count', 'created_at' ];
		$requested_orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'name';
		$args['order_by']  = in_array( $requested_orderby, $valid_orderby, true ) ? $requested_orderby : 'name';
		$args['order']     = isset( $_REQUEST['order'] ) && 'desc' === strtolower( sanitize_key( wp_unslash( $_REQUEST['order'] ) ) ) ? 'DESC' : 'ASC';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Get all items (categories are typically few, no pagination needed).
		$this->items = PressPrimer_Assignment_Category::find( $args );

		// Set pagination.
		$this->set_pagination_args(
			[
				'total_items' => count( $this->items ),
				'per_page'    => count( $this->items ),
				'total_pages' => 1,
			]
		);
	}

	/**
	 * Process bulk actions
	 *
	 * @since 1.0.0
	 */
	protected function process_bulk_action() {
		if ( 'delete' !== $this->current_action() ) {
			return;
		}

		// Check capability.
		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			return;
		}

		// Get IDs.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below.
		$ids = isset( $_REQUEST['ppa-category'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['ppa-category'] ) ) : [];

		if ( empty( $ids ) ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_REQUEST['_wpnonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ),
				'bulk-ppa-categories'
			)
		) {
			wp_die( esc_html__( 'Security check failed.', 'pressprimer-assignment' ) );
		}

		// Delete items.
		foreach ( $ids as $id ) {
			$category = PressPrimer_Assignment_Category::get( $id );
			if ( $category ) {
				$category->delete();
			}
		}

		// Redirect.
		wp_safe_redirect(
			add_query_arg(
				[
					'page'    => $this->get_page_slug(),
					'message' => 'deleted',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Default column display
	 *
	 * @since 1.0.0
	 *
	 * @param object $item        Category object.
	 * @param string $column_name Column name.
	 * @return string Column content.
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'description':
				$description = $item->description;
				if ( empty( $description ) ) {
					return '<span class="ppa-text-muted">&mdash;</span>';
				}
				// Truncate long descriptions.
				if ( strlen( $description ) > 80 ) {
					return esc_html( substr( $description, 0, 80 ) ) . '&hellip;';
				}
				return esc_html( $description );

			case 'slug':
				return '<code>' . esc_html( $item->slug ) . '</code>';

			case 'assignments':
				return absint( $item->assignment_count );

			default:
				return '';
		}
	}

	/**
	 * Checkbox column
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Category object.
	 * @return string Checkbox HTML.
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="ppa-category[]" value="%d" />',
			absint( $item->id )
		);
	}

	/**
	 * Name column with row actions
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Category object.
	 * @return string Name column HTML.
	 */
	protected function column_name( $item ) {
		$page_slug = $this->get_page_slug();

		$edit_url = add_query_arg(
			[
				'page'     => $page_slug,
				'action'   => 'edit',
				'id'       => $item->id,
				'taxonomy' => $this->taxonomy,
			],
			admin_url( 'admin.php' )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				[
					'action' => 'ppa_delete_category',
					'id'     => $item->id,
				],
				admin_url( 'admin-post.php' )
			),
			'pressprimer_assignment_delete_category_' . $item->id
		);

		$title = '<strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $item->name ) . '</a></strong>';

		$confirm_text = $this->is_tag()
			? __( 'Are you sure you want to delete this tag?', 'pressprimer-assignment' )
			: __( 'Are you sure you want to delete this category?', 'pressprimer-assignment' );

		// Row actions.
		$actions           = [];
		$actions['edit']   = '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'pressprimer-assignment' ) . '</a>';
		$actions['delete'] = '<a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( $confirm_text ) . '\');">' . esc_html__( 'Delete', 'pressprimer-assignment' ) . '</a>';

		return $title . $this->row_actions( $actions );
	}

	/**
	 * Parent column
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Category object.
	 * @return string Parent column HTML.
	 */
	protected function column_parent( $item ) {
		if ( empty( $item->parent_id ) ) {
			return '<span class="ppa-text-muted">' . esc_html__( '(none)', 'pressprimer-assignment' ) . '</span>';
		}

		$parent = PressPrimer_Assignment_Category::get( $item->parent_id );
		if ( ! $parent ) {
			return '<span class="ppa-text-muted">' . esc_html__( '(unknown)', 'pressprimer-assignment' ) . '</span>';
		}

		return esc_html( $parent->name );
	}

	/**
	 * Message when no items found
	 *
	 * @since 1.0.0
	 */
	public function no_items() {
		if ( $this->is_tag() ) {
			esc_html_e( 'No tags found.', 'pressprimer-assignment' );
		} else {
			esc_html_e( 'No categories found.', 'pressprimer-assignment' );
		}
	}
}
