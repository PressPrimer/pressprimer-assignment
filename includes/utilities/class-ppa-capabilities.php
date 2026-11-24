<?php
/**
 * Capabilities handler
 *
 * Manages user roles and capabilities for PressPrimer Assignment.
 *
 * @package PressPrimer_Assignment
 * @subpackage Utilities
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Capabilities class
 *
 * Handles setup and removal of custom capabilities.
 * Defines capabilities for assignment management, submissions viewing,
 * settings access, and report viewing.
 *
 * Note: The PressPrimer Teacher role is created by PressPrimer Quiz.
 * This plugin adds its own capabilities to that role when it exists.
 * Premium capabilities (manage_own, view_submissions_own) are added by addons.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Capabilities {

	/**
	 * Full management access capability
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PPA_CAP_MANAGE_ALL = 'pressprimer_assignment_manage_all';

	/**
	 * Settings management capability
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PPA_CAP_MANAGE_SETTINGS = 'pressprimer_assignment_manage_settings';

	/**
	 * Own content management capability
	 *
	 * Allows managing own assignments and viewing own submissions.
	 * Granted to teachers by default; used as baseline access level.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PPA_CAP_MANAGE_OWN = 'pressprimer_assignment_manage_own';

	/**
	 * Reports viewing capability
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PPA_CAP_VIEW_REPORTS = 'pressprimer_assignment_view_reports';

	/**
	 * Setup capabilities
	 *
	 * Adds all plugin capabilities to appropriate roles.
	 * Called during plugin activation.
	 *
	 * @since 1.0.0
	 */
	public static function setup_capabilities() {
		self::add_admin_capabilities();
		self::setup_teacher_capabilities();
	}

	/**
	 * Add capabilities to administrator role
	 *
	 * Administrators get full access to all plugin features.
	 *
	 * @since 1.0.0
	 */
	private static function add_admin_capabilities() {
		$admin = get_role( 'administrator' );

		if ( ! $admin ) {
			return;
		}

		$admin->add_cap( self::PPA_CAP_MANAGE_OWN );
		$admin->add_cap( self::PPA_CAP_MANAGE_ALL );
		$admin->add_cap( self::PPA_CAP_MANAGE_SETTINGS );
		$admin->add_cap( self::PPA_CAP_VIEW_REPORTS );
	}

	/**
	 * Setup teacher role capabilities
	 *
	 * Checks for the shared PressPrimer Teacher role (created by Quiz)
	 * and adds assignment capabilities if appropriate.
	 *
	 * Note: The teacher role uses the 'pressprimer_quiz_teacher' slug
	 * as it is created and owned by PressPrimer Quiz. Premium capabilities
	 * like manage_own and view_submissions_own are added by addon plugins.
	 *
	 * @since 1.0.0
	 */
	private static function setup_teacher_capabilities() {
		$teacher = get_role( 'pressprimer_quiz_teacher' );

		if ( ! $teacher ) {
			// Teacher role doesn't exist yet (Quiz not installed).
			// Capabilities will be added when Quiz is activated, or
			// addon plugins can handle this independently.
			return;
		}

		// Base capabilities for teachers (free plugin).
		$teacher->add_cap( self::PPA_CAP_MANAGE_OWN );
		$teacher->add_cap( self::PPA_CAP_VIEW_REPORTS );

		/**
		 * Filter the capabilities assigned to the teacher role.
		 *
		 * Addons can use this filter to add premium capabilities
		 * such as manage_all and view_submissions_all.
		 *
		 * @since 1.0.0
		 *
		 * @param array $capabilities Array of capability strings to add.
		 */
		$teacher_caps = apply_filters( 'pressprimer_assignment_teacher_capabilities', [] );

		foreach ( $teacher_caps as $cap ) {
			$teacher->add_cap( $cap );
		}
	}

	/**
	 * Remove capabilities
	 *
	 * Removes all plugin capabilities from all roles.
	 * Called during plugin uninstall.
	 *
	 * @since 1.0.0
	 */
	public static function remove_capabilities() {
		$all_caps = self::get_all_capabilities();

		// Get all WordPress roles.
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Initializing roles if not set.
		}

		foreach ( $wp_roles->role_objects as $role ) {
			foreach ( $all_caps as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Get all plugin capabilities
	 *
	 * Returns an array of all capabilities used by the plugin,
	 * including those that may be added by addons.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of capability strings.
	 */
	public static function get_all_capabilities() {
		$capabilities = [
			self::PPA_CAP_MANAGE_OWN,
			self::PPA_CAP_MANAGE_ALL,
			self::PPA_CAP_MANAGE_SETTINGS,
			self::PPA_CAP_VIEW_REPORTS,
		];

		/**
		 * Filter the list of all plugin capabilities.
		 *
		 * Addons should add their capabilities to this list so they
		 * are properly cleaned up during uninstall.
		 *
		 * @since 1.0.0
		 *
		 * @param array $capabilities Array of capability strings.
		 */
		return apply_filters( 'pressprimer_assignment_all_capabilities', $capabilities );
	}

	/**
	 * Check if current user can manage an assignment
	 *
	 * Checks if the current user has permission to manage
	 * a specific assignment.
	 *
	 * @since 1.0.0
	 *
	 * @param int $assignment_id Assignment ID.
	 * @return bool True if user can manage the assignment.
	 */
	public static function current_user_can_manage_assignment( $assignment_id ) {
		// Users with manage_all can manage any assignment.
		if ( current_user_can( self::PPA_CAP_MANAGE_ALL ) ) {
			return true;
		}

		// Administrators can always manage.
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		/**
		 * Filter whether the current user can manage an assignment.
		 *
		 * Addons can use this filter to implement ownership-based
		 * access control for teachers managing their own assignments.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $can_manage    Whether the user can manage the assignment.
		 * @param int  $assignment_id The assignment ID.
		 * @param int  $user_id       The current user ID.
		 */
		return apply_filters(
			'pressprimer_assignment_can_manage',
			false,
			absint( $assignment_id ),
			get_current_user_id()
		);
	}

	/**
	 * Get assignments shared with a teacher beyond the ones they created
	 *
	 * Teachers (manage_own) work with submissions on assignments they
	 * created. Addons can share more assignments with them — Educator
	 * shares the assignments distributed to groups the teacher leads —
	 * so the teacher can review and grade their own students' work on
	 * them. Sharing grants grading access only; editing an assignment
	 * stays with its creator.
	 *
	 * @since 2.2.4
	 *
	 * @param int $user_id Teacher's user ID. Defaults to the current user.
	 * @return int[] Shared assignment IDs.
	 */
	public static function get_shared_assignment_ids( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return [];
		}

		/**
		 * Filters the assignments shared with a teacher for grading.
		 *
		 * The teacher sees and grades submissions on these assignments,
		 * limited to the students returned by the
		 * pressprimer_assignment_visible_user_ids filter.
		 *
		 * @since 2.2.4
		 *
		 * @param int[] $assignment_ids Shared assignment IDs. Default empty.
		 * @param int   $user_id        Teacher's user ID.
		 */
		$assignment_ids = apply_filters( 'pressprimer_assignment_shared_assignment_ids', [], $user_id );

		return array_values( array_unique( array_filter( array_map( 'absint', (array) $assignment_ids ) ) ) );
	}

	/**
	 * Build the SQL condition for submissions on shared assignments
	 *
	 * Matches rows on assignments shared with the teacher whose student is
	 * one of the teacher's own students. Callers OR it with their own-
	 * assignment condition. Column names are fixed identifiers supplied by
	 * the caller, never user input.
	 *
	 * @since 2.2.4
	 *
	 * @param int    $user_id           Teacher's user ID.
	 * @param string $assignment_column Assignment ID column, e.g. 's.assignment_id'.
	 * @param string $user_column       Student user ID column, e.g. 's.user_id'.
	 * @return array {
	 *     @type string $0 SQL condition with placeholders, or '' when nothing is shared.
	 *     @type array  $1 Values for the placeholders.
	 * }
	 */
	public static function get_shared_scope_sql( $user_id, $assignment_column, $user_column ) {
		$assignment_ids = self::get_shared_assignment_ids( $user_id );

		if ( empty( $assignment_ids ) ) {
			return [ '', [] ];
		}

		/** This filter is documented in includes/admin/class-ppa-admin-submissions.php */
		$student_ids = apply_filters( 'pressprimer_assignment_visible_user_ids', null, absint( $user_id ) );

		if ( is_array( $student_ids ) && empty( $student_ids ) ) {
			return [ '', [] ];
		}

		$sql    = $assignment_column . ' IN (' . implode( ',', array_fill( 0, count( $assignment_ids ), '%d' ) ) . ')';
		$values = $assignment_ids;

		if ( is_array( $student_ids ) ) {
			$student_ids = array_map( 'absint', $student_ids );
			$sql        .= ' AND ' . $user_column . ' IN (' . implode( ',', array_fill( 0, count( $student_ids ), '%d' ) ) . ')';
			$values      = array_merge( $values, $student_ids );
		}

		return [ '(' . $sql . ')', $values ];
	}

	/**
	 * Check whether a teacher may work with a submission on a shared assignment
	 *
	 * @since 2.2.4
	 *
	 * @param PressPrimer_Assignment_Submission $submission Submission instance.
	 * @param int                               $user_id    Teacher's user ID. Defaults to the current user.
	 * @return bool True when the assignment is shared with the teacher and the student is theirs.
	 */
	public static function can_access_shared_submission( $submission, $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $submission || ! in_array( (int) $submission->assignment_id, self::get_shared_assignment_ids( $user_id ), true ) ) {
			return false;
		}

		/** This filter is documented in includes/admin/class-ppa-admin-submissions.php */
		$student_ids = apply_filters( 'pressprimer_assignment_visible_user_ids', null, $user_id );

		return null === $student_ids || in_array( (int) $submission->user_id, array_map( 'absint', $student_ids ), true );
	}
}
