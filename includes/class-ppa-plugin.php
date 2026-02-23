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

		// Initialize components
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
		// Check if admin role has our capabilities.
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			// Capabilities missing, set them up.
			if ( class_exists( 'PressPrimer_Assignment_Capabilities' ) ) {
				PressPrimer_Assignment_Capabilities::setup_capabilities();
			}
		}
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
