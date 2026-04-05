<?php
/**
 * LifterLMS Integration
 *
 * Integrates PressPrimer Assignment with LifterLMS.
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
 * LifterLMS Integration class
 *
 * Marks LifterLMS lessons or courses as complete when a student passes
 * a linked assignment. The class loads unconditionally but acts
 * conditionally — if LifterLMS is not active, init() returns early.
 *
 * Meta storage follows the LearnDash/TutorLMS pattern: the PPA assignment
 * ID is stored as post meta on the LifterLMS lesson or course post.
 *
 * @since 2.0.0
 */
class PressPrimer_Assignment_LifterLMS {

	/**
	 * Meta key for storing the PPA assignment ID on a LifterLMS lesson/course
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const META_KEY_ASSIGNMENT_ID = '_ppa_lifterlms_assignment_id';

	/**
	 * Meta key for the completion type ('lesson' or 'course')
	 *
	 * Stored on the same LifterLMS lesson/course post as META_KEY_ASSIGNMENT_ID.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const META_KEY_COMPLETION_TYPE = '_ppa_lifterlms_completion_type';

	/**
	 * Settings option key
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const SETTINGS_KEY = 'pressprimer_assignment_lifterlms_settings';

	/**
	 * Initialize the integration
	 *
	 * Only hooks into WordPress when LifterLMS is active.
	 *
	 * @since 2.0.0
	 */
	public function init() {
		// Only initialize if LifterLMS is active.
		if ( ! defined( 'LLMS_PLUGIN_FILE' ) ) {
			return;
		}

		// Completion tracking — mark LifterLMS content complete when assignment is passed.
		add_action( 'pressprimer_assignment_submission_passed', [ $this, 'handle_pass' ], 10, 2 );

		// REST API endpoints.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Map LifterLMS Instructor role to PPA capabilities.
		$this->map_instructor_capabilities();
		add_filter( 'pressprimer_assignment_user_has_teacher_capability', [ $this, 'check_instructor_capability' ], 10, 2 );
	}

	// =========================================================================
	// Completion Tracking.
	// =========================================================================

	/**
	 * Handle assignment passed event
	 *
	 * Called when a student passes an assignment. Finds LifterLMS posts
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

		// Find LifterLMS posts that have this assignment linked.
		$linked_posts = $this->get_lifterlms_posts_for_assignment( $assignment_id );

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
					'lms'             => 'lifterlms',
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
	 * Get LifterLMS posts that use a specific assignment
	 *
	 * Searches for LifterLMS lessons and courses that have the given
	 * assignment ID stored in their post meta.
	 *
	 * @since 2.0.0
	 *
	 * @param int $assignment_id Assignment ID.
	 * @return array Array of post objects.
	 */
	private function get_lifterlms_posts_for_assignment( $assignment_id ) {
		$args = [
			'post_type'      => [ 'lesson', 'course' ],
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
	 * Mark a LifterLMS object as complete for a user
	 *
	 * Uses LifterLMS's llms_mark_complete() function. Handles both
	 * lesson and course completion types.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $user_id         User ID.
	 * @param int    $object_id       LifterLMS lesson or course ID.
	 * @param string $completion_type 'lesson' or 'course'.
	 */
	private function mark_complete( $user_id, $object_id, $completion_type ) {
		if ( ! function_exists( 'llms_mark_complete' ) ) {
			return;
		}

		// Check if already complete.
		if ( function_exists( 'llms_is_complete' ) && llms_is_complete( $user_id, $object_id, $completion_type ) ) {
			return;
		}

		// For lessons, verify the lesson has a valid parent section.
		// LifterLMS cascades completion upward (lesson -> section -> course),
		// so if _llms_parent_section is missing, the cascade triggers a fatal error.
		if ( 'lesson' === $completion_type ) {
			$parent_section = get_post_meta( $object_id, '_llms_parent_section', true );
			if ( empty( $parent_section ) ) {
				return;
			}
		}

		llms_mark_complete( $user_id, $object_id, $completion_type );

		/**
		 * Fires after a LifterLMS object is marked complete due to PPA assignment pass.
		 *
		 * @since 2.0.0
		 *
		 * @param int    $object_id       LifterLMS lesson or course ID.
		 * @param int    $user_id         User ID.
		 * @param string $completion_type 'lesson' or 'course'.
		 */
		do_action( 'pressprimer_assignment_lifterlms_completed', $object_id, $user_id, $completion_type );
	}

	// =========================================================================
	// Settings & Configuration.
	// =========================================================================

	/**
	 * Check if the LifterLMS integration is enabled
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if enabled.
	 */
	public function is_enabled() {
		if ( ! defined( 'LLMS_PLUGIN_FILE' ) ) {
			return false;
		}

		$settings = get_option( self::SETTINGS_KEY, [] );
		return ! empty( $settings['enabled'] );
	}

	// =========================================================================
	// REST API.
	// =========================================================================

	/**
	 * Register REST routes for LifterLMS integration
	 *
	 * @since 2.0.0
	 */
	public function register_rest_routes() {
		register_rest_route(
			'ppa/v1',
			'/lifterlms/status',
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
			'/lifterlms/settings',
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
			'/lifterlms/objects',
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
	 * REST handler: Get LifterLMS integration status
	 *
	 * Returns detection status, version, and linked assignment count.
	 *
	 * @since 2.0.0
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function rest_get_status() {
		$active = defined( 'LLMS_PLUGIN_FILE' );

		$status = [
			'active'  => $active,
			'version' => $active && defined( 'LLMS_PLUGIN_VERSION' ) ? LLMS_PLUGIN_VERSION : null,
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
	 * REST handler: Save LifterLMS integration settings
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
	 * REST handler: Get LifterLMS lessons and courses for assignment editor selector
	 *
	 * Returns a list of LifterLMS lessons and courses, optionally filtered by search term.
	 * Used by the assignment editor's searchable select dropdown.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_get_objects( $request ) {
		$search = sanitize_text_field( $request->get_param( 'search' ) );

		$args = [
			'post_type'      => [ 'lesson', 'course' ],
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
			$type_label = 'lesson' === $post->post_type
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
	 * Get the LifterLMS object linked to an assignment
	 *
	 * Performs a reverse lookup: finds the LifterLMS lesson/course post
	 * that has the given assignment ID stored in its post meta.
	 *
	 * @since 2.0.0
	 *
	 * @param int $assignment_id Assignment ID.
	 * @return array|null Array with 'object_id' and 'completion_type', or null if not linked.
	 */
	public function get_linked_lifterlms_object( $assignment_id ) {
		$linked_posts = $this->get_lifterlms_posts_for_assignment( $assignment_id );

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
	 * Save a LifterLMS link for an assignment
	 *
	 * Stores the assignment ID and completion type as post meta on the
	 * LifterLMS lesson/course post. Removes any previous link first.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $assignment_id   Assignment ID.
	 * @param int    $object_id       LifterLMS lesson or course post ID.
	 * @param string $completion_type 'lesson' or 'course'.
	 */
	public function save_lifterlms_link( $assignment_id, $object_id, $completion_type ) {
		// Validate the LifterLMS post exists and is a lesson or course.
		$post = get_post( $object_id );
		if ( ! $post || ! in_array( $post->post_type, [ 'lesson', 'course' ], true ) ) {
			return;
		}

		if ( ! in_array( $completion_type, [ 'lesson', 'course' ], true ) ) {
			$completion_type = 'lesson';
		}

		// Remove any existing link for this assignment.
		$this->remove_lifterlms_link( $assignment_id );

		// Save the new link on the LifterLMS post.
		update_post_meta( $object_id, self::META_KEY_ASSIGNMENT_ID, $assignment_id );
		update_post_meta( $object_id, self::META_KEY_COMPLETION_TYPE, $completion_type );
	}

	/**
	 * Remove the LifterLMS link for an assignment
	 *
	 * Finds and removes the assignment ID and completion type meta from
	 * any LifterLMS posts that reference this assignment.
	 *
	 * @since 2.0.0
	 *
	 * @param int $assignment_id Assignment ID.
	 */
	public function remove_lifterlms_link( $assignment_id ) {
		$linked_posts = $this->get_lifterlms_posts_for_assignment( $assignment_id );

		foreach ( $linked_posts as $post ) {
			delete_post_meta( $post->ID, self::META_KEY_ASSIGNMENT_ID );
			delete_post_meta( $post->ID, self::META_KEY_COMPLETION_TYPE );
		}
	}

	/**
	 * Count assignments linked to LifterLMS objects
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
	 * Map LifterLMS Instructor capabilities to PPA teacher capabilities
	 *
	 * Grants LifterLMS Instructors and Instructor Assistants the ability
	 * to manage assignments and grade submissions.
	 *
	 * @since 2.0.0
	 */
	private function map_instructor_capabilities() {
		$instructor_roles = [ 'instructor', 'instructors_assistant' ];

		foreach ( $instructor_roles as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
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
	}

	/**
	 * Check if a user has LifterLMS instructor capability
	 *
	 * Allows LifterLMS Instructors to pass PPA teacher capability checks.
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

		$instructor_roles = [ 'instructor', 'instructors_assistant' ];

		foreach ( $instructor_roles as $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				return true;
			}
		}

		return false;
	}
}
