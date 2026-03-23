<?php
/**
 * Grading queue service
 *
 * Provides data access for the cross-assignment grading queue,
 * including pending submission retrieval with filters, badge count,
 * and sequential grading navigation.
 *
 * @package PressPrimer_Assignment
 * @subpackage Services
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Grading queue service class
 *
 * Retrieves submissions awaiting grading across all assignments,
 * respecting teacher ownership filters for the Educator addon.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Grading_Queue_Service {

	/**
	 * Allowed order-by fields mapping
	 *
	 * Maps API parameter names to safe SQL column references.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $allowed_orderby = [
		'submitted_at'     => 's.submitted_at',
		'assignment_title' => 'a.title',
		'status'           => 's.status',
	];

	/**
	 * Get pending submissions for grading queue
	 *
	 * Retrieves submissions with status "submitted" or "grading"
	 * across all accessible assignments. Supports filtering by
	 * assignment, status, and student search.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     Query arguments.
	 *
	 *     @type int|null $assignment_id Filter by assignment ID.
	 *     @type array    $status        Status filter. Default ['submitted', 'grading'].
	 *     @type string   $search        Search by student name or email.
	 *     @type string   $orderby       Order by field. Default 'submitted_at'.
	 *     @type string   $order         Sort direction. Default 'ASC'.
	 *     @type int      $per_page      Items per page. Default 50.
	 *     @type int      $page          Page number. Default 1.
	 * }
	 * @return array {
	 *     @type array $items Array of submission objects.
	 *     @type int   $total Total count of matching submissions.
	 *     @type int   $page  Current page.
	 *     @type int   $pages Total pages.
	 * }
	 */
	public static function get_queue( $args = [] ) {
		global $wpdb;

		$defaults = [
			'assignment_id' => null,
			'status'        => [ 'submitted', 'grading' ],
			'search'        => '',
			'orderby'       => 'submitted_at',
			'order'         => 'ASC',
			'per_page'      => 50,
			'page'          => 1,
		];

		$args = wp_parse_args( $args, $defaults );

		$submissions_table = $wpdb->prefix . 'ppa_submissions';
		$assignments_table = $wpdb->prefix . 'ppa_assignments';

		// Get accessible assignment IDs (respects teacher ownership).
		$assignment_ids = self::get_accessible_assignment_ids();

		if ( empty( $assignment_ids ) ) {
			return [
				'items' => [],
				'total' => 0,
				'page'  => 1,
				'pages' => 0,
			];
		}

		// Build safe IN clause from integer array.
		$id_placeholders = implode( ',', array_fill( 0, count( $assignment_ids ), '%d' ) );

		// Base WHERE params.
		$where_params = $assignment_ids;

		// Status filter.
		$statuses       = (array) $args['status'];
		$valid_statuses = [ 'submitted', 'grading', 'graded', 'returned' ];
		$statuses       = array_intersect( $statuses, $valid_statuses );

		if ( empty( $statuses ) ) {
			$statuses = [ 'submitted', 'grading' ];
		}

		$status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$where_params        = array_merge( $where_params, $statuses );

		$where_sql = "WHERE s.assignment_id IN ({$id_placeholders}) AND s.status IN ({$status_placeholders})";

		// Assignment filter.
		$has_assignment_filter = ! empty( $args['assignment_id'] );
		if ( $has_assignment_filter ) {
			$where_sql     .= ' AND s.assignment_id = %d';
			$where_params[] = absint( $args['assignment_id'] );
		}

		// Only show the most recent submission per user/assignment pair.
		// A subquery finds the latest pending submission ID for each
		// (user_id, assignment_id) group, and we filter the main query
		// to only include those IDs. This avoids grading old submissions
		// when a student has resubmitted.
		$latest_subquery_where  = "WHERE assignment_id IN ({$id_placeholders}) AND status IN ({$status_placeholders})";
		$latest_subquery_params = array_merge( $assignment_ids, $statuses );

		if ( $has_assignment_filter ) {
			$latest_subquery_where   .= ' AND assignment_id = %d';
			$latest_subquery_params[] = absint( $args['assignment_id'] );
		}

		$latest_join   = "JOIN (SELECT MAX(id) AS latest_id FROM {$submissions_table} {$latest_subquery_where} GROUP BY user_id, assignment_id) latest ON s.id = latest.latest_id";
		$latest_params = $latest_subquery_params;

		// Pagination.
		$per_page = min( absint( $args['per_page'] ), 100 );
		$page     = max( 1, absint( $args['page'] ) );
		$offset   = ( $page - 1 ) * $per_page;

		// Validate orderby.
		$orderby_key = isset( self::$allowed_orderby[ $args['orderby'] ] )
			? $args['orderby']
			: 'submitted_at';

		$is_asc = 'ASC' === strtoupper( $args['order'] );

		// Get total count (separate query per CLAUDE.md rules).
		$count_params = array_merge( $where_params, $latest_params );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Array param count is dynamic (assignment IDs + statuses + optional assignment filter + subquery params).
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$submissions_table} s JOIN {$assignments_table} a ON s.assignment_id = a.id {$latest_join} {$where_sql}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from $wpdb->prefix, WHERE and JOIN built with placeholders.
				$count_params
			)
		);

		// Main query with explicit ASC/DESC branches.
		$select_fields = 's.id, s.uuid, s.assignment_id, s.user_id, s.status,
			s.submitted_at, s.created_at, s.submission_number,
			a.title AS assignment_title, a.max_points';

		$limit_params = array_merge( $where_params, $latest_params, [ $per_page, $offset ] );

		$orderby_col = self::$allowed_orderby[ $orderby_key ];

		if ( $is_asc ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Array param count is dynamic (assignment IDs + statuses + optional assignment filter + subquery params + pagination).
				$wpdb->prepare(
					"SELECT {$select_fields} FROM {$submissions_table} s JOIN {$assignments_table} a ON s.assignment_id = a.id {$latest_join} {$where_sql} ORDER BY {$orderby_col} ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from $wpdb->prefix, columns from validated whitelist.
					$limit_params
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Array param count is dynamic (assignment IDs + statuses + optional assignment filter + subquery params + pagination).
				$wpdb->prepare(
					"SELECT {$select_fields} FROM {$submissions_table} s JOIN {$assignments_table} a ON s.assignment_id = a.id {$latest_join} {$where_sql} ORDER BY {$orderby_col} DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from $wpdb->prefix, columns from validated whitelist.
					$limit_params
				)
			);
		}

		if ( empty( $rows ) ) {
			$rows = [];
		}

		// Enrich rows with user data.
		$items = self::enrich_with_user_data( $rows );

		// Post-query search filter (searches user display_name and email).
		if ( ! empty( $args['search'] ) ) {
			$search_lower = strtolower( sanitize_text_field( $args['search'] ) );
			$items        = array_values(
				array_filter(
					$items,
					function ( $item ) use ( $search_lower ) {
						return false !== strpos( strtolower( $item->student_name ), $search_lower )
							|| false !== strpos( strtolower( $item->student_email ), $search_lower );
					}
				)
			);

			// When searching, total reflects filtered count.
			$total = count( $items );
		}

		return [
			'items' => $items,
			'total' => $total,
			'page'  => $page,
			'pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
		];
	}

	/**
	 * Get count of pending submissions for menu badge
	 *
	 * Returns the number of submissions in "submitted" or "grading"
	 * status across all accessible assignments.
	 *
	 * @since 1.0.0
	 *
	 * @return int Pending submission count.
	 */
	public static function get_pending_count() {
		global $wpdb;

		$submissions_table = $wpdb->prefix . 'ppa_submissions';

		$assignment_ids = self::get_accessible_assignment_ids();

		if ( empty( $assignment_ids ) ) {
			return 0;
		}

		$id_placeholders = implode( ',', array_fill( 0, count( $assignment_ids ), '%d' ) );

		// Count only the latest submission per user/assignment pair
		// to match the grading queue display.
		$params = array_merge(
			$assignment_ids,
			[ 'submitted', 'grading' ],
			$assignment_ids,
			[ 'submitted', 'grading' ]
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Array param count is dynamic (assignment IDs + 2 status strings, repeated for subquery).
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$submissions_table} s JOIN (SELECT MAX(id) AS latest_id FROM {$submissions_table} WHERE assignment_id IN ({$id_placeholders}) AND status IN (%s, %s) GROUP BY user_id, assignment_id) latest ON s.id = latest.latest_id WHERE s.assignment_id IN ({$id_placeholders}) AND s.status IN (%s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix, IN clause uses placeholders.
				$params
			)
		);
	}

	/**
	 * Get next submission in queue after grading
	 *
	 * Returns the next oldest pending submission ID, useful for
	 * sequential grading workflow navigation.
	 *
	 * @since 1.0.0
	 *
	 * @param int $current_id Current submission ID being graded.
	 * @return int|null Next submission ID or null if queue is empty.
	 */
	public static function get_next_in_queue( $current_id ) {
		global $wpdb;

		$submissions_table = $wpdb->prefix . 'ppa_submissions';
		$current_id        = absint( $current_id );

		$assignment_ids = self::get_accessible_assignment_ids();

		if ( empty( $assignment_ids ) ) {
			return null;
		}

		$id_placeholders = implode( ',', array_fill( 0, count( $assignment_ids ), '%d' ) );

		// Only consider the latest submission per user/assignment pair.
		$params = array_merge(
			$assignment_ids,
			[ 'submitted', 'grading' ],
			$assignment_ids,
			[ 'submitted', 'grading', $current_id ]
		);

		// Get the next oldest pending submission (FIFO), restricted to
		// the latest submission per user/assignment.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$next_id = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Array param count is dynamic (assignment IDs + statuses for subquery + assignment IDs + statuses + current_id).
			$wpdb->prepare(
				"SELECT s.id FROM {$submissions_table} s JOIN (SELECT MAX(id) AS latest_id FROM {$submissions_table} WHERE assignment_id IN ({$id_placeholders}) AND status IN (%s, %s) GROUP BY user_id, assignment_id) latest ON s.id = latest.latest_id WHERE s.assignment_id IN ({$id_placeholders}) AND s.status IN (%s, %s) AND s.id != %d ORDER BY s.submitted_at ASC, s.created_at ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix, IN clause uses placeholders.
				$params
			)
		);

		return $next_id ? (int) $next_id : null;
	}

	/**
	 * Get accessible assignment IDs for current user
	 *
	 * Returns IDs of all published assignments the current user
	 * can access. Users with manage_all see all assignments.
	 * Users with only manage_own see their own assignments.
	 * The filter hook allows addons to further customize access.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of integer assignment IDs.
	 */
	private static function get_accessible_assignment_ids() {
		global $wpdb;

		$table   = $wpdb->prefix . 'ppa_assignments';
		$user_id = get_current_user_id();

		// Built-in ownership scoping: manage_own users see only their own.
		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE status = 'published' AND author_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table from $wpdb->prefix.
					$user_id
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$ids = $wpdb->get_col(
				"SELECT id FROM {$table} WHERE status = 'published'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table from $wpdb->prefix.
			);
		}

		$ids = array_map( 'intval', $ids );

		/**
		 * Filters the list of accessible assignment IDs.
		 *
		 * Addons (e.g., Educator) can use this to further restrict
		 * which assignments a user can access in the grading queue.
		 *
		 * @since 1.0.0
		 *
		 * @param int[] $ids     Array of assignment IDs.
		 * @param int   $user_id Current user ID.
		 */
		return apply_filters( 'pressprimer_assignment_accessible_assignment_ids', $ids, $user_id );
	}

	/**
	 * Get assignment list for filter dropdown
	 *
	 * Returns a simplified list of accessible assignments for
	 * use in the queue filter UI.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of objects with id and title properties.
	 */
	public static function get_assignments_for_filter() {
		global $wpdb;

		$table = $wpdb->prefix . 'ppa_assignments';

		// Reuse the accessible IDs logic (includes ownership scoping + addon filter).
		$accessible_ids = self::get_accessible_assignment_ids();

		if ( empty( $accessible_ids ) ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $accessible_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title FROM {$table} WHERE id IN ({$placeholders}) ORDER BY title ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table from $wpdb->prefix, $placeholders is a generated string of %d tokens.
				$accessible_ids
			)
		);
	}

	/**
	 * Enrich submission rows with user data
	 *
	 * Adds student name, email, formatted date, and time-ago strings
	 * to each submission row.
	 *
	 * @since 1.0.0
	 *
	 * @param array $rows Array of database row objects.
	 * @return array Enriched row objects.
	 */
	private static function enrich_with_user_data( $rows ) {
		if ( empty( $rows ) ) {
			return [];
		}

		// Collect unique user IDs to batch-load.
		$user_ids = array_unique( array_map( 'intval', wp_list_pluck( $rows, 'user_id' ) ) );

		// Pre-load user data in a single query.
		$users = [];
		foreach ( $user_ids as $uid ) {
			$user_data = get_userdata( $uid );
			if ( $user_data ) {
				$users[ $uid ] = $user_data;
			}
		}

		$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		foreach ( $rows as $row ) {
			$uid  = (int) $row->user_id;
			$user = isset( $users[ $uid ] ) ? $users[ $uid ] : null;

			$row->student_name  = $user ? $user->display_name : __( 'Unknown', 'pressprimer-assignment' );
			$row->student_email = $user ? $user->user_email : '';

			// Use submitted_at if available, fall back to created_at.
			$date_value          = ! empty( $row->submitted_at ) ? $row->submitted_at : $row->created_at;
			$row->formatted_date = $date_value ? wp_date( $date_format, strtotime( $date_value ) ) : '';
			$row->time_ago       = $date_value
				? human_time_diff( strtotime( $date_value ), current_time( 'timestamp' ) )
				: '';

			// Cast numeric fields.
			$row->id                = (int) $row->id;
			$row->assignment_id     = (int) $row->assignment_id;
			$row->user_id           = (int) $row->user_id;
			$row->max_points        = (float) $row->max_points;
			$row->submission_number = (int) $row->submission_number;
		}

		return $rows;
	}
}
