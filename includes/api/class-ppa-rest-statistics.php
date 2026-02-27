<?php
/**
 * REST API controller for statistics
 *
 * Handles dashboard statistics, activity chart, and recent submissions
 * endpoints for the PressPrimer Assignment admin dashboard.
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
 * REST API statistics controller
 *
 * Registers and handles REST API routes for assignment statistics.
 *
 * Routes:
 * - GET /ppa/v1/statistics/dashboard      Dashboard summary stats
 * - GET /ppa/v1/statistics/activity-chart  Daily activity chart data
 * - GET /ppa/v1/statistics/submissions     Recent submissions list
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_REST_Statistics {

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
		// Dashboard summary stats.
		register_rest_route(
			self::API_NAMESPACE,
			'/statistics/dashboard',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_dashboard_stats' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// Activity chart data.
		register_rest_route(
			self::API_NAMESPACE,
			'/statistics/activity-chart',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_activity_chart' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'days' => [
						'description' => __( 'Number of days of data to return.', 'pressprimer-assignment' ),
						'type'        => 'integer',
						'default'     => 90,
						'minimum'     => 1,
						'maximum'     => 730,
					],
				],
			]
		);

		// Overview stats for reports page.
		register_rest_route(
			self::API_NAMESPACE,
			'/statistics/overview',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_overview_stats' ],
				'permission_callback' => [ $this, 'check_reports_permission' ],
			]
		);

		// Assignment performance report.
		register_rest_route(
			self::API_NAMESPACE,
			'/statistics/assignment-performance',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_assignment_performance' ],
				'permission_callback' => [ $this, 'check_reports_permission' ],
			]
		);

		// Recent submissions for dashboard table.
		register_rest_route(
			self::API_NAMESPACE,
			'/statistics/submissions',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_recent_submissions' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'per_page' => [
						'description' => __( 'Number of submissions to return.', 'pressprimer-assignment' ),
						'type'        => 'integer',
						'default'     => 10,
						'minimum'     => 1,
						'maximum'     => 50,
					],
				],
			]
		);
	}

	/**
	 * Check if current user has permission
	 *
	 * Allows access for users with manage_own (teachers) or
	 * manage_all (admins) capability. Matches Quiz pattern.
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
	 * user's ID for teachers (see only their assignments' data).
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
	 * Get dashboard statistics
	 *
	 * Returns summary metrics for the dashboard stats cards
	 * and popular assignments sidebar.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_dashboard_stats( $request ) {
		try {
			$service   = new PressPrimer_Assignment_Statistics_Service();
			$author_id = $this->get_author_id();
			$stats     = $service->get_dashboard_stats( $author_id );

			return new WP_REST_Response(
				[
					'success' => true,
					'data'    => $stats,
				],
				200
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'dashboard_error',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Check if current user has reports permission
	 *
	 * Allows access for users with the view_reports capability.
	 * Falls back to manage_own for backwards compatibility.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool True if user has permission.
	 */
	public function check_reports_permission( $request ) {
		return current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_VIEW_REPORTS )
			|| current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL );
	}

	/**
	 * Get overview statistics for reports
	 *
	 * Returns all-time aggregate stats for the reports
	 * overview cards (total submissions, avg score, pass rate,
	 * avg grading time).
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_overview_stats( $request ) {
		try {
			$service   = new PressPrimer_Assignment_Statistics_Service();
			$author_id = $this->get_author_id();
			$stats     = $service->get_overview_stats( $author_id );

			return new WP_REST_Response(
				[
					'success' => true,
					'data'    => $stats,
				],
				200
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'overview_error',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Get assignment performance report
	 *
	 * Returns per-assignment metrics with date filtering, search,
	 * sorting, and pagination for the performance report table.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_assignment_performance( $request ) {
		try {
			$service = new PressPrimer_Assignment_Statistics_Service();

			$args = [
				'date_from' => $request->get_param( 'date_from' ),
				'date_to'   => $request->get_param( 'date_to' ),
				'search'    => $request->get_param( 'search' ) ?? '',
				'orderby'   => $request->get_param( 'orderby' ) ?? 'submissions',
				'order'     => $request->get_param( 'order' ) ?? 'DESC',
				'per_page'  => $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 20,
				'page'      => $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1,
				'author_id' => $this->get_author_id(),
			];

			$data = $service->get_assignment_performance( $args );

			return new WP_REST_Response(
				[
					'success' => true,
					'data'    => $data,
				],
				200
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'performance_error',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Get activity chart data
	 *
	 * Returns daily submission counts and average scores
	 * for the dashboard activity chart.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_activity_chart( $request ) {
		$service   = new PressPrimer_Assignment_Statistics_Service();
		$author_id = $this->get_author_id();

		$args = [
			'days'      => $request->get_param( 'days' ) ? absint( $request->get_param( 'days' ) ) : 90,
			'author_id' => $author_id,
		];

		$data = $service->get_activity_chart_data( $args );

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => $data,
			],
			200
		);
	}

	/**
	 * Get recent submissions
	 *
	 * Returns the most recent submissions for the dashboard
	 * activity table.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_recent_submissions( $request ) {
		$service   = new PressPrimer_Assignment_Statistics_Service();
		$author_id = $this->get_author_id();

		$args = [
			'per_page'  => $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 10,
			'author_id' => $author_id,
		];

		$data = $service->get_recent_submissions( $args );

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => $data,
			],
			200
		);
	}
}
