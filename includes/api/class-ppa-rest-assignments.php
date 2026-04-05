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
	 * Allows access for users with manage_own (teachers/instructors) or
	 * manage_all (admins) capability. Data scoping happens in callbacks.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool True if user has permission.
	 */
	public function check_permission( $request ) {
		return current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_OWN )
			|| current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL );
	}

	/**
	 * Get the author ID filter for the current user
	 *
	 * Returns null for admins (see all data) or the current
	 * user's ID for teachers (see only their own assignments).
	 *
	 * @since 1.0.0
	 *
	 * @return int|null Author ID or null for unrestricted access.
	 */
	private function get_author_id() {
		if ( current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			return null;
		}
		return get_current_user_id();
	}

	/**
	 * Check if the current user can access a specific assignment
	 *
	 * Returns true for admins. For manage_own users, checks that
	 * the assignment's author_id matches the current user.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
	 * @return bool True if user can access the assignment.
	 */
	private function can_access_assignment( $assignment ) {
		if ( current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			return true;
		}
		return (int) $assignment->author_id === get_current_user_id();
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
			'category' => [
				'description' => __( 'Filter by category ID.', 'pressprimer-assignment' ),
				'type'        => 'integer',
				'default'     => 0,
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
		$page        = absint( $request->get_param( 'page' ) ) ?: 1;
		$per_page    = absint( $request->get_param( 'per_page' ) ) ?: 20;
		$per_page    = min( $per_page, 100 );
		$search      = sanitize_text_field( $request->get_param( 'search' ) ?? '' );
		$status      = sanitize_text_field( $request->get_param( 'status' ) ?? '' );
		$order_by    = sanitize_text_field( $request->get_param( 'order_by' ) ?? 'created_at' );
		$order       = sanitize_text_field( $request->get_param( 'order' ) ?? 'DESC' );
		$category_id = absint( $request->get_param( 'category' ) );

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

		// Scope to current user's assignments for manage_own users.
		$author_id = $this->get_author_id();
		if ( null !== $author_id ) {
			$args['where']['author_id'] = $author_id;
		}

		// Handle category filter with a JOIN query.
		if ( $category_id > 0 ) {
			$items = $this->get_assignments_by_category( $category_id, $search, $args, $author_id );
			$total = $this->count_assignments_by_category( $category_id, $search, $args, $author_id );
		} elseif ( $search ) {
			// Handle search with direct database query for LIKE support.
			$items = $this->search_assignments( $search, $args, $author_id );
			$total = $this->count_search_assignments( $search, $args, $author_id );
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
	 * @param string   $search    Search term.
	 * @param array    $args      Query arguments.
	 * @param int|null $author_id Author ID for ownership scoping, or null for all.
	 * @return array Array of assignment instances.
	 */
	private function search_assignments( $search, $args, $author_id = null ) {
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

		// Build dynamic WHERE parts with params array.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$where  = 'WHERE title LIKE %s';
		$params = [ $like_term ];

		if ( $has_status ) {
			$where   .= ' AND status = %s';
			$params[] = $status;
		}

		if ( null !== $author_id ) {
			$where   .= ' AND author_id = %d';
			$params[] = $author_id;
		}

		if ( $is_asc ) {
			$where .= ' ORDER BY %i ASC LIMIT %d, %d';
		} else {
			$where .= ' ORDER BY %i DESC LIMIT %d, %d';
		}

		$params[] = $order_by_field;
		$params[] = $offset;
		$params[] = $limit;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Array param count is dynamic.
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where}", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$params
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

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
	 * @param string   $search    Search term.
	 * @param array    $args      Query arguments.
	 * @param int|null $author_id Author ID for ownership scoping, or null for all.
	 * @return int Total count.
	 */
	private function count_search_assignments( $search, $args, $author_id = null ) {
		global $wpdb;

		$table     = $wpdb->prefix . 'ppa_assignments';
		$like_term = '%' . $wpdb->esc_like( $search ) . '%';

		// Build dynamic WHERE with params array.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$where  = 'WHERE title LIKE %s';
		$params = [ $like_term ];

		if ( ! empty( $args['where']['status'] ) ) {
			$where   .= ' AND status = %s';
			$params[] = $args['where']['status'];
		}

		if ( null !== $author_id ) {
			$where   .= ' AND author_id = %d';
			$params[] = $author_id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Array param count is dynamic.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} {$where}", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$params
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
				'pressprimer_assignment_not_found',
				__( 'Assignment not found.', 'pressprimer-assignment' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! $this->can_access_assignment( $assignment ) ) {
			return new WP_Error(
				'pressprimer_assignment_forbidden',
				__( 'You do not have permission to access this assignment.', 'pressprimer-assignment' ),
				[ 'status' => 403 ]
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

		// Handle LifterLMS link.
		$this->save_lifterlms_link_from_data( $assignment_id, $data );

		// Handle LearnPress link.
		$this->save_learnpress_link_from_data( $assignment_id, $data );

		// Clear dashboard statistics cache (assignment count changed).
		if ( class_exists( 'PressPrimer_Assignment_Statistics_Service' ) ) {
			PressPrimer_Assignment_Statistics_Service::clear_all_caches();
		}

		/**
		 * Fire audit log event for assignment created.
		 *
		 * @since 2.0.0
		 */
		do_action(
			'pressprimer_assignment_log_event',
			'assignment.created',
			'assignment',
			$assignment_id,
			[
				'title'     => $assignment->title,
				'author_id' => $assignment->author_id,
			]
		);

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
				'pressprimer_assignment_not_found',
				__( 'Assignment not found.', 'pressprimer-assignment' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! $this->can_access_assignment( $assignment ) ) {
			return new WP_Error(
				'pressprimer_assignment_forbidden',
				__( 'You do not have permission to edit this assignment.', 'pressprimer-assignment' ),
				[ 'status' => 403 ]
			);
		}

		$data = $this->sanitize_assignment_data( $request );

		// Track which fields changed for the audit log.
		$old_status     = $assignment->status;
		$changed_fields = array_keys( $data );

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

		// Handle LifterLMS link.
		$this->save_lifterlms_link_from_data( $id, $data );

		// Handle LearnPress link.
		$this->save_learnpress_link_from_data( $id, $data );

		// Clear dashboard statistics cache (status or other stats-relevant fields may have changed).
		if ( class_exists( 'PressPrimer_Assignment_Statistics_Service' ) ) {
			PressPrimer_Assignment_Statistics_Service::clear_all_caches();
		}

		// Fire audit log events based on what changed.
		$new_status = $assignment->status;

		if ( $old_status !== $new_status && 'published' === $new_status ) {
			// Fire audit log event for assignment published.
			do_action(
				'pressprimer_assignment_log_event',
				'assignment.published',
				'assignment',
				$id,
				[ 'title' => $assignment->title ]
			);
		} elseif ( $old_status !== $new_status && 'archived' === $new_status ) {
			// Fire audit log event for assignment archived.
			do_action(
				'pressprimer_assignment_log_event',
				'assignment.archived',
				'assignment',
				$id,
				[ 'title' => $assignment->title ]
			);
		}

		// Fire audit log event for assignment updated.
		do_action(
			'pressprimer_assignment_log_event',
			'assignment.updated',
			'assignment',
			$id,
			[
				'changed_fields' => $changed_fields,
				'title'          => $assignment->title,
			]
		);

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
				'pressprimer_assignment_not_found',
				__( 'Assignment not found.', 'pressprimer-assignment' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! $this->can_access_assignment( $assignment ) ) {
			return new WP_Error(
				'pressprimer_assignment_forbidden',
				__( 'You do not have permission to delete this assignment.', 'pressprimer-assignment' ),
				[ 'status' => 403 ]
			);
		}

		// Capture title before deletion for audit log.
		$assignment_title = $assignment->title;

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

		/**
		 * Fire audit log event for assignment deleted.
		 *
		 * Enterprise addon listens to this and writes to the audit log.
		 * When Enterprise is not active, this hook fires harmlessly.
		 *
		 * @since 2.0.0
		 *
		 * @param string $event_type  Event identifier.
		 * @param string $object_type Object type affected.
		 * @param int    $object_id   Object ID.
		 * @param array  $data        Additional context.
		 */
		do_action(
			'pressprimer_assignment_log_event',
			'assignment.deleted',
			'assignment',
			$id,
			[
				'title' => $assignment_title,
			]
		);

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

		if ( null !== $request->get_param( 'submission_type' ) ) {
			$submission_type = sanitize_text_field( $request->get_param( 'submission_type' ) );
			if ( in_array( $submission_type, [ 'file', 'text', 'either' ], true ) ) {
				$data['submission_type'] = $submission_type;
			}
		}

		// Theme field - validate against allowed themes.
		if ( null !== $request->get_param( 'theme' ) ) {
			$theme = sanitize_key( $request->get_param( 'theme' ) );
			if ( in_array( $theme, [ 'default', 'modern', 'minimal' ], true ) ) {
				$data['theme'] = $theme;
			}
		}

		// Notification email (comma-separated email addresses).
		if ( null !== $request->get_param( 'notification_email' ) ) {
			$raw_emails = sanitize_text_field( $request->get_param( 'notification_email' ) );
			if ( '' === trim( $raw_emails ) ) {
				$data['notification_email'] = null;
			} else {
				// Validate each email address individually.
				$emails = array_map( 'trim', explode( ',', $raw_emails ) );
				$valid  = [];
				foreach ( $emails as $email_addr ) {
					$sanitized_email = sanitize_email( $email_addr );
					if ( is_email( $sanitized_email ) ) {
						$valid[] = $sanitized_email;
					}
				}
				$data['notification_email'] = ! empty( $valid ) ? implode( ', ', $valid ) : null;
			}
		}

		// LifterLMS integration fields (not stored on the assignment — handled separately).
		if ( null !== $request->get_param( 'ppa_lifterlms_object_id' ) ) {
			$data['ppa_lifterlms_object_id'] = absint( $request->get_param( 'ppa_lifterlms_object_id' ) );
		}

		if ( null !== $request->get_param( 'ppa_lifterlms_completion_type' ) ) {
			$completion_type = sanitize_text_field( $request->get_param( 'ppa_lifterlms_completion_type' ) );
			if ( in_array( $completion_type, [ 'lesson', 'course' ], true ) ) {
				$data['ppa_lifterlms_completion_type'] = $completion_type;
			}
		}

		// LearnPress integration fields (not stored on the assignment — handled separately).
		if ( null !== $request->get_param( 'ppa_learnpress_object_id' ) ) {
			$data['ppa_learnpress_object_id'] = absint( $request->get_param( 'ppa_learnpress_object_id' ) );
		}

		if ( null !== $request->get_param( 'ppa_learnpress_completion_type' ) ) {
			$completion_type = sanitize_text_field( $request->get_param( 'ppa_learnpress_completion_type' ) );
			if ( in_array( $completion_type, [ 'lesson', 'course' ], true ) ) {
				$data['ppa_learnpress_completion_type'] = $completion_type;
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
	 * Get assignments filtered by category
	 *
	 * Uses a JOIN with the assignment_tax table to filter by category.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $category_id Category ID to filter by.
	 * @param string   $search      Optional search term.
	 * @param array    $args        Query arguments.
	 * @param int|null $author_id   Author ID for ownership scoping, or null for all.
	 * @return array Array of assignment instances.
	 */
	private function get_assignments_by_category( $category_id, $search, $args, $author_id = null ) {
		global $wpdb;

		$table     = $wpdb->prefix . 'ppa_assignments';
		$tax_table = $wpdb->prefix . 'ppa_assignment_tax';
		$offset    = absint( $args['offset'] );
		$limit     = absint( $args['limit'] );
		$is_asc    = 'ASC' === strtoupper( $args['order'] );

		// Validate order_by field.
		$order_by_field   = $args['order_by'];
		$allowed_order_by = [ 'id', 'title', 'status', 'created_at', 'updated_at' ];
		if ( ! in_array( $order_by_field, $allowed_order_by, true ) ) {
			$order_by_field = 'created_at';
		}

		$has_status = ! empty( $args['where']['status'] );
		$status     = $has_status ? $args['where']['status'] : '';
		$has_search = ! empty( $search );
		$like_term  = $has_search ? '%' . $wpdb->esc_like( $search ) . '%' : '';

		// Build the base query parts.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$base_query = "SELECT DISTINCT a.* FROM {$table} a INNER JOIN {$tax_table} t ON a.id = t.assignment_id WHERE t.category_id = %d";

		$params = [ $category_id ];

		if ( $has_search ) {
			$base_query .= ' AND a.title LIKE %s';
			$params[]    = $like_term;
		}

		if ( $has_status ) {
			$base_query .= ' AND a.status = %s';
			$params[]    = $status;
		}

		if ( null !== $author_id ) {
			$base_query .= ' AND a.author_id = %d';
			$params[]    = $author_id;
		}

		if ( $is_asc ) {
			$base_query .= ' ORDER BY a.%i ASC LIMIT %d, %d';
		} else {
			$base_query .= ' ORDER BY a.%i DESC LIMIT %d, %d';
		}

		$params[] = $order_by_field;
		$params[] = $offset;
		$params[] = $limit;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare( $base_query, $params ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$results = [];
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$results[] = PressPrimer_Assignment_Assignment::from_row( $row );
			}
		}

		return $results;
	}

	/**
	 * Count assignments filtered by category
	 *
	 * @since 1.0.0
	 *
	 * @param int      $category_id Category ID to filter by.
	 * @param string   $search      Optional search term.
	 * @param array    $args        Query arguments.
	 * @param int|null $author_id   Author ID for ownership scoping, or null for all.
	 * @return int Total count.
	 */
	private function count_assignments_by_category( $category_id, $search, $args, $author_id = null ) {
		global $wpdb;

		$table     = $wpdb->prefix . 'ppa_assignments';
		$tax_table = $wpdb->prefix . 'ppa_assignment_tax';

		$has_status = ! empty( $args['where']['status'] );
		$status     = $has_status ? $args['where']['status'] : '';
		$has_search = ! empty( $search );
		$like_term  = $has_search ? '%' . $wpdb->esc_like( $search ) . '%' : '';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$base_query = "SELECT COUNT(DISTINCT a.id) FROM {$table} a INNER JOIN {$tax_table} t ON a.id = t.assignment_id WHERE t.category_id = %d";

		$params = [ $category_id ];

		if ( $has_search ) {
			$base_query .= ' AND a.title LIKE %s';
			$params[]    = $like_term;
		}

		if ( $has_status ) {
			$base_query .= ' AND a.status = %s';
			$params[]    = $status;
		}

		if ( null !== $author_id ) {
			$base_query .= ' AND a.author_id = %d';
			$params[]    = $author_id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare( $base_query, $params ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $count;
	}

	/**
	 * Save or remove a LifterLMS link based on sanitized data
	 *
	 * Extracts the LifterLMS fields from the sanitized assignment data
	 * and delegates to the LifterLMS integration class.
	 *
	 * @since 2.0.0
	 *
	 * @param int   $assignment_id Assignment ID.
	 * @param array $data          Sanitized assignment data (may contain ppa_lifterlms_* keys).
	 */
	private function save_lifterlms_link_from_data( $assignment_id, $data ) {
		if ( ! class_exists( 'PressPrimer_Assignment_LifterLMS' ) || ! defined( 'LLMS_PLUGIN_FILE' ) ) {
			return;
		}

		// Only act if LifterLMS fields were submitted.
		if ( ! array_key_exists( 'ppa_lifterlms_object_id', $data ) ) {
			return;
		}

		$lifterlms = new PressPrimer_Assignment_LifterLMS();
		$object_id = absint( $data['ppa_lifterlms_object_id'] );

		if ( 0 === $object_id ) {
			// Clear the link.
			$lifterlms->remove_lifterlms_link( $assignment_id );
		} else {
			$completion_type = isset( $data['ppa_lifterlms_completion_type'] )
				? $data['ppa_lifterlms_completion_type']
				: 'lesson';

			$lifterlms->save_lifterlms_link( $assignment_id, $object_id, $completion_type );
		}
	}

	/**
	 * Save or remove a LearnPress link based on sanitized data
	 *
	 * Extracts the LearnPress fields from the sanitized assignment data
	 * and delegates to the LearnPress integration class.
	 *
	 * @since 2.0.0
	 *
	 * @param int   $assignment_id Assignment ID.
	 * @param array $data          Sanitized assignment data (may contain ppa_learnpress_* keys).
	 */
	private function save_learnpress_link_from_data( $assignment_id, $data ) {
		if ( ! class_exists( 'PressPrimer_Assignment_LearnPress' ) || ! defined( 'LEARNPRESS_VERSION' ) ) {
			return;
		}

		// Only act if LearnPress fields were submitted.
		if ( ! array_key_exists( 'ppa_learnpress_object_id', $data ) ) {
			return;
		}

		$learnpress = new PressPrimer_Assignment_LearnPress();
		$object_id  = absint( $data['ppa_learnpress_object_id'] );

		if ( 0 === $object_id ) {
			// Clear the link.
			$learnpress->remove_learnpress_link( $assignment_id );
		} else {
			$completion_type = isset( $data['ppa_learnpress_completion_type'] )
				? $data['ppa_learnpress_completion_type']
				: 'lesson';

			$learnpress->save_learnpress_link( $assignment_id, $object_id, $completion_type );
		}
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
		// Get assigned categories.
		$categories    = $assignment->get_categories();
		$category_ids  = [];
		$category_data = [];
		foreach ( $categories as $cat ) {
			$category_ids[]  = (int) $cat->id;
			$category_data[] = [
				'id'       => (int) $cat->id,
				'name'     => $cat->name,
				'taxonomy' => $cat->taxonomy,
			];
		}

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
			'submission_type'    => $assignment->submission_type,
			'status'             => $assignment->status,
			'author_id'          => (int) $assignment->author_id,
			'notification_email' => $assignment->notification_email ?? '',
			'submission_count'   => (int) $assignment->submission_count,
			'graded_count'       => (int) $assignment->graded_count,
			'categories'         => $category_ids,
			'category_details'   => $category_data,
			'created_at'         => $assignment->created_at,
			'updated_at'         => $assignment->updated_at,
		];

		// Include LifterLMS link data if LifterLMS is active.
		if ( defined( 'LLMS_PLUGIN_FILE' ) && class_exists( 'PressPrimer_Assignment_LifterLMS' ) ) {
			$lifterlms  = new PressPrimer_Assignment_LifterLMS();
			$linked_obj = $lifterlms->get_linked_lifterlms_object( (int) $assignment->id );

			$data['ppa_lifterlms_object_id']       = $linked_obj ? $linked_obj['object_id'] : 0;
			$data['ppa_lifterlms_completion_type'] = $linked_obj ? $linked_obj['completion_type'] : 'lesson';
		}

		// Include LearnPress link data if LearnPress is active.
		if ( defined( 'LEARNPRESS_VERSION' ) && class_exists( 'PressPrimer_Assignment_LearnPress' ) ) {
			$learnpress = new PressPrimer_Assignment_LearnPress();
			$linked_obj = $learnpress->get_linked_learnpress_object( (int) $assignment->id );

			$data['ppa_learnpress_object_id']       = $linked_obj ? $linked_obj['object_id'] : 0;
			$data['ppa_learnpress_completion_type'] = $linked_obj ? $linked_obj['completion_type'] : 'lesson';
		}

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
