<?php
/**
 * Uncanny Automator Loader
 *
 * Bootstraps the PressPrimer Assignment integration with Uncanny Automator.
 *
 * @package PressPrimer_Assignment
 * @subpackage Integrations\UncannyAutomator
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Automator Loader class
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Automator_Loader {

	/**
	 * Initialize the Automator integration
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'automator_add_integration', array( $this, 'add_integration' ) );
	}

	/**
	 * Register integration, helpers, and triggers with Automator
	 *
	 * @since 1.0.0
	 */
	public function add_integration() {
		$this->include_files();

		$helpers = new PressPrimer_Assignment_Automator_Helpers();

		new PressPrimer_Assignment_Automator_Integration();
		new PressPrimer_Assignment_Assignment_Submitted( $helpers );
		new PressPrimer_Assignment_Assignment_Graded( $helpers );
		new PressPrimer_Assignment_Assignment_Passed( $helpers );
		new PressPrimer_Assignment_Assignment_Failed( $helpers );
	}

	/**
	 * Include required integration files
	 *
	 * @since 1.0.0
	 */
	private function include_files() {
		$base_path = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'includes/integrations/uncanny-automator/';

		require_once $base_path . 'class-ppa-automator-integration.php';
		require_once $base_path . 'class-ppa-automator-helpers.php';
		require_once $base_path . 'triggers/class-ppa-assignment-submitted.php';
		require_once $base_path . 'triggers/class-ppa-assignment-graded.php';
		require_once $base_path . 'triggers/class-ppa-assignment-passed.php';
		require_once $base_path . 'triggers/class-ppa-assignment-failed.php';
	}
}
