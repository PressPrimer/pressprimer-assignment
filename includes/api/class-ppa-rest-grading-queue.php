<?php
/**
 * REST API controller for grading queue
 *
 * Handles retrieval of pending submissions across all assignments
 * and provides the pending count for admin menu badges.
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
 * REST API grading queue controller
 *
 * Registers and handles REST API routes for the grading queue.
 *
 * Routes:
 * - GET /ppa/v1/grading-queue         List pending submissions
 * - GET /ppa/v1/grading-queue/count   Get pending count (for badge)
 * - GET /ppa/v1/grading-queue/next    Get next submission ID
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_REST_Grading_Queue {

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
		// Queue list.
		register_rest_route(
			self::API_NAMESPACE,
			'/grading-queue',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_queue' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => $this->get_collection_params(),
			]
		);

		// Pending count (for menu badge).
		register_rest_route(
			self::API_NAMESPACE,
			'/grading-queue/count',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_count' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// Next in queue (for sequential grading).
		register_rest_route(
			self::API_NAMESPACE,
			'/grading-queue/next/(?P<current_id>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_next' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// Assignments filter list.
		register_rest_route(
			self::API_NAMESPACE,
			'/grading-queue/assignments',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_assignments' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Check if current user has permission
	 *
	 * Allows access for users with manage_own (teachers/instructors) or
	 * manage_all (admins) capability. Data scoping happens in the service layer
	 * via get_accessible_assignment_ids().
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
	 * Get collection parameters for queue endpoint
	 *
	 * @since 1.0.0
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		return [
			'page'          => [
				'description' => __( 'Current page of the collection.', 'pressprimer-assignment' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			],
			'per_page'      => [
				'description' => __( 'Maximum number of items to return.', 'pressprimer-assignment' ),
				'type'        => 'integer',
				'default'     => 50,
				'minimum'     => 1,
				'maximum'     => 100,
			],
			'assignment_id' => [
				'description' => __( 'Filter by assignment ID.', 'pressprimer-assignment' ),
				'type'        => 'integer',
				'default'     => 0,
			],
			'status'        => [
				'description' => __( 'Filter by submission status.', 'pressprimer-assignment' ),
				'type'        => 'string',
				'default'     => '',
				'enum'        => [ 'submitted', 'grading', '' ],
			],
			'search'        => [
				'description' => __( 'Search by student name or email.', 'pressprimer-assignment' ),
				'type'        => 'string',
				'default'     => '',
			],
			'orderby'       => [
				'description' => __( 'Field to order by.', 'pressprimer-assignment' ),
				'type'        => 'string',
				'default'     => 'submitted_at',
				'enum'        => [ 'submitted_at', 'assignment_title', 'status' ],
			],
			'order'         => [
				'description' => __( 'Sort direction.', 'pressprimer-assignment' ),
				'type'        => 'string',
				'default'     => 'ASC',
				'enum'        => [ 'ASC', 'DESC' ],
			],
		];
	}

	/**
	 * Get grading queue list
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function get_queue( $request ) {
		$args = [
			'assignment_id' => absint( $request->get_param( 'assignment_id' ) ) ?: null,
			'search'        => sanitize_text_field( $request->get_param( 'search' ) ?? '' ),
			'orderby'       => sanitize_text_field( $request->get_param( 'orderby' ) ?? 'submitted_at' ),
			'order'         => sanitize_text_field( $request->get_param( 'order' ) ?? 'ASC' ),
			'per_page'      => absint( $request->get_param( 'per_page' ) ) ?: 50,
			'page'          => absint( $request->get_param( 'page' ) ) ?: 1,
		];

		// Handle status filter.
		$status = sanitize_text_field( $request->get_param( 'status' ) ?? '' );
		if ( ! empty( $status ) ) {
			$args['status'] = [ $status ];
		} else {
			$args['status'] = [ 'submitted', 'grading', 'graded' ];
		}

		$result = PressPrimer_Assignment_Grading_Queue_Service::get_queue( $args );

		return rest_ensure_response( $result );
	}

	/**
	 * Get pending submission count
	 *
	 * Returns the count for use in admin menu badges.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function get_count( $request ) {
		$count = PressPrimer_Assignment_Grading_Queue_Service::get_pending_count();

		return rest_ensure_response(
			[
				'count' => $count,
			]
		);
	}

	/**
	 * Get next submission in queue
	 *
	 * Returns the next submission ID for sequential grading.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function get_next( $request ) {
		$current_id = absint( $request->get_param( 'current_id' ) );
		$next_id    = PressPrimer_Assignment_Grading_Queue_Service::get_next_in_queue( $current_id );

		return rest_ensure_response(
			[
				'next_id' => $next_id,
			]
		);
	}

	/**
	 * Get assignments for filter dropdown
	 *
	 * Returns a simplified list of accessible assignments.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function get_assignments( $request ) {
		$assignments = PressPrimer_Assignment_Grading_Queue_Service::get_assignments_for_filter();

		$data = array_map(
			function ( $assignment ) {
				return [
					'id'    => (int) $assignment->id,
					'title' => $assignment->title,
				];
			},
			$assignments
		);

		return rest_ensure_response( $data );
	}
}
