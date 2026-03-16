<?php
/**
 * REST API controller for categories
 *
 * Handles CRUD operations for categories and tags via the REST API.
 *
 * @package PressPrimer_Assignment
 * @subpackage API
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API categories controller
 *
 * Registers and handles REST API routes for category management.
 *
 * Routes:
 * - GET    /ppa/v1/categories          List categories/tags
 * - POST   /ppa/v1/categories          Create category/tag
 * - GET    /ppa/v1/categories/{id}     Get single category
 * - PUT    /ppa/v1/categories/{id}     Update category/tag
 * - DELETE /ppa/v1/categories/{id}     Delete category/tag
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_REST_Categories {

	/**
	 * API namespace
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const API_NAMESPACE = 'ppa/v1';

	/**
	 * Initialize REST routes
	 *
	 * Hooks into rest_api_init to register routes.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		// Collection routes: list and create.
		register_rest_route(
			self::API_NAMESPACE,
			'/categories',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'check_read_permission' ],
					'args'                => $this->get_collection_params(),
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'check_write_permission' ],
				],
			]
		);

		// Single item routes: get, update, delete.
		register_rest_route(
			self::API_NAMESPACE,
			'/categories/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'check_read_permission' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'check_write_permission' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'check_write_permission' ],
				],
			]
		);
	}

	/**
	 * Check read permission
	 *
	 * Any user who can manage assignments can read categories.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if user has permission.
	 */
	public function check_read_permission() {
		return current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_OWN )
			|| current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL );
	}

	/**
	 * Check write permission
	 *
	 * Users with manage_own can create/update their own categories.
	 * Users with manage_all can manage any category.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if user has permission.
	 */
	public function check_write_permission() {
		return current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_OWN )
			|| current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL );
	}

	/**
	 * Check if current user can access a specific category
	 *
	 * Admins can access any category. Manage_own users can
	 * only access categories they created.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Category $category Category instance.
	 * @return bool True if user can access.
	 */
	private function can_access_category( $category ) {
		if ( current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			return true;
		}
		return (int) $category->created_by === get_current_user_id();
	}

	/**
	 * Get collection parameters for list endpoint
	 *
	 * @since 1.0.0
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		return [
			'taxonomy' => [
				'description' => __( 'Filter by taxonomy type.', 'pressprimer-assignment' ),
				'type'        => 'string',
				'enum'        => [ 'category', 'tag', '' ],
				'default'     => '',
			],
			'search'   => [
				'description' => __( 'Search term for filtering by name.', 'pressprimer-assignment' ),
				'type'        => 'string',
				'default'     => '',
			],
			'parent'   => [
				'description' => __( 'Filter by parent category ID.', 'pressprimer-assignment' ),
				'type'        => 'integer',
				'default'     => 0,
			],
		];
	}

	/**
	 * Get list of categories/tags
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function get_items( $request ) {
		$taxonomy = sanitize_text_field( $request->get_param( 'taxonomy' ) ?? '' );
		$search   = sanitize_text_field( $request->get_param( 'search' ) ?? '' );
		$parent   = absint( $request->get_param( 'parent' ) );

		$args = [
			'order_by' => 'name',
			'order'    => 'ASC',
			'where'    => [],
		];

		// Filter by taxonomy.
		if ( $taxonomy && in_array( $taxonomy, [ 'category', 'tag' ], true ) ) {
			$args['where']['taxonomy'] = $taxonomy;
		}

		// Filter by parent.
		if ( $parent > 0 ) {
			$args['where']['parent_id'] = $parent;
		}

		// Scope to current user's categories for manage_own users.
		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			$args['where']['created_by'] = get_current_user_id();
		}

		// Handle search.
		if ( $search ) {
			$items = $this->search_categories( $search, $args );
		} else {
			$items = PressPrimer_Assignment_Category::find( $args );
		}

		$data = array_map( [ $this, 'prepare_item_for_response' ], $items );

		return rest_ensure_response( $data );
	}

	/**
	 * Search categories by name
	 *
	 * @since 1.0.0
	 *
	 * @param string $search Search term.
	 * @param array  $args   Query arguments.
	 * @return array Array of category instances.
	 */
	private function search_categories( $search, $args ) {
		global $wpdb;

		$table     = $wpdb->prefix . 'ppa_categories';
		$like_term = '%' . $wpdb->esc_like( $search ) . '%';

		$has_taxonomy   = ! empty( $args['where']['taxonomy'] );
		$taxonomy       = $has_taxonomy ? $args['where']['taxonomy'] : '';
		$has_created_by = ! empty( $args['where']['created_by'] );

		// Build dynamic WHERE with params array.
		$where  = 'WHERE name LIKE %s';
		$params = [ $like_term ];

		if ( $has_taxonomy ) {
			$where   .= ' AND taxonomy = %s';
			$params[] = $taxonomy;
		}

		if ( $has_created_by ) {
			$where   .= ' AND created_by = %d';
			$params[] = absint( $args['where']['created_by'] );
		}

		$where .= ' ORDER BY name ASC';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Array param count is dynamic.
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix, WHERE built with placeholders.
				$params
			)
		);

		$results = [];
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$results[] = PressPrimer_Assignment_Category::from_row( $row );
			}
		}

		return $results;
	}

	/**
	 * Get a single category
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_item( $request ) {
		$id       = absint( $request->get_param( 'id' ) );
		$category = PressPrimer_Assignment_Category::get( $id );

		if ( ! $category ) {
			return new WP_Error(
				'pressprimer_assignment_not_found',
				__( 'Category not found.', 'pressprimer-assignment' ),
				[ 'status' => 404 ]
			);
		}

		return rest_ensure_response( $this->prepare_item_for_response( $category ) );
	}

	/**
	 * Create a new category or tag
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function create_item( $request ) {
		$data = $this->sanitize_category_data( $request );

		$category_id = PressPrimer_Assignment_Category::create( $data );

		if ( is_wp_error( $category_id ) ) {
			return new WP_Error(
				$category_id->get_error_code(),
				$category_id->get_error_message(),
				[ 'status' => 400 ]
			);
		}

		$category = PressPrimer_Assignment_Category::get( $category_id );

		return rest_ensure_response( $this->prepare_item_for_response( $category ) );
	}

	/**
	 * Update an existing category or tag
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function update_item( $request ) {
		$id       = absint( $request->get_param( 'id' ) );
		$category = PressPrimer_Assignment_Category::get( $id );

		if ( ! $category ) {
			return new WP_Error(
				'pressprimer_assignment_not_found',
				__( 'Category not found.', 'pressprimer-assignment' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! $this->can_access_category( $category ) ) {
			return new WP_Error(
				'pressprimer_assignment_forbidden',
				__( 'You do not have permission to edit this category.', 'pressprimer-assignment' ),
				[ 'status' => 403 ]
			);
		}

		$data = $this->sanitize_category_data( $request );

		// Update category properties.
		if ( isset( $data['name'] ) ) {
			$category->name = $data['name'];

			// Regenerate slug if name changed and no explicit slug provided.
			if ( null === $request->get_param( 'slug' ) ) {
				$category->slug = PressPrimer_Assignment_Category::generate_unique_slug(
					sanitize_title( $data['name'] ),
					$category->taxonomy,
					$category->id
				);
			}
		}

		if ( isset( $data['slug'] ) ) {
			$category->slug = PressPrimer_Assignment_Category::generate_unique_slug(
				$data['slug'],
				$category->taxonomy,
				$category->id
			);
		}

		if ( array_key_exists( 'description', $data ) ) {
			$category->description = $data['description'];
		}

		if ( array_key_exists( 'parent_id', $data ) && 'category' === $category->taxonomy ) {
			// Prevent setting self as parent.
			$new_parent = $data['parent_id'];
			if ( null !== $new_parent && absint( $new_parent ) === absint( $category->id ) ) {
				return new WP_Error(
					'pressprimer_assignment_invalid_parent',
					__( 'A category cannot be its own parent.', 'pressprimer-assignment' ),
					[ 'status' => 400 ]
				);
			}
			$category->parent_id = $new_parent;
		}

		$result = $category->save();

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				[ 'status' => 400 ]
			);
		}

		return rest_ensure_response( $this->prepare_item_for_response( $category ) );
	}

	/**
	 * Delete a category or tag
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function delete_item( $request ) {
		$id       = absint( $request->get_param( 'id' ) );
		$category = PressPrimer_Assignment_Category::get( $id );

		if ( ! $category ) {
			return new WP_Error(
				'pressprimer_assignment_not_found',
				__( 'Category not found.', 'pressprimer-assignment' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! $this->can_access_category( $category ) ) {
			return new WP_Error(
				'pressprimer_assignment_forbidden',
				__( 'You do not have permission to delete this category.', 'pressprimer-assignment' ),
				[ 'status' => 403 ]
			);
		}

		$result = $category->delete();

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response(
			[
				'deleted' => true,
				'id'      => $id,
			]
		);
	}

	/**
	 * Sanitize category data from request
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return array Sanitized category data.
	 */
	private function sanitize_category_data( $request ) {
		$data = [];

		if ( null !== $request->get_param( 'name' ) ) {
			$data['name'] = sanitize_text_field( $request->get_param( 'name' ) );
		}

		if ( null !== $request->get_param( 'slug' ) ) {
			$data['slug'] = sanitize_title( $request->get_param( 'slug' ) );
		}

		if ( null !== $request->get_param( 'description' ) ) {
			$data['description'] = sanitize_textarea_field( $request->get_param( 'description' ) );
		}

		if ( null !== $request->get_param( 'taxonomy' ) ) {
			$taxonomy = sanitize_text_field( $request->get_param( 'taxonomy' ) );
			if ( in_array( $taxonomy, [ 'category', 'tag' ], true ) ) {
				$data['taxonomy'] = $taxonomy;
			}
		}

		if ( null !== $request->get_param( 'parent_id' ) ) {
			$parent_id = $request->get_param( 'parent_id' );
			if ( '' === $parent_id || null === $parent_id || 0 === $parent_id || '0' === $parent_id ) {
				$data['parent_id'] = null;
			} else {
				$data['parent_id'] = absint( $parent_id );
			}
		}

		return $data;
	}

	/**
	 * Prepare category for REST response
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Category $category Category instance.
	 * @return array Formatted category data.
	 */
	private function prepare_item_for_response( $category ) {
		return [
			'id'               => (int) $category->id,
			'name'             => $category->name,
			'slug'             => $category->slug,
			'description'      => $category->description ?? '',
			'parent_id'        => $category->parent_id ? (int) $category->parent_id : null,
			'taxonomy'         => $category->taxonomy,
			'assignment_count' => (int) $category->assignment_count,
			'created_at'       => $category->created_at,
		];
	}
}
