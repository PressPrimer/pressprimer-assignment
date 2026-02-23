<?php
/**
 * Admin reports page
 *
 * Handles the reports and analytics interface.
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
 * Admin reports class
 *
 * Manages the reports admin page with React-powered interface.
 * The React bundle is enqueued by class-ppa-admin.php based on the current page.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Admin_Reports {

	/**
	 * Initialize reports admin
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// React assets are enqueued by class-ppa-admin.php.
		// Future hooks (AJAX, exports) will be registered here.
	}

	/**
	 * Render reports page
	 *
	 * Outputs the container div for the React application to mount into.
	 *
	 * @since 1.0.0
	 */
	public function render() {
		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_VIEW_REPORTS ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'pressprimer-assignment' ),
				esc_html__( 'Permission Denied', 'pressprimer-assignment' ),
				[ 'response' => 403 ]
			);
		}

		echo '<div id="ppa-reports-root" class="ppa-admin-react-root"></div>';
	}
}
