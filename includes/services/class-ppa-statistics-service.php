<?php
/**
 * Statistics service
 *
 * Provides dashboard statistics and chart data for PressPrimer Assignment.
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
 * Statistics service class
 *
 * Queries the database for assignment and submission metrics,
 * caching results with WordPress transients for performance.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Statistics_Service {

	/**
	 * Cache duration for dashboard stats (in seconds)
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const STATS_CACHE_DURATION = 300; // 5 minutes.

	/**
	 * Cache duration for chart data (in seconds)
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const CHART_CACHE_DURATION = 900; // 15 minutes.

	/**
	 * Cache duration for overview stats (in seconds)
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const OVERVIEW_CACHE_DURATION = 3600; // 1 hour.

	/**
	 * Clear dashboard statistics cache
	 *
	 * Call this method when assignments or submissions change
	 * to ensure the dashboard displays fresh data.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $author_id Optional. Clear cache for specific author only.
	 */
	public static function clear_dashboard_cache( $author_id = null ) {
		if ( $author_id ) {
			delete_transient( 'pressprimer_assignment_dashboard_stats_' . absint( $author_id ) );
		}

		// Always clear the global cache.
		delete_transient( 'pressprimer_assignment_dashboard_stats_all' );
	}

	/**
	 * Clear activity chart transient cache
	 *
	 * Deletes all activity chart transients so the next request
	 * fetches fresh data from the database. Called when submissions
	 * are created, graded, or deleted.
	 *
	 * @since 1.0.0
	 */
	public static function clear_activity_chart_cache() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Deleting transients by prefix.
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_pressprimer\_assignment\_chart\_%'
				OR option_name LIKE '_transient_timeout_pressprimer\_assignment\_chart\_%'"
		);
	}

	/**
	 * Clear all statistics caches
	 *
	 * Convenience method to clear both dashboard and chart caches.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $author_id Optional. Author ID for targeted dashboard cache clear.
	 */
	public static function clear_all_caches( $author_id = null ) {
		self::clear_dashboard_cache( $author_id );
		self::clear_activity_chart_cache();
		self::clear_overview_cache();
	}

	/**
	 * Clear overview statistics cache
	 *
	 * Deletes overview stats transients so the next request
	 * fetches fresh data from the database.
	 *
	 * @since 1.0.0
	 */
	public static function clear_overview_cache() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Deleting transients by prefix.
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_pressprimer\_assignment\_overview\_%'
				OR option_name LIKE '_transient_timeout_pressprimer\_assignment\_overview\_%'"
		);
	}

	/**
	 * Get overview statistics for reports page
	 *
	 * Returns all-time aggregate metrics for the reports overview cards.
	 * Results are cached with a 1-hour transient.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $author_id Filter by assignment author (null for all).
	 * @return array Overview statistics.
	 */
	public function get_overview_stats( $author_id = null ) {
		$cache_key = 'pressprimer_assignment_overview_' . ( $author_id ? absint( $author_id ) : 'all' );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$assignments_table = $wpdb->prefix . 'ppa_assignments';
		$submissions_table = $wpdb->prefix . 'ppa_submissions';

		// Build assignment filter.
		$assignment_filter = '';
		$assignment_params = [];

		if ( $author_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$assignment_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$assignments_table} WHERE author_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
					$author_id
				)
			);

			if ( empty( $assignment_ids ) ) {
				$stats = [
					'total_submissions'      => 0,
					'avg_score'              => 0,
					'pass_rate'              => 0,
					'avg_grading_time_hours' => 0,
				];

				set_transient( $cache_key, $stats, self::OVERVIEW_CACHE_DURATION );
				return $stats;
			}

			$id_placeholders   = implode( ',', array_fill( 0, count( $assignment_ids ), '%d' ) );
			$assignment_filter = " AND s.assignment_id IN ({$id_placeholders})";
			$assignment_params = array_map( 'absint', $assignment_ids );
		}

		// Total submissions (non-draft).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_submissions = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic param count from assignment IDs.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$submissions_table} s WHERE s.status != 'draft'{$assignment_filter}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix, filter built with placeholders.
				$assignment_params
			)
		);

		// Average score and pass rate (graded/returned submissions only).
		$score_params = $assignment_params;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$score_row = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic param count from assignment IDs.
			$wpdb->prepare(
				"SELECT
					ROUND(AVG( (s.score / a.max_points) * 100 ), 1) AS avg_score,
					ROUND( (SUM(CASE WHEN s.passed = 1 THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100, 1 ) AS pass_rate
				FROM {$submissions_table} s
				INNER JOIN {$assignments_table} a ON s.assignment_id = a.id
				WHERE s.status IN ('graded', 'returned')
					AND s.score IS NOT NULL
					AND a.max_points > 0
					{$assignment_filter}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix, filter built with placeholders.
				$score_params
			)
		);

		$avg_score = 0;
		$pass_rate = 0;

		if ( $score_row ) {
			$avg_score = $score_row->avg_score ? (float) $score_row->avg_score : 0;
			$pass_rate = $score_row->pass_rate ? (float) $score_row->pass_rate : 0;
		}

		// Average grading turnaround time (submitted_at to graded_at, in hours).
		$time_params = $assignment_params;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$avg_grading_seconds = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic param count from assignment IDs.
			$wpdb->prepare(
				"SELECT ROUND(AVG(TIMESTAMPDIFF(SECOND, s.submitted_at, s.graded_at)))
				FROM {$submissions_table} s
				WHERE s.status IN ('graded', 'returned')
					AND s.submitted_at IS NOT NULL
					AND s.graded_at IS NOT NULL
					AND s.graded_at > s.submitted_at
					{$assignment_filter}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix, filter built with placeholders.
				$time_params
			)
		);

		$avg_grading_hours = $avg_grading_seconds ? round( (float) $avg_grading_seconds / 3600, 1 ) : 0;

		$stats = [
			'total_submissions'      => $total_submissions,
			'avg_score'              => $avg_score,
			'pass_rate'              => $pass_rate,
			'avg_grading_time_hours' => $avg_grading_hours,
		];

		set_transient( $cache_key, $stats, self::OVERVIEW_CACHE_DURATION );

		return $stats;
	}

	/**
	 * Get assignment performance report data
	 *
	 * Returns per-assignment performance metrics with date range filtering,
	 * search, server-side sorting, and pagination. Mirrors Quiz's
	 * get_quiz_performance() method adapted for assignment data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     Query arguments.
	 *
	 *     @type string   $date_from Date range start (YYYY-MM-DD).
	 *     @type string   $date_to   Date range end (YYYY-MM-DD).
	 *     @type string   $search    Search term for assignment title.
	 *     @type string   $orderby   Sort column key.
	 *     @type string   $order     Sort direction (ASC/DESC).
	 *     @type int      $per_page  Items per page.
	 *     @type int      $page      Current page number.
	 *     @type int|null $author_id Filter by assignment author.
	 * }
	 * @return array {
	 *     Report data.
	 *
	 *     @type array $items       Array of assignment performance rows.
	 *     @type int   $total       Total matching assignments.
	 *     @type int   $total_pages Total pages.
	 *     @type int   $page        Current page.
	 * }
	 */
	public function get_assignment_performance( $args = [] ) {
		global $wpdb;

		$defaults = [
			'date_from' => null,
			'date_to'   => null,
			'search'    => '',
			'orderby'   => 'submissions',
			'order'     => 'DESC',
			'per_page'  => 20,
			'page'      => 1,
			'author_id' => null,
		];

		$args = wp_parse_args( $args, $defaults );

		$assignments_table = $wpdb->prefix . 'ppa_assignments';
		$submissions_table = $wpdb->prefix . 'ppa_submissions';

		$where = [ "a.status = 'published'" ];

		// Author filtering (teacher sees own assignments only).
		if ( $args['author_id'] ) {
			$where[] = $wpdb->prepare( 'a.author_id = %d', $args['author_id'] );
		}

		// Search by title.
		if ( ! empty( $args['search'] ) ) {
			$where[] = $wpdb->prepare( 'a.title LIKE %s', '%' . $wpdb->esc_like( $args['search'] ) . '%' );
		}

		$where_sql = implode( ' AND ', $where );

		// Build date filtering for submissions (non-draft only).
		$date_where = "s.status != 'draft'";
		if ( $args['date_from'] ) {
			$date_where .= $wpdb->prepare( ' AND s.submitted_at >= %s', sanitize_text_field( $args['date_from'] ) );
		}
		if ( $args['date_to'] ) {
			$date_where .= $wpdb->prepare( ' AND s.submitted_at <= %s', sanitize_text_field( $args['date_to'] ) . ' 23:59:59' );
		}

		// Build graded-only date filter for score/pass calculations.
		$graded_where = "s.status IN ('graded', 'returned') AND s.score IS NOT NULL AND a.max_points > 0";
		if ( $args['date_from'] ) {
			$graded_where .= $wpdb->prepare( ' AND s.submitted_at >= %s', sanitize_text_field( $args['date_from'] ) );
		}
		if ( $args['date_to'] ) {
			$graded_where .= $wpdb->prepare( ' AND s.submitted_at <= %s', sanitize_text_field( $args['date_to'] ) . ' 23:59:59' );
		}

		// Validate orderby against whitelist.
		$allowed_orderby = [
			'title'            => 'a.title',
			'submissions'      => 'submissions',
			'avg_score'        => 'avg_score',
			'pass_rate'        => 'pass_rate',
			'awaiting_grading' => 'awaiting_grading',
			'avg_grading_time' => 'avg_grading_time',
		];

		$orderby_column = isset( $allowed_orderby[ $args['orderby'] ] ) ? $allowed_orderby[ $args['orderby'] ] : 'submissions';
		$order          = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Build ORDER BY with sanitize_sql_orderby.
		$order_sql = sanitize_sql_orderby( "{$orderby_column} {$order}" );
		$order_sql = $order_sql ? "ORDER BY {$order_sql}" : 'ORDER BY submissions DESC';

		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		// Main query: LEFT JOIN to include assignments with zero submissions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic report queries with pagination not suitable for caching.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					a.id,
					a.title,
					COUNT(CASE WHEN {$date_where} THEN s.id END) AS submissions,
					ROUND(AVG(CASE WHEN {$graded_where} THEN (s.score / a.max_points) * 100 END), 1) AS avg_score,
					ROUND(
						(SUM(CASE WHEN {$graded_where} AND s.passed = 1 THEN 1 ELSE 0 END) /
						NULLIF(COUNT(CASE WHEN {$graded_where} THEN s.id END), 0)) * 100,
					1) AS pass_rate,
					COUNT(CASE WHEN s.status IN ('submitted', 'grading') THEN s.id END) AS awaiting_grading,
					ROUND(AVG(
						CASE WHEN {$graded_where}
							AND s.submitted_at IS NOT NULL
							AND s.graded_at IS NOT NULL
							AND s.graded_at > s.submitted_at
							THEN TIMESTAMPDIFF(SECOND, s.submitted_at, s.graded_at)
						END
					)) AS avg_grading_time
				FROM {$assignments_table} a
				LEFT JOIN {$submissions_table} s ON a.id = s.assignment_id
				WHERE {$where_sql}
				GROUP BY a.id
				{$order_sql}
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and validated clauses safely constructed.
				$args['per_page'],
				$offset
			)
		);

		// Get total count of matching assignments.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic report queries.
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$assignments_table} a WHERE {$where_sql}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and validated where clause.
		);

		// Format results.
		$items = [];
		if ( $results ) {
			foreach ( $results as $row ) {
				$items[] = [
					'id'               => (int) $row->id,
					'title'            => $row->title,
					'submissions'      => (int) $row->submissions,
					'avg_score'        => null !== $row->avg_score ? (float) $row->avg_score : null,
					'pass_rate'        => null !== $row->pass_rate ? (float) $row->pass_rate : null,
					'awaiting_grading' => (int) $row->awaiting_grading,
					'avg_grading_time' => null !== $row->avg_grading_time ? (int) $row->avg_grading_time : null,
				];
			}
		}

		return [
			'items'       => $items,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $args['per_page'] ),
			'page'        => $args['page'],
		];
	}

	/**
	 * Get dashboard statistics
	 *
	 * Returns summary metrics for the dashboard page.
	 * Results are cached with a 5-minute transient.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $author_id Filter by assignment author (null for all).
	 * @return array Dashboard statistics.
	 */
	public function get_dashboard_stats( $author_id = null ) {
		$cache_key = 'pressprimer_assignment_dashboard_stats_' . ( $author_id ? absint( $author_id ) : 'all' );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$assignments_table = $wpdb->prefix . 'ppa_assignments';
		$submissions_table = $wpdb->prefix . 'ppa_submissions';

		$seven_days_ago  = gmdate( 'Y-m-d H:i:s', time() - ( 7 * DAY_IN_SECONDS ) );
		$thirty_days_ago = gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) );

		// --- Total published assignments ---
		if ( $author_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$total_assignments = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$assignments_table} WHERE status = 'published' AND author_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
					$author_id
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$total_assignments = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$assignments_table} WHERE status = 'published'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
			);
		}

		// --- Build assignment ID filter for submissions queries ---
		$assignment_filter = '';
		$assignment_params = [];

		if ( $author_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$assignment_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$assignments_table} WHERE author_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
					$author_id
				)
			);

			if ( empty( $assignment_ids ) ) {
				// No assignments found — return zeros.
				$stats = [
					'total_assignments'   => $total_assignments,
					'recent_submissions'  => 0,
					'awaiting_grading'    => 0,
					'recent_graded'       => 0,
					'recent_avg_score'    => 0,
					'recent_return_rate'  => 0,
					'popular_assignments' => [],
				];

				set_transient( $cache_key, $stats, self::STATS_CACHE_DURATION );
				return $stats;
			}

			$id_placeholders   = implode( ',', array_fill( 0, count( $assignment_ids ), '%d' ) );
			$assignment_filter = " AND s.assignment_id IN ({$id_placeholders})";
			$assignment_params = array_map( 'absint', $assignment_ids );
		}

		// --- Recent submissions (7 days) ---
		$recent_params = $assignment_params;
		$recent_where  = "WHERE s.status != 'draft' AND s.submitted_at >= %s" . $assignment_filter;

		$recent_params[] = $seven_days_ago;

		// Reorder: submitted_at param must come after assignment_id params in the WHERE clause.
		// Actually, the WHERE has assignment_filter after submitted_at, so params order = [submitted_at, ...assignment_ids].
		// Let's fix the order: WHERE clause has s.submitted_at >= %s first, then AND s.assignment_id IN (...).
		$recent_params_ordered   = [];
		$recent_params_ordered[] = $seven_days_ago;
		$recent_params_ordered   = array_merge( $recent_params_ordered, $assignment_params );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$recent_submissions = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic param count from assignment IDs.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$submissions_table} s WHERE s.status != 'draft' AND s.submitted_at >= %s{$assignment_filter}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix, filter built with placeholders.
				$recent_params_ordered
			)
		);

		// --- Awaiting grading (submitted + grading status) ---
		$awaiting_params = $assignment_params;

		if ( $author_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$awaiting_grading = (int) $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic param count from assignment IDs.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$submissions_table} s WHERE s.status IN ('submitted', 'grading'){$assignment_filter}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix, filter built with placeholders.
					$awaiting_params
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$awaiting_grading = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$submissions_table} s WHERE s.status IN ('submitted', 'grading')" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix.
			);
		}

		// --- Graded in last 7 days ---
		$graded_params   = [];
		$graded_params[] = $seven_days_ago;
		$graded_params   = array_merge( $graded_params, $assignment_params );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$recent_graded = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic param count from assignment IDs.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$submissions_table} s WHERE s.status IN ('graded', 'returned') AND s.graded_at >= %s{$assignment_filter}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix, filter built with placeholders.
				$graded_params
			)
		);

		// --- Average score (7 days) ---
		$avg_params   = [];
		$avg_params[] = $seven_days_ago;
		$avg_params   = array_merge( $avg_params, $assignment_params );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$avg_row = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic param count from assignment IDs.
			$wpdb->prepare(
				"SELECT AVG(s.score) AS avg_score,
					COUNT(*) AS total_graded,
					SUM(CASE WHEN s.status = 'returned' THEN 1 ELSE 0 END) AS total_returned
				FROM {$submissions_table} s
				INNER JOIN {$assignments_table} a ON s.assignment_id = a.id
				WHERE s.status IN ('graded', 'returned')
					AND s.graded_at >= %s
					AND s.score IS NOT NULL
					AND a.max_points > 0
					{$assignment_filter}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix, filter built with placeholders.
				$avg_params
			)
		);

		$recent_avg_score   = 0;
		$recent_return_rate = 0;

		if ( $avg_row && $avg_row->total_graded > 0 ) {
			// Calculate average as percentage of max_points.
			// We need per-submission percentage, so use a different query.
			$pct_params   = [];
			$pct_params[] = $seven_days_ago;
			$pct_params   = array_merge( $pct_params, $assignment_params );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$avg_pct = $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic param count from assignment IDs.
				$wpdb->prepare(
					"SELECT ROUND(AVG( (s.score / a.max_points) * 100 ), 1)
					FROM {$submissions_table} s
					INNER JOIN {$assignments_table} a ON s.assignment_id = a.id
					WHERE s.status IN ('graded', 'returned')
						AND s.graded_at >= %s
						AND s.score IS NOT NULL
						AND a.max_points > 0
						{$assignment_filter}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix, filter built with placeholders.
					$pct_params
				)
			);

			$recent_avg_score = $avg_pct ? (float) $avg_pct : 0;

			$recent_return_rate = round(
				( (int) $avg_row->total_returned / (int) $avg_row->total_graded ) * 100,
				1
			);
		}

		// --- Popular assignments (top 5 by submissions in last 30 days) ---
		$popular_params   = [];
		$popular_params[] = $thirty_days_ago;
		$popular_params   = array_merge( $popular_params, $assignment_params );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$popular_assignments = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic param count from assignment IDs.
			$wpdb->prepare(
				"SELECT a.id, a.title, COUNT(s.id) AS submission_count
				FROM {$assignments_table} a
				INNER JOIN {$submissions_table} s ON a.id = s.assignment_id
				WHERE s.status != 'draft'
					AND s.submitted_at >= %s
					{$assignment_filter}
				GROUP BY a.id, a.title
				ORDER BY submission_count DESC
				LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix, filter built with placeholders.
				$popular_params
			)
		);

		// Format popular assignments.
		$popular = [];
		if ( $popular_assignments ) {
			foreach ( $popular_assignments as $row ) {
				$popular[] = [
					'id'               => (int) $row->id,
					'title'            => $row->title,
					'submission_count' => (int) $row->submission_count,
				];
			}
		}

		$stats = [
			'total_assignments'   => $total_assignments,
			'recent_submissions'  => $recent_submissions,
			'awaiting_grading'    => $awaiting_grading,
			'recent_graded'       => $recent_graded,
			'recent_avg_score'    => $recent_avg_score,
			'recent_return_rate'  => $recent_return_rate,
			'popular_assignments' => $popular,
		];

		set_transient( $cache_key, $stats, self::STATS_CACHE_DURATION );

		return $stats;
	}

	/**
	 * Get activity chart data
	 *
	 * Returns daily aggregated submission and score data for the dashboard chart.
	 * Fills in complete date range with zeros for missing days.
	 * Results are cached with a 15-minute transient.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     Optional arguments.
	 *
	 *     @type int      $days      Number of days to include (default 90, max 730).
	 *     @type int|null $author_id Filter by assignment author.
	 * }
	 * @return array {
	 *     Chart data.
	 *
	 *     @type array $data Array of daily data points with date, submissions, avg_score.
	 * }
	 */
	public function get_activity_chart_data( $args = [] ) {
		$days      = isset( $args['days'] ) ? min( absint( $args['days'] ), 730 ) : 90;
		$author_id = isset( $args['author_id'] ) ? $args['author_id'] : null;

		$cache_key = 'pressprimer_assignment_chart_' . $days . '_' . ( $author_id ? absint( $author_id ) : 'all' );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$assignments_table = $wpdb->prefix . 'ppa_assignments';
		$submissions_table = $wpdb->prefix . 'ppa_submissions';

		$end_date   = gmdate( 'Y-m-d' );
		$start_date = gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );

		// Build assignment filter.
		$assignment_filter = '';
		$assignment_params = [];

		if ( $author_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$assignment_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$assignments_table} WHERE author_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
					$author_id
				)
			);

			if ( empty( $assignment_ids ) ) {
				$result = [
					'data'       => $this->fill_date_range( $start_date, $end_date ),
					'start_date' => $start_date,
					'end_date'   => $end_date,
					'days'       => $days,
				];
				set_transient( $cache_key, $result, self::CHART_CACHE_DURATION );
				return $result;
			}

			$id_placeholders   = implode( ',', array_fill( 0, count( $assignment_ids ), '%d' ) );
			$assignment_filter = " AND s.assignment_id IN ({$id_placeholders})";
			$assignment_params = array_map( 'absint', $assignment_ids );
		}

		$query_params   = [];
		$query_params[] = $start_date;
		$query_params[] = $end_date;
		$query_params   = array_merge( $query_params, $assignment_params );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic param count from assignment IDs.
			$wpdb->prepare(
				"SELECT DATE(s.submitted_at) AS date,
					COUNT(*) AS submissions,
					ROUND(AVG(
						CASE WHEN s.score IS NOT NULL AND a.max_points > 0
							THEN (s.score / a.max_points) * 100
							ELSE NULL
						END
					), 1) AS avg_score
				FROM {$submissions_table} s
				INNER JOIN {$assignments_table} a ON s.assignment_id = a.id
				WHERE s.status != 'draft'
					AND DATE(s.submitted_at) >= %s
					AND DATE(s.submitted_at) <= %s
					{$assignment_filter}
				GROUP BY DATE(s.submitted_at)
				ORDER BY date ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix, filter built with placeholders.
				$query_params
			)
		);

		// Index results by date.
		$data_by_date = [];
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$data_by_date[ $row->date ] = [
					'submissions' => (int) $row->submissions,
					'avg_score'   => $row->avg_score !== null ? (float) $row->avg_score : null,
				];
			}
		}

		// Fill complete date range.
		$data = $this->fill_date_range( $start_date, $end_date, $data_by_date );

		$result = [
			'data'       => $data,
			'start_date' => $start_date,
			'end_date'   => $end_date,
			'days'       => $days,
		];
		set_transient( $cache_key, $result, self::CHART_CACHE_DURATION );

		return $result;
	}

	/**
	 * Get recent submissions
	 *
	 * Returns the most recent submissions for the dashboard activity table.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     Optional arguments.
	 *
	 *     @type int      $per_page  Number of items to return (default 10, max 50).
	 *     @type int|null $author_id Filter by assignment author.
	 * }
	 * @return array {
	 *     @type array $items Array of recent submission data.
	 * }
	 */
	public function get_recent_submissions( $args = [] ) {
		$per_page  = isset( $args['per_page'] ) ? min( absint( $args['per_page'] ), 50 ) : 10;
		$author_id = isset( $args['author_id'] ) ? $args['author_id'] : null;

		global $wpdb;

		$assignments_table = $wpdb->prefix . 'ppa_assignments';
		$submissions_table = $wpdb->prefix . 'ppa_submissions';

		// Build assignment filter.
		$assignment_filter = '';
		$assignment_params = [];

		if ( $author_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$assignment_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$assignments_table} WHERE author_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
					$author_id
				)
			);

			if ( empty( $assignment_ids ) ) {
				return [ 'items' => [] ];
			}

			$id_placeholders   = implode( ',', array_fill( 0, count( $assignment_ids ), '%d' ) );
			$assignment_filter = " AND s.assignment_id IN ({$id_placeholders})";
			$assignment_params = array_map( 'absint', $assignment_ids );
		}

		$query_params   = $assignment_params;
		$query_params[] = $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic param count from assignment IDs + per_page.
			$wpdb->prepare(
				"SELECT s.id, s.assignment_id, s.user_id, s.status,
					s.score, s.submitted_at, s.text_content, s.file_count,
					a.title AS assignment_title, a.max_points
				FROM {$submissions_table} s
				INNER JOIN {$assignments_table} a ON s.assignment_id = a.id
				WHERE s.status != 'draft'
					{$assignment_filter}
				ORDER BY s.submitted_at DESC
				LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix, filter built with placeholders.
				$query_params
			)
		);

		$items = [];

		if ( $rows ) {
			foreach ( $rows as $row ) {
				$user = get_userdata( (int) $row->user_id );

				$score_percent = null;
				$passed        = null;
				if ( null !== $row->score && (float) $row->max_points > 0 ) {
					$score_percent = round( ( (float) $row->score / (float) $row->max_points ) * 100, 1 );
					$passed        = (float) $row->score >= ( (float) $row->max_points * 0.6 ); // Default 60% pass.
				}

				// Determine submission type.
				$submission_type = 'file';
				if ( ! empty( $row->text_content ) ) {
					$submission_type = 'text';
				} elseif ( (int) $row->file_count === 0 && empty( $row->text_content ) ) {
					$submission_type = 'file'; // Default fallback.
				}

				$items[] = [
					'id'               => (int) $row->id,
					'assignment_id'    => (int) $row->assignment_id,
					'assignment_title' => $row->assignment_title,
					'user_id'          => (int) $row->user_id,
					'student_name'     => $user ? $user->display_name : __( 'Unknown', 'pressprimer-assignment' ),
					'score'            => $row->score !== null ? (float) $row->score : null,
					'max_points'       => (float) $row->max_points,
					'score_percent'    => $score_percent,
					'passed'           => $passed,
					'status'           => $row->status,
					'submission_type'  => $submission_type,
					'submitted_at'     => $row->submitted_at,
				];
			}
		}

		return [ 'items' => $items ];
	}

	/**
	 * Fill a date range with data
	 *
	 * Creates an array of daily data points for the given range,
	 * filling in zeros for dates with no data.
	 *
	 * @since 1.0.0
	 *
	 * @param string $start_date  Start date (YYYY-MM-DD).
	 * @param string $end_date    End date (YYYY-MM-DD).
	 * @param array  $data_by_date Existing data indexed by date.
	 * @return array Array of data points.
	 */
	private function fill_date_range( $start_date, $end_date, $data_by_date = [] ) {
		$data    = [];
		$current = new DateTime( $start_date, new DateTimeZone( 'UTC' ) );
		$end     = new DateTime( $end_date, new DateTimeZone( 'UTC' ) );

		while ( $current <= $end ) {
			$date_str = $current->format( 'Y-m-d' );

			if ( isset( $data_by_date[ $date_str ] ) ) {
				$data[] = [
					'date'        => $date_str,
					'submissions' => $data_by_date[ $date_str ]['submissions'],
					'avg_score'   => $data_by_date[ $date_str ]['avg_score'],
				];
			} else {
				$data[] = [
					'date'        => $date_str,
					'submissions' => 0,
					'avg_score'   => null,
				];
			}

			$current->modify( '+1 day' );
		}

		return $data;
	}
}
