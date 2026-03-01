<?php
/**
 * Submissions renderer
 *
 * Handles rendering of the My Submissions dashboard for students.
 * Displays a table of the user's submissions across all assignments.
 *
 * @package PressPrimer_Assignment
 * @subpackage Frontend
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Submissions renderer class
 *
 * Renders the [ppa_my_submissions] shortcode output showing
 * a student's submission history across all assignments.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Submissions_Renderer {

	/**
	 * Render the submissions dashboard
	 *
	 * Main entry point called by the shortcode handler.
	 * Fetches the user's submissions, groups by assignment,
	 * and renders the dashboard template.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $user_id User ID.
	 * @param array $display Display options from shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function render( $user_id, $display = [] ) {
		$defaults = [
			'per_page'    => 10,
			'show_status' => true,
			'show_score'  => true,
			'show_date'   => true,
		];

		$display = wp_parse_args( $display, $defaults );
		$user_id = absint( $user_id );

		$theme       = get_option( 'pressprimer_assignment_frontend_theme', 'default' );
		$theme_class = 'ppa-theme-' . sanitize_html_class( $theme );

		// Get all non-draft submissions for this user.
		$submissions = $this->get_user_submissions( $user_id );

		// Build template data for each submission.
		$items = [];
		foreach ( $submissions as $submission ) {
			$assignment = $submission->get_assignment();
			$title      = $assignment ? $assignment->title : __( 'Unknown Assignment', 'pressprimer-assignment' );

			// Determine the submission date.
			$date_value = ! empty( $submission->submitted_at ) ? $submission->submitted_at : $submission->created_at;

			$formatted_date = '';
			if ( ! empty( $date_value ) ) {
				$formatted_date = date_i18n(
					get_option( 'date_format' ),
					strtotime( $date_value )
				);
			}

			// Find the page URL for this assignment, linking to the specific submission.
			$view_url = '';
			if ( $assignment && 'published' === $assignment->status ) {
				$base_url = $this->get_assignment_url( $assignment );
				if ( $base_url ) {
					$view_url = add_query_arg( 'ppa_submission', absint( $submission->id ), $base_url );
				}
			}

			$items[] = (object) [
				'submission'        => $submission,
				'assignment'        => $assignment,
				'title'             => $title,
				'submission_number' => (int) $submission->submission_number,
				'formatted_date'    => $formatted_date,
				'date_value'        => $date_value,
				'status_label'      => $this->get_status_label( $submission->status ),
				'view_url'          => $view_url,
			];
		}

		// Start output buffering.
		ob_start();

		// Make variables available to the template.
		$submissions = $items;

		$template_path = $this->get_template_path( 'dashboard/my-submissions.php' );

		if ( $template_path ) {
			include $template_path;
		}

		$html = ob_get_clean();

		/**
		 * Filters the rendered submissions dashboard output.
		 *
		 * @since 1.0.0
		 *
		 * @param string $html    Rendered HTML.
		 * @param int    $user_id User ID.
		 * @param array  $display Display options.
		 */
		$html = apply_filters( 'pressprimer_assignment_submissions_render_output', $html, $user_id, $display );

		return wp_kses( $html, $this->get_allowed_html() );
	}

	/**
	 * Get user submissions
	 *
	 * Fetches all non-draft submissions for the user, newest first.
	 * All submissions are returned so students can see their full
	 * history across resubmissions and compare feedback.
	 *
	 * Uses a custom query because the Model::find() method does
	 * not support != comparisons.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 * @return array Array of Submission instances.
	 */
	private function get_user_submissions( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$table   = $wpdb->prefix . 'ppa_submissions';

		// Get all non-draft submissions for this user, newest first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND status != %s ORDER BY submitted_at DESC, created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is built from $wpdb->prefix.
				$user_id,
				'draft'
			)
		);

		if ( empty( $rows ) ) {
			return [];
		}

		$results = [];

		foreach ( $rows as $row ) {
			// Hydrate into a Submission model instance.
			$submission = new PressPrimer_Assignment_Submission();
			foreach ( $row as $key => $value ) {
				if ( property_exists( $submission, $key ) ) {
					$submission->$key = $value;
				}
			}

			$results[] = $submission;
		}

		return $results;
	}

	/**
	 * Get the URL of a page containing the assignment shortcode
	 *
	 * Searches published posts/pages for a [ppa_assignment] shortcode
	 * referencing the given assignment ID.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
	 * @return string Page permalink or empty string if not found.
	 */
	private function get_assignment_url( $assignment ) {
		global $wpdb;

		$assignment_id = absint( $assignment->id );

		// Search for pages containing the shortcode with this ID.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = %s AND post_content LIKE %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpdb->posts is a core table name.
				'publish',
				'%[ppa_assignment%id="' . $wpdb->esc_like( (string) $assignment_id ) . '"%'
			)
		);

		if ( $post_id ) {
			return get_permalink( (int) $post_id );
		}

		return '';
	}

	/**
	 * Get status label for display
	 *
	 * Returns a human-readable label for a submission status.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Submission status.
	 * @return string Translated status label.
	 */
	public function get_status_label( $status ) {
		$labels = [
			'draft'     => __( 'Draft', 'pressprimer-assignment' ),
			'submitted' => __( 'Submitted', 'pressprimer-assignment' ),
			'grading'   => __( 'Being Reviewed', 'pressprimer-assignment' ),
			'graded'    => __( 'Graded', 'pressprimer-assignment' ),
			'returned'  => __( 'Graded & Returned', 'pressprimer-assignment' ),
		];

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Get template path with theme override support
	 *
	 * Checks child theme, parent theme, then plugin templates directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template_name Template file name relative to templates dir.
	 * @return string|false Full path to template file or false if not found.
	 */
	private function get_template_path( $template_name ) {
		// Check child theme override first.
		$theme_path = get_stylesheet_directory() . '/pressprimer-assignment/' . $template_name;
		if ( file_exists( $theme_path ) ) {
			return $theme_path;
		}

		// Check parent theme.
		$parent_path = get_template_directory() . '/pressprimer-assignment/' . $template_name;
		if ( $parent_path !== $theme_path && file_exists( $parent_path ) ) {
			return $parent_path;
		}

		// Plugin template.
		$plugin_path = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'templates/' . $template_name;
		if ( file_exists( $plugin_path ) ) {
			return $plugin_path;
		}

		return false;
	}

	/**
	 * Get allowed HTML tags for dashboard output
	 *
	 * Returns an array of allowed HTML tags and attributes for use
	 * with wp_kses(). Extends post allowed tags with table elements.
	 *
	 * @since 1.0.0
	 *
	 * @return array Allowed HTML tags and attributes.
	 */
	private function get_allowed_html() {
		$allowed = wp_kses_allowed_html( 'post' );

		// Table elements.
		$allowed['table'] = [
			'class' => true,
			'role'  => true,
		];

		$allowed['thead'] = [
			'class' => true,
		];

		$allowed['tbody'] = [
			'class' => true,
		];

		$allowed['tr'] = [
			'class' => true,
		];

		$allowed['th'] = [
			'class' => true,
			'scope' => true,
		];

		$allowed['td'] = [
			'class'      => true,
			'data-label' => true,
		];

		// Time element.
		$allowed['time'] = [
			'datetime' => true,
			'class'    => true,
		];

		// Ensure div and span have data/aria attributes.
		if ( ! isset( $allowed['div'] ) ) {
			$allowed['div'] = [];
		}
		$allowed['div']['data-*'] = true;
		$allowed['div']['aria-*'] = true;
		$allowed['div']['role']   = true;

		if ( ! isset( $allowed['span'] ) ) {
			$allowed['span'] = [];
		}
		$allowed['span']['data-*'] = true;
		$allowed['span']['aria-*'] = true;
		$allowed['span']['role']   = true;

		// Section element.
		$allowed['section'] = [
			'class'      => true,
			'aria-label' => true,
			'role'       => true,
		];

		// Navigation.
		$allowed['nav'] = [
			'class'      => true,
			'aria-label' => true,
			'role'       => true,
		];

		return $allowed;
	}
}
