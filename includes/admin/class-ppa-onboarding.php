<?php
/**
 * Onboarding wizard
 *
 * Guides new users through a quick tour of PressPrimer Assignment
 * after activation. Follows the same pattern as PressPrimer Quiz
 * onboarding, using user meta to track progress.
 *
 * @package PressPrimer_Assignment
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Onboarding class
 *
 * Singleton that manages the onboarding wizard state and AJAX endpoints.
 * Enqueues the React onboarding bundle on Assignment admin pages when
 * the current user has not yet completed or skipped the tour.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Onboarding {

	/**
	 * User meta key: onboarding completed flag
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_COMPLETED = 'pressprimer_assignment_onboarding_completed';

	/**
	 * User meta key: onboarding skipped flag
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_SKIPPED = 'pressprimer_assignment_onboarding_skipped';

	/**
	 * User meta key: current onboarding step
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_STEP = 'pressprimer_assignment_onboarding_step';

	/**
	 * User meta key: onboarding started flag
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_STARTED = 'pressprimer_assignment_onboarding_started';

	/**
	 * Total number of onboarding steps
	 *
	 * Guided-build tour (2.2): welcome, basics, grading, file
	 * settings, publish, page, complete.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const TOTAL_STEPS = 7;

	/**
	 * Option name: map of assignment ID => page ID created by the tour
	 *
	 * Makes the "Put it on a page" action idempotent without a meta
	 * query — re-running it for the same assignment reuses the page.
	 *
	 * @since 2.2.0
	 * @var string
	 */
	const SETUP_PAGES_OPTION = 'pressprimer_assignment_setup_pages';

	/**
	 * Bundled sample assignment template keys
	 *
	 * Each key maps to a JSON pack in assets/data/sample-assignments/.
	 * The welcome step offers these as optional prefills for the real
	 * assignment editor.
	 *
	 * @since 2.2.0
	 * @var string[]
	 */
	const SAMPLE_KEYS = [
		'reflective-essay',
		'case-study-analysis',
		'compliance-acknowledgment',
	];

	/**
	 * Singleton instance
	 *
	 * @since 1.0.0
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @since 1.0.0
	 *
	 * @return self Singleton instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * Registers AJAX handlers and enqueue hooks.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		add_action( 'wp_ajax_pressprimer_assignment_onboarding_progress', [ $this, 'handle_progress_ajax' ] );
		add_action( 'wp_ajax_pressprimer_assignment_get_onboarding_state', [ $this, 'handle_get_state_ajax' ] );
		add_action( 'wp_ajax_pressprimer_assignment_setup_create_page', [ $this, 'handle_create_page_ajax' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ] );

		// 2.2: nonce'd relaunch links (Settings, dashboard) reset the
		// tour state server-side before the tour auto-opens.
		add_action( 'admin_init', [ $this, 'maybe_handle_relaunch' ] );
	}

	/**
	 * Check whether the current user can use the setup wizard
	 *
	 * Anyone who manages assignments gets the guided build — admins and
	 * (on Educator sites) teachers alike, each with their own per-user
	 * state. Admin-only content inside the wizard (the step 6 premium
	 * line) is gated separately.
	 *
	 * @since 2.2.0
	 *
	 * @return bool True when the user can run the wizard.
	 */
	private function user_can_use_wizard() {
		return current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_OWN )
			|| current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL );
	}

	/**
	 * Get the tour relaunch URL
	 *
	 * Points at the PPA dashboard with a nonce'd parameter; arriving with
	 * it resets the user's tour state, and the tour auto-opens there.
	 *
	 * @since 2.2.0
	 *
	 * @return string Relaunch URL.
	 */
	public static function get_relaunch_url() {
		return wp_nonce_url(
			add_query_arg(
				'ppa-relaunch',
				'1',
				admin_url( 'admin.php?page=pressprimer-assignment' )
			),
			'pressprimer_assignment_setup_relaunch'
		);
	}

	/**
	 * Get all bundled sample assignment templates
	 *
	 * @since 2.2.0
	 *
	 * @return array[] Sanitized template arrays, keyed order per SAMPLE_KEYS.
	 */
	public static function get_sample_assignments() {
		$samples = [];

		foreach ( self::SAMPLE_KEYS as $key ) {
			$sample = self::get_sample_assignment( $key );
			if ( $sample ) {
				$samples[] = $sample;
			}
		}

		return $samples;
	}

	/**
	 * Load and sanitize one bundled sample assignment template
	 *
	 * The packs ship with the plugin, but they are still treated as
	 * data: every field is sanitized on load (json_decode is not
	 * sanitization) and file types are validated against the editor's
	 * own whitelist.
	 *
	 * @since 2.2.0
	 *
	 * @param string $key Template key (must be in SAMPLE_KEYS).
	 * @return array|null Sanitized template, or null when unknown/unreadable.
	 */
	public static function get_sample_assignment( $key ) {
		$key = sanitize_key( $key );

		if ( ! in_array( $key, self::SAMPLE_KEYS, true ) ) {
			return null;
		}

		$path = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'assets/data/sample-assignments/' . $key . '.json';

		if ( ! file_exists( $path ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a bundled plugin file, not a remote URL.
		$raw = json_decode( (string) file_get_contents( $path ), true );

		if ( ! is_array( $raw ) ) {
			return null;
		}

		$valid_types = [ 'pdf', 'docx', 'pptx', 'txt', 'rtf', 'odt', 'jpg', 'jpeg', 'png', 'gif' ];
		$file_types  = [];

		if ( isset( $raw['allowed_file_types'] ) && is_array( $raw['allowed_file_types'] ) ) {
			foreach ( $raw['allowed_file_types'] as $type ) {
				$type = sanitize_key( $type );
				if ( in_array( $type, $valid_types, true ) ) {
					$file_types[] = $type;
				}
			}
		}

		$submission_type = isset( $raw['submission_type'] ) ? sanitize_key( $raw['submission_type'] ) : 'file';
		if ( ! in_array( $submission_type, [ 'file', 'text', 'either' ], true ) ) {
			$submission_type = 'file';
		}

		return [
			'key'                => $key,
			'title'              => isset( $raw['title'] ) ? sanitize_text_field( $raw['title'] ) : '',
			'description'        => isset( $raw['description'] ) ? sanitize_text_field( $raw['description'] ) : '',
			'instructions'       => isset( $raw['instructions'] ) ? wp_kses_post( $raw['instructions'] ) : '',
			'grading_guidelines' => isset( $raw['grading_guidelines'] ) ? wp_kses_post( $raw['grading_guidelines'] ) : '',
			'allowed_file_types' => $file_types,
			'max_points'         => isset( $raw['max_points'] ) ? max( 1, absint( $raw['max_points'] ) ) : 100,
			'passing_score'      => isset( $raw['passing_score'] ) ? absint( $raw['passing_score'] ) : 60,
			'submission_type'    => $submission_type,
		];
	}

	/**
	 * Check whether the onboarding should show for the current user
	 *
	 * Returns true when the user has the required capability and has
	 * not yet completed or permanently skipped the onboarding.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if onboarding should display.
	 */
	public function should_show_onboarding() {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		if ( ! $this->user_can_use_wizard() ) {
			return false;
		}

		// Already completed.
		if ( get_user_meta( $user_id, self::META_COMPLETED, true ) ) {
			return false;
		}

		// Permanently skipped.
		if ( 'permanent' === get_user_meta( $user_id, self::META_SKIPPED, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Mark the onboarding as completed for the current user
	 *
	 * @since 1.0.0
	 */
	public function complete_onboarding() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		update_user_meta( $user_id, self::META_COMPLETED, true );
		update_user_meta( $user_id, self::META_STEP, self::TOTAL_STEPS );

		/**
		 * Fires when a user completes the setup wizard.
		 *
		 * @since 2.2.0
		 *
		 * @param int $user_id The user who completed the wizard.
		 */
		do_action( 'pressprimer_assignment_onboarding_completed', $user_id );
	}

	/**
	 * Skip the onboarding for the current user
	 *
	 * @since 1.0.0
	 *
	 * @param bool $permanent Whether to permanently skip (don't show again).
	 */
	public function skip_onboarding( $permanent = false ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		if ( $permanent ) {
			update_user_meta( $user_id, self::META_SKIPPED, 'permanent' );
		}

		// Always mark as completed so the tour doesn't reappear on navigation.
		update_user_meta( $user_id, self::META_COMPLETED, true );

		/**
		 * Fires when a user skips the setup wizard.
		 *
		 * @since 2.2.0
		 *
		 * @param int  $user_id   The user who skipped.
		 * @param bool $permanent Whether the skip is permanent.
		 */
		do_action( 'pressprimer_assignment_onboarding_skipped', $user_id, (bool) $permanent );
	}

	/**
	 * Reset the onboarding for the current user
	 *
	 * Used when relaunching the tour from the dashboard.
	 *
	 * @since 1.0.0
	 */
	public function reset_onboarding() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		delete_user_meta( $user_id, self::META_COMPLETED );
		delete_user_meta( $user_id, self::META_SKIPPED );
		delete_user_meta( $user_id, self::META_STEP );
		delete_user_meta( $user_id, self::META_STARTED );

		/**
		 * Fires when a user's setup wizard state is reset (relaunch).
		 *
		 * @since 2.2.0
		 *
		 * @param int $user_id The user whose wizard state was reset.
		 */
		do_action( 'pressprimer_assignment_onboarding_reset', $user_id );
	}

	/**
	 * Mark the onboarding as started for the current user
	 *
	 * @since 1.0.0
	 */
	public function start_onboarding() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		update_user_meta( $user_id, self::META_STARTED, true );
		update_user_meta( $user_id, self::META_STEP, 1 );

		// Clear any previous skip.
		delete_user_meta( $user_id, self::META_SKIPPED );

		/**
		 * Fires when a user starts the setup wizard.
		 *
		 * @since 2.2.0
		 *
		 * @param int $user_id The user who started the wizard.
		 */
		do_action( 'pressprimer_assignment_onboarding_started', $user_id );
	}

	/**
	 * Update the current step for the user
	 *
	 * @since 1.0.0
	 *
	 * @param int $step Step number.
	 */
	public function update_step( $step ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$step = max( 1, min( self::TOTAL_STEPS, absint( $step ) ) );
		update_user_meta( $user_id, self::META_STEP, $step );
	}

	/**
	 * Get the current onboarding state for the user
	 *
	 * @since 1.0.0
	 *
	 * @return array State array with should_show, current_step, etc.
	 */
	public function get_onboarding_state() {
		$user_id = get_current_user_id();

		return [
			'should_show'  => $this->should_show_onboarding(),
			'current_step' => $user_id ? absint( get_user_meta( $user_id, self::META_STEP, true ) ) : 0,
			'total_steps'  => self::TOTAL_STEPS,
			'completed'    => $user_id ? (bool) get_user_meta( $user_id, self::META_COMPLETED, true ) : false,
			'started'      => $user_id ? (bool) get_user_meta( $user_id, self::META_STARTED, true ) : false,
		];
	}

	/**
	 * Get the JavaScript data object for the React onboarding bundle
	 *
	 * @since 1.0.0
	 *
	 * @return array Data passed via wp_localize_script().
	 */
	public function get_js_data() {
		/**
		 * Filters the plugin name displayed in onboarding.
		 *
		 * Used by Enterprise addon for white-label branding.
		 *
		 * @since 1.0.0
		 *
		 * @param string $name Default plugin name.
		 */
		$plugin_name = apply_filters(
			'pressprimer_assignment_plugin_name',
			__( 'PressPrimer Assignment', 'pressprimer-assignment' )
		);

		// Template picks are display data only; the full pack is loaded
		// server-side by the editor from the nonce'd ppa-template param.
		$templates = [];
		foreach ( self::get_sample_assignments() as $sample ) {
			$templates[] = [
				'key'         => $sample['key'],
				'title'       => $sample['title'],
				'description' => $sample['description'],
			];
		}

		return [
			'state'         => $this->get_onboarding_state(),
			'nonce'         => wp_create_nonce( 'pressprimer_assignment_onboarding' ),
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'restNonce'     => wp_create_nonce( 'wp_rest' ),
			'relaunchUrl'   => self::get_relaunch_url(),
			'templates'     => $templates,
			'templateNonce' => wp_create_nonce( 'pressprimer_assignment_setup_template' ),
			// Runtime detection only — nothing about the site's LMS is
			// ever stored (the tour collects no data).
			'lms'           => [
				'learndash' => defined( 'LEARNDASH_VERSION' ),
				'tutorlms'  => defined( 'TUTOR_VERSION' ),
			],
			'docsUrl'       => class_exists( 'PressPrimer_Assignment_Upgrade_Page' )
				? PressPrimer_Assignment_Upgrade_Page::utm_url(
					'https://pressprimer.com/knowledge-base/pressprimer-assignment/',
					'onboarding-docs',
					'onboarding'
				)
				: 'https://pressprimer.com/knowledge-base/pressprimer-assignment/',
			// The 011 email ask on the finish stop. Eligibility is
			// resolved server-side: skipped silently once the user has
			// answered anywhere or when the intake is disabled.
			'emailOptin'    => [
				'eligible'   => class_exists( 'PressPrimer_Assignment_Email_Optin_Service' )
					&& PressPrimer_Assignment_Email_Optin_Service::is_eligible( get_current_user_id(), 'wizard' ),
				'privacyUrl' => 'https://pressprimer.com/privacy/',
			],
			// The 10-submission milestone prompt (011): threshold-triggered
			// and admin-only (the gate lives in the service, which also
			// yields to a due review prompt); it additionally waits out a
			// pending What's New wave — one ask per moment, never stack.
			'milestone'     => [
				'eligible' => class_exists( 'PressPrimer_Assignment_Email_Optin_Service' )
					&& PressPrimer_Assignment_Email_Optin_Service::milestone_reached()
					&& PressPrimer_Assignment_Email_Optin_Service::is_eligible( get_current_user_id(), 'milestone' )
					&& ! PressPrimer_Assignment_Email_Optin_Service::whats_new_visible( get_current_user_id() ),
			],
			'isAdmin'       => current_user_can( 'manage_options' ),
			'pluginUrl'     => PRESSPRIMER_ASSIGNMENT_PLUGIN_URL,
			'urls'          => [
				'dashboard'     => admin_url( 'admin.php?page=pressprimer-assignment' ),
				'assignments'   => admin_url( 'admin.php?page=pressprimer-assignment-assignments' ),
				'newAssignment' => admin_url( 'admin.php?page=pressprimer-assignment-assignments&action=new' ),
				'submissions'   => admin_url( 'admin.php?page=pressprimer-assignment-submissions' ),
				'grading'       => admin_url( 'admin.php?page=pressprimer-assignment-grading' ),
				'categories'    => admin_url( 'admin.php?page=pressprimer-assignment-categories' ),
				'reports'       => admin_url( 'admin.php?page=pressprimer-assignment-reports' ),
				'settings'      => admin_url( 'admin.php?page=pressprimer-assignment-settings' ),
			],
			'i18n'          => [
				'pluginName'  => $plugin_name,
				'welcomeBack' => __( 'Welcome back! Let\'s continue the tour.', 'pressprimer-assignment' ),
			],
		];
	}

	/**
	 * Conditionally enqueue the guided-tour React bundle
	 *
	 * Loads on Assignment admin pages: the tour overlays the REAL admin
	 * UI (2.2 pivot decision) and auto-opens while should_show is true.
	 * The JS init function checks should_show before rendering, and the
	 * relaunch links depend on the bundle being present on the dashboard.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function maybe_enqueue_assets( $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page check.
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		// Only load on Assignment admin pages.
		$is_ppa_page = false !== strpos( $hook, 'pressprimer-assignment' )
			|| ( ! empty( $current_page ) && 0 === strpos( $current_page, 'pressprimer-assignment' ) );

		if ( ! $is_ppa_page ) {
			return;
		}

		if ( ! $this->user_can_use_wizard() ) {
			return;
		}

		$asset_file = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'build/onboarding.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'ppa-onboarding',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'build/onboarding.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Enqueue CSS if it exists.
		$css_file = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'build/style-onboarding.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'ppa-onboarding',
				PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'build/style-onboarding.css',
				[],
				$asset['version']
			);
		}

		// wp-scripts emits a SECOND CSS file per entry — onboarding.css —
		// for styles imported by components outside the entry's own
		// style.css (e.g. shared EmailOptinAsk.css). Without it those
		// components render unstyled.
		$component_css = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'build/onboarding.css';
		if ( file_exists( $component_css ) ) {
			wp_enqueue_style(
				'ppa-onboarding-components',
				PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'build/onboarding.css',
				[],
				$asset['version']
			);
		}

		wp_localize_script(
			'ppa-onboarding',
			'pressprimerAssignmentOnboardingData',
			$this->get_js_data()
		);
	}

	/**
	 * Handle AJAX request for onboarding progress updates
	 *
	 * Accepts actions: start, next, prev, skip, complete, reset.
	 *
	 * @since 1.0.0
	 */
	public function handle_progress_ajax() {
		check_ajax_referer( 'pressprimer_assignment_onboarding', 'nonce' );

		if ( ! $this->user_can_use_wizard() ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_key applied below.
		$action_type = isset( $_POST['action_type'] ) ? sanitize_key( wp_unslash( $_POST['action_type'] ) ) : '';
		$step        = isset( $_POST['step'] ) ? absint( wp_unslash( $_POST['step'] ) ) : 0;
		$permanent   = isset( $_POST['permanent'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['permanent'] ) );

		switch ( $action_type ) {
			case 'start':
				$this->start_onboarding();
				// The guided tour navigates to the editor immediately after
				// starting, so the landing step must persist before the
				// page unloads — otherwise the welcome modal reappears.
				if ( $step > 0 ) {
					$this->update_step( $step );
				}
				break;

			case 'next':
			case 'prev':
				if ( $step > 0 ) {
					$this->update_step( $step );
				}
				break;

			case 'skip':
				$this->skip_onboarding( $permanent );
				break;

			case 'complete':
				$this->complete_onboarding();
				break;

			case 'reset':
				$this->reset_onboarding();
				break;

			default:
				wp_send_json_error( [ 'message' => 'Invalid action type.' ] );
				break;
		}

		wp_send_json_success( $this->get_onboarding_state() );
	}

	/**
	 * Handle AJAX request to retrieve onboarding state
	 *
	 * @since 1.0.0
	 */
	public function handle_get_state_ajax() {
		check_ajax_referer( 'pressprimer_assignment_onboarding', 'nonce' );

		if ( ! $this->user_can_use_wizard() ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		wp_send_json_success( $this->get_onboarding_state() );
	}

	/**
	 * Handle the tour's one-click "Put it on a page" action
	 *
	 * Creates a published page containing the assignment block, titled
	 * after the assignment. Idempotent: re-running for the same
	 * assignment returns the previously created page. Requires the
	 * assignment to be published — nothing goes live before the user's
	 * explicit publish action in the editor.
	 *
	 * @since 2.2.0
	 */
	public function handle_create_page_ajax() {
		check_ajax_referer( 'pressprimer_assignment_onboarding', 'nonce' );

		if ( ! $this->user_can_use_wizard() ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'pressprimer-assignment' ) ] );
		}

		$assignment_id = isset( $_POST['assignment_id'] ) ? absint( wp_unslash( $_POST['assignment_id'] ) ) : 0;

		if ( ! $assignment_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid assignment.', 'pressprimer-assignment' ) ] );
		}

		$assignment = PressPrimer_Assignment_Assignment::get( $assignment_id );

		if ( ! $assignment ) {
			wp_send_json_error( [ 'message' => __( 'Invalid assignment.', 'pressprimer-assignment' ) ] );
		}

		// Teachers may only create pages for their own assignments.
		if (
			(int) $assignment->author_id !== get_current_user_id()
			&& ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL )
		) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'pressprimer-assignment' ) ] );
		}

		if ( 'published' !== $assignment->status ) {
			wp_send_json_error( [ 'message' => __( 'Publish the assignment first, then create its page.', 'pressprimer-assignment' ) ] );
		}

		// Idempotent: reuse the page this flow already created for this
		// assignment (unless it has since been deleted).
		$pages       = get_option( self::SETUP_PAGES_OPTION, [] );
		$existing_id = isset( $pages[ $assignment_id ] ) ? absint( $pages[ $assignment_id ] ) : 0;

		if ( $existing_id ) {
			$existing = get_post( $existing_id );

			if ( $existing && 'page' === $existing->post_type && 'trash' !== $existing->post_status ) {
				wp_send_json_success(
					[
						'page_id'  => (int) $existing->ID,
						'page_url' => get_permalink( $existing ),
						'created'  => false,
					]
				);
			}
		}

		$page_id = wp_insert_post(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $assignment->title,
				'post_content' => '<!-- wp:pressprimer-assignment/assignment {"assignmentId":' . absint( $assignment_id ) . '} /-->',
			],
			true
		);

		if ( is_wp_error( $page_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not create the page.', 'pressprimer-assignment' ) ] );
		}

		if ( ! is_array( $pages ) ) {
			$pages = [];
		}
		$pages[ $assignment_id ] = (int) $page_id;
		update_option( self::SETUP_PAGES_OPTION, $pages, false );

		wp_send_json_success(
			[
				'page_id'  => (int) $page_id,
				'page_url' => get_permalink( $page_id ),
				'created'  => true,
			]
		);
	}

	/**
	 * Handle a tour relaunch request
	 *
	 * The Settings and dashboard "Setup wizard" links point at the PPA
	 * dashboard with a nonce'd relaunch parameter; arriving with it
	 * resets the user's tour state (the existing reset action) so the
	 * tour auto-opens fresh.
	 *
	 * @since 2.2.0
	 */
	public function maybe_handle_relaunch() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing check; nonce verified below.
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing check; nonce verified below.
		if ( 0 !== strpos( $current_page, 'pressprimer-assignment' ) || ! isset( $_GET['ppa-relaunch'] ) ) {
			return;
		}

		check_admin_referer( 'pressprimer_assignment_setup_relaunch' );

		if ( ! $this->user_can_use_wizard() ) {
			return;
		}

		$this->reset_onboarding();
	}
}
