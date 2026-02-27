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
			delete_transient( 'ppa_dashboard_stats_' . absint( $author_id ) );
		}

		// Always clear the global cache.
		delete_transient( 'ppa_dashboard_stats_all' );
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
			WHERE option_name LIKE '_transient_ppa\_chart\_%'
				OR option_name LIKE '_transient_timeout_ppa\_chart\_%'"
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
		$cache_key = 'ppa_dashboard_stats_' . ( $author_id ? absint( $author_id ) : 'all' );
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

		$cache_key = 'ppa_chart_' . $days . '_' . ( $author_id ? absint( $author_id ) : 'all' );
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
