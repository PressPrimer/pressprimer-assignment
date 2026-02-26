<?php
/**
 * LearnDash Integration
 *
 * Integrates PressPrimer Assignment with LearnDash LMS.
 * Adds meta boxes to lessons and topics for assignment attachment.
 * Handles completion tracking when assignments are graded as passed.
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
 * LearnDash Integration class
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_LearnDash {

	/**
	 * Meta key for storing PPA assignment ID
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY_ASSIGNMENT_ID = '_ppa_learndash_assignment_id';

	/**
	 * Supported post types for assignment attachment
	 *
	 * Only lessons and topics — courses are not supported.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $supported_post_types = [
		'sfwd-lessons',
		'sfwd-topic',
	];

	/**
	 * Whether the assignment has already been rendered via the_content filter.
	 *
	 * Prevents double-rendering when the_content fires multiple times
	 * on the same page load.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $assignment_rendered = false;

	/**
	 * Initialize the integration
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Only initialize if LearnDash is active.
		if ( ! defined( 'LEARNDASH_VERSION' ) ) {
			return;
		}

		// Admin hooks.
		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_meta_box' ], 10, 2 );

		// AJAX handler for classic editor assignment search.
		add_action( 'wp_ajax_pressprimer_assignment_search_assignments_learndash', [ $this, 'ajax_search_assignments' ] );

		// Gutenberg support - register meta on init AND rest_api_init for reliability.
		add_action( 'init', [ $this, 'register_meta_fields' ] );
		add_action( 'rest_api_init', [ $this, 'register_meta_fields' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );

		// Frontend hooks.
		add_filter( 'the_content', [ $this, 'maybe_display_assignment' ], 20 );
		add_filter( 'learndash_mark_complete_button', [ $this, 'maybe_hide_mark_complete' ], 10, 2 );

		// Completion tracking — mark LD content complete when assignment is passed.
		add_action( 'pressprimer_assignment_submission_passed', [ $this, 'handle_assignment_passed' ], 10, 2 );

		// Prevent lesson/topic completion when PPA assignment is attached and not passed.
		add_filter( 'learndash-lesson-can-complete', [ $this, 'maybe_prevent_lesson_completion' ], 10, 4 );

		// REST API endpoints.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Map LearnDash instructor capabilities.
		$this->map_instructor_capabilities();
		add_filter( 'pressprimer_assignment_user_has_teacher_capability', [ $this, 'check_instructor_capability' ], 10, 2 );
	}

	/**
	 * Register meta boxes for LearnDash post types
	 *
	 * Only registers for Classic Editor — Gutenberg uses the sidebar panel.
	 *
	 * @since 1.0.0
	 */
	public function register_meta_boxes() {
		// Don't register metabox if using block editor — we use the sidebar panel instead.
		$screen = get_current_screen();
		if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			return;
		}

		foreach ( $this->supported_post_types as $post_type ) {
			add_meta_box(
				'ppa_learndash_assignment',
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
		wp_nonce_field( 'pressprimer_assignment_learndash_meta_box', 'pressprimer_assignment_learndash_nonce' );

		$assignment_id = get_post_meta( $post->ID, self::META_KEY_ASSIGNMENT_ID, true );

		// Get assignment display label if one is selected.
		$assignment_display = '';
		if ( $assignment_id ) {
			$assignment         = PressPrimer_Assignment_Assignment::get( $assignment_id );
			$assignment_display = $assignment ? sprintf( '%d - %s', $assignment->id, $assignment->title ) : '';
		}
		?>
		<div class="ppa-learndash-meta-box">
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

			<p class="description" style="margin-top: 10px;">
				<?php esc_html_e( 'The assignment will appear at the end of this content. Users must pass to mark it complete.', 'pressprimer-assignment' ); ?>
			</p>
		</div>

		<?php
		$this->enqueue_meta_box_assets();
	}

	/**
	 * Enqueue meta box styles and scripts
	 *
	 * Uses wp_add_inline_style and wp_add_inline_script per WordPress.org guidelines.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_meta_box_assets() {
		// Ensure admin scripts are enqueued.
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

		// Localize script data.
		wp_localize_script(
			'ppa-admin',
			'pressprimerAssignmentLearnDash',
			[
				'nonce'   => wp_create_nonce( 'pressprimer_assignment_learndash_search' ),
				'strings' => [
					'noAssignmentsFound' => __( 'No assignments found', 'pressprimer-assignment' ),
					'removeAssignment'   => __( 'Remove assignment', 'pressprimer-assignment' ),
				],
			]
		);

		// Meta box styles.
		$inline_css = '
			.ppa-learndash-meta-box .ppa-assignment-selector {
				position: relative;
				display: flex;
				align-items: center;
				gap: 4px;
			}
			.ppa-learndash-meta-box .ppa-assignment-search {
				flex: 1;
			}
			.ppa-learndash-meta-box .ppa-assignment-search[readonly] {
				background: #f0f6fc;
				cursor: default;
			}
			.ppa-learndash-meta-box .ppa-assignment-results {
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
			.ppa-learndash-meta-box .ppa-assignment-result-item {
				padding: 8px 12px;
				cursor: pointer;
				border-bottom: 1px solid #f0f0f0;
			}
			.ppa-learndash-meta-box .ppa-assignment-result-item:hover {
				background: #f0f0f0;
			}
			.ppa-learndash-meta-box .ppa-assignment-result-item:last-child {
				border-bottom: none;
			}
			.ppa-learndash-meta-box .ppa-remove-assignment {
				color: #d63638;
				text-decoration: none;
				padding: 4px;
				border: none;
				background: none;
				cursor: pointer;
				display: flex;
				align-items: center;
			}
			.ppa-learndash-meta-box .ppa-remove-assignment:hover {
				color: #b32d2e;
			}
			.ppa-learndash-meta-box .ppa-no-results {
				padding: 12px;
				color: #666;
				font-style: italic;
			}
			.ppa-learndash-meta-box .ppa-assignment-result-item .ppa-assignment-id {
				color: #666;
				font-weight: 600;
				margin-right: 4px;
			}
		';
		wp_add_inline_style( 'ppa-admin', $inline_css );

		// Meta box script.
		$inline_script = 'jQuery(document).ready(function($) {' .
			'var config = window.pressprimerAssignmentLearnDash || {};' .
			'var nonce = config.nonce || "";' .
			'var strings = config.strings || {};' .
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
						'action: "pressprimer_assignment_search_assignments_learndash",' .
						'nonce: nonce,' .
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
							'action: "pressprimer_assignment_search_assignments_learndash",' .
							'nonce: nonce,' .
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
								'$results.html(\'<div class="ppa-no-results">\' + strings.noAssignmentsFound + \'</div>\').show();' .
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
					'$search.after(\'<button type="button" class="ppa-remove-assignment button-link" aria-label="\' + strings.removeAssignment + \'" title="\' + strings.removeAssignment + \'"><span class="dashicons dashicons-no-alt"></span></button>\');' .
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
		if ( ! isset( $_POST['pressprimer_assignment_learndash_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['pressprimer_assignment_learndash_nonce'] ) ),
				'pressprimer_assignment_learndash_meta_box'
			)
		) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check post type.
		if ( ! in_array( $post->post_type, $this->supported_post_types, true ) ) {
			return;
		}

		// Save assignment ID.
		$assignment_id = isset( $_POST['ppa_assignment_id'] ) ? absint( wp_unslash( $_POST['ppa_assignment_id'] ) ) : 0;

		if ( $assignment_id ) {
			update_post_meta( $post_id, self::META_KEY_ASSIGNMENT_ID, $assignment_id );
		} else {
			delete_post_meta( $post_id, self::META_KEY_ASSIGNMENT_ID );
		}
	}

	/**
	 * Register meta fields for Gutenberg
	 *
	 * Uses both register_post_meta (for standard WP handling) and register_rest_field
	 * (for compatibility with custom REST controllers like LearnDash uses).
	 *
	 * @since 1.0.0
	 */
	public function register_meta_fields() {
		foreach ( $this->supported_post_types as $post_type ) {
			// Standard meta registration.
			register_post_meta(
				$post_type,
				self::META_KEY_ASSIGNMENT_ID,
				[
					'type'              => 'integer',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'absint',
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				]
			);

			// Also register as REST field for LearnDash custom REST controller compatibility.
			register_rest_field(
				$post_type,
				'ppa_assignment_id',
				[
					'get_callback'    => function ( $post ) {
						return absint( get_post_meta( $post['id'], self::META_KEY_ASSIGNMENT_ID, true ) );
					},
					'update_callback' => function ( $value, $post ) {
						if ( ! current_user_can( 'edit_post', $post->ID ) ) {
							return new WP_Error(
								'rest_forbidden',
								__( 'You do not have permission to edit this post.', 'pressprimer-assignment' ),
								[ 'status' => 403 ]
							);
						}
						$value = absint( $value );
						if ( $value > 0 ) {
							update_post_meta( $post->ID, self::META_KEY_ASSIGNMENT_ID, $value );
						} else {
							delete_post_meta( $post->ID, self::META_KEY_ASSIGNMENT_ID );
						}
						return true;
					},
					'schema'          => [
						'type'        => 'integer',
						'description' => __( 'PressPrimer Assignment ID', 'pressprimer-assignment' ),
						'context'     => [ 'view', 'edit' ],
					],
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
			'ppa-learndash-editor',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/js/learndash-editor.js',
			[ 'wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ],
			PRESSPRIMER_ASSIGNMENT_VERSION,
			true
		);

		wp_localize_script(
			'ppa-learndash-editor',
			'pressprimerAssignmentLearnDash',
			[
				'metaKeyAssignmentId' => self::META_KEY_ASSIGNMENT_ID,
				'postType'            => $screen->post_type,
				'restNonce'           => wp_create_nonce( 'wp_rest' ),
				'strings'             => [
					'panelTitle'        => __( 'PressPrimer Assignment', 'pressprimer-assignment' ),
					'selectAssignment'  => __( 'Select Assignment', 'pressprimer-assignment' ),
					'searchPlaceholder' => __( 'Search for an assignment...', 'pressprimer-assignment' ),
					'noAssignment'      => __( 'No assignment selected', 'pressprimer-assignment' ),
					'assignmentHelp'    => __( 'The assignment will appear at the end of this content. Users must pass to mark it complete.', 'pressprimer-assignment' ),
				],
			]
		);
	}

	/**
	 * Maybe display assignment in content
	 *
	 * Appends the assignment shortcode after LearnDash lesson/topic content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Post content.
	 * @return string Modified content.
	 */
	public function maybe_display_assignment( $content ) {
		// Only on singular LearnDash lessons and topics.
		if ( ! is_singular( [ 'sfwd-lessons', 'sfwd-topic' ] ) ) {
			return $content;
		}

		// Prevent double-rendering (the_content can fire multiple times).
		if ( $this->assignment_rendered ) {
			return $content;
		}

		$post_id       = get_the_ID();
		$assignment_id = get_post_meta( $post_id, self::META_KEY_ASSIGNMENT_ID, true );

		if ( ! $assignment_id ) {
			return $content;
		}

		// Mark as rendered before processing.
		$this->assignment_rendered = true;

		// Check lesson restriction — assignment locked until all topics in the lesson are complete.
		if ( 'sfwd-lessons' === get_post_type( $post_id ) ) {
			if ( ! $this->are_lesson_topics_complete( $post_id ) ) {
				$assignment = PressPrimer_Assignment_Assignment::get( $assignment_id );
				$title      = $assignment ? $assignment->title : __( 'Assignment', 'pressprimer-assignment' );

				$restriction_message = get_option( 'pressprimer_assignment_learndash_restriction_message', '' );
				if ( empty( $restriction_message ) ) {
					$restriction_message = __( 'Complete all topics in this lesson to unlock the assignment.', 'pressprimer-assignment' );
				}

				return $content . $this->render_restriction_placeholder( $title, $restriction_message );
			}
		}

		// Render assignment shortcode.
		$assignment_shortcode = sprintf(
			'[ppa_assignment id="%d"]',
			absint( $assignment_id )
		);

		return $content . do_shortcode( $assignment_shortcode );
	}

	/**
	 * Maybe hide Mark Complete button
	 *
	 * Hides the LearnDash Mark Complete button when an assignment is attached
	 * but the user has not yet passed it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $button Button HTML.
	 * @param array  $args   Button arguments.
	 * @return string Modified button HTML.
	 */
	public function maybe_hide_mark_complete( $button, $args ) {
		if ( empty( $args['post'] ) ) {
			return $button;
		}

		$post_id   = is_object( $args['post'] ) ? $args['post']->ID : $args['post'];
		$post_type = get_post_type( $post_id );

		// Only hide for lessons and topics.
		if ( ! in_array( $post_type, [ 'sfwd-lessons', 'sfwd-topic' ], true ) ) {
			return $button;
		}

		// Check if an assignment is mapped.
		$assignment_id = get_post_meta( $post_id, self::META_KEY_ASSIGNMENT_ID, true );

		if ( ! $assignment_id ) {
			return $button;
		}

		// If the user has already passed the assignment, show the Mark Complete button.
		$user_id = get_current_user_id();
		if ( $user_id && $this->has_user_passed_assignment( $user_id, $assignment_id ) ) {
			return $button;
		}

		// Assignment attached but not yet passed — hide the button.
		return '';
	}

	/**
	 * Handle assignment passed event
	 *
	 * When a student passes an assignment, find all LearnDash content
	 * that uses this assignment and mark it complete.
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

		// Find LearnDash content that uses this assignment.
		$ld_posts = $this->get_learndash_posts_for_assignment( $submission->assignment_id );

		foreach ( $ld_posts as $ld_post ) {
			$this->mark_learndash_complete( $ld_post->ID, $submission->user_id );
		}
	}

	/**
	 * Get LearnDash posts that use a specific assignment
	 *
	 * @since 1.0.0
	 *
	 * @param int $assignment_id Assignment ID.
	 * @return array Array of post objects.
	 */
	private function get_learndash_posts_for_assignment( $assignment_id ) {
		$args = [
			'post_type'      => $this->supported_post_types,
			'posts_per_page' => -1,
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for LD integration.
				[
					'key'   => self::META_KEY_ASSIGNMENT_ID,
					'value' => $assignment_id,
				],
			],
		];

		return get_posts( $args );
	}

	/**
	 * Mark LearnDash content as complete
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id LearnDash post ID.
	 * @param int $user_id User ID.
	 */
	private function mark_learndash_complete( $post_id, $user_id ) {
		$course_id = $this->get_course_id_for_post( $post_id );

		if ( ! $course_id ) {
			return;
		}

		if ( function_exists( 'learndash_process_mark_complete' ) ) {
			learndash_process_mark_complete( $user_id, $post_id, false, $course_id );
		}
	}

	/**
	 * Prevent lesson/topic completion when an assignment is attached and not passed
	 *
	 * This filter blocks LearnDash from marking a lesson or topic complete
	 * if it has a PPA assignment attached that the user hasn't passed yet.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $can_complete Whether the user can complete.
	 * @param int  $post_id      The lesson or topic ID.
	 * @param int  $course_id    The course ID.
	 * @param int  $user_id      The user ID.
	 * @return bool Modified can_complete value.
	 */
	public function maybe_prevent_lesson_completion( $can_complete, $post_id, $course_id, $user_id ) {
		if ( ! $can_complete ) {
			return false;
		}

		// Check if this lesson/topic has a PPA assignment attached.
		$assignment_id = get_post_meta( $post_id, self::META_KEY_ASSIGNMENT_ID, true );

		if ( ! $assignment_id ) {
			return $can_complete;
		}

		// Check if the user has passed the assignment.
		if ( $this->has_user_passed_assignment( $user_id, $assignment_id ) ) {
			return $can_complete;
		}

		// Block completion — user hasn't passed the assignment yet.
		return false;
	}

	/**
	 * Check if a user has passed a specific assignment
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id       User ID.
	 * @param int $assignment_id Assignment ID.
	 * @return bool True if user has passed.
	 */
	private function has_user_passed_assignment( $user_id, $assignment_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ppa_submissions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic user/assignment check, not suitable for caching.
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
	 * Get course ID for a LearnDash post
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return int|null Course ID or null.
	 */
	private function get_course_id_for_post( $post_id ) {
		// Get course ID from post meta (LearnDash stores this).
		$course_id = get_post_meta( $post_id, 'course_id', true );

		if ( $course_id ) {
			return (int) $course_id;
		}

		// Try LearnDash function.
		if ( function_exists( 'learndash_get_course_id' ) ) {
			return learndash_get_course_id( $post_id );
		}

		return null;
	}

	/**
	 * Check if all topics in a lesson are complete
	 *
	 * Returns true if the lesson has no topics (assignment should be accessible).
	 * Returns false if there are incomplete topics.
	 *
	 * @since 1.0.0
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return bool True if all topics are complete or no topics exist.
	 */
	private function are_lesson_topics_complete( $lesson_id ) {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		$course_id = $this->get_course_id_for_post( $lesson_id );

		if ( ! $course_id ) {
			return true; // Can't determine course, allow access.
		}

		// Get topics for this lesson.
		$topics = $this->get_lesson_topics( $lesson_id, $course_id );

		// If no topics, assignment is accessible.
		if ( empty( $topics ) ) {
			return true;
		}

		// Check if each topic is complete.
		if ( ! function_exists( 'learndash_is_topic_complete' ) ) {
			return true; // Can't check, allow access.
		}

		foreach ( $topics as $topic_id ) {
			if ( ! learndash_is_topic_complete( $user_id, $topic_id, $course_id ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get topics for a lesson
	 *
	 * @since 1.0.0
	 *
	 * @param int $lesson_id Lesson ID.
	 * @param int $course_id Course ID.
	 * @return array Array of topic IDs.
	 */
	private function get_lesson_topics( $lesson_id, $course_id ) {
		if ( function_exists( 'learndash_get_topic_list' ) ) {
			$topics = learndash_get_topic_list( $lesson_id, $course_id );

			if ( is_array( $topics ) ) {
				return wp_list_pluck( $topics, 'ID' );
			}
		}

		return [];
	}

	/**
	 * AJAX handler for searching assignments (classic editor)
	 *
	 * @since 1.0.0
	 */
	public function ajax_search_assignments() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'pressprimer_assignment_learndash_search', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-assignment' ) ] );
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'pressprimer-assignment' ) ] );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ppa_assignments';

		// Check if requesting recent assignments.
		$recent = isset( $_POST['recent'] ) && rest_sanitize_boolean( wp_unslash( $_POST['recent'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.

		if ( $recent ) {
			$user_id = get_current_user_id();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- AJAX search results, not suitable for caching.
			$assignments = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, title FROM {$table} WHERE status = 'published' AND author_id = %d ORDER BY id DESC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
					$user_id
				)
			);

			wp_send_json_success( [ 'assignments' => $assignments ] );
			return;
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.

		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( [ 'assignments' => [] ] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- AJAX search results, not suitable for caching.
		$assignments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title FROM {$table} WHERE title LIKE %s AND status = 'published' ORDER BY title ASC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				'%' . $wpdb->esc_like( $search ) . '%'
			)
		);

		wp_send_json_success( [ 'assignments' => $assignments ] );
	}

	/**
	 * Map LearnDash Group Leader role to PPA teacher capabilities
	 *
	 * Grants assignment management capabilities to the group_leader role
	 * so instructors can create and manage their own assignments.
	 *
	 * @since 1.0.0
	 */
	private function map_instructor_capabilities() {
		$group_leader = get_role( 'group_leader' );

		if ( ! $group_leader ) {
			return;
		}

		// Only add capabilities if the role doesn't already have them.
		if ( ! $group_leader->has_cap( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			$group_leader->add_cap( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL );
			$group_leader->add_cap( PressPrimer_Assignment_Capabilities::PPA_CAP_VIEW_REPORTS );
		}
	}

	/**
	 * Check if user is a LearnDash Group Leader
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

		// Check if user is a LearnDash Group Leader.
		$user = get_userdata( $user_id );
		if ( $user && in_array( 'group_leader', (array) $user->roles, true ) ) {
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
			'/learndash/assignments/search',
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
			'/learndash/status',
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
			'/learndash/settings',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'rest_save_settings' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
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
			$user_id = get_current_user_id();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REST search results, not suitable for caching.
			$assignments = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, title FROM {$table} WHERE status = 'published' AND author_id = %d ORDER BY id DESC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
					$user_id
				)
			);

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
				"SELECT id, title FROM {$table} WHERE title LIKE %s AND status = 'published' ORDER BY title ASC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
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
	 * REST endpoint: Get LearnDash integration status
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_get_status( $request ) {
		$status = [
			'active'      => defined( 'LEARNDASH_VERSION' ),
			'version'     => defined( 'LEARNDASH_VERSION' ) ? LEARNDASH_VERSION : null,
			'integration' => 'working',
		];

		// Count how many LearnDash posts have PPA assignments attached.
		if ( $status['active'] ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Status count query, not suitable for caching.
			$count                          = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''",
					self::META_KEY_ASSIGNMENT_ID
				)
			);
			$status['attached_assignments'] = (int) $count;
		}

		// Get settings.
		$settings = [
			'restriction_message' => get_option( 'pressprimer_assignment_learndash_restriction_message', '' ),
		];

		return new WP_REST_Response(
			[
				'success'  => true,
				'status'   => $status,
				'settings' => $settings,
			]
		);
	}

	/**
	 * REST endpoint: Save LearnDash settings
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_save_settings( $request ) {
		$restriction_message = $request->get_param( 'restriction_message' );

		if ( null !== $restriction_message ) {
			update_option(
				'pressprimer_assignment_learndash_restriction_message',
				sanitize_textarea_field( $restriction_message )
			);
		}

		return new WP_REST_Response(
			[
				'success' => true,
			]
		);
	}

	/**
	 * Render the restriction placeholder for locked assignments
	 *
	 * Shows a lock icon, assignment title, and restriction message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $assignment_title    Assignment title.
	 * @param string $restriction_message Message to display.
	 * @return string HTML output.
	 */
	private function render_restriction_placeholder( $assignment_title, $restriction_message ) {
		// Enqueue styles for the restriction placeholder.
		$this->enqueue_restriction_placeholder_styles();

		ob_start();
		?>
		<div class="ppa-restriction-placeholder">
			<div class="ppa-restriction-placeholder__icon" aria-hidden="true">&#x1f512;</div>
			<div class="ppa-restriction-placeholder__title"><?php echo esc_html( $assignment_title ); ?></div>
			<div class="ppa-restriction-placeholder__message">
				<?php echo esc_html( $restriction_message ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Enqueue styles for the restriction placeholder
	 *
	 * @since 1.0.0
	 */
	private function enqueue_restriction_placeholder_styles() {
		static $styles_enqueued = false;

		if ( $styles_enqueued ) {
			return;
		}

		$styles_enqueued = true;

		// Register a minimal handle so wp_add_inline_style has something to attach to.
		wp_register_style( 'ppa-restriction-placeholder', false, [], PRESSPRIMER_ASSIGNMENT_VERSION );
		wp_enqueue_style( 'ppa-restriction-placeholder' );

		$inline_css = '
			.ppa-restriction-placeholder {
				display: flex;
				flex-direction: column;
				align-items: center;
				gap: 1.5rem;
				padding: 3rem;
				background: #f9fafb;
				border: 2px solid #e5e7eb;
				border-radius: 1rem;
				text-align: center;
				width: 100%;
				max-width: 600px;
				margin: 24px auto;
				box-sizing: border-box;
			}
			.ppa-restriction-placeholder__icon {
				font-size: 3rem;
				line-height: 1;
				opacity: 0.8;
			}
			.ppa-restriction-placeholder__title {
				font-size: 1.25rem;
				font-weight: 600;
				color: #1f2937;
				line-height: 1.4;
			}
			.ppa-restriction-placeholder__message {
				font-size: 1.125rem;
				color: #1f2937;
				line-height: 1.7;
			}
		';
		wp_add_inline_style( 'ppa-restriction-placeholder', $inline_css );
	}
}
