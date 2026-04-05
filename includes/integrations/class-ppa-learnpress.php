<?php
/**
 * LearnPress Integration
 *
 * Integrates PressPrimer Assignment with LearnPress LMS.
 * Handles completion tracking when assignments are graded as passed.
 *
 * @package PressPrimer_Assignment
 * @subpackage Integrations
 * @since 2.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LearnPress Integration class
 *
 * Marks LearnPress lessons or courses as complete when a student passes
 * a linked assignment. The class loads unconditionally but acts
 * conditionally -- if LearnPress is not active, init() returns early.
 *
 * Meta storage follows the LearnDash/TutorLMS/LifterLMS pattern: the PPA
 * assignment ID is stored as post meta on the LearnPress lesson or course post.
 *
 * NOTE: LearnPress API stability varies between versions. Verify the completion
 * API against the minimum supported LearnPress version (4.0.0) at build time.
 *
 * @since 2.0.0
 */
class PressPrimer_Assignment_LearnPress {

	/**
	 * Minimum LearnPress version required
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const MIN_VERSION = '4.0.0';

	/**
	 * Meta key for storing the PPA assignment ID on a LearnPress lesson/course
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const META_KEY_ASSIGNMENT_ID = '_ppa_learnpress_assignment_id';

	/**
	 * Meta key for the completion type ('lesson' or 'course')
	 *
	 * Stored on the same LearnPress lesson/course post as META_KEY_ASSIGNMENT_ID.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const META_KEY_COMPLETION_TYPE = '_ppa_learnpress_completion_type';

	/**
	 * Settings option key
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const SETTINGS_KEY = 'pressprimer_assignment_learnpress_settings';

	/**
	 * Initialize the integration
	 *
	 * Only hooks into WordPress when LearnPress is active and compatible.
	 *
	 * @since 2.0.0
	 */
	public function init() {
		// Only initialize if LearnPress is active and meets version requirement.
		if ( ! $this->is_learnpress_compatible() ) {
			return;
		}

		// Completion tracking -- mark LearnPress content complete when assignment is passed.
		add_action( 'pressprimer_assignment_submission_passed', [ $this, 'handle_pass' ], 10, 2 );

		// REST API endpoints.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Map LearnPress Instructor role to PPA capabilities.
		$this->map_instructor_capabilities();
		add_filter( 'pressprimer_assignment_user_has_teacher_capability', [ $this, 'check_instructor_capability' ], 10, 2 );
	}

	/**
	 * Check if LearnPress is active and meets version requirement
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if LearnPress is active and compatible.
	 */
	public function is_learnpress_compatible() {
		if ( ! defined( 'LEARNPRESS_VERSION' ) ) {
			return false;
		}

		return version_compare( LEARNPRESS_VERSION, self::MIN_VERSION, '>=' );
	}

	// =========================================================================
	// Completion Tracking.
	// =========================================================================

	/**
	 * Handle assignment passed event
	 *
	 * Called when a student passes an assignment. Finds LearnPress posts
	 * linked to this assignment and marks them complete.
	 *
	 * @since 2.0.0
	 *
	 * @param int   $submission_id Submission ID.
	 * @param float $score         The score achieved.
	 */
	public function handle_pass( $submission_id, $score ) {
		// Check if integration is enabled.
		if ( ! $this->is_enabled() ) {
			return;
		}

		$submission = PressPrimer_Assignment_Submission::get( $submission_id );

		if ( ! $submission || ! $submission->user_id ) {
			return;
		}

		$assignment_id = $submission->assignment_id;
		$user_id       = $submission->user_id;

		// Find LearnPress posts that have this assignment linked.
		$linked_posts = $this->get_learnpress_posts_for_assignment( $assignment_id );

		if ( empty( $linked_posts ) ) {
			return;
		}

		foreach ( $linked_posts as $linked_post ) {
			$completion_type = get_post_meta( $linked_post->ID, self::META_KEY_COMPLETION_TYPE, true );
			if ( ! in_array( $completion_type, [ 'lesson', 'course' ], true ) ) {
				$completion_type = 'lesson';
			}

			$this->mark_complete( $user_id, $linked_post->ID, $completion_type );

			/**
			 * Fire audit log event for LMS completion triggered.
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
				'lms.completion_triggered',
				'submission',
				$submission_id,
				[
					'lms'             => 'learnpress',
					'object_id'       => $linked_post->ID,
					'completion_type' => $completion_type,
					'user_id'         => $user_id,
					'assignment_id'   => $assignment_id,
					'score'           => $score,
				]
			);
		}
	}

	/**
	 * Get LearnPress posts that use a specific assignment
	 *
	 * Searches for LearnPress lessons and courses that have the given
	 * assignment ID stored in their post meta.
	 *
	 * @since 2.0.0
	 *
	 * @param int $assignment_id Assignment ID.
	 * @return array Array of post objects.
	 */
	private function get_learnpress_posts_for_assignment( $assignment_id ) {
		$post_types = [ 'lp_course' ];

		// Use LP_LESSON_CPT constant if available, otherwise fall back to string.
		if ( defined( 'LP_LESSON_CPT' ) ) {
			$post_types[] = LP_LESSON_CPT;
		} else {
			$post_types[] = 'lp_lesson';
		}

		$args = [
			'post_type'      => $post_types,
			'posts_per_page' => -1,
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for LMS integration.
				[
					'key'   => self::META_KEY_ASSIGNMENT_ID,
					'value' => $assignment_id,
				],
			],
		];

		return get_posts( $args );
	}

	/**
	 * Mark a LearnPress object as complete for a user
	 *
	 * Uses the LearnPress user API (learn_press_get_user) to complete
	 * lessons or courses. This matches the approach used in PressPrimer
	 * Quiz's LearnPress integration.
	 *
	 * NOTE: Verify this API against minimum supported LearnPress version
	 * (4.0.0) at build time. LearnPress API stability varies between versions.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $user_id         User ID.
	 * @param int    $object_id       LearnPress lesson or course ID.
	 * @param string $completion_type 'lesson' or 'course'.
	 */
	private function mark_complete( $user_id, $object_id, $completion_type ) {
		if ( ! function_exists( 'learn_press_get_user' ) ) {
			return;
		}

		$user = learn_press_get_user( $user_id );
		if ( ! $user ) {
			return;
		}

		if ( 'lesson' === $completion_type ) {
			$this->complete_lesson( $user, $object_id, $user_id );
		} elseif ( 'course' === $completion_type ) {
			$this->complete_course( $user, $object_id, $user_id );
		}
	}

	/**
	 * Complete a LearnPress lesson for a user
	 *
	 * Uses $user->complete_lesson() which is the same approach as
	 * PressPrimer Quiz's LearnPress integration. Requires looking up the
	 * course ID for the lesson via LearnPress section tables.
	 *
	 * @since 2.0.0
	 *
	 * @param object $user      LearnPress user object.
	 * @param int    $lesson_id LearnPress lesson post ID.
	 * @param int    $user_id   WordPress user ID.
	 */
	private function complete_lesson( $user, $lesson_id, $user_id ) {
		// Check if already completed.
		if ( method_exists( $user, 'has_completed_item' ) ) {
			$course_id = $this->get_lesson_course_id( $lesson_id );
			if ( $course_id && $user->has_completed_item( $lesson_id, $course_id ) ) {
				return;
			}
		}

		// LearnPress requires the course ID for lesson completion.
		if ( ! isset( $course_id ) ) {
			$course_id = $this->get_lesson_course_id( $lesson_id );
		}

		if ( ! $course_id ) {
			return;
		}

		if ( ! method_exists( $user, 'complete_lesson' ) ) {
			return;
		}

		$result = $user->complete_lesson( $lesson_id, $course_id );

		if ( ! is_wp_error( $result ) ) {
			/**
			 * Fires after a LearnPress lesson is marked complete due to PPA assignment pass.
			 *
			 * @since 2.0.0
			 *
			 * @param int $lesson_id LearnPress lesson post ID.
			 * @param int $course_id LearnPress course post ID.
			 * @param int $user_id   User ID.
			 */
			do_action( 'pressprimer_assignment_learnpress_lesson_completed', $lesson_id, $course_id, $user_id );

			// Trigger auto-course-completion if all lessons are now complete.
			$this->maybe_finish_course( $course_id, $user_id );
		}
	}

	/**
	 * Complete a LearnPress course for a user
	 *
	 * Uses $user->finish_course() which matches the PressPrimer Quiz
	 * LearnPress integration approach.
	 *
	 * @since 2.0.0
	 *
	 * @param object $user      LearnPress user object.
	 * @param int    $course_id LearnPress course post ID.
	 * @param int    $user_id   WordPress user ID.
	 */
	private function complete_course( $user, $course_id, $user_id ) {
		// Check if already finished.
		if ( method_exists( $user, 'has_finished_course' ) && $user->has_finished_course( $course_id ) ) {
			return;
		}

		if ( ! method_exists( $user, 'finish_course' ) ) {
			return;
		}

		$user->finish_course( $course_id );

		/**
		 * Fires after a LearnPress course is finished due to PPA assignment pass.
		 *
		 * @since 2.0.0
		 *
		 * @param int $course_id LearnPress course post ID.
		 * @param int $user_id   User ID.
		 */
		do_action( 'pressprimer_assignment_learnpress_course_completed', $course_id, $user_id );
	}

	/**
	 * Get course ID for a lesson
	 *
	 * LearnPress stores lesson-course relationships in:
	 * - {prefix}learnpress_sections (section_id, section_course_id)
	 * - {prefix}learnpress_section_items (section_id, item_id)
	 *
	 * This matches the approach used in PressPrimer Quiz's LearnPress integration.
	 *
	 * @since 2.0.0
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @return int|null Course post ID or null.
	 */
	private function get_lesson_course_id( $lesson_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic lesson lookup, not suitable for caching.
		$course_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT s.section_course_id
				 FROM {$wpdb->prefix}learnpress_section_items AS si
				 INNER JOIN {$wpdb->prefix}learnpress_sections AS s ON si.section_id = s.section_id
				 WHERE si.item_id = %d
				 LIMIT 1",
				$lesson_id
			)
		);

		return $course_id ? absint( $course_id ) : null;
	}

	/**
	 * Trigger LearnPress course completion if all lessons are done
	 *
	 * LearnPress does not automatically cascade lesson completion to course
	 * completion. This method checks if all items in the course are complete
	 * and, if so, programmatically finishes the course for the user.
	 *
	 * This matches the Quiz LearnPress integration's maybe_finish_course().
	 *
	 * @since 2.0.0
	 *
	 * @param int $course_id Course post ID.
	 * @param int $user_id   User ID.
	 */
	private function maybe_finish_course( $course_id, $user_id ) {
		if ( ! function_exists( 'learn_press_get_user' ) || ! function_exists( 'learn_press_get_course' ) ) {
			return;
		}

		$user   = learn_press_get_user( $user_id );
		$course = learn_press_get_course( $course_id );

		if ( ! $user || ! $course ) {
			return;
		}

		// Don't re-finish an already-completed course.
		if ( method_exists( $user, 'has_finished_course' ) && $user->has_finished_course( $course_id ) ) {
			return;
		}

		// Get all curriculum items (lessons, LP quizzes, etc.).
		$items = [];
		if ( method_exists( $course, 'get_items' ) ) {
			$items = $course->get_items();
		}

		if ( empty( $items ) ) {
			return;
		}

		// Check if every item is completed.
		$all_complete = true;
		foreach ( $items as $item_id ) {
			if ( method_exists( $user, 'has_completed_item' ) ) {
				if ( ! $user->has_completed_item( $item_id, $course_id ) ) {
					$all_complete = false;
					break;
				}
			}
		}

		if ( ! $all_complete ) {
			return;
		}

		// All items complete -- finish the course.
		if ( method_exists( $user, 'finish_course' ) ) {
			$user->finish_course( $course_id );
		}
	}

	// =========================================================================
	// Settings & Configuration.
	// =========================================================================

	/**
	 * Check if the LearnPress integration is enabled
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if enabled.
	 */
	public function is_enabled() {
		if ( ! $this->is_learnpress_compatible() ) {
			return false;
		}

		$settings = get_option( self::SETTINGS_KEY, [] );
		return ! empty( $settings['enabled'] );
	}

	// =========================================================================
	// REST API.
	// =========================================================================

	/**
	 * Register REST routes for LearnPress integration
	 *
	 * @since 2.0.0
	 */
	public function register_rest_routes() {
		register_rest_route(
			'ppa/v1',
			'/learnpress/status',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'rest_get_status' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ) || current_user_can( 'ppa_manage_settings' );
				},
			]
		);

		register_rest_route(
			'ppa/v1',
			'/learnpress/settings',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'rest_save_settings' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ) || current_user_can( 'ppa_manage_settings' );
				},
			]
		);

		register_rest_route(
			'ppa/v1',
			'/learnpress/objects',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'rest_get_objects' ],
				'permission_callback' => function () {
					return current_user_can( 'ppa_view_assignments' )
						|| current_user_can( 'ppa_create_assignments' )
						|| current_user_can( 'ppa_edit_assignments' );
				},
				'args'                => [
					'search' => [
						'type'    => 'string',
						'default' => '',
					],
				],
			]
		);
	}

	/**
	 * REST handler: Get LearnPress integration status
	 *
	 * Returns detection status, version, compatibility, and linked assignment count.
	 *
	 * @since 2.0.0
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function rest_get_status() {
		$active = defined( 'LEARNPRESS_VERSION' );

		$status = [
			'active'      => $active,
			'version'     => $active ? LEARNPRESS_VERSION : null,
			'min_version' => self::MIN_VERSION,
			'compatible'  => $this->is_learnpress_compatible(),
		];

		if ( $active ) {
			$status['attached_assignments'] = $this->count_linked_assignments();
		}

		$settings = get_option( self::SETTINGS_KEY, [] );

		return new WP_REST_Response(
			[
				'success'  => true,
				'status'   => $status,
				'settings' => [
					'enabled' => ! empty( $settings['enabled'] ),
				],
			],
			200
		);
	}

	/**
	 * REST handler: Save LearnPress integration settings
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_save_settings( $request ) {
		$data     = $request->get_json_params();
		$settings = get_option( self::SETTINGS_KEY, [] );

		if ( isset( $data['enabled'] ) ) {
			$settings['enabled'] = (bool) $data['enabled'];
		}

		update_option( self::SETTINGS_KEY, $settings );

		return new WP_REST_Response(
			[
				'success'  => true,
				'settings' => $settings,
			],
			200
		);
	}

	/**
	 * REST handler: Get LearnPress lessons and courses for assignment editor selector
	 *
	 * Returns a list of LearnPress lessons and courses, optionally filtered by search term.
	 * Used by the assignment editor's searchable select dropdown.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_get_objects( $request ) {
		$search = sanitize_text_field( $request->get_param( 'search' ) );

		$post_types = [ 'lp_course' ];

		// Use LP_LESSON_CPT constant if available.
		if ( defined( 'LP_LESSON_CPT' ) ) {
			$post_types[] = LP_LESSON_CPT;
		} else {
			$post_types[] = 'lp_lesson';
		}

		$args = [
			'post_type'      => $post_types,
			'posts_per_page' => 50,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$posts   = get_posts( $args );
		$objects = [];

		foreach ( $posts as $post ) {
			$lp_lesson_type = defined( 'LP_LESSON_CPT' ) ? LP_LESSON_CPT : 'lp_lesson';
			$type_label     = $lp_lesson_type === $post->post_type
				? __( 'Lesson', 'pressprimer-assignment' )
				: __( 'Course', 'pressprimer-assignment' );

			$objects[] = [
				'id'    => $post->ID,
				'title' => $post->post_title,
				'type'  => $post->post_type,
				'label' => sprintf( '[%s] %s', $type_label, $post->post_title ),
			];
		}

		return new WP_REST_Response(
			[
				'success' => true,
				'objects' => $objects,
			],
			200
		);
	}

	// =========================================================================
	// Per-Assignment Link Management.
	// =========================================================================

	/**
	 * Get the LearnPress object linked to an assignment
	 *
	 * Performs a reverse lookup: finds the LearnPress lesson/course post
	 * that has the given assignment ID stored in its post meta.
	 *
	 * @since 2.0.0
	 *
	 * @param int $assignment_id Assignment ID.
	 * @return array|null Array with 'object_id' and 'completion_type', or null if not linked.
	 */
	public function get_linked_learnpress_object( $assignment_id ) {
		$linked_posts = $this->get_learnpress_posts_for_assignment( $assignment_id );

		if ( empty( $linked_posts ) ) {
			return null;
		}

		// Return the first linked post (one-to-one mapping expected).
		$post            = $linked_posts[0];
		$completion_type = get_post_meta( $post->ID, self::META_KEY_COMPLETION_TYPE, true );

		if ( ! in_array( $completion_type, [ 'lesson', 'course' ], true ) ) {
			$completion_type = 'lesson';
		}

		return [
			'object_id'       => $post->ID,
			'completion_type' => $completion_type,
		];
	}

	/**
	 * Save a LearnPress link for an assignment
	 *
	 * Stores the assignment ID and completion type as post meta on the
	 * LearnPress lesson/course post. Removes any previous link first.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $assignment_id   Assignment ID.
	 * @param int    $object_id       LearnPress lesson or course post ID.
	 * @param string $completion_type 'lesson' or 'course'.
	 */
	public function save_learnpress_link( $assignment_id, $object_id, $completion_type ) {
		$lp_lesson_type = defined( 'LP_LESSON_CPT' ) ? LP_LESSON_CPT : 'lp_lesson';

		// Validate the LearnPress post exists and is a lesson or course.
		$post = get_post( $object_id );
		if ( ! $post || ! in_array( $post->post_type, [ $lp_lesson_type, 'lp_course' ], true ) ) {
			return;
		}

		if ( ! in_array( $completion_type, [ 'lesson', 'course' ], true ) ) {
			$completion_type = 'lesson';
		}

		// Remove any existing link for this assignment.
		$this->remove_learnpress_link( $assignment_id );

		// Save the new link on the LearnPress post.
		update_post_meta( $object_id, self::META_KEY_ASSIGNMENT_ID, $assignment_id );
		update_post_meta( $object_id, self::META_KEY_COMPLETION_TYPE, $completion_type );
	}

	/**
	 * Remove the LearnPress link for an assignment
	 *
	 * Finds and removes the assignment ID and completion type meta from
	 * any LearnPress posts that reference this assignment.
	 *
	 * @since 2.0.0
	 *
	 * @param int $assignment_id Assignment ID.
	 */
	public function remove_learnpress_link( $assignment_id ) {
		$linked_posts = $this->get_learnpress_posts_for_assignment( $assignment_id );

		foreach ( $linked_posts as $post ) {
			delete_post_meta( $post->ID, self::META_KEY_ASSIGNMENT_ID );
			delete_post_meta( $post->ID, self::META_KEY_COMPLETION_TYPE );
		}
	}

	/**
	 * Count assignments linked to LearnPress objects
	 *
	 * @since 2.0.0
	 *
	 * @return int Number of linked assignments.
	 */
	private function count_linked_assignments() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin status check.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != '' AND meta_value IS NOT NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress core table.
				self::META_KEY_ASSIGNMENT_ID
			)
		);

		return absint( $count );
	}

	// =========================================================================
	// Instructor Role Mapping.
	// =========================================================================

	/**
	 * Map LearnPress Instructor capabilities to PPA teacher capabilities
	 *
	 * Grants LearnPress Instructors (lp_teacher role) the ability
	 * to manage assignments and grade submissions.
	 *
	 * @since 2.0.0
	 */
	private function map_instructor_capabilities() {
		$role = get_role( 'lp_teacher' );
		if ( ! $role ) {
			return;
		}

		$ppa_caps = [
			'ppa_view_assignments',
			'ppa_create_assignments',
			'ppa_edit_assignments',
			'ppa_delete_assignments',
			'ppa_grade_submissions',
			'ppa_view_submissions',
			'ppa_view_reports',
		];

		foreach ( $ppa_caps as $cap ) {
			if ( ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Check if a user has LearnPress instructor capability
	 *
	 * Allows LearnPress Instructors (lp_teacher) to pass PPA teacher capability checks.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $has_capability Whether user has the capability.
	 * @param int  $user_id        User ID being checked.
	 * @return bool Modified capability check result.
	 */
	public function check_instructor_capability( $has_capability, $user_id ) {
		if ( $has_capability ) {
			return true;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		if ( in_array( 'lp_teacher', (array) $user->roles, true ) ) {
			return true;
		}

		return false;
	}
}
