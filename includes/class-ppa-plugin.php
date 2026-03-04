<?php
/**
 * Main plugin class
 *
 * Coordinates the plugin initialization and component setup.
 *
 * @package PressPrimer_Assignment
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class
 *
 * Implements singleton pattern to ensure only one instance exists.
 * Initializes all plugin components on init hook (priority 0).
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Plugin {

	/**
	 * Singleton instance
	 *
	 * @since 1.0.0
	 * @var PressPrimer_Assignment_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * Returns the single instance of the plugin class.
	 * Creates the instance if it doesn't exist.
	 *
	 * @since 1.0.0
	 *
	 * @return PressPrimer_Assignment_Plugin The plugin instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor
	 *
	 * Prevents direct instantiation. Use get_instance() instead.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Constructor is private for singleton
	}

	/**
	 * Run the plugin
	 *
	 * Initializes all plugin components in the correct order.
	 * This method is called from the pressprimer_assignment_init() function.
	 *
	 * @since 1.0.0
	 */
	public function run() {
		// Ensure capabilities are set up (handles cases where activation hook didn't run,
		// such as WordPress Playground or manual file installations)
		$this->ensure_capabilities();

		// Check and run migrations
		if ( class_exists( 'PressPrimer_Assignment_Migrator' ) ) {
			PressPrimer_Assignment_Migrator::maybe_migrate();
		}

		// Initialize addon manager (allows premium addons to register)
		$this->init_addon_manager();

		// Register cron hooks.
		$this->register_cron_hooks();

		// Register email notification hooks.
		$this->register_email_hooks();

		// Register statistics cache invalidation hooks.
		$this->register_statistics_hooks();

		// Initialize components.
		$this->init_admin();
		$this->init_frontend();
		$this->init_integrations();
		$this->init_rest_api();
		$this->init_blocks();
	}

	/**
	 * Initialize addon manager
	 *
	 * Sets up the addon manager and fires the registration hook
	 * for premium addons to register themselves.
	 *
	 * @since 1.0.0
	 */
	private function init_addon_manager() {
		if ( class_exists( 'PressPrimer_Assignment_Addon_Manager' ) ) {
			$addon_manager = PressPrimer_Assignment_Addon_Manager::get_instance();
			$addon_manager->init();
		}
	}

	/**
	 * Ensure capabilities are set up
	 *
	 * Checks if plugin capabilities exist and sets them up if missing.
	 * This handles cases where the activation hook didn't run properly,
	 * such as in WordPress Playground or manual file installations.
	 *
	 * @since 1.0.0
	 */
	private function ensure_capabilities() {
		// Check if admin role has ALL required capabilities.
		$admin = get_role( 'administrator' );
		if ( ! $admin ) {
			return;
		}

		$required_caps = [
			PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL,
			PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_OWN,
			PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_SETTINGS,
			PressPrimer_Assignment_Capabilities::PPA_CAP_VIEW_REPORTS,
		];

		foreach ( $required_caps as $cap ) {
			if ( ! $admin->has_cap( $cap ) ) {
				// At least one capability missing, run full setup.
				if ( class_exists( 'PressPrimer_Assignment_Capabilities' ) ) {
					PressPrimer_Assignment_Capabilities::setup_capabilities();
				}
				break;
			}
		}
	}

	/**
	 * Register WP Cron action hooks
	 *
	 * Registers callbacks for scheduled background tasks.
	 *
	 * @since 1.0.0
	 */
	private function register_cron_hooks() {
		if ( class_exists( 'PressPrimer_Assignment_PDF_Service' ) ) {
			add_action( 'ppa_extract_pdf_text', [ 'PressPrimer_Assignment_PDF_Service', 'process_scheduled_extraction' ] );
		}
	}

	/**
	 * Register email notification hooks
	 *
	 * Sets up email notifications for submission and grading events.
	 * Runs on both frontend and admin contexts since AJAX handlers
	 * fire in admin context.
	 *
	 * @since 1.0.0
	 */
	private function register_email_hooks() {
		if ( class_exists( 'PressPrimer_Assignment_Email_Service' ) ) {
			PressPrimer_Assignment_Email_Service::register_hooks();
		}
	}

	/**
	 * Register statistics cache invalidation hooks
	 *
	 * Clears dashboard and activity chart caches when submissions
	 * are created, graded, returned, or deleted. Matches the Quiz
	 * pattern of proactive cache invalidation on data changes.
	 *
	 * @since 1.0.0
	 */
	private function register_statistics_hooks() {
		if ( ! class_exists( 'PressPrimer_Assignment_Statistics_Service' ) ) {
			return;
		}

		// Clear caches when a submission is submitted.
		add_action(
			'pressprimer_assignment_submission_submitted',
			[ 'PressPrimer_Assignment_Statistics_Service', 'clear_all_caches' ]
		);

		// Clear caches when a submission is graded.
		add_action(
			'pressprimer_assignment_submission_graded',
			[ 'PressPrimer_Assignment_Statistics_Service', 'clear_all_caches' ]
		);

		// Clear caches when a submission is returned.
		add_action(
			'pressprimer_assignment_submission_returned',
			[ 'PressPrimer_Assignment_Statistics_Service', 'clear_all_caches' ]
		);

		// Clear caches when a submission is deleted.
		add_action(
			'pressprimer_assignment_submission_deleted',
			[ 'PressPrimer_Assignment_Statistics_Service', 'clear_all_caches' ]
		);

		// Clear caches when an assignment is deleted.
		add_action(
			'pressprimer_assignment_deleted',
			[ 'PressPrimer_Assignment_Statistics_Service', 'clear_all_caches' ]
		);
	}

	/**
	 * Initialize admin components
	 *
	 * Loads admin-only functionality when in wp-admin.
	 *
	 * @since 1.0.0
	 */
	private function init_admin() {
		if ( ! is_admin() ) {
			return;
		}

		// Initialize admin class
		if ( class_exists( 'PressPrimer_Assignment_Admin' ) ) {
			$admin = new PressPrimer_Assignment_Admin();
			$admin->init();
		}

		// Initialize onboarding
		if ( class_exists( 'PressPrimer_Assignment_Onboarding' ) ) {
			PressPrimer_Assignment_Onboarding::get_instance();
		}
	}

	/**
	 * Initialize frontend components
	 *
	 * Loads public-facing functionality (shortcodes, frontend rendering).
	 *
	 * @since 1.0.0
	 */
	private function init_frontend() {
		// Initialize frontend (file download handler).
		if ( class_exists( 'PressPrimer_Assignment_Frontend' ) ) {
			$frontend = new PressPrimer_Assignment_Frontend();
			$frontend->init();
		}

		// Initialize shortcodes.
		if ( class_exists( 'PressPrimer_Assignment_Shortcodes' ) ) {
			$shortcodes = new PressPrimer_Assignment_Shortcodes();
			$shortcodes->init();
		}

		// Initialize submission handler.
		if ( class_exists( 'PressPrimer_Assignment_Submission_Handler' ) ) {
			$handler = new PressPrimer_Assignment_Submission_Handler();
			$handler->init();
		}

		// Initialize text submission handler.
		if ( class_exists( 'PressPrimer_Assignment_Text_Handler' ) ) {
			$text_handler = new PressPrimer_Assignment_Text_Handler();
			$text_handler->init();
		}
	}

	/**
	 * Initialize LMS integrations
	 *
	 * Detects and initializes integrations with supported LMS plugins.
	 * Only loads integration if the corresponding LMS is active.
	 *
	 * @since 1.0.0
	 */
	private function init_integrations() {
		// LearnDash integration
		if ( defined( 'LEARNDASH_VERSION' ) ) {
			if ( class_exists( 'PressPrimer_Assignment_LearnDash' ) ) {
				$learndash = new PressPrimer_Assignment_LearnDash();
				$learndash->init();
			}
		}

		// TutorLMS integration
		if ( defined( 'TUTOR_VERSION' ) ) {
			if ( class_exists( 'PressPrimer_Assignment_TutorLMS' ) ) {
				$tutor = new PressPrimer_Assignment_TutorLMS();
				$tutor->init();
			}
		}

		// Uncanny Automator integration.
		if ( class_exists( 'Uncanny_Automator\Automator_Functions' ) ) {
			require_once PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'includes/integrations/uncanny-automator/class-ppa-automator-loader.php';
			$automator = new PressPrimer_Assignment_Automator_Loader();
			$automator->init();
		}
	}

	/**
	 * Initialize REST API
	 *
	 * Registers REST API endpoints for assignment functionality.
	 *
	 * @since 1.0.0
	 */
	private function init_rest_api() {
		// Assignments REST API.
		if ( class_exists( 'PressPrimer_Assignment_REST_Assignments' ) ) {
			$assignments_api = new PressPrimer_Assignment_REST_Assignments();
			$assignments_api->init();
		}

		// Grading Queue REST API.
		if ( class_exists( 'PressPrimer_Assignment_REST_Grading_Queue' ) ) {
			$grading_queue_api = new PressPrimer_Assignment_REST_Grading_Queue();
			$grading_queue_api->init();
		}

		// Submissions REST API.
		if ( class_exists( 'PressPrimer_Assignment_REST_Submissions' ) ) {
			$submissions_api = new PressPrimer_Assignment_REST_Submissions();
			$submissions_api->init();
		}

		// Settings REST API.
		if ( class_exists( 'PressPrimer_Assignment_REST_Settings' ) ) {
			$settings_api = new PressPrimer_Assignment_REST_Settings();
			$settings_api->init();
		}

		// Statistics REST API (dashboard).
		if ( class_exists( 'PressPrimer_Assignment_REST_Statistics' ) ) {
			$statistics_api = new PressPrimer_Assignment_REST_Statistics();
			$statistics_api->init();
		}

		// Categories REST API.
		if ( class_exists( 'PressPrimer_Assignment_REST_Categories' ) ) {
			$categories_api = new PressPrimer_Assignment_REST_Categories();
			$categories_api->init();
		}
	}

	/**
	 * Initialize Gutenberg blocks
	 *
	 * Registers block types for the block editor.
	 *
	 * @since 1.0.0
	 */
	private function init_blocks() {
		if ( class_exists( 'PressPrimer_Assignment_Blocks' ) ) {
			$blocks = new PressPrimer_Assignment_Blocks();
			$blocks->init();
		}
	}
}
