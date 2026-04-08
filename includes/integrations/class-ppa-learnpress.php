<?php
/**
 * LearnPress Integration
 *
 * Integrates PressPrimer Assignment with LearnPress for seamless
 * assignment experiences within courses. Mirrors the PressPrimer Quiz
 * LearnPress integration and the TutorLMS/LifterLMS Assignment integrations.
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
 * LearnPress Integration Class
 *
 * Handles all LearnPress integration functionality including:
 * - Meta boxes on LearnPress lesson post type for attaching assignments
 * - Assignment rendering via learn-press/after-content-item-summary/lp_lesson at priority 9
 * - Mark complete button suppression via remove_action() at priority 10 on the same hook
 *   (this both hides the button AND blocks forward progress — no separate completion filter
 *   is needed since removing the button IS the blocker)
 * - Completion tracking on assignment pass (only when require_pass is disabled; when enabled,
 *   the button reappears after the pass and the student clicks it manually)
 * - Enrollment gating via is_user_enrolled() helper
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
	 * Meta key for storing the PPA assignment ID on lessons
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const META_KEY_ASSIGNMENT_ID = '_ppa_learnpress_assignment_id';

	/**
	 * Meta key for storing require pass setting
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const META_KEY_REQUIRE_PASS = '_ppa_learnpress_require_pass';

	/**
	 * Whether the lesson assignment has already been rendered via the_content filter.
	 *
	 * Prevents double-rendering since the_content() can fire multiple times.
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	private $lesson_assignment_rendered = false;

	/**
	 * Initialize the integration
	 *
	 * @since 2.0.0
	 */
	public function init() {
		// Only initialize if LearnPress is active and compatible.
		if ( ! $this->is_learnpress_compatible() ) {
			return;
		}

		// Admin hooks.
		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_meta_box' ], 10, 2 );

		// AJAX handler for assignment search.
		add_action( 'wp_ajax_pressprimer_assignment_search_assignments_learnpress', [ $this, 'ajax_search_assignments' ] );

		// Map LearnPress Instructor role to PPA teacher capabilities.
		$this->map_instructor_capabilities();
		add_filter( 'pressprimer_assignment_user_has_teacher_capability', [ $this, 'check_instructor_capability' ], 10, 2 );

		/*
		 * Frontend rendering — mirror PressPrimer Quiz's LearnPress integration.
		 * 1. Render the assignment BEFORE LearnPress's mark-complete button (priority 9).
		 * 2. At priority 10, conditionally remove LearnPress's complete button render
		 *    (priority 11) when the assignment is attached with require_pass and the user
		 *    has not passed.
		 */
		add_action( 'learn-press/after-content-item-summary/lp_lesson', [ $this, 'render_assignment_in_lesson' ], 9 );
		add_action( 'learn-press/after-content-item-summary/lp_lesson', [ $this, 'maybe_hide_complete_button' ], 10 );

		// Completion tracking.
		add_action( 'pressprimer_assignment_submission_passed', [ $this, 'handle_assignment_passed' ], 10, 2 );

		// REST API endpoints.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
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

	/**
	 * Get the LearnPress lesson post type
	 *
	 * @since 2.0.0
	 *
	 * @return string Lesson post type slug.
	 */
	private function get_lesson_post_type() {
		return defined( 'LP_LESSON_CPT' ) ? LP_LESSON_CPT : 'lp_lesson';
	}

	/**
	 * Get supported post types for integration
	 *
	 * @since 2.0.0
	 *
	 * @return array Array of supported post type slugs.
	 */
	private function get_supported_post_types() {
		return [ $this->get_lesson_post_type() ];
	}

	// =========================================================================
	// Meta Box.
	// =========================================================================

	/**
	 * Register meta boxes for LearnPress post types
	 *
	 * Registered unconditionally — WordPress renders classic metaboxes
	 * inside the Gutenberg Document panel as a legacy meta box section,
	 * giving a consistent UI with PressPrimer Quiz's LearnPress metabox.
	 *
	 * @since 2.0.0
	 */
	public function register_meta_boxes() {
		foreach ( $this->get_supported_post_types() as $post_type ) {
			add_meta_box(
				'ppa_learnpress_assignment',
				__( 'PressPrimer Assignment', 'pressprimer-assignment' ),
				[ $this, 'render_meta_box' ],
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the meta box
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'pressprimer_assignment_learnpress_meta_box', 'pressprimer_assignment_learnpress_nonce' );

		$assignment_id = get_post_meta( $post->ID, self::META_KEY_ASSIGNMENT_ID, true );
		$require_pass  = get_post_meta( $post->ID, self::META_KEY_REQUIRE_PASS, true );

		// Get assignment display label if one is selected.
		$assignment_display = '';
		if ( $assignment_id ) {
			$assignment         = PressPrimer_Assignment_Assignment::get( $assignment_id );
			$assignment_display = $assignment ? sprintf( '%d - %s', $assignment->id, $assignment->title ) : '';
		}
		?>
		<div class="ppa-learnpress-meta-box">
			<p>
				<label for="ppa_assignment_search">
					<?php esc_html_e( 'Select Assignment:', 'pressprimer-assignment' ); ?>
				</label>
			</p>
			<div class="ppa-assignment-selector">
				<input
					type="text"
					id="ppa_assignment_search"
					class="ppa-assignment-search widefat"
					placeholder="<?php esc_attr_e( 'Click to browse or type to search...', 'pressprimer-assignment' ); ?>"
					value="<?php echo esc_attr( $assignment_display ); ?>"
					autocomplete="off"
					<?php echo esc_attr( $assignment_id ? 'readonly' : '' ); ?>
				/>
				<input
					type="hidden"
					id="ppa_assignment_id"
					name="pressprimer_assignment_id"
					value="<?php echo esc_attr( $assignment_id ); ?>"
				/>
				<div id="ppa_assignment_results" class="ppa-assignment-results ppa-hidden"></div>
				<?php if ( $assignment_id ) : ?>
					<button type="button" class="ppa-remove-assignment button-link" aria-label="<?php esc_attr_e( 'Remove assignment', 'pressprimer-assignment' ); ?>" title="<?php esc_attr_e( 'Remove assignment', 'pressprimer-assignment' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				<?php endif; ?>
			</div>

			<p class="ppa-meta-box-description">
				<label>
					<input
						type="checkbox"
						name="pressprimer_assignment_require_pass"
						value="1"
						<?php checked( $require_pass, '1' ); ?>
					/>
					<?php esc_html_e( 'Require passing grade to complete lesson', 'pressprimer-assignment' ); ?>
				</label>
			</p>
			<p class="description">
				<?php esc_html_e( 'When enabled, students must pass this assignment to mark the lesson complete.', 'pressprimer-assignment' ); ?>
			</p>
		</div>
		<?php
		$this->enqueue_meta_box_assets();
	}

	/**
	 * Enqueue meta box assets (styles and scripts)
	 *
	 * @since 2.0.0
	 */
	private function enqueue_meta_box_assets() {
		wp_enqueue_style(
			'ppa-admin',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/css/admin.css',
			[],
			PRESSPRIMER_ASSIGNMENT_VERSION
		);

		wp_enqueue_script(
			'ppa-admin',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			PRESSPRIMER_ASSIGNMENT_VERSION,
			true
		);

		// Localize script with nonces and translatable strings.
		wp_localize_script(
			'ppa-admin',
			'pressprimerAssignmentLearnPressMetaBox',
			[
				'nonce'   => wp_create_nonce( 'pressprimer_assignment_learnpress_search' ),
				'strings' => [
					'noAssignmentsFound' => __( 'No assignments found', 'pressprimer-assignment' ),
					'removeAssignment'   => __( 'Remove assignment', 'pressprimer-assignment' ),
				],
			]
		);

		// Inline CSS for meta box styling.
		$inline_css = '
			.ppa-learnpress-meta-box .ppa-assignment-selector {
				position: relative;
				display: flex;
				align-items: center;
				gap: 4px;
			}
			.ppa-learnpress-meta-box .ppa-assignment-search {
				flex: 1;
			}
			.ppa-learnpress-meta-box .ppa-assignment-search[readonly] {
				background: #f0f6fc;
				cursor: default;
			}
			.ppa-learnpress-meta-box .ppa-assignment-results {
				position: absolute;
				top: 100%;
				left: 0;
				right: 30px;
				background: #fff;
				border: 1px solid #ddd;
				border-top: none;
				max-height: 250px;
				overflow-y: auto;
				z-index: 1000;
				box-shadow: 0 2px 4px rgba(0,0,0,0.1);
			}
			.ppa-learnpress-meta-box .ppa-assignment-result-item {
				padding: 8px 12px;
				cursor: pointer;
				border-bottom: 1px solid #f0f0f0;
			}
			.ppa-learnpress-meta-box .ppa-assignment-result-item:hover {
				background: #f0f0f0;
			}
			.ppa-learnpress-meta-box .ppa-assignment-result-item:last-child {
				border-bottom: none;
			}
			.ppa-learnpress-meta-box .ppa-remove-assignment {
				color: #d63638;
				text-decoration: none;
				padding: 4px;
				border: none;
				background: none;
				cursor: pointer;
				display: flex;
				align-items: center;
			}
			.ppa-learnpress-meta-box .ppa-remove-assignment:hover {
				color: #b32d2e;
			}
			.ppa-learnpress-meta-box .ppa-no-results {
				padding: 12px;
				color: #666;
				font-style: italic;
			}
			.ppa-learnpress-meta-box .ppa-assignment-result-item .ppa-assignment-id {
				color: #666;
				font-weight: 600;
				margin-right: 4px;
			}
		';
		wp_add_inline_style( 'ppa-admin', $inline_css );

		// Inline JavaScript for meta box functionality.
		$inline_script = 'jQuery(document).ready(function($) {' .
			'var config = window.pressprimerAssignmentLearnPressMetaBox || {};' .
			'var searchTimeout;' .
			'var $search = $("#ppa_assignment_search");' .
			'var $results = $("#ppa_assignment_results");' .
			'var $assignmentId = $("#ppa_assignment_id");' .
			'var $removeBtn = $(".ppa-remove-assignment");' .
			'function formatAssignment(item) {' .
				'return \'<div class="ppa-assignment-result-item" data-id="\' + item.id + \'" data-title="\' + $("<div/>").text(item.title).html() + \'">\' +' .
					'\'<span class="ppa-assignment-id">\' + item.id + \'</span> - \' + $("<div/>").text(item.title).html() +' .
					'\'</div>\';' .
			'}' .
			'$search.on("focus", function() {' .
				'if ($assignmentId.val()) { return; }' .
				'$.ajax({' .
					'url: ajaxurl,' .
					'type: "POST",' .
					'data: {' .
						'action: "pressprimer_assignment_search_assignments_learnpress",' .
						'nonce: config.nonce,' .
						'recent: 1' .
					'},' .
					'success: function(response) {' .
						'if (response.success && response.data.assignments.length > 0) {' .
							'var html = "";' .
							'response.data.assignments.forEach(function(item) {' .
								'html += formatAssignment(item);' .
							'});' .
							'$results.html(html).show();' .
						'}' .
					'}' .
				'});' .
			'});' .
			'$search.on("input", function() {' .
				'var query = $(this).val();' .
				'clearTimeout(searchTimeout);' .
				'if (query.length < 2) {' .
					'$search.trigger("focus");' .
					'return;' .
				'}' .
				'searchTimeout = setTimeout(function() {' .
					'$.ajax({' .
						'url: ajaxurl,' .
						'type: "POST",' .
						'data: {' .
							'action: "pressprimer_assignment_search_assignments_learnpress",' .
							'nonce: config.nonce,' .
							'search: query' .
						'},' .
						'success: function(response) {' .
							'if (response.success && response.data.assignments.length > 0) {' .
								'var html = "";' .
								'response.data.assignments.forEach(function(item) {' .
									'html += formatAssignment(item);' .
								'});' .
								'$results.html(html).show();' .
							'} else {' .
								'$results.html(\'<div class="ppa-no-results">\' + config.strings.noAssignmentsFound + \'</div>\').show();' .
							'}' .
						'}' .
					'});' .
				'}, 300);' .
			'});' .
			'$results.on("click", ".ppa-assignment-result-item", function() {' .
				'var $item = $(this);' .
				'var id = $item.data("id");' .
				'var title = $item.data("title");' .
				'var display = id + " - " + title;' .
				'$assignmentId.val(id);' .
				'$search.val(display).attr("readonly", true);' .
				'$results.hide();' .
				'if (!$removeBtn.length) {' .
					'$search.after(\'<button type="button" class="ppa-remove-assignment button-link" aria-label="\' + config.strings.removeAssignment + \'" title="\' + config.strings.removeAssignment + \'"><span class="dashicons dashicons-no-alt"></span></button>\');' .
					'$removeBtn = $(".ppa-remove-assignment");' .
				'}' .
			'});' .
			'$(document).on("click", ".ppa-remove-assignment", function() {' .
				'$assignmentId.val("");' .
				'$search.val("").removeAttr("readonly");' .
				'$(this).remove();' .
				'$removeBtn = $();' .
			'});' .
			'$(document).on("click", function(e) {' .
				'if (!$(e.target).closest(".ppa-assignment-selector").length) {' .
					'$results.hide();' .
				'}' .
			'});' .
		'});';
		wp_add_inline_script( 'ppa-admin', $inline_script );
	}

	/**
	 * Save meta box data
	 *
	 * @since 2.0.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_meta_box( $post_id, $post ) {
		// Verify nonce.
		if ( ! isset( $_POST['pressprimer_assignment_learnpress_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pressprimer_assignment_learnpress_nonce'] ) ), 'pressprimer_assignment_learnpress_meta_box' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check post type.
		if ( ! in_array( $post->post_type, $this->get_supported_post_types(), true ) ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save assignment ID.
		$assignment_id = isset( $_POST['pressprimer_assignment_id'] ) ? absint( wp_unslash( $_POST['pressprimer_assignment_id'] ) ) : 0;
		if ( $assignment_id ) {
			update_post_meta( $post_id, self::META_KEY_ASSIGNMENT_ID, $assignment_id );
		} else {
			delete_post_meta( $post_id, self::META_KEY_ASSIGNMENT_ID );
		}

		// Save require pass setting.
		$require_pass = isset( $_POST['pressprimer_assignment_require_pass'] ) ? '1' : '';
		update_post_meta( $post_id, self::META_KEY_REQUIRE_PASS, $require_pass );
	}

	// =========================================================================
	// AJAX Search.
	// =========================================================================

	/**
	 * AJAX handler for searching assignments
	 *
	 * @since 2.0.0
	 */
	public function ajax_search_assignments() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'pressprimer_assignment_learnpress_search', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-assignment' ) ] );
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'pressprimer-assignment' ) ] );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ppa_assignments';

		$recent = isset( $_POST['recent'] ) && rest_sanitize_boolean( wp_unslash( $_POST['recent'] ) );

		if ( $recent ) {
			$user_id = get_current_user_id();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- AJAX search results, not suitable for caching.
			$assignments = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, title FROM {$table} WHERE status = 'published' AND author_id = %d ORDER BY id DESC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe (wpdb prefix + constant).
					$user_id
				)
			);

			wp_send_json_success( [ 'assignments' => $assignments ] );
			return;
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( [ 'assignments' => [] ] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- AJAX search results, not suitable for caching.
		$assignments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title FROM {$table} WHERE title LIKE %s AND status = 'published' ORDER BY title ASC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe (wpdb prefix + constant).
				'%' . $wpdb->esc_like( $search ) . '%'
			)
		);

		wp_send_json_success( [ 'assignments' => $assignments ] );
	}

	// =========================================================================
	// Frontend Rendering.
	// =========================================================================

	/**
	 * Render the attached assignment inside a LearnPress lesson.
	 *
	 * Fires on learn-press/after-content-item-summary/lp_lesson at priority 9,
	 * just BEFORE LearnPress's own item_lesson_complete_button callback (priority 11).
	 * This places the assignment between the lesson content and the Mark Complete
	 * button, matching the position used by PressPrimer Quiz.
	 *
	 * @since 2.0.0
	 */
	public function render_assignment_in_lesson() {
		// Prevent double-rendering if the hook fires multiple times.
		if ( $this->lesson_assignment_rendered ) {
			return;
		}

		$post = get_post();
		if ( ! $post || ! in_array( $post->post_type, $this->get_supported_post_types(), true ) ) {
			return;
		}

		$lesson_id     = (int) $post->ID;
		$assignment_id = get_post_meta( $lesson_id, self::META_KEY_ASSIGNMENT_ID, true );

		if ( ! $assignment_id ) {
			return;
		}

		// Enforce enrollment (bypass for users who can edit the lesson).
		$course_id = $this->get_lesson_course_id( $lesson_id );
		if ( ! current_user_can( 'edit_post', $lesson_id ) && ! $this->is_user_enrolled( $course_id ) ) {
			return;
		}

		$this->lesson_assignment_rendered = true;

		$assignment_shortcode = sprintf(
			'[pressprimer_assignment id="%d"]',
			absint( $assignment_id )
		);

		echo '<div class="ppa-learnpress-assignment-wrapper">';
		// do_shortcode() output is escaped by the assignment renderer itself.
		echo do_shortcode( $assignment_shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}

	// =========================================================================
	// Completion Blocker & Button Hide.
	// =========================================================================

	/**
	 * Conditionally remove the LearnPress mark-complete button for this lesson.
	 *
	 * Fires on learn-press/after-content-item-summary/lp_lesson at priority 10,
	 * between our assignment render (priority 9) and LearnPress's default
	 * item_lesson_complete_button render (priority 11). When the lesson has an
	 * attached assignment with require_pass=1 and the user has NOT passed it,
	 * we call remove_action() on the priority 11 callback to prevent the button
	 * from rendering at all.
	 *
	 * This pattern matches PressPrimer Quiz's LearnPress integration exactly:
	 * removing the button IS the completion blocker. No separate
	 * learn-press/can-complete-lesson filter is needed.
	 *
	 * @since 2.0.0
	 */
	public function maybe_hide_complete_button() {
		$post = get_post();
		if ( ! $post || ! in_array( $post->post_type, $this->get_supported_post_types(), true ) ) {
			return;
		}

		$lesson_id     = (int) $post->ID;
		$assignment_id = get_post_meta( $lesson_id, self::META_KEY_ASSIGNMENT_ID, true );
		if ( ! $assignment_id ) {
			return;
		}

		$require_pass = get_post_meta( $lesson_id, self::META_KEY_REQUIRE_PASS, true );
		if ( '1' !== $require_pass ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( $user_id && $this->has_user_passed_assignment( $user_id, (int) $assignment_id ) ) {
			// User already passed — leave the button alone so they can mark complete.
			return;
		}

		// Remove LearnPress's default complete button render callback.
		if ( ! function_exists( 'learn_press_get_template' ) ) {
			return;
		}

		$lp_template = learn_press_get_template( 'single-course' );
		if ( ! $lp_template || ! method_exists( $lp_template, 'func' ) ) {
			return;
		}

		remove_action(
			'learn-press/after-content-item-summary/lp_lesson',
			$lp_template->func( 'item_lesson_complete_button' ),
			11
		);
	}

	/**
	 * Check whether the current user is enrolled in the given LearnPress course.
	 *
	 * Matches the enrollment-check pattern used by PressPrimer Quiz's LearnPress
	 * integration: admins bypass, courses flagged with _lp_no_required_enroll
	 * bypass, course authors/instructors bypass, and otherwise LearnPress's
	 * has_enrolled_or_finished() is consulted.
	 *
	 * @since 2.0.0
	 *
	 * @param int|null $course_id Course post ID. When null or 0, returns true
	 *                            (no enrollment context to check).
	 * @return bool True if user is enrolled or should bypass the enrollment check.
	 */
	private function is_user_enrolled( $course_id ) {
		// Admins bypass.
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// No course context — no enrollment to enforce.
		if ( empty( $course_id ) ) {
			return true;
		}

		// Course flagged as not requiring enrollment.
		$no_required = get_post_meta( $course_id, '_lp_no_required_enroll', true );
		if ( 'yes' === $no_required ) {
			return true;
		}

		// Must be logged in for anything beyond this point.
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user_id = get_current_user_id();

		// Course author bypass.
		$course_post = get_post( $course_id );
		if ( $course_post && (int) $course_post->post_author === $user_id ) {
			return true;
		}

		// LearnPress enrollment check.
		if ( function_exists( 'learn_press_get_user' ) ) {
			$user = learn_press_get_user( $user_id );
			if ( $user && method_exists( $user, 'has_enrolled_or_finished' ) ) {
				return (bool) $user->has_enrolled_or_finished( $course_id );
			}
		}

		return false;
	}

	/**
	 * Check if a user has passed a specific assignment
	 *
	 * @since 2.0.0
	 *
	 * @param int $user_id       User ID.
	 * @param int $assignment_id PPA Assignment ID.
	 * @return bool True if user has at least one passing submission.
	 */
	private function has_user_passed_assignment( int $user_id, int $assignment_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'ppa_submissions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off check, not suitable for persistent caching.
		$passed = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND assignment_id = %d AND passed = 1 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe (wpdb prefix + constant).
				$user_id,
				$assignment_id
			)
		);

		return (int) $passed > 0;
	}

	// =========================================================================
	// Completion Trigger.
	// =========================================================================

	/**
	 * Handle assignment passed event
	 *
	 * Called when a student passes an assignment. Finds LearnPress lessons
	 * linked to this assignment with require_pass enabled and marks them
	 * complete for the user. When require_pass is disabled the assignment
	 * is treated as optional — the student controls completion themselves
	 * by clicking Mark Complete, and grading does not trigger auto-
	 * completion. This matches the LifterLMS and TutorLMS integrations.
	 *
	 * @since 2.0.0
	 *
	 * @param int   $submission_id Submission ID.
	 * @param float $score         The score achieved.
	 */
	public function handle_assignment_passed( $submission_id, $score ) {
		$submission = PressPrimer_Assignment_Submission::get( $submission_id );

		if ( ! $submission || ! $submission->user_id ) {
			return;
		}

		// Find LearnPress lessons that use this assignment.
		$lessons = $this->get_lessons_for_assignment( $submission->assignment_id );

		foreach ( $lessons as $lesson ) {
			$require_pass = get_post_meta( $lesson->ID, self::META_KEY_REQUIRE_PASS, true );

			if ( '1' !== $require_pass ) {
				continue;
			}

			$this->mark_lesson_complete( (int) $lesson->ID, (int) $submission->user_id );

			/**
			 * Fire audit log event for LMS completion triggered.
			 *
			 * Enterprise addon listens to this and writes to the audit log.
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
					'lms'           => 'learnpress',
					'object_id'     => $lesson->ID,
					'user_id'       => $submission->user_id,
					'assignment_id' => $submission->assignment_id,
					'score'         => $score,
				]
			);
		}
	}

	/**
	 * Get LearnPress lessons that use a specific assignment
	 *
	 * @since 2.0.0
	 *
	 * @param int $assignment_id Assignment ID.
	 * @return array Array of post objects.
	 */
	private function get_lessons_for_assignment( $assignment_id ) {
		$args = [
			'post_type'      => $this->get_lesson_post_type(),
			'posts_per_page' => -1,
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for LearnPress integration.
				[
					'key'   => self::META_KEY_ASSIGNMENT_ID,
					'value' => $assignment_id,
				],
			],
		];

		return get_posts( $args );
	}

	/**
	 * Mark a LearnPress lesson as complete for a user
	 *
	 * Uses the LearnPress user API (learn_press_get_user) to complete
	 * the lesson. Requires looking up the course ID for the lesson via
	 * LearnPress section tables. After lesson completion, checks whether
	 * all course items are done and, if so, finishes the course.
	 *
	 * @since 2.0.0
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @param int $user_id   User ID.
	 */
	private function mark_lesson_complete( $lesson_id, $user_id ) {
		if ( ! function_exists( 'learn_press_get_user' ) ) {
			return;
		}

		$user = learn_press_get_user( $user_id );
		if ( ! $user ) {
			return;
		}

		$course_id = $this->get_lesson_course_id( $lesson_id );
		if ( ! $course_id ) {
			return;
		}

		// Skip if already completed.
		if ( method_exists( $user, 'has_completed_item' ) && $user->has_completed_item( $lesson_id, $course_id ) ) {
			return;
		}

		if ( ! method_exists( $user, 'complete_lesson' ) ) {
			return;
		}

		$result = $user->complete_lesson( $lesson_id, $course_id );

		if ( is_wp_error( $result ) ) {
			return;
		}

		/**
		 * Fires after PPA marks a LearnPress lesson complete.
		 *
		 * @since 2.0.0
		 *
		 * @param int $lesson_id Lesson post ID.
		 * @param int $course_id Course post ID.
		 * @param int $user_id   User ID.
		 */
		do_action( 'pressprimer_assignment_learnpress_lesson_completed', $lesson_id, $course_id, $user_id );

		// Trigger auto-course-completion if all lessons are now complete.
		$this->maybe_finish_course( $course_id, $user_id );
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

			/**
			 * Fires after PPA finishes a LearnPress course.
			 *
			 * @since 2.0.0
			 *
			 * @param int $course_id Course post ID.
			 * @param int $user_id   User ID.
			 */
			do_action( 'pressprimer_assignment_learnpress_course_completed', $course_id, $user_id );
		}
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

	// =========================================================================
	// REST API.
	// =========================================================================

	/**
	 * Register REST routes
	 *
	 * @since 2.0.0
	 */
	public function register_rest_routes() {
		register_rest_route(
			'ppa/v1',
			'/learnpress/assignments/search',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'rest_search_assignments' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => [
					'search' => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'recent' => [
						'required'          => false,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

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
	}

	/**
	 * REST endpoint: Search assignments
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_search_assignments( $request ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'ppa_assignments';
		$recent = $request->get_param( 'recent' );

		if ( $recent ) {
			if ( current_user_can( 'manage_options' ) ) {
				// Admins see all published assignments.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REST search results, not suitable for caching.
				$assignments = $wpdb->get_results(
					"SELECT id, title FROM {$table} WHERE status = 'published' ORDER BY id DESC LIMIT 50" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe (wpdb prefix + constant).
				);
			} else {
				$user_id = get_current_user_id();
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REST search results, not suitable for caching.
				$assignments = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT id, title FROM {$table} WHERE status = 'published' AND author_id = %d ORDER BY id DESC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe (wpdb prefix + constant).
						$user_id
					)
				);
			}

			return new WP_REST_Response(
				[
					'success'     => true,
					'assignments' => $assignments,
				]
			);
		}

		$search = $request->get_param( 'search' );

		if ( empty( $search ) || strlen( $search ) < 2 ) {
			return new WP_REST_Response(
				[
					'success'     => true,
					'assignments' => [],
				]
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REST search results, not suitable for caching.
		$assignments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title FROM {$table} WHERE title LIKE %s AND status = 'published' ORDER BY title ASC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe (wpdb prefix + constant).
				'%' . $wpdb->esc_like( $search ) . '%'
			)
		);

		return new WP_REST_Response(
			[
				'success'     => true,
				'assignments' => $assignments,
			]
		);
	}

	/**
	 * REST endpoint: Get LearnPress integration status
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
			'integration' => 'working',
		];

		// Count how many LearnPress lessons have PPA assignments attached.
		if ( $active ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Status count query, not suitable for caching.
			$count                          = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress core table name.
					self::META_KEY_ASSIGNMENT_ID
				)
			);
			$status['attached_assignments'] = (int) $count;
		}

		return new WP_REST_Response(
			[
				'success' => true,
				'status'  => $status,
			]
		);
	}
}
