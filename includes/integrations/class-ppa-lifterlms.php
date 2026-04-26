<?php
/**
 * LifterLMS Integration
 *
 * Integrates PressPrimer Assignment with LifterLMS for seamless
 * assignment experiences within courses. Mirrors the PressPrimer Quiz
 * LifterLMS integration and the existing TutorLMS Assignment integration.
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
 * LifterLMS Integration Class
 *
 * Handles all LifterLMS integration functionality including:
 * - Meta boxes on LifterLMS lesson post type for attaching assignments
 * - Assignment display in lesson content via the_content filter
 * - Completion tracking on assignment pass (when require_pass is enabled)
 * - Lesson completion blocker via llms_is_complete filter
 * - Mark complete button hiding via llms_show_mark_complete_button filter
 *
 * @since 2.0.0
 */
class PressPrimer_Assignment_LifterLMS {

	/**
	 * Meta key for storing the PPA assignment ID on lessons
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const META_KEY_ASSIGNMENT_ID = '_ppa_lifterlms_assignment_id';

	/**
	 * Meta key for storing require pass setting
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const META_KEY_REQUIRE_PASS = '_ppa_lifterlms_require_pass';

	/**
	 * Supported post types for integration
	 *
	 * LifterLMS lessons only — courses are completed via lesson cascade.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	private $supported_post_types = [ 'lesson' ];

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
		// Only initialize if LifterLMS is active.
		if ( ! defined( 'LLMS_PLUGIN_FILE' ) ) {
			return;
		}

		// Admin hooks.
		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_meta_box' ], 10, 2 );

		// AJAX handler for assignment search.
		add_action( 'wp_ajax_pressprimer_assignment_search_assignments_lifterlms', [ $this, 'ajax_search_assignments' ] );

		// Map LifterLMS Instructor role to PPA teacher capabilities.
		$this->map_instructor_capabilities();
		add_filter( 'pressprimer_assignment_user_has_teacher_capability', [ $this, 'check_instructor_capability' ], 10, 2 );

		// Frontend rendering — three-pronged approach to match PressPrimer Quiz:
		// 1. Primary: classic LifterLMS lesson templates render via lesson buttons hook.
		add_action( 'llms_before_lesson_buttons', [ $this, 'display_assignment_in_lesson' ], 10, 2 );

		// 2. Block-based lessons: render before the navigation block.
		add_action( 'llms_lesson-navigation_block_render', [ $this, 'display_assignment_before_navigation_block' ], 5 );

		// 3. Fallback: the_content filter for any template that doesn't fire the above.
		add_filter( 'the_content', [ $this, 'append_assignment_to_lesson_content' ], 20 );

		// Hide LifterLMS mark complete button when assignment requires passing.
		add_filter( 'llms_show_mark_complete_button', [ $this, 'maybe_hide_complete_button' ], 10, 2 );

		// Blocker: prevent llms_is_complete from reporting true until assignment passed.
		add_filter( 'llms_is_complete', [ $this, 'maybe_prevent_lesson_completion' ], 10, 4 );

		// Completion tracking.
		add_action( 'pressprimer_assignment_submission_passed', [ $this, 'handle_assignment_passed' ], 10, 2 );

		// REST API endpoints.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	// =========================================================================
	// Meta Box.
	// =========================================================================

	/**
	 * Register meta boxes for LifterLMS post types
	 *
	 * Registered unconditionally — WordPress renders classic metaboxes
	 * inside the Gutenberg Document panel as a legacy meta box section,
	 * giving a consistent UI with PressPrimer Quiz's LifterLMS metabox.
	 *
	 * @since 2.0.0
	 */
	public function register_meta_boxes() {
		foreach ( $this->supported_post_types as $post_type ) {
			add_meta_box(
				'ppa_lifterlms_assignment',
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
		wp_nonce_field( 'pressprimer_assignment_lifterlms_meta_box', 'pressprimer_assignment_lifterlms_nonce' );

		$assignment_id = get_post_meta( $post->ID, self::META_KEY_ASSIGNMENT_ID, true );
		$require_pass  = get_post_meta( $post->ID, self::META_KEY_REQUIRE_PASS, true );

		// Get assignment display label if one is selected.
		$assignment_display = '';
		if ( $assignment_id ) {
			$assignment         = PressPrimer_Assignment_Assignment::get( $assignment_id );
			$assignment_display = $assignment ? sprintf( '%d - %s', $assignment->id, $assignment->title ) : '';
		}
		?>
		<div class="ppa-lifterlms-meta-box">
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
			'pressprimerAssignmentLifterLMSMetaBox',
			[
				'nonce'   => wp_create_nonce( 'pressprimer_assignment_lifterlms_search' ),
				'strings' => [
					'noAssignmentsFound' => __( 'No assignments found', 'pressprimer-assignment' ),
					'removeAssignment'   => __( 'Remove assignment', 'pressprimer-assignment' ),
				],
			]
		);

		// Inline CSS for meta box styling.
		$inline_css = '
			.ppa-lifterlms-meta-box .ppa-assignment-selector {
				position: relative;
				display: flex;
				align-items: center;
				gap: 4px;
			}
			.ppa-lifterlms-meta-box .ppa-assignment-search {
				flex: 1;
			}
			.ppa-lifterlms-meta-box .ppa-assignment-search[readonly] {
				background: #f0f6fc;
				cursor: default;
			}
			.ppa-lifterlms-meta-box .ppa-assignment-results {
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
			.ppa-lifterlms-meta-box .ppa-assignment-result-item {
				padding: 8px 12px;
				cursor: pointer;
				border-bottom: 1px solid #f0f0f0;
			}
			.ppa-lifterlms-meta-box .ppa-assignment-result-item:hover {
				background: #f0f0f0;
			}
			.ppa-lifterlms-meta-box .ppa-assignment-result-item:last-child {
				border-bottom: none;
			}
			.ppa-lifterlms-meta-box .ppa-remove-assignment {
				color: #d63638;
				text-decoration: none;
				padding: 4px;
				border: none;
				background: none;
				cursor: pointer;
				display: flex;
				align-items: center;
			}
			.ppa-lifterlms-meta-box .ppa-remove-assignment:hover {
				color: #b32d2e;
			}
			.ppa-lifterlms-meta-box .ppa-no-results {
				padding: 12px;
				color: #666;
				font-style: italic;
			}
			.ppa-lifterlms-meta-box .ppa-assignment-result-item .ppa-assignment-id {
				color: #666;
				font-weight: 600;
				margin-right: 4px;
			}
		';
		wp_add_inline_style( 'ppa-admin', $inline_css );

		// Inline JavaScript for meta box functionality.
		$inline_script = 'jQuery(document).ready(function($) {' .
			'var config = window.pressprimerAssignmentLifterLMSMetaBox || {};' .
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
						'action: "pressprimer_assignment_search_assignments_lifterlms",' .
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
							'action: "pressprimer_assignment_search_assignments_lifterlms",' .
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
		if ( ! isset( $_POST['pressprimer_assignment_lifterlms_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pressprimer_assignment_lifterlms_nonce'] ) ), 'pressprimer_assignment_lifterlms_meta_box' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check post type.
		if ( ! in_array( $post->post_type, $this->supported_post_types, true ) ) {
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
		if ( ! check_ajax_referer( 'pressprimer_assignment_lifterlms_search', 'nonce', false ) ) {
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
	 * Display assignment on the lesson page via the lesson buttons hook.
	 *
	 * Primary rendering hook for classic LifterLMS lesson templates. Fires
	 * inside the lesson buttons wrapper, before the Mark Complete button,
	 * placing the assignment between the lesson content and navigation.
	 * LifterLMS handles enrollment checks before firing this hook.
	 *
	 * @since 2.0.0
	 *
	 * @param LLMS_Lesson  $lesson  Current lesson object.
	 * @param LLMS_Student $student Current student object.
	 */
	public function display_assignment_in_lesson( $lesson, $student ) {
		if ( ! $lesson || ! is_a( $lesson, 'LLMS_Lesson' ) ) {
			return;
		}

		$lesson_id     = (int) $lesson->get( 'id' );
		$assignment_id = get_post_meta( $lesson_id, self::META_KEY_ASSIGNMENT_ID, true );

		if ( ! $assignment_id ) {
			return;
		}

		// Mark as rendered to prevent duplicate output from the content fallback.
		$this->lesson_assignment_rendered = true;

		$assignment_shortcode = sprintf(
			'[pressprimer_assignment id="%d"]',
			absint( $assignment_id )
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output is escaped internally.
		echo '<div class="ppa-lifterlms-assignment-wrapper">' . do_shortcode( $assignment_shortcode ) . '</div>';
	}

	/**
	 * Display assignment before the lesson navigation block (block editor lessons).
	 *
	 * Fires on the LifterLMS lesson-navigation block render hook at priority
	 * 5 so the assignment renders before the navigation block appears. Used
	 * for lessons built with the block editor that include the LifterLMS
	 * Lesson Navigation block.
	 *
	 * @since 2.0.0
	 */
	public function display_assignment_before_navigation_block() {
		if ( $this->lesson_assignment_rendered ) {
			return;
		}

		global $post;

		if ( ! $post || 'lesson' !== $post->post_type ) {
			return;
		}

		$assignment_id = get_post_meta( $post->ID, self::META_KEY_ASSIGNMENT_ID, true );
		if ( ! $assignment_id ) {
			return;
		}

		if ( ! function_exists( 'llms_get_post' ) ) {
			return;
		}

		$lesson = llms_get_post( $post );
		if ( ! $lesson || ! is_a( $lesson, 'LLMS_Lesson' ) ) {
			return;
		}

		// Enrollment check — LifterLMS does not gate this hook automatically.
		$user_id       = get_current_user_id();
		$parent_course = $lesson->get( 'parent_course' );

		if ( ! $this->is_user_enrolled( $user_id, (int) $parent_course ) && ! current_user_can( 'edit_post', $lesson->get( 'id' ) ) ) {
			echo '<div class="ppa-access-denied">';
			esc_html_e( 'Enroll in this course to access the assignment.', 'pressprimer-assignment' );
			echo '</div>';
			return;
		}

		$this->lesson_assignment_rendered = true;

		$assignment_shortcode = sprintf(
			'[pressprimer_assignment id="%d"]',
			absint( $assignment_id )
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output is escaped internally.
		echo '<div class="ppa-lifterlms-assignment-wrapper">' . do_shortcode( $assignment_shortcode ) . '</div>';
	}

	/**
	 * Append assignment to lesson content via the_content filter (fallback).
	 *
	 * Only fires if neither the primary lesson buttons hook nor the block
	 * navigation hook rendered the assignment. Skips rendering when the
	 * LifterLMS navigation block is present in the post content so the
	 * block render hook can handle placement.
	 *
	 * @since 2.0.0
	 *
	 * @param string $content The post content.
	 * @return string Modified content with assignment appended.
	 */
	public function append_assignment_to_lesson_content( $content ) {
		if ( ! is_singular( 'lesson' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		if ( $this->lesson_assignment_rendered ) {
			return $content;
		}

		global $post;
		if ( ! $post ) {
			return $content;
		}

		// If the navigation block exists on this lesson, let the block hook render.
		if ( function_exists( 'has_block' ) && has_block( 'llms/lesson-navigation', $post ) ) {
			return $content;
		}

		$assignment_id = get_post_meta( $post->ID, self::META_KEY_ASSIGNMENT_ID, true );
		if ( ! $assignment_id ) {
			return $content;
		}

		if ( ! function_exists( 'llms_get_post' ) ) {
			return $content;
		}

		$lesson = llms_get_post( $post );
		if ( ! $lesson || ! is_a( $lesson, 'LLMS_Lesson' ) ) {
			return $content;
		}

		// Enrollment check.
		$user_id       = get_current_user_id();
		$parent_course = $lesson->get( 'parent_course' );

		if ( ! $this->is_user_enrolled( $user_id, (int) $parent_course ) && ! current_user_can( 'edit_post', $lesson->get( 'id' ) ) ) {
			$content .= '<div class="ppa-access-denied">';
			$content .= esc_html__( 'Enroll in this course to access the assignment.', 'pressprimer-assignment' );
			$content .= '</div>';
			return $content;
		}

		$this->lesson_assignment_rendered = true;

		$assignment_shortcode = sprintf(
			'[pressprimer_assignment id="%d"]',
			absint( $assignment_id )
		);

		$content .= '<div class="ppa-lifterlms-assignment-wrapper">';
		$content .= do_shortcode( $assignment_shortcode );
		$content .= '</div>';

		return $content;
	}

	/**
	 * Check if a user is enrolled in a LifterLMS course.
	 *
	 * @since 2.0.0
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id LifterLMS course ID.
	 * @return bool True if enrolled, false otherwise.
	 */
	private function is_user_enrolled( $user_id, $course_id ) {
		if ( ! $user_id || ! $course_id ) {
			return false;
		}

		if ( ! function_exists( 'llms_is_user_enrolled' ) ) {
			return false;
		}

		return (bool) llms_is_user_enrolled( $user_id, $course_id );
	}

	// =========================================================================
	// Completion Blocker & Button Hide.
	// =========================================================================

	/**
	 * Maybe hide the LifterLMS mark complete button when assignment is attached.
	 *
	 * Only hides the button when an assignment is attached, "Require passing
	 * grade" is enabled, and the user has not yet passed the assignment or
	 * already marked the lesson complete.
	 *
	 * LifterLMS passes an LLMS_Lesson object as the second argument, not a
	 * post ID — matching the `llms_show_mark_complete_button` filter signature.
	 *
	 * @since 2.0.0
	 *
	 * @param bool        $show   Whether to show the button.
	 * @param LLMS_Lesson $lesson Current lesson object.
	 * @return bool Modified show decision.
	 */
	public function maybe_hide_complete_button( $show, $lesson ) {
		if ( ! $show || ! $lesson || ! is_a( $lesson, 'LLMS_Lesson' ) ) {
			return $show;
		}

		$lesson_id     = (int) $lesson->get( 'id' );
		$assignment_id = get_post_meta( $lesson_id, self::META_KEY_ASSIGNMENT_ID, true );

		if ( ! $assignment_id ) {
			return $show;
		}

		$require_pass = get_post_meta( $lesson_id, self::META_KEY_REQUIRE_PASS, true );
		if ( '1' !== $require_pass ) {
			return $show;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $show;
		}

		// If the lesson is already complete, keep the button visible.
		if ( function_exists( 'llms_is_complete' ) && llms_is_complete( $user_id, $lesson_id, 'lesson' ) ) {
			return $show;
		}

		// If the user has already passed the assignment, keep the button.
		if ( $this->has_user_passed_assignment( $user_id, (int) $assignment_id ) ) {
			return $show;
		}

		return false;
	}

	/**
	 * Prevent llms_is_complete from reporting true until the assignment is passed.
	 *
	 * Fires on LifterLMS's llms_is_complete filter. For lesson objects where
	 * an assignment with require_pass is attached and the user has not yet
	 * passed it, force the completion state to false. This prevents LifterLMS
	 * from advancing the student past the lesson.
	 *
	 * @since 2.0.0
	 *
	 * @param bool   $completed Whether LifterLMS reports the object complete.
	 * @param int    $user_id   User ID.
	 * @param int    $object_id LifterLMS object ID.
	 * @param string $type      Object type ('lesson', 'section', 'course', ...).
	 * @return bool Modified completion state.
	 */
	public function maybe_prevent_lesson_completion( $completed, $user_id, $object_id, $type ) {
		if ( ! $completed ) {
			return $completed;
		}

		if ( 'lesson' !== $type ) {
			return $completed;
		}

		$assignment_id = get_post_meta( $object_id, self::META_KEY_ASSIGNMENT_ID, true );
		if ( ! $assignment_id ) {
			return $completed;
		}

		$require_pass = get_post_meta( $object_id, self::META_KEY_REQUIRE_PASS, true );
		if ( '1' !== $require_pass ) {
			return $completed;
		}

		if ( $user_id && $this->has_user_passed_assignment( (int) $user_id, (int) $assignment_id ) ) {
			return $completed;
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
	 * Called when a student passes an assignment. Finds LifterLMS lessons
	 * linked to this assignment with require_pass enabled and marks them
	 * complete for the user.
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

		// Find LifterLMS lessons that use this assignment.
		$lessons = $this->get_lessons_for_assignment( $submission->assignment_id );

		foreach ( $lessons as $lesson ) {
			$require_pass = get_post_meta( $lesson->ID, self::META_KEY_REQUIRE_PASS, true );

			if ( '1' !== $require_pass ) {
				continue;
			}

			$this->mark_lesson_complete( $lesson->ID, (int) $submission->user_id );

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
					'lms'           => 'lifterlms',
					'object_id'     => $lesson->ID,
					'user_id'       => $submission->user_id,
					'assignment_id' => $submission->assignment_id,
					'score'         => $score,
				]
			);
		}
	}

	/**
	 * Get LifterLMS lessons that use a specific assignment
	 *
	 * @since 2.0.0
	 *
	 * @param int $assignment_id Assignment ID.
	 * @return array Array of post objects.
	 */
	private function get_lessons_for_assignment( $assignment_id ) {
		$args = [
			'post_type'      => 'lesson',
			'posts_per_page' => -1,
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for LifterLMS integration.
				[
					'key'   => self::META_KEY_ASSIGNMENT_ID,
					'value' => $assignment_id,
				],
			],
		];

		return get_posts( $args );
	}

	/**
	 * Mark a LifterLMS lesson as complete for a user
	 *
	 * Uses LifterLMS's llms_mark_complete() function. Verifies the lesson
	 * has a valid parent section before calling, because LifterLMS cascades
	 * completion upward (lesson -> section -> course) and orphaned lessons
	 * trigger a fatal error.
	 *
	 * @since 2.0.0
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @param int $user_id   User ID.
	 */
	private function mark_lesson_complete( $lesson_id, $user_id ) {
		if ( ! function_exists( 'llms_mark_complete' ) ) {
			return;
		}

		// Check if already complete.
		if ( function_exists( 'llms_is_complete' ) && llms_is_complete( $user_id, $lesson_id, 'lesson' ) ) {
			return;
		}

		// Verify the lesson has a valid parent section.
		// LifterLMS cascades completion upward (lesson -> section -> course),
		// so if _llms_parent_section is missing, the cascade triggers a fatal error.
		$parent_section = get_post_meta( $lesson_id, '_llms_parent_section', true );
		if ( empty( $parent_section ) ) {
			return;
		}

		llms_mark_complete( $user_id, $lesson_id, 'lesson' );

		/**
		 * Fires after PPA marks a LifterLMS lesson complete.
		 *
		 * @since 2.0.0
		 *
		 * @param int $lesson_id Lesson post ID.
		 * @param int $user_id   User ID.
		 */
		do_action( 'pressprimer_assignment_lifterlms_lesson_completed', $lesson_id, $user_id );
	}

	// =========================================================================
	// Instructor Role Mapping.
	// =========================================================================

	/**
	 * Map LifterLMS Instructor capabilities to PPA teacher capabilities
	 *
	 * Grants own-tier assignment management capabilities to the
	 * LifterLMS instructor and instructors_assistant roles so they
	 * can create and manage their own assignments. Matches the
	 * TutorLMS / LearnDash integration pattern of granting _own
	 * capabilities, not _all.
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

			// Remove legacy manage_all if previously granted.
			if ( $role->has_cap( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
				$role->remove_cap( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL );
			}

			// Remove legacy short-prefix caps that earlier builds of this
			// integration granted but nothing in the codebase ever checked.
			$legacy_orphan_caps = [
				'ppa_view_assignments',
				'ppa_create_assignments',
				'ppa_edit_assignments',
				'ppa_delete_assignments',
				'ppa_grade_submissions',
				'ppa_view_submissions',
				'ppa_view_reports',
			];
			foreach ( $legacy_orphan_caps as $legacy_cap ) {
				if ( $role->has_cap( $legacy_cap ) ) {
					$role->remove_cap( $legacy_cap );
				}
			}

			// Grant own-tier capabilities.
			if ( ! $role->has_cap( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_OWN ) ) {
				$role->add_cap( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_OWN );
			}
			if ( ! $role->has_cap( PressPrimer_Assignment_Capabilities::PPA_CAP_VIEW_REPORTS ) ) {
				$role->add_cap( PressPrimer_Assignment_Capabilities::PPA_CAP_VIEW_REPORTS );
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
			'/lifterlms/assignments/search',
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
			'/lifterlms/status',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'rest_get_status' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
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
	 * REST endpoint: Get LifterLMS integration status
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_get_status( $request ) {
		$active = defined( 'LLMS_PLUGIN_FILE' );

		$status = [
			'active'      => $active,
			'version'     => defined( 'LLMS_VERSION' ) ? LLMS_VERSION : null,
			'integration' => 'working',
		];

		// Count how many LifterLMS lessons have PPA assignments attached.
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
