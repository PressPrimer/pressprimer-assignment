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
	const META_COMPLETED = 'ppa_onboarding_completed';

	/**
	 * User meta key: onboarding skipped flag
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_SKIPPED = 'ppa_onboarding_skipped';

	/**
	 * User meta key: current onboarding step
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_STEP = 'ppa_onboarding_step';

	/**
	 * User meta key: onboarding started flag
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_STARTED = 'ppa_onboarding_started';

	/**
	 * Total number of onboarding steps
	 *
	 * Steps: welcome, menu, dashboard, assignments, grading, settings, complete.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const TOTAL_STEPS = 7;

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
		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ] );
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

		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
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

		return [
			'state'     => $this->get_onboarding_state(),
			'nonce'     => wp_create_nonce( 'ppa_onboarding' ),
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'pluginUrl' => PRESSPRIMER_ASSIGNMENT_PLUGIN_URL,
			'urls'      => [
				'dashboard'   => admin_url( 'admin.php?page=pressprimer-assignment' ),
				'assignments' => admin_url( 'admin.php?page=pressprimer-assignment-assignments' ),
				'submissions' => admin_url( 'admin.php?page=pressprimer-assignment-submissions' ),
				'grading'     => admin_url( 'admin.php?page=pressprimer-assignment-grading' ),
				'categories'  => admin_url( 'admin.php?page=pressprimer-assignment-categories' ),
				'reports'     => admin_url( 'admin.php?page=pressprimer-assignment-reports' ),
				'settings'    => admin_url( 'admin.php?page=pressprimer-assignment-settings' ),
			],
			'i18n'      => [
				'pluginName'  => $plugin_name,
				'welcomeBack' => __( 'Welcome back! Let\'s continue the tour.', 'pressprimer-assignment' ),
			],
		];
	}

	/**
	 * Conditionally enqueue the onboarding React bundle
	 *
	 * Always loads on Assignment admin pages so the relaunch function
	 * (window.ppaLaunchOnboarding) is available from the Dashboard.
	 * The JS init function checks should_show before auto-rendering.
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

		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
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
		check_ajax_referer( 'ppa_onboarding', 'nonce' );

		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_key applied below.
		$action_type = isset( $_POST['action_type'] ) ? sanitize_key( wp_unslash( $_POST['action_type'] ) ) : '';
		$step        = isset( $_POST['step'] ) ? absint( wp_unslash( $_POST['step'] ) ) : 0;
		$permanent   = isset( $_POST['permanent'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['permanent'] ) );

		switch ( $action_type ) {
			case 'start':
				$this->start_onboarding();
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
		check_ajax_referer( 'ppa_onboarding', 'nonce' );

		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		wp_send_json_success( $this->get_onboarding_state() );
	}
}
