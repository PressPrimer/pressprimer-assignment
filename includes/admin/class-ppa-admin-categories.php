<?php
/**
 * Admin categories page
 *
 * Handles the categories and tags management interface.
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
 * Admin categories class
 *
 * Manages the categories admin page with React-powered interface.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Admin_Categories {

	/**
	 * Initialize categories admin
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Hooks will be added in future prompts.
	}

	/**
	 * Render categories page
	 *
	 * Outputs the container div for the React application to mount into.
	 *
	 * @since 1.0.0
	 */
	public function render() {
		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'pressprimer-assignment' ),
				esc_html__( 'Permission Denied', 'pressprimer-assignment' ),
				[ 'response' => 403 ]
			);
		}

		echo '<div class="wrap">';
		echo '<div id="ppa-admin-root" class="ppa-admin-react-root"></div>';
		echo '</div>';
	}
}
