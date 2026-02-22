<?php
/**
 * Admin class
 *
 * Handles WordPress admin interface for PressPrimer Assignment.
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
 * Admin class
 *
 * Manages admin menus, pages, and assets for the plugin.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Admin {

	/**
	 * Initialize admin functionality
	 *
	 * Hooks into WordPress admin to add menus and enqueue assets.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'admin_menu', [ $this, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Initialize sub-admin classes.
		$this->init_sub_admins();
	}

	/**
	 * Initialize sub-admin page classes
	 *
	 * @since 1.0.0
	 */
	private function init_sub_admins() {
		if ( class_exists( 'PressPrimer_Assignment_Admin_Settings' ) ) {
			$settings = new PressPrimer_Assignment_Admin_Settings();
			$settings->init();
		}

		if ( class_exists( 'PressPrimer_Assignment_Admin_Assignments' ) ) {
			$assignments = new PressPrimer_Assignment_Admin_Assignments();
			$assignments->init();
		}

		if ( class_exists( 'PressPrimer_Assignment_Admin_Submissions' ) ) {
			$submissions = new PressPrimer_Assignment_Admin_Submissions();
			$submissions->init();
		}

		if ( class_exists( 'PressPrimer_Assignment_Admin_Categories' ) ) {
			$categories = new PressPrimer_Assignment_Admin_Categories();
			$categories->init();
		}

		if ( class_exists( 'PressPrimer_Assignment_Admin_Reports' ) ) {
			$reports = new PressPrimer_Assignment_Admin_Reports();
			$reports->init();
		}
	}

	/**
	 * Register admin menus
	 *
	 * Creates the main PPA Assignments menu and all submenus.
	 *
	 * @since 1.0.0
	 */
	public function register_menus() {
		// Main menu page.
		add_menu_page(
			__( 'PPA Assignments', 'pressprimer-assignment' ),
			__( 'PPA Assignments', 'pressprimer-assignment' ),
			PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL,
			'pressprimer-assignment',
			[ $this, 'render_assignments' ],
			'dashicons-media-document',
			31
		);

		// Assignments submenu (replaces main menu link).
		add_submenu_page(
			'pressprimer-assignment',
			__( 'Assignments', 'pressprimer-assignment' ),
			__( 'Assignments', 'pressprimer-assignment' ),
			PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL,
			'pressprimer-assignment',
			[ $this, 'render_assignments' ]
		);

		// Submissions submenu.
		add_submenu_page(
			'pressprimer-assignment',
			__( 'Submissions', 'pressprimer-assignment' ),
			__( 'Submissions', 'pressprimer-assignment' ),
			PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL,
			'pressprimer-assignment-submissions',
			[ $this, 'render_submissions' ]
		);

		// Categories submenu.
		add_submenu_page(
			'pressprimer-assignment',
			__( 'Categories', 'pressprimer-assignment' ),
			__( 'Categories', 'pressprimer-assignment' ),
			PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL,
			'pressprimer-assignment-categories',
			[ $this, 'render_categories' ]
		);

		// Reports submenu.
		add_submenu_page(
			'pressprimer-assignment',
			__( 'Reports', 'pressprimer-assignment' ),
			__( 'Reports', 'pressprimer-assignment' ),
			PressPrimer_Assignment_Capabilities::PPA_CAP_VIEW_REPORTS,
			'pressprimer-assignment-reports',
			[ $this, 'render_reports' ]
		);

		// Settings submenu.
		add_submenu_page(
			'pressprimer-assignment',
			__( 'Settings', 'pressprimer-assignment' ),
			__( 'Settings', 'pressprimer-assignment' ),
			PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_SETTINGS,
			'pressprimer-assignment-settings',
			[ $this, 'render_settings' ]
		);

		/**
		 * Fires after the core admin menu items are registered.
		 *
		 * Premium addons should use this hook to add their own submenu pages.
		 *
		 * @since 1.0.0
		 */
		do_action( 'pressprimer_assignment_admin_menu' );
	}

	/**
	 * Enqueue admin assets
	 *
	 * Loads CSS and JavaScript files for PressPrimer Assignment admin pages.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		// Get current page from URL for robust page detection.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page check.
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		// Only load on PressPrimer Assignment admin pages.
		$is_ppa_page = false !== strpos( $hook, 'pressprimer-assignment' )
			|| ( ! empty( $current_page ) && 0 === strpos( $current_page, 'pressprimer-assignment' ) );

		if ( ! $is_ppa_page ) {
			return;
		}

		// Enqueue admin CSS.
		wp_enqueue_style(
			'ppa-admin',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/css/admin.css',
			[],
			PRESSPRIMER_ASSIGNMENT_VERSION
		);

		// Enqueue admin JavaScript.
		wp_enqueue_script(
			'ppa-admin',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			PRESSPRIMER_ASSIGNMENT_VERSION,
			true
		);

		// Localize script with data.
		wp_localize_script(
			'ppa-admin',
			'pressprimerAssignmentAdmin',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pressprimer_assignment_admin_nonce' ),
				'debug'   => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'strings' => [
					'confirmDelete'      => __( 'Are you sure you want to delete this item?', 'pressprimer-assignment' ),
					'confirmDeleteTitle' => __( 'Delete Item', 'pressprimer-assignment' ),
					'error'              => __( 'An error occurred. Please try again.', 'pressprimer-assignment' ),
					'saved'              => __( 'Changes saved successfully.', 'pressprimer-assignment' ),
					'delete'             => __( 'Delete', 'pressprimer-assignment' ),
					'cancel'             => __( 'Cancel', 'pressprimer-assignment' ),
				],
			]
		);

		// Enqueue React admin app.
		$this->enqueue_react_app();
	}

	/**
	 * Enqueue React admin app assets
	 *
	 * Loads the compiled React application for admin pages.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_react_app() {
		$asset_file = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'build/admin.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'ppa-admin-react',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'build/admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Enqueue React app styles if they exist.
		$style_file = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'build/style-admin.css';
		if ( file_exists( $style_file ) ) {
			wp_enqueue_style(
				'ppa-admin-react',
				PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'build/style-admin.css',
				[],
				$asset['version']
			);
		}
	}

	/**
	 * Render assignments page
	 *
	 * @since 1.0.0
	 */
	public function render_assignments() {
		if ( class_exists( 'PressPrimer_Assignment_Admin_Assignments' ) ) {
			$admin = new PressPrimer_Assignment_Admin_Assignments();
			$admin->render();
		}
	}

	/**
	 * Render submissions page
	 *
	 * @since 1.0.0
	 */
	public function render_submissions() {
		if ( class_exists( 'PressPrimer_Assignment_Admin_Submissions' ) ) {
			$admin = new PressPrimer_Assignment_Admin_Submissions();
			$admin->render();
		}
	}

	/**
	 * Render categories page
	 *
	 * @since 1.0.0
	 */
	public function render_categories() {
		if ( class_exists( 'PressPrimer_Assignment_Admin_Categories' ) ) {
			$admin = new PressPrimer_Assignment_Admin_Categories();
			$admin->render();
		}
	}

	/**
	 * Render reports page
	 *
	 * @since 1.0.0
	 */
	public function render_reports() {
		if ( class_exists( 'PressPrimer_Assignment_Admin_Reports' ) ) {
			$admin = new PressPrimer_Assignment_Admin_Reports();
			$admin->render();
		}
	}

	/**
	 * Render settings page
	 *
	 * @since 1.0.0
	 */
	public function render_settings() {
		if ( class_exists( 'PressPrimer_Assignment_Admin_Settings' ) ) {
			$admin = new PressPrimer_Assignment_Admin_Settings();
			$admin->render();
		}
	}
}
