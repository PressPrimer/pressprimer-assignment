<?php
/**
 * Uncanny Automator Integration
 *
 * Registers PressPrimer Assignment as an integration in Uncanny Automator.
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
 * Automator Integration class
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Automator_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup integration metadata
	 *
	 * @since 1.0.0
	 */
	protected function setup() {
		$this->set_integration( 'PPA' );
		$this->set_name( 'Assignments' );
		$this->set_icon_url( PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/images/presspilot-mascot.svg' );
	}
}
