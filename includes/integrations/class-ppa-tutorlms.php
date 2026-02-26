<?php
/**
 * TutorLMS Integration
 *
 * Integrates PressPrimer Assignment with TutorLMS for seamless
 * assignment experiences within courses.
 *
 * @package PressPrimer_Assignment
 * @subpackage Integrations
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TutorLMS Integration Class
 *
 * Handles all TutorLMS integration functionality including:
 * - Meta boxes for lesson post type
 * - Assignment display in lesson content
 * - Completion tracking on assignment pass
 * - Access control respecting TutorLMS enrollment
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_TutorLMS {

	/**
	 * Meta key for storing the PPA assignment ID on lessons
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY_ASSIGNMENT_ID = '_ppa_tutorlms_assignment_id';

	/**
	 * Meta key for storing require pass setting
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY_REQUIRE_PASS = '_ppa_tutorlms_require_pass';

	/**
	 * Supported post types for integration
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $supported_post_types = [ 'lesson' ];

	/**
	 * Whether the lesson assignment has already been rendered via the_content filter.
	 *
	 * Prevents double-rendering since Tutor LMS calls the_content() via
	 * tutor_load_template() outside the standard WordPress loop.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $lesson_assignment_rendered = false;

	/**
	 * Initialize the integration
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Only initialize if TutorLMS is active.
		if ( ! defined( 'TUTOR_VERSION' ) ) {
			return;
		}

		// Admin hooks.
		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_meta_box' ], 10, 2 );

		// AJAX handler for classic editor assignment search.
		add_action( 'wp_ajax_pressprimer_assignment_search_assignments_tutorlms', [ $this, 'ajax_search_assignments' ] );

		// Gutenberg support.
		add_action( 'init', [ $this, 'register_meta_fields' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );

		// Course builder support.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_course_builder_assets' ] );

		// Map TutorLMS Instructor role to PPA teacher capabilities.
		$this->map_instructor_capabilities();
		add_filter( 'pressprimer_assignment_user_has_teacher_capability', [ $this, 'check_instructor_capability' ], 10, 2 );

		// Frontend hooks — append assignment to lesson content via the_content filter.
		// Tutor LMS calls the_content() inside tutor_load_template() which does
		// NOT use the WordPress loop, so in_the_loop()/is_main_query() are false.
		add_filter( 'the_content', [ $this, 'append_assignment_to_lesson_content' ], 20 );

		// Ensure the Overview tab is shown for empty lessons that have an assignment mapped.
		// Without this, Tutor LMS hides the tab (and never calls the_content) when
		// the lesson post_content is empty and the user is not an admin.
		add_filter( 'tutor_has_lesson_content', [ $this, 'force_lesson_content_for_assignment' ], 10, 2 );

		// Hide Tutor's mark complete button when assignment is attached.
		add_filter( 'tutor_lesson/single/complete_form', [ $this, 'maybe_hide_complete_button' ] );

		// Completion tracking.
		add_action( 'pressprimer_assignment_submission_passed', [ $this, 'handle_assignment_passed' ], 10, 2 );

		// REST API endpoints.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/**
	 * Register meta boxes for TutorLMS post types
	 *
	 * Only registers for Classic Editor - Gutenberg uses the sidebar panel.
	 *
	 * @since 1.0.0
	 */
	public function register_meta_boxes() {
		// Don't register metabox if using block editor.
		$screen = get_current_screen();
		if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			return;
		}

		foreach ( $this->supported_post_types as $post_type ) {
			add_meta_box(
				'ppa_tutorlms_assignment',
				__( 'PressPrimer Assignment', 'pressprimer-assignment' ),
				[ $this, 'render_meta_box' ],
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render the meta box
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'pressprimer_assignment_tutorlms_meta_box', 'pressprimer_assignment_tutorlms_nonce' );

		$assignment_id = get_post_meta( $post->ID, self::META_KEY_ASSIGNMENT_ID, true );
		$require_pass  = get_post_meta( $post->ID, self::META_KEY_REQUIRE_PASS, true );

		// Get assignment display label if one is selected.
		$assignment_display = '';
		if ( $assignment_id ) {
			$assignment         = PressPrimer_Assignment_Assignment::get( $assignment_id );
			$assignment_display = $assignment ? sprintf( '%d - %s', $assignment->id, $assignment->title ) : '';
		}
		?>
		<div class="ppa-tutorlms-meta-box">
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
					name="ppa_assignment_id"
					value="<?php echo esc_attr( $assignment_id ); ?>"
				/>
				<div id="ppa_assignment_results" class="ppa-assignment-results" style="display: none;"></div>
				<?php if ( $assignment_id ) : ?>
					<button type="button" class="ppa-remove-assignment button-link" aria-label="<?php esc_attr_e( 'Remove assignment', 'pressprimer-assignment' ); ?>" title="<?php esc_attr_e( 'Remove assignment', 'pressprimer-assignment' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				<?php endif; ?>
			</div>

			<p style="margin-top: 12px;">
				<label>
					<input
						type="checkbox"
						name="ppa_require_pass"
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
	 * @since 1.0.0
	 */
	private function enqueue_meta_box_assets() {
		// Ensure admin scripts are enqueued (they may not be on LMS post type edit screens).
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
			'pressprimerAssignmentTutorLMSMetaBox',
			[
				'nonce'   => wp_create_nonce( 'pressprimer_assignment_tutorlms_search' ),
				'strings' => [
					'noAssignmentsFound' => __( 'No assignments found', 'pressprimer-assignment' ),
					'removeAssignment'   => __( 'Remove assignment', 'pressprimer-assignment' ),
				],
			]
		);

		// Inline CSS for meta box styling.
		$inline_css = '
			.ppa-tutorlms-meta-box .ppa-assignment-selector {
				position: relative;
				display: flex;
				align-items: center;
				gap: 4px;
			}
			.ppa-tutorlms-meta-box .ppa-assignment-search {
				flex: 1;
			}
			.ppa-tutorlms-meta-box .ppa-assignment-search[readonly] {
				background: #f0f6fc;
				cursor: default;
			}
			.ppa-tutorlms-meta-box .ppa-assignment-results {
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
			.ppa-tutorlms-meta-box .ppa-assignment-result-item {
				padding: 8px 12px;
				cursor: pointer;
				border-bottom: 1px solid #f0f0f0;
			}
			.ppa-tutorlms-meta-box .ppa-assignment-result-item:hover {
				background: #f0f0f0;
			}
			.ppa-tutorlms-meta-box .ppa-assignment-result-item:last-child {
				border-bottom: none;
			}
			.ppa-tutorlms-meta-box .ppa-remove-assignment {
				color: #d63638;
				text-decoration: none;
				padding: 4px;
				border: none;
				background: none;
				cursor: pointer;
				display: flex;
				align-items: center;
			}
			.ppa-tutorlms-meta-box .ppa-remove-assignment:hover {
				color: #b32d2e;
			}
			.ppa-tutorlms-meta-box .ppa-no-results {
				padding: 12px;
				color: #666;
				font-style: italic;
			}
			.ppa-tutorlms-meta-box .ppa-assignment-result-item .ppa-assignment-id {
				color: #666;
				font-weight: 600;
				margin-right: 4px;
			}
		';
		wp_add_inline_style( 'ppa-admin', $inline_css );

		// Inline JavaScript for meta box functionality.
		$inline_script = 'jQuery(document).ready(function($) {' .
			'var config = window.pressprimerAssignmentTutorLMSMetaBox || {};' .
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
						'action: "pressprimer_assignment_search_assignments_tutorlms",' .
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
							'action: "pressprimer_assignment_search_assignments_tutorlms",' .
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
	 * @since 1.0.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_meta_box( $post_id, $post ) {
		// Verify nonce.
		if ( ! isset( $_POST['pressprimer_assignment_tutorlms_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pressprimer_assignment_tutorlms_nonce'] ) ), 'pressprimer_assignment_tutorlms_meta_box' ) ) {
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
		$assignment_id = isset( $_POST['ppa_assignment_id'] ) ? absint( wp_unslash( $_POST['ppa_assignment_id'] ) ) : 0;
		if ( $assignment_id ) {
			update_post_meta( $post_id, self::META_KEY_ASSIGNMENT_ID, $assignment_id );
		} else {
			delete_post_meta( $post_id, self::META_KEY_ASSIGNMENT_ID );
		}

		// Save require pass setting.
		$require_pass = isset( $_POST['ppa_require_pass'] ) ? '1' : '';
		update_post_meta( $post_id, self::META_KEY_REQUIRE_PASS, $require_pass );
	}

	/**
	 * Register meta fields for Gutenberg
	 *
	 * @since 1.0.0
	 */
	public function register_meta_fields() {
		foreach ( $this->supported_post_types as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_KEY_ASSIGNMENT_ID,
				[
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'integer',
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				]
			);

			register_post_meta(
				$post_type,
				self::META_KEY_REQUIRE_PASS,
				[
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'string',
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				]
			);
		}
	}

	/**
	 * Enqueue block editor assets
	 *
	 * @since 1.0.0
	 */
	public function enqueue_block_editor_assets() {
		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->post_type, $this->supported_post_types, true ) ) {
			return;
		}

		wp_enqueue_script(
			'ppa-tutorlms-editor',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/js/tutorlms-editor.js',
			[ 'wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ],
			PRESSPRIMER_ASSIGNMENT_VERSION,
			true
		);

		wp_localize_script(
			'ppa-tutorlms-editor',
			'pressprimerAssignmentTutorLMS',
			[
				'metaKeyAssignmentId' => self::META_KEY_ASSIGNMENT_ID,
				'metaKeyRequirePass'  => self::META_KEY_REQUIRE_PASS,
				'postType'            => $screen->post_type,
				'restNonce'           => wp_create_nonce( 'wp_rest' ),
				'strings'             => [
					'panelTitle'        => __( 'PressPrimer Assignment', 'pressprimer-assignment' ),
					'selectAssignment'  => __( 'Select Assignment', 'pressprimer-assignment' ),
					'searchPlaceholder' => __( 'Click to browse or type to search...', 'pressprimer-assignment' ),
					'noAssignment'      => __( 'No assignment selected', 'pressprimer-assignment' ),
					'requirePassLabel'  => __( 'Require passing grade to complete lesson', 'pressprimer-assignment' ),
					'requirePassHelp'   => __( 'When enabled, students must pass this assignment to mark the lesson complete.', 'pressprimer-assignment' ),
				],
			]
		);
	}

	/**
	 * Enqueue course builder assets
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_course_builder_assets( $hook ) {
		// Only load on TutorLMS course builder page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'tutor_page_create-course' !== $hook && ! isset( $_GET['page'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'create-course' !== $page ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$course_id = isset( $_GET['course_id'] ) ? absint( wp_unslash( $_GET['course_id'] ) ) : 0;

		// Get existing lesson assignment associations for this course.
		$lesson_assignments = $this->get_lesson_assignments_for_course( $course_id );

		wp_enqueue_script(
			'ppa-tutorlms-course-builder',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/js/tutorlms-course-builder.js',
			[ 'wp-element' ],
			PRESSPRIMER_ASSIGNMENT_VERSION,
			true
		);

		wp_localize_script(
			'ppa-tutorlms-course-builder',
			'pressprimerAssignmentTutorCourseBuilder',
			[
				'courseId'          => $course_id,
				'restUrl'           => rest_url(),
				'restNonce'         => wp_create_nonce( 'wp_rest' ),
				'adminUrl'          => admin_url(),
				'lessonAssignments' => $lesson_assignments,
				'strings'           => [
					'searchPlaceholder' => __( 'Search assignments...', 'pressprimer-assignment' ),
					'noAssignments'     => __( 'No assignments found', 'pressprimer-assignment' ),
					'error'             => __( 'Error loading assignments', 'pressprimer-assignment' ),
				],
			]
		);
	}

	/**
	 * Get lesson assignment associations for a course
	 *
	 * @since 1.0.0
	 *
	 * @param int $course_id Course ID.
	 * @return array Associative array of lesson_id => assignment data.
	 */
	private function get_lesson_assignments_for_course( $course_id ) {
		if ( ! $course_id ) {
			return [];
		}

		$lesson_assignments = [];

		// Get all topics for this course (lessons are children of topics in Tutor LMS).
		$topics = get_posts(
			[
				'post_type'      => 'topics',
				'post_parent'    => $course_id,
				'posts_per_page' => -1,
				'post_status'    => 'any',
			]
		);

		foreach ( $topics as $topic ) {
			$lessons = get_posts(
				[
					'post_type'      => 'lesson',
					'post_parent'    => $topic->ID,
					'posts_per_page' => -1,
					'post_status'    => 'any',
				]
			);

			foreach ( $lessons as $lesson ) {
				$assignment_id = get_post_meta( $lesson->ID, self::META_KEY_ASSIGNMENT_ID, true );
				if ( $assignment_id ) {
					$assignment = PressPrimer_Assignment_Assignment::get( $assignment_id );
					if ( $assignment ) {
						$lesson_assignments[ $lesson->ID ] = [
							'id'    => $assignment->id,
							'title' => $assignment->title,
						];
					}
				}
			}
		}

		return $lesson_assignments;
	}

	/**
	 * AJAX handler for searching assignments
	 *
	 * @since 1.0.0
	 */
	public function ajax_search_assignments() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'pressprimer_assignment_tutorlms_search', 'nonce', false ) ) {
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

	/**
	 * Append assignment to lesson content via the_content filter.
	 *
	 * Tutor LMS renders lesson text via the_content() inside its own template
	 * loader (tutor_load_template) which bypasses the WordPress loop. This means
	 * in_the_loop() and is_main_query() return false. Additionally, Tutor LMS
	 * spotlight mode loads lesson content via AJAX (tutor_render_lesson_content),
	 * where is_singular() returns false entirely. We guard with a post_type
	 * check on the current post instead, which works in both contexts.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The post content.
	 * @return string Modified content with assignment appended.
	 */
	public function append_assignment_to_lesson_content( $content ) {
		// Only for Tutor LMS lesson posts. We check $post->post_type instead of
		// is_singular('lesson') because Tutor's spotlight mode loads lessons via
		// AJAX where the main WP query is not a singular lesson query.
		$post = get_post();
		if ( ! $post || 'lesson' !== $post->post_type ) {
			return $content;
		}

		// Prevent double-rendering (the_content can fire multiple times).
		if ( $this->lesson_assignment_rendered ) {
			return $content;
		}

		$post_id       = get_the_ID();
		$assignment_id = get_post_meta( $post_id, self::META_KEY_ASSIGNMENT_ID, true );

		if ( ! $assignment_id ) {
			return $content;
		}

		// Mark as rendered before processing.
		$this->lesson_assignment_rendered = true;

		// Check if user is enrolled in the course.
		$course_id = $this->get_course_id_for_lesson( $post_id );
		$user_id   = get_current_user_id();

		if ( $course_id && ! $this->is_user_enrolled( $user_id, $course_id ) ) {
			$content .= '<div class="ppa-tutorlms-access-denied">';
			$content .= '<p>' . esc_html__( 'Enroll in this course to access the assignment.', 'pressprimer-assignment' ) . '</p>';
			$content .= '</div>';
			return $content;
		}

		// Render assignment shortcode.
		$assignment_shortcode = sprintf(
			'[ppa_assignment id="%d"]',
			absint( $assignment_id )
		);

		$content .= '<div class="ppa-tutorlms-assignment-wrapper">';
		$content .= do_shortcode( $assignment_shortcode );
		$content .= '</div>';

		return $content;
	}

	/**
	 * Force Tutor LMS to show the Overview tab when an assignment is mapped.
	 *
	 * Tutor's Lesson::has_lesson_content() returns false for non-admin users
	 * when the lesson post_content is empty. This causes the Overview tab
	 * (and its the_content() call) to be hidden, preventing our assignment from
	 * rendering. We override it to true when a PPA assignment is attached.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $has_content Whether Tutor thinks the lesson has content.
	 * @param int  $lesson_id   Lesson post ID.
	 * @return bool True if an assignment is mapped, original value otherwise.
	 */
	public function force_lesson_content_for_assignment( $has_content, $lesson_id ) {
		if ( $has_content ) {
			return $has_content;
		}

		$assignment_id = get_post_meta( $lesson_id, self::META_KEY_ASSIGNMENT_ID, true );

		return ! empty( $assignment_id );
	}

	/**
	 * Maybe hide the complete button when assignment is attached
	 *
	 * @since 1.0.0
	 *
	 * @param string $form Complete form HTML.
	 * @return string Modified form HTML.
	 */
	public function maybe_hide_complete_button( $form ) {
		$post_id       = get_the_ID();
		$assignment_id = get_post_meta( $post_id, self::META_KEY_ASSIGNMENT_ID, true );

		if ( $assignment_id ) {
			// Only hide the complete button if "Require passing grade" is enabled.
			$require_pass = get_post_meta( $post_id, self::META_KEY_REQUIRE_PASS, true );

			if ( '1' === $require_pass ) {
				// If the lesson is already marked complete, don't hide the button.
				if ( function_exists( 'tutor_utils' ) ) {
					$completed = tutor_utils()->is_completed_lesson( $post_id, get_current_user_id() );
					if ( false !== $completed ) {
						return $form;
					}
				}

				// If the user has already passed this assignment, don't hide the button.
				$user_id = get_current_user_id();
				if ( $user_id && $this->has_user_passed_assignment( $user_id, (int) $assignment_id ) ) {
					return $form;
				}

				// Return empty to hide the complete button - user must pass assignment.
				return '';
			}
		}

		return $form;
	}

	/**
	 * Check if a user has passed a specific assignment
	 *
	 * @since 1.0.0
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

	/**
	 * Check if a PressPrimer Quiz requirement on the same lesson is satisfied
	 *
	 * When both PressPrimer Quiz and PressPrimer Assignment are attached to the
	 * same TutorLMS lesson with require_pass enabled, neither plugin should
	 * complete the lesson until BOTH requirements are met.
	 *
	 * @since 1.0.0
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @param int $user_id   User ID.
	 * @return bool True if no quiz requirement exists, or if the quiz has been passed.
	 */
	private function is_quiz_requirement_satisfied( $lesson_id, $user_id ) {
		// Check if PressPrimer Quiz is also attached with require_pass.
		$quiz_id      = get_post_meta( $lesson_id, '_ppq_tutorlms_quiz_id', true );
		$quiz_require = get_post_meta( $lesson_id, '_ppq_tutorlms_require_pass', true );

		// No quiz requirement — Assignment can proceed with completion.
		if ( ! $quiz_id || '1' !== $quiz_require ) {
			return true;
		}

		// Quiz is required. Check if the user has passed it.
		// Use Quiz's class if available, otherwise query the table directly.
		if ( class_exists( 'PressPrimer_Quiz_TutorLMS' ) && method_exists( 'PressPrimer_Quiz_TutorLMS', 'user_has_passed_quiz_static' ) ) {
			return PressPrimer_Quiz_TutorLMS::user_has_passed_quiz_static( (int) $quiz_id, $user_id );
		}

		// Fallback: query ppq_attempts table directly.
		global $wpdb;
		$table = $wpdb->prefix . 'ppq_attempts';

		// Check table exists before querying.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cross-plugin check, not suitable for caching.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		if ( ! $table_exists ) {
			// Quiz plugin tables don't exist — no quiz requirement to enforce.
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cross-plugin check, not suitable for caching.
		$passed = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE quiz_id = %d AND user_id = %d AND status = 'submitted' AND passed = 1 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe (wpdb prefix + constant).
				(int) $quiz_id,
				$user_id
			)
		);

		return (int) $passed > 0;
	}

	/**
	 * Handle assignment passed event
	 *
	 * @since 1.0.0
	 *
	 * @param int   $submission_id The submission ID.
	 * @param float $score         The score.
	 */
	public function handle_assignment_passed( $submission_id, $score ) {
		$submission = PressPrimer_Assignment_Submission::get( $submission_id );

		if ( ! $submission || ! $submission->user_id ) {
			return;
		}

		// Find TutorLMS lessons that use this assignment.
		$lessons = $this->get_lessons_for_assignment( $submission->assignment_id );

		foreach ( $lessons as $lesson ) {
			$require_pass = get_post_meta( $lesson->ID, self::META_KEY_REQUIRE_PASS, true );

			if ( '1' === $require_pass ) {
				// Cross-plugin check: if PressPrimer Quiz also requires a passing
				// score on this lesson, only complete when BOTH have been passed.
				if ( ! $this->is_quiz_requirement_satisfied( $lesson->ID, $submission->user_id ) ) {
					continue;
				}

				$this->mark_lesson_complete( $lesson->ID, $submission->user_id );
			}
		}
	}

	/**
	 * Get lessons that use a specific assignment
	 *
	 * @since 1.0.0
	 *
	 * @param int $assignment_id Assignment ID.
	 * @return array Array of post objects.
	 */
	private function get_lessons_for_assignment( $assignment_id ) {
		$args = [
			'post_type'      => 'lesson',
			'posts_per_page' => -1,
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for TutorLMS integration.
				[
					'key'   => self::META_KEY_ASSIGNMENT_ID,
					'value' => $assignment_id,
				],
			],
		];

		return get_posts( $args );
	}

	/**
	 * Mark a TutorLMS lesson as complete
	 *
	 * @since 1.0.0
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @param int $user_id   User ID.
	 */
	private function mark_lesson_complete( $lesson_id, $user_id ) {
		// Use TutorLMS LessonModel class (TutorLMS 2.x+).
		if ( class_exists( '\Tutor\Models\LessonModel' ) ) {
			\Tutor\Models\LessonModel::mark_lesson_complete( $lesson_id, $user_id );

			/**
			 * Fires after PPA marks a TutorLMS lesson complete.
			 *
			 * @since 1.0.0
			 *
			 * @param int $lesson_id Lesson post ID.
			 * @param int $user_id   User ID.
			 */
			do_action( 'pressprimer_assignment_tutorlms_lesson_completed', $lesson_id, $user_id );

			// Trigger auto-course-completion if all lessons are now complete.
			// TutorLMS only checks this on course page load, so we trigger it here
			// to complete the course immediately after the final lesson finishes.
			$this->maybe_auto_complete_course( $lesson_id, $user_id );
		}
	}

	/**
	 * Trigger TutorLMS auto-course-completion if all content is done
	 *
	 * TutorLMS normally only checks for auto-completion on course page load.
	 * This method triggers the check immediately after a lesson is programmatically
	 * marked complete, so the course completes without requiring a page visit.
	 *
	 * @since 1.0.0
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @param int $user_id   User ID.
	 */
	private function maybe_auto_complete_course( $lesson_id, $user_id ) {
		if ( ! class_exists( '\Tutor\Models\CourseModel' ) ) {
			return;
		}

		$course_id = $this->get_course_id_for_lesson( $lesson_id );
		if ( ! $course_id ) {
			return;
		}

		if ( \Tutor\Models\CourseModel::can_autocomplete_course( $course_id, $user_id ) ) {
			\Tutor\Models\CourseModel::mark_course_as_completed( $course_id, $user_id );
		}
	}

	/**
	 * Get course ID for a lesson
	 *
	 * @since 1.0.0
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @return int|null Course ID or null.
	 */
	private function get_course_id_for_lesson( $lesson_id ) {
		// TutorLMS stores course ID in post meta.
		$course_id = get_post_meta( $lesson_id, '_tutor_course_id_for_lesson', true );

		if ( $course_id ) {
			return (int) $course_id;
		}

		// Try TutorLMS function.
		if ( function_exists( 'tutor_utils' ) ) {
			$utils = tutor_utils();
			if ( method_exists( $utils, 'get_course_id_by_lesson' ) ) {
				return $utils->get_course_id_by_lesson( $lesson_id );
			}
		}

		return null;
	}

	/**
	 * Check if user has access to a course
	 *
	 * Checks enrollment, public course status, admin/instructor status,
	 * and completed course enrollment records.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool True if user has access.
	 */
	private function is_user_enrolled( $user_id, $course_id ) {
		// Public courses are accessible to everyone (no enrollment required).
		$is_public = get_post_meta( $course_id, '_tutor_is_public_course', true );
		if ( 'yes' === $is_public ) {
			return true;
		}

		// Admins and course instructors always have access.
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		if ( function_exists( 'tutor_utils' ) ) {
			$utils = tutor_utils();

			// Check if user is an instructor for this course.
			if ( method_exists( $utils, 'is_instructor_of_this_course' ) ) {
				if ( $utils->is_instructor_of_this_course( $user_id, $course_id ) ) {
					return true;
				}
			}

			// Check enrollment (includes completed courses — enrollment record
			// keeps post_status='completed' even after course completion).
			if ( method_exists( $utils, 'is_enrolled' ) ) {
				if ( $utils->is_enrolled( $course_id, $user_id ) ) {
					return true;
				}
			}
		}

		if ( ! $user_id ) {
			return false;
		}

		return true; // Default to allowing if we can't check.
	}

	/**
	 * Map TutorLMS Instructor role to PPA teacher capabilities
	 *
	 * Grants assignment management capabilities to the tutor_instructor role
	 * so instructors can create and manage their own assignments.
	 *
	 * @since 1.0.0
	 */
	private function map_instructor_capabilities() {
		$instructor = get_role( 'tutor_instructor' );

		if ( ! $instructor ) {
			return;
		}

		// Only add capabilities if the role doesn't already have them.
		if ( ! $instructor->has_cap( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			$instructor->add_cap( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL );
			$instructor->add_cap( PressPrimer_Assignment_Capabilities::PPA_CAP_VIEW_REPORTS );
		}
	}

	/**
	 * Check if user is a TutorLMS Instructor
	 *
	 * @since 1.0.0
	 *
	 * @param bool $has_capability Whether user has teacher capability.
	 * @param int  $user_id        User ID.
	 * @return bool Modified capability.
	 */
	public function check_instructor_capability( $has_capability, $user_id ) {
		if ( $has_capability ) {
			return $has_capability;
		}

		// Check if user is a TutorLMS Instructor.
		$user = get_userdata( $user_id );
		if ( $user && in_array( 'tutor_instructor', (array) $user->roles, true ) ) {
			return true;
		}

		return $has_capability;
	}

	/**
	 * Register REST routes
	 *
	 * @since 1.0.0
	 */
	public function register_rest_routes() {
		register_rest_route(
			'ppa/v1',
			'/tutorlms/assignments/search',
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
			'/tutorlms/status',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'rest_get_status' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		register_rest_route(
			'ppa/v1',
			'/tutorlms/lesson-assignment',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'rest_save_lesson_assignment' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => [
					'course_id'     => [
						'required'          => false,
						'sanitize_callback' => 'absint',
					],
					'lesson_id'     => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'assignment_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/**
	 * REST endpoint: Search assignments
	 *
	 * @since 1.0.0
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
	 * REST endpoint: Get TutorLMS integration status
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_get_status( $request ) {
		$status = [
			'active'      => defined( 'TUTOR_VERSION' ),
			'version'     => defined( 'TUTOR_VERSION' ) ? TUTOR_VERSION : null,
			'integration' => 'working',
		];

		// Count how many TutorLMS lessons have PPA assignments attached.
		if ( $status['active'] ) {
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

	/**
	 * REST endpoint: Save lesson assignment association
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_save_lesson_assignment( $request ) {
		$lesson_id     = $request->get_param( 'lesson_id' );
		$assignment_id = $request->get_param( 'assignment_id' );

		// Handle temporary lesson IDs (lesson-0, lesson-1, etc.).
		$is_temp_id = strpos( $lesson_id, 'lesson-' ) === 0;

		if ( $is_temp_id ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Please save the lesson first before attaching an assignment.', 'pressprimer-assignment' ),
				],
				400
			);
		}

		$lesson_id = absint( $lesson_id );

		// Validate lesson exists.
		$lesson = get_post( $lesson_id );
		if ( ! $lesson || 'lesson' !== $lesson->post_type ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Invalid lesson.', 'pressprimer-assignment' ),
				],
				400
			);
		}

		// Check user can edit this lesson.
		// Note: Tutor LMS registers custom capabilities (edit_tutor_lesson) that may not
		// be in the database if Tutor wasn't properly activated. We check manage_options
		// (admin) or the Tutor-specific edit_tutor_lesson capability, plus verify the
		// lesson author matches for non-admins.
		$can_edit = current_user_can( 'manage_options' )
			|| current_user_can( 'edit_tutor_lesson' )
			|| ( (int) $lesson->post_author === get_current_user_id() && current_user_can( 'edit_posts' ) );

		if ( ! $can_edit ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Permission denied.', 'pressprimer-assignment' ),
				],
				403
			);
		}

		// Save or remove the assignment association.
		if ( $assignment_id ) {
			// Validate assignment exists.
			$assignment = PressPrimer_Assignment_Assignment::get( $assignment_id );
			if ( ! $assignment ) {
				return new WP_REST_Response(
					[
						'success' => false,
						'message' => __( 'Assignment not found.', 'pressprimer-assignment' ),
					],
					400
				);
			}

			update_post_meta( $lesson_id, self::META_KEY_ASSIGNMENT_ID, $assignment_id );
			// Default to requiring pass when attaching an assignment via REST API.
			update_post_meta( $lesson_id, self::META_KEY_REQUIRE_PASS, '1' );
		} else {
			delete_post_meta( $lesson_id, self::META_KEY_ASSIGNMENT_ID );
			delete_post_meta( $lesson_id, self::META_KEY_REQUIRE_PASS );
		}

		return new WP_REST_Response(
			[
				'success'       => true,
				'lesson_id'     => $lesson_id,
				'assignment_id' => $assignment_id,
			]
		);
	}
}
