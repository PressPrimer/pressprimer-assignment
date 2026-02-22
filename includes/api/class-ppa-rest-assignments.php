<?php
/**
 * REST API controller for assignments
 *
 * Handles CRUD operations for assignments via the REST API.
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
 * REST API assignments controller
 *
 * Registers and handles REST API routes for assignment management.
 *
 * Routes:
 * - GET    /ppa/v1/assignments          List assignments
 * - POST   /ppa/v1/assignments          Create assignment
 * - GET    /ppa/v1/assignments/{id}     Get single assignment
 * - PUT    /ppa/v1/assignments/{id}     Update assignment
 * - DELETE /ppa/v1/assignments/{id}     Delete assignment
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_REST_Assignments {

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
			'/assignments',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => $this->get_collection_params(),
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);

		// Single item routes: get, update, delete.
		register_rest_route(
			self::API_NAMESPACE,
			'/assignments/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);
	}

	/**
	 * Check if current user has permission
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool True if user has permission.
	 */
	public function check_permission( $request ) {
		return current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL );
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
			'page'     => [
				'description' => __( 'Current page of the collection.', 'pressprimer-assignment' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			],
			'per_page' => [
				'description' => __( 'Maximum number of items to return.', 'pressprimer-assignment' ),
				'type'        => 'integer',
				'default'     => 20,
				'minimum'     => 1,
				'maximum'     => 100,
			],
			'search'   => [
				'description' => __( 'Search term for filtering.', 'pressprimer-assignment' ),
				'type'        => 'string',
				'default'     => '',
			],
			'status'   => [
				'description' => __( 'Filter by assignment status.', 'pressprimer-assignment' ),
				'type'        => 'string',
				'enum'        => [ 'draft', 'published', 'archived', '' ],
				'default'     => '',
			],
			'order_by' => [
				'description' => __( 'Field to order by.', 'pressprimer-assignment' ),
				'type'        => 'string',
				'default'     => 'created_at',
				'enum'        => [ 'id', 'title', 'status', 'created_at', 'updated_at' ],
			],
			'order'    => [
				'description' => __( 'Sort direction.', 'pressprimer-assignment' ),
				'type'        => 'string',
				'default'     => 'DESC',
				'enum'        => [ 'ASC', 'DESC' ],
			],
		];
	}

	/**
	 * Get list of assignments
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function get_items( $request ) {
		$page     = absint( $request->get_param( 'page' ) ) ?: 1;
		$per_page = absint( $request->get_param( 'per_page' ) ) ?: 20;
		$per_page = min( $per_page, 100 );
		$search   = sanitize_text_field( $request->get_param( 'search' ) ?? '' );
		$status   = sanitize_text_field( $request->get_param( 'status' ) ?? '' );
		$order_by = sanitize_text_field( $request->get_param( 'order_by' ) ?? 'created_at' );
		$order    = sanitize_text_field( $request->get_param( 'order' ) ?? 'DESC' );

		// Validate order_by against allowed fields.
		$allowed_order_by = [ 'id', 'title', 'status', 'created_at', 'updated_at' ];
		if ( ! in_array( $order_by, $allowed_order_by, true ) ) {
			$order_by = 'created_at';
		}

		// Validate order direction.
		$order = strtoupper( $order );
		if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
			$order = 'DESC';
		}

		// Build query args.
		$args = [
			'order_by' => $order_by,
			'order'    => $order,
			'limit'    => $per_page,
			'offset'   => ( $page - 1 ) * $per_page,
			'where'    => [],
		];

		if ( $status && in_array( $status, [ 'draft', 'published', 'archived' ], true ) ) {
			$args['where']['status'] = $status;
		}

		// Handle search with direct database query for LIKE support.
		if ( $search ) {
			$items = $this->search_assignments( $search, $args );
			$total = $this->count_search_assignments( $search, $args );
		} else {
			$items = PressPrimer_Assignment_Assignment::find( $args );
			$total = PressPrimer_Assignment_Assignment::count( $args['where'] );
		}

		$data = array_map( [ $this, 'prepare_item_for_response' ], $items );

		return rest_ensure_response(
			[
				'items' => $data,
				'total' => $total,
				'page'  => $page,
				'pages' => (int) ceil( $total / $per_page ),
			]
		);
	}

	/**
	 * Search assignments by title
	 *
	 * Uses LIKE query for search functionality.
	 *
	 * @since 1.0.0
	 *
	 * @param string $search Search term.
	 * @param array  $args   Query arguments.
	 * @return array Array of assignment instances.
	 */
	private function search_assignments( $search, $args ) {
		global $wpdb;

		$table      = $wpdb->prefix . 'ppa_assignments';
		$like_term  = '%' . $wpdb->esc_like( $search ) . '%';
		$has_status = ! empty( $args['where']['status'] );
		$status     = $has_status ? $args['where']['status'] : '';
		$offset     = absint( $args['offset'] );
		$limit      = absint( $args['limit'] );
		$is_asc     = 'ASC' === strtoupper( $args['order'] );

		// Validate order_by field.
		$order_by_field   = $args['order_by'];
		$allowed_order_by = [ 'id', 'title', 'status', 'created_at', 'updated_at' ];
		if ( ! in_array( $order_by_field, $allowed_order_by, true ) ) {
			$order_by_field = 'created_at';
		}

		// Use explicit query branches to avoid dynamic SQL construction.
		if ( $has_status && $is_asc ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE title LIKE %s AND status = %s ORDER BY %i ASC LIMIT %d, %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$like_term,
					$status,
					$order_by_field,
					$offset,
					$limit
				)
			);
		} elseif ( $has_status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE title LIKE %s AND status = %s ORDER BY %i DESC LIMIT %d, %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$like_term,
					$status,
					$order_by_field,
					$offset,
					$limit
				)
			);
		} elseif ( $is_asc ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE title LIKE %s ORDER BY %i ASC LIMIT %d, %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$like_term,
					$order_by_field,
					$offset,
					$limit
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE title LIKE %s ORDER BY %i DESC LIMIT %d, %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$like_term,
					$order_by_field,
					$offset,
					$limit
				)
			);
		}

		$results = [];
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$results[] = PressPrimer_Assignment_Assignment::from_row( $row );
			}
		}

		return $results;
	}

	/**
	 * Count search results
	 *
	 * @since 1.0.0
	 *
	 * @param string $search Search term.
	 * @param array  $args   Query arguments.
	 * @return int Total count.
	 */
	private function count_search_assignments( $search, $args ) {
		global $wpdb;

		$table     = $wpdb->prefix . 'ppa_assignments';
		$like_term = '%' . $wpdb->esc_like( $search ) . '%';

		if ( ! empty( $args['where']['status'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE title LIKE %s AND status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$like_term,
					$args['where']['status']
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE title LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$like_term
			)
		);
	}

	/**
	 * Get a single assignment
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_item( $request ) {
		$id         = absint( $request->get_param( 'id' ) );
		$assignment = PressPrimer_Assignment_Assignment::get( $id );

		if ( ! $assignment ) {
			return new WP_Error(
				'ppa_not_found',
				__( 'Assignment not found.', 'pressprimer-assignment' ),
				[ 'status' => 404 ]
			);
		}

		return rest_ensure_response( $this->prepare_item_for_response( $assignment ) );
	}

	/**
	 * Create a new assignment
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function create_item( $request ) {
		$data = $this->sanitize_assignment_data( $request );

		// Set author to current user.
		$data['author_id'] = get_current_user_id();

		$assignment_id = PressPrimer_Assignment_Assignment::create( $data );

		if ( is_wp_error( $assignment_id ) ) {
			return new WP_Error(
				$assignment_id->get_error_code(),
				$assignment_id->get_error_message(),
				[ 'status' => 400 ]
			);
		}

		$assignment = PressPrimer_Assignment_Assignment::get( $assignment_id );

		// Handle category assignment.
		$categories = $request->get_param( 'categories' );
		if ( is_array( $categories ) ) {
			$assignment->set_categories( array_map( 'absint', $categories ) );
		}

		return rest_ensure_response( $this->prepare_item_for_response( $assignment ) );
	}

	/**
	 * Update an existing assignment
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function update_item( $request ) {
		$id         = absint( $request->get_param( 'id' ) );
		$assignment = PressPrimer_Assignment_Assignment::get( $id );

		if ( ! $assignment ) {
			return new WP_Error(
				'ppa_not_found',
				__( 'Assignment not found.', 'pressprimer-assignment' ),
				[ 'status' => 404 ]
			);
		}

		$data = $this->sanitize_assignment_data( $request );

		// Update assignment properties.
		foreach ( $data as $key => $value ) {
			if ( property_exists( $assignment, $key ) ) {
				$assignment->$key = $value;
			}
		}

		$result = $assignment->save();

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				[ 'status' => 400 ]
			);
		}

		// Handle category assignment.
		$categories = $request->get_param( 'categories' );
		if ( is_array( $categories ) ) {
			$assignment->set_categories( array_map( 'absint', $categories ) );
		}

		return rest_ensure_response( $this->prepare_item_for_response( $assignment ) );
	}

	/**
	 * Delete an assignment
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function delete_item( $request ) {
		$id         = absint( $request->get_param( 'id' ) );
		$assignment = PressPrimer_Assignment_Assignment::get( $id );

		if ( ! $assignment ) {
			return new WP_Error(
				'ppa_not_found',
				__( 'Assignment not found.', 'pressprimer-assignment' ),
				[ 'status' => 404 ]
			);
		}

		/**
		 * Fires before an assignment is deleted.
		 *
		 * @since 1.0.0
		 *
		 * @param PressPrimer_Assignment_Assignment $assignment The assignment being deleted.
		 */
		do_action( 'pressprimer_assignment_before_delete', $assignment );

		$result = $assignment->delete();

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				[ 'status' => 500 ]
			);
		}

		/**
		 * Fires after an assignment is deleted.
		 *
		 * @since 1.0.0
		 *
		 * @param int $id The deleted assignment ID.
		 */
		do_action( 'pressprimer_assignment_deleted', $id );

		return rest_ensure_response(
			[
				'deleted' => true,
				'id'      => $id,
			]
		);
	}

	/**
	 * Sanitize assignment data from request
	 *
	 * Extracts and sanitizes all assignment fields from the request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return array Sanitized assignment data.
	 */
	private function sanitize_assignment_data( $request ) {
		$data = [];

		// Text fields.
		if ( null !== $request->get_param( 'title' ) ) {
			$data['title'] = sanitize_text_field( $request->get_param( 'title' ) );
		}

		// Rich text fields - allow safe HTML.
		if ( null !== $request->get_param( 'description' ) ) {
			$data['description'] = wp_kses_post( $request->get_param( 'description' ) );
		}

		if ( null !== $request->get_param( 'instructions' ) ) {
			$data['instructions'] = wp_kses_post( $request->get_param( 'instructions' ) );
		}

		if ( null !== $request->get_param( 'grading_guidelines' ) ) {
			$data['grading_guidelines'] = wp_kses_post( $request->get_param( 'grading_guidelines' ) );
		}

		// Numeric fields.
		if ( null !== $request->get_param( 'max_points' ) ) {
			$data['max_points'] = floatval( $request->get_param( 'max_points' ) );
		}

		if ( null !== $request->get_param( 'passing_score' ) ) {
			$data['passing_score'] = floatval( $request->get_param( 'passing_score' ) );
		}

		if ( null !== $request->get_param( 'max_file_size' ) ) {
			$data['max_file_size'] = absint( $request->get_param( 'max_file_size' ) );
		}

		if ( null !== $request->get_param( 'max_files' ) ) {
			$data['max_files'] = absint( $request->get_param( 'max_files' ) );
		}

		if ( null !== $request->get_param( 'max_resubmissions' ) ) {
			$data['max_resubmissions'] = absint( $request->get_param( 'max_resubmissions' ) );
		}

		// Boolean fields stored as integer.
		if ( null !== $request->get_param( 'allow_resubmission' ) ) {
			$data['allow_resubmission'] = $request->get_param( 'allow_resubmission' ) ? 1 : 0;
		}

		// Enum fields - validate against allowed values.
		if ( null !== $request->get_param( 'status' ) ) {
			$status = sanitize_text_field( $request->get_param( 'status' ) );
			if ( in_array( $status, [ 'draft', 'published', 'archived' ], true ) ) {
				$data['status'] = $status;
			}
		}

		// JSON fields.
		if ( null !== $request->get_param( 'allowed_file_types' ) ) {
			$file_types = $request->get_param( 'allowed_file_types' );
			if ( is_string( $file_types ) ) {
				// Already JSON string from frontend.
				$decoded = json_decode( $file_types, true );
				if ( is_array( $decoded ) ) {
					$data['allowed_file_types'] = wp_json_encode(
						array_map( 'sanitize_text_field', $decoded )
					);
				}
			} elseif ( is_array( $file_types ) ) {
				$data['allowed_file_types'] = wp_json_encode(
					array_map( 'sanitize_text_field', $file_types )
				);
			}
		}

		return $data;
	}

	/**
	 * Prepare assignment for REST response
	 *
	 * Formats assignment data for the API response.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
	 * @return array Formatted assignment data.
	 */
	private function prepare_item_for_response( $assignment ) {
		$data = [
			'id'                 => (int) $assignment->id,
			'uuid'               => $assignment->uuid,
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
			'status'             => $assignment->status,
			'author_id'          => (int) $assignment->author_id,
			'submission_count'   => (int) $assignment->submission_count,
			'graded_count'       => (int) $assignment->graded_count,
			'created_at'         => $assignment->created_at,
			'updated_at'         => $assignment->updated_at,
		];

		/**
		 * Filters the assignment REST response data.
		 *
		 * @since 1.0.0
		 *
		 * @param array                              $data       Response data.
		 * @param PressPrimer_Assignment_Assignment   $assignment Assignment instance.
		 */
		return apply_filters( 'pressprimer_assignment_rest_response', $data, $assignment );
	}
}
