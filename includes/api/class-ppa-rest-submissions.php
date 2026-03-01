<?php
/**
 * REST API controller for submissions
 *
 * Handles submission listing with filters, single submission
 * retrieval with grading context, grading updates, and
 * bulk return operations.
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
 * REST API submissions controller
 *
 * Registers and handles REST API routes for submission management.
 *
 * Routes:
 * - GET    /ppa/v1/submissions                List submissions with filters
 * - GET    /ppa/v1/submissions/{id}           Get single submission with context
 * - PUT    /ppa/v1/submissions/{id}           Update grading data
 * - POST   /ppa/v1/submissions/bulk-return    Return multiple submissions
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_REST_Submissions {

	/**
	 * API namespace
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const API_NAMESPACE = 'ppa/v1';

	/**
	 * Allowed order-by fields mapping
	 *
	 * Maps API parameter names to safe SQL column references.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $allowed_orderby = [
		'submitted_at' => 's.submitted_at',
		'status'       => 's.status',
		'score'        => 's.score',
		'student_name' => 'u.display_name',
	];

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
		// Collection route: list submissions.
		register_rest_route(
			self::API_NAMESPACE,
			'/submissions',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => $this->get_collection_params(),
			]
		);

		// Single submission with grading context.
		register_rest_route(
			self::API_NAMESPACE,
			'/submissions/(?P<id>\d+)',
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
			]
		);

		// Bulk return.
		register_rest_route(
			self::API_NAMESPACE,
			'/submissions/bulk-return',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'bulk_return' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// File content serving (inline, for grading interface viewers).
		register_rest_route(
			self::API_NAMESPACE,
			'/files/(?P<id>\d+)/content',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'serve_file_content' ],
				'permission_callback' => [ $this, 'check_permission' ],
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
	 * user's ID for teachers (see only their own assignments' submissions).
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
	 * Check if the current user can access a submission
	 *
	 * For manage_own users, the submission's assignment must be
	 * authored by the current user.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Submission $submission Submission instance.
	 * @return bool True if user can access.
	 */
	private function can_access_submission( $submission ) {
		if ( current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			return true;
		}

		$assignment = $submission->get_assignment();

		if ( ! $assignment ) {
			return false;
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
			'page'          => [
				'description' => __( 'Current page of the collection.', 'pressprimer-assignment' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			],
			'per_page'      => [
				'description' => __( 'Maximum number of items to return.', 'pressprimer-assignment' ),
				'type'        => 'integer',
				'default'     => 20,
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
				'enum'        => [ 'submitted', 'grading', 'graded', 'returned', '' ],
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
				'enum'        => [ 'submitted_at', 'status', 'score', 'student_name' ],
			],
			'order'         => [
				'description' => __( 'Sort direction.', 'pressprimer-assignment' ),
				'type'        => 'string',
				'default'     => 'DESC',
				'enum'        => [ 'ASC', 'DESC' ],
			],
		];
	}

	/**
	 * Get list of submissions
	 *
	 * Retrieves submissions with filters, pagination, and
	 * enriched user data. Supports per-assignment filtering.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function get_items( $request ) {
		global $wpdb;

		$sub_table = $wpdb->prefix . 'ppa_submissions';
		$asg_table = $wpdb->prefix . 'ppa_assignments';

		$assignment_id = absint( $request->get_param( 'assignment_id' ) );
		$status        = sanitize_text_field( $request->get_param( 'status' ) ?? '' );
		$search        = sanitize_text_field( $request->get_param( 'search' ) ?? '' );
		$orderby       = sanitize_text_field( $request->get_param( 'orderby' ) ?? 'submitted_at' );
		$order         = sanitize_text_field( $request->get_param( 'order' ) ?? 'DESC' );
		$per_page      = min( absint( $request->get_param( 'per_page' ) ) ?: 20, 100 );
		$page          = max( 1, absint( $request->get_param( 'page' ) ) ?: 1 );
		$offset        = ( $page - 1 ) * $per_page;

		// Scope to current user's assignments for manage_own users.
		$author_id = $this->get_author_id();

		// Build WHERE.
		$where_clauses = [ 's.status != %s' ];
		$where_params  = [ 'draft' ];

		if ( null !== $author_id ) {
			$where_clauses[] = 'a.author_id = %d';
			$where_params[]  = $author_id;
		}

		if ( $assignment_id > 0 ) {
			$where_clauses[] = 's.assignment_id = %d';
			$where_params[]  = $assignment_id;
		}

		$valid_statuses = [ 'submitted', 'grading', 'graded', 'returned' ];
		if ( ! empty( $status ) && in_array( $status, $valid_statuses, true ) ) {
			$where_clauses[] = 's.status = %s';
			$where_params[]  = $status;
		}

		// Search by student name/email.
		if ( ! empty( $search ) ) {
			$like_term       = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clauses[] = '(u.display_name LIKE %s OR u.user_email LIKE %s)';
			$where_params[]  = $like_term;
			$where_params[]  = $like_term;
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );

		// Validate orderby.
		$orderby_key = isset( self::$allowed_orderby[ $orderby ] ) ? $orderby : 'submitted_at';
		$orderby_col = self::$allowed_orderby[ $orderby_key ];
		$is_asc      = 'ASC' === strtoupper( $order );

		// Count query.
		$count_params = $where_params;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Array param count is dynamic.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$sub_table} s LEFT JOIN {$asg_table} a ON s.assignment_id = a.id LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID {$where_sql}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from $wpdb->prefix, WHERE built with placeholders.
				$count_params
			)
		);

		// Data query.
		$select_fields = 's.id, s.uuid, s.assignment_id, s.user_id, s.status,
			s.submitted_at, s.graded_at, s.returned_at, s.created_at,
			s.submission_number, s.score, s.passed, s.file_count,
			s.text_content,
			a.title AS assignment_title, a.max_points, a.passing_score,
			u.display_name AS student_name, u.user_email AS student_email';

		$limit_params = array_merge( $where_params, [ $per_page, $offset ] );

		if ( $is_asc ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Array param count is dynamic.
				$wpdb->prepare(
					"SELECT {$select_fields} FROM {$sub_table} s LEFT JOIN {$asg_table} a ON s.assignment_id = a.id LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID {$where_sql} ORDER BY {$orderby_col} ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from $wpdb->prefix, columns from validated whitelist.
					$limit_params
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Array param count is dynamic.
				$wpdb->prepare(
					"SELECT {$select_fields} FROM {$sub_table} s LEFT JOIN {$asg_table} a ON s.assignment_id = a.id LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID {$where_sql} ORDER BY {$orderby_col} DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from $wpdb->prefix, columns from validated whitelist.
					$limit_params
				)
			);
		}

		if ( empty( $rows ) ) {
			$rows = [];
		}

		// Format rows.
		$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$items       = array_map(
			function ( $row ) use ( $date_format ) {
				$date_value = ! empty( $row->submitted_at ) ? $row->submitted_at : $row->created_at;

				return [
					'id'                => (int) $row->id,
					'uuid'              => $row->uuid,
					'assignment_id'     => (int) $row->assignment_id,
					'user_id'           => (int) $row->user_id,
					'status'            => $row->status,
					'submission_number' => (int) $row->submission_number,
					'score'             => null !== $row->score ? (float) $row->score : null,
					'passed'            => null !== $row->passed ? (bool) $row->passed : null,
					'file_count'        => (int) $row->file_count,
					'has_text'          => ! empty( $row->text_content ),
					'submitted_at'      => $row->submitted_at,
					'graded_at'         => $row->graded_at,
					'returned_at'       => $row->returned_at,
					'formatted_date'    => $date_value ? wp_date( $date_format, strtotime( $date_value ) ) : '',
					'time_ago'          => $date_value
						? human_time_diff( strtotime( $date_value ), current_time( 'timestamp' ) )
						: '',
					'student_name'      => $row->student_name ?: __( 'Unknown', 'pressprimer-assignment' ),
					'student_email'     => $row->student_email ?: '',
					'assignment_title'  => $row->assignment_title ?: '',
					'max_points'        => (float) $row->max_points,
					'passing_score'     => (float) $row->passing_score,
				];
			},
			$rows
		);

		// Summary stats (calculated from full result set, not just current page).
		$stats = $this->get_summary_stats( $assignment_id, $author_id );

		return rest_ensure_response(
			[
				'items' => $items,
				'total' => $total,
				'page'  => $page,
				'pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
				'stats' => $stats,
			]
		);
	}

	/**
	 * Get single submission with grading context
	 *
	 * Returns the submission along with assignment details,
	 * files, and sibling IDs for prev/next navigation.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function get_item( $request ) {
		$id         = absint( $request->get_param( 'id' ) );
		$submission = PressPrimer_Assignment_Submission::get( $id );

		if ( ! $submission ) {
			return new WP_Error(
				'ppa_not_found',
				__( 'Submission not found.', 'pressprimer-assignment' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! $this->can_access_submission( $submission ) ) {
			return new WP_Error(
				'ppa_forbidden',
				__( 'You do not have permission to view this submission.', 'pressprimer-assignment' ),
				[ 'status' => 403 ]
			);
		}

		$assignment = $submission->get_assignment();
		$files      = $submission->get_files();
		$user       = get_userdata( $submission->user_id );

		$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		// Get sibling submission IDs for navigation.
		$siblings = $this->get_siblings( $submission );

		// Format files for response.
		$file_data = [];
		foreach ( $files as $file ) {
			$file_data[] = [
				'id'                => (int) $file->id,
				'original_filename' => $file->original_filename,
				'file_size'         => (int) $file->file_size,
				'file_extension'    => $file->file_extension,
				'mime_type'         => $file->mime_type,
				'download_url'      => $this->get_file_download_url( $file ),
			];
		}

		return rest_ensure_response(
			[
				'submission' => [
					'id'                    => (int) $submission->id,
					'uuid'                  => $submission->uuid,
					'assignment_id'         => (int) $submission->assignment_id,
					'status'                => $submission->status,
					'submitted_at'          => $submission->submitted_at,
					'graded_at'             => $submission->graded_at,
					'returned_at'           => $submission->returned_at,
					'formatted_date'        => $submission->submitted_at
						? wp_date( $date_format, strtotime( $submission->submitted_at ) )
						: '',
					'formatted_graded_at'   => $submission->graded_at
						? wp_date( $date_format, strtotime( $submission->graded_at ) )
						: '',
					'formatted_returned_at' => $submission->returned_at
						? wp_date( $date_format, strtotime( $submission->returned_at ) )
						: '',
					'submission_number'     => (int) $submission->submission_number,
					'score'                 => null !== $submission->score ? (float) $submission->score : null,
					'feedback'              => $submission->feedback,
					'passed'                => null !== $submission->passed ? (bool) $submission->passed : null,
					'student_name'          => $user ? $user->display_name : __( 'Unknown', 'pressprimer-assignment' ),
					'student_email'         => $user ? $user->user_email : '',
					'student_notes'         => $submission->student_notes,
					'text_content'          => $submission->text_content,
					'word_count'            => $submission->word_count ? (int) $submission->word_count : null,
					'file_count'            => (int) $submission->file_count,
					'grader_id'             => $submission->grader_id ? (int) $submission->grader_id : null,
				],
				'assignment' => $assignment ? [
					'id'                 => (int) $assignment->id,
					'title'              => $assignment->title,
					'description'        => $assignment->description,
					'instructions'       => $assignment->instructions,
					'max_points'         => (float) $assignment->max_points,
					'passing_score'      => (float) $assignment->passing_score,
					'grading_guidelines' => $assignment->grading_guidelines,
				] : null,
				'files'      => $file_data,
				'siblings'   => $siblings,
			]
		);
	}

	/**
	 * Update submission grading data
	 *
	 * Allows setting score, feedback, and status. Uses the
	 * grading service for grade operations and return operations.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function update_item( $request ) {
		$id         = absint( $request->get_param( 'id' ) );
		$submission = PressPrimer_Assignment_Submission::get( $id );

		if ( ! $submission ) {
			return new WP_Error(
				'ppa_not_found',
				__( 'Submission not found.', 'pressprimer-assignment' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! $this->can_access_submission( $submission ) ) {
			return new WP_Error(
				'ppa_forbidden',
				__( 'You do not have permission to grade this submission.', 'pressprimer-assignment' ),
				[ 'status' => 403 ]
			);
		}

		$new_status = sanitize_text_field( $request->get_param( 'status' ) ?? '' );
		$score      = $request->get_param( 'score' );
		$feedback   = $request->get_param( 'feedback' );

		// Handle status change to 'grading' (admin opened the submission).
		if ( 'grading' === $new_status && 'submitted' === $submission->status ) {
			$submission->status = PressPrimer_Assignment_Submission::STATUS_GRADING;
			$result             = $submission->save();

			if ( is_wp_error( $result ) ) {
				return new WP_Error(
					$result->get_error_code(),
					$result->get_error_message(),
					[ 'status' => 500 ]
				);
			}

			return rest_ensure_response( [ 'status' => 'grading' ] );
		}

		// Handle grading (score provided).
		if ( null !== $score ) {
			$feedback_text = null !== $feedback ? wp_kses_post( $feedback ) : '';

			$grading_service = new PressPrimer_Assignment_Grading_Service();
			$result          = $grading_service->grade( $id, floatval( $score ), $feedback_text );

			if ( is_wp_error( $result ) ) {
				return new WP_Error(
					$result->get_error_code(),
					$result->get_error_message(),
					[ 'status' => 400 ]
				);
			}

			// If status is 'returned', also return to student.
			if ( 'returned' === $new_status ) {
				$return_result = $grading_service->return_submission( $id );

				if ( is_wp_error( $return_result ) ) {
					return new WP_Error(
						$return_result->get_error_code(),
						$return_result->get_error_message(),
						[ 'status' => 400 ]
					);
				}

				$result['status'] = 'returned';
			}

			return rest_ensure_response( $result );
		}

		// Handle return without re-grading.
		if ( 'returned' === $new_status ) {
			$grading_service = new PressPrimer_Assignment_Grading_Service();
			$result          = $grading_service->return_submission( $id );

			if ( is_wp_error( $result ) ) {
				return new WP_Error(
					$result->get_error_code(),
					$result->get_error_message(),
					[ 'status' => 400 ]
				);
			}

			return rest_ensure_response( [ 'status' => 'returned' ] );
		}

		// Handle feedback-only update (no score change).
		if ( null !== $feedback ) {
			$submission->feedback = wp_kses_post( $feedback );
			$result               = $submission->save();

			if ( is_wp_error( $result ) ) {
				return new WP_Error(
					$result->get_error_code(),
					$result->get_error_message(),
					[ 'status' => 500 ]
				);
			}

			return rest_ensure_response( [ 'status' => $submission->status ] );
		}

		return new WP_Error(
			'ppa_no_changes',
			__( 'No valid changes provided.', 'pressprimer-assignment' ),
			[ 'status' => 400 ]
		);
	}

	/**
	 * Bulk return submissions to students
	 *
	 * Returns multiple graded submissions at once.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function bulk_return( $request ) {
		$ids = $request->get_param( 'ids' );

		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return new WP_Error(
				'ppa_invalid_ids',
				__( 'No submission IDs provided.', 'pressprimer-assignment' ),
				[ 'status' => 400 ]
			);
		}

		$ids             = array_map( 'absint', $ids );
		$grading_service = new PressPrimer_Assignment_Grading_Service();
		$returned        = 0;
		$errors          = [];

		foreach ( $ids as $submission_id ) {
			$submission = PressPrimer_Assignment_Submission::get( $submission_id );

			if ( ! $submission ) {
				$errors[] = [
					'id'      => $submission_id,
					'message' => __( 'Submission not found.', 'pressprimer-assignment' ),
				];
				continue;
			}

			if ( ! $this->can_access_submission( $submission ) ) {
				$errors[] = [
					'id'      => $submission_id,
					'message' => __( 'You do not have permission to return this submission.', 'pressprimer-assignment' ),
				];
				continue;
			}

			$result = $grading_service->return_submission( $submission_id );

			if ( is_wp_error( $result ) ) {
				$errors[] = [
					'id'      => $submission_id,
					'message' => $result->get_error_message(),
				];
			} else {
				++$returned;
			}
		}

		return rest_ensure_response(
			[
				'returned' => $returned,
				'errors'   => $errors,
			]
		);
	}

	/**
	 * Get summary stats for an assignment's submissions
	 *
	 * Returns count breakdown by status for the filter header.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $assignment_id Assignment ID (0 for all).
	 * @param int|null $author_id    Author ID for ownership scoping, or null for all.
	 * @return array Stats array with total, pending, grading, graded, returned counts.
	 */
	private function get_summary_stats( $assignment_id, $author_id = null ) {
		global $wpdb;

		$table     = $wpdb->prefix . 'ppa_submissions';
		$asg_table = $wpdb->prefix . 'ppa_assignments';

		// Build WHERE with params array.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$where  = 'WHERE s.status != %s';
		$params = [ 'draft' ];

		if ( $assignment_id > 0 ) {
			$where   .= ' AND s.assignment_id = %d';
			$params[] = $assignment_id;
		}

		if ( null !== $author_id ) {
			$where   .= ' AND a.author_id = %d';
			$params[] = $author_id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Array param count is dynamic.
			$wpdb->prepare(
				"SELECT s.status, COUNT(*) AS cnt FROM {$table} s JOIN {$asg_table} a ON s.assignment_id = a.id {$where} GROUP BY s.status", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$params
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$stats = [
			'total'     => 0,
			'submitted' => 0,
			'grading'   => 0,
			'graded'    => 0,
			'returned'  => 0,
		];

		if ( $rows ) {
			foreach ( $rows as $row ) {
				$count           = (int) $row->cnt;
				$stats['total'] += $count;

				if ( isset( $stats[ $row->status ] ) ) {
					$stats[ $row->status ] = $count;
				}
			}
		}

		return $stats;
	}

	/**
	 * Get sibling submission IDs for navigation
	 *
	 * Returns the previous and next submission IDs within
	 * the same assignment, ordered by submitted_at.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Submission $submission Current submission.
	 * @return array Array with prev and next keys.
	 */
	private function get_siblings( $submission ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ppa_submissions';

		// Get previous (submitted before current, excluding drafts).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$prev = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE assignment_id = %d AND status != %s AND id < %d ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				$submission->assignment_id,
				'draft',
				$submission->id
			)
		);

		// Get next (submitted after current, excluding drafts).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$next = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE assignment_id = %d AND status != %s AND id > %d ORDER BY id ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				$submission->assignment_id,
				'draft',
				$submission->id
			)
		);

		return [
			'prev' => $prev ? (int) $prev : null,
			'next' => $next ? (int) $next : null,
		];
	}

	/**
	 * Serve file content inline for the grading interface
	 *
	 * Streams the file with appropriate Content-Type for inline viewing
	 * (images, PDFs, text files). Used by the React document viewers.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error Error on failure (exits on success).
	 */
	public function serve_file_content( $request ) {
		$file_id = absint( $request->get_param( 'id' ) );

		$file = PressPrimer_Assignment_Submission_File::get( $file_id );
		if ( ! $file ) {
			return new WP_Error(
				'ppa_file_not_found',
				__( 'File not found.', 'pressprimer-assignment' ),
				[ 'status' => 404 ]
			);
		}

		// Verify the file's submission belongs to a user-accessible assignment.
		$submission = PressPrimer_Assignment_Submission::get( $file->submission_id );
		if ( ! $submission || ! $this->can_access_submission( $submission ) ) {
			return new WP_Error(
				'ppa_forbidden',
				__( 'You do not have permission to view this file.', 'pressprimer-assignment' ),
				[ 'status' => 403 ]
			);
		}

		// Verify the file exists on disk.
		$full_path = $file->get_full_path();

		if ( ! file_exists( $full_path ) ) {
			return new WP_Error(
				'ppa_file_missing',
				__( 'File not found on server.', 'pressprimer-assignment' ),
				[ 'status' => 404 ]
			);
		}

		// Send inline headers (no Content-Disposition: attachment).
		nocache_headers();
		header( 'Content-Type: ' . $file->mime_type );
		header( 'Content-Length: ' . $file->file_size );
		header( 'Content-Disposition: inline; filename="' . $file->original_filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Serving file for inline viewing.
		readfile( $full_path );
		exit;
	}

	/**
	 * Get file download URL
	 *
	 * Builds the REST API URL for serving file content inline.
	 * Used by the grading interface document viewers.
	 *
	 * @since 1.0.0
	 *
	 * @param object $file Submission file instance.
	 * @return string File content URL.
	 */
	private function get_file_download_url( $file ) {
		return rest_url( self::API_NAMESPACE . '/files/' . $file->id . '/content' );
	}
}
