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
 * Follows the same hybrid architecture as PressPrimer Quiz:
 * PHP WP_List_Table for list views, React for editors and dashboards.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Admin {

	/**
	 * Sub-admin instances
	 *
	 * Stored so the same instance used for screen_options()
	 * is reused for render(), preserving the list_table reference.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $sub_admins = [];

	/**
	 * Initialize admin functionality
	 *
	 * Hooks into WordPress admin to add menus and enqueue assets.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'admin_menu', [ $this, 'register_menus' ] );
		add_action( 'admin_menu', [ $this, 'add_grading_badge' ], 999 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Initialize sub-admin classes.
		$this->init_sub_admins();
	}

	/**
	 * Initialize sub-admin page classes
	 *
	 * Each sub-admin class handles its own hooks (admin_init, screen options, etc.).
	 *
	 * @since 1.0.0
	 */
	private function init_sub_admins() {
		$classes = [
			'settings'    => 'PressPrimer_Assignment_Admin_Settings',
			'assignments' => 'PressPrimer_Assignment_Admin_Assignments',
			'submissions' => 'PressPrimer_Assignment_Admin_Submissions',
			'categories'  => 'PressPrimer_Assignment_Admin_Categories',
			'grading'     => 'PressPrimer_Assignment_Admin_Grading',
			'reports'     => 'PressPrimer_Assignment_Admin_Reports',
		];

		foreach ( $classes as $key => $class_name ) {
			if ( class_exists( $class_name ) ) {
				$this->sub_admins[ $key ] = new $class_name();
				$this->sub_admins[ $key ]->init();
			}
		}
	}

	/**
	 * Register admin menus
	 *
	 * Creates the main Assignments menu and all submenus.
	 *
	 * @since 1.0.0
	 */
	public function register_menus() {
		/**
		 * Filters the plugin name displayed in the admin menu.
		 *
		 * Used by Enterprise addon for white-label branding.
		 *
		 * @since 1.0.0
		 *
		 * @param string $name Default plugin name.
		 */
		$plugin_name = apply_filters(
			'pressprimer_assignment_plugin_name',
			__( 'Assignments', 'pressprimer-assignment' )
		);

		// Main menu page (Dashboard).
		add_menu_page(
			$plugin_name,
			$plugin_name,
			PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL,
			'pressprimer-assignment',
			[ $this, 'render_dashboard' ],
			$this->get_menu_icon(),
			31
		);

		// Dashboard submenu (replaces main menu link).
		add_submenu_page(
			'pressprimer-assignment',
			__( 'Dashboard', 'pressprimer-assignment' ),
			__( 'Dashboard', 'pressprimer-assignment' ),
			PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL,
			'pressprimer-assignment',
			[ $this, 'render_dashboard' ]
		);

		// Assignments submenu.
		add_submenu_page(
			'pressprimer-assignment',
			__( 'Assignments', 'pressprimer-assignment' ),
			__( 'Assignments', 'pressprimer-assignment' ),
			PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL,
			'pressprimer-assignment-assignments',
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

		// Grading queue submenu.
		add_submenu_page(
			'pressprimer-assignment',
			__( 'Grading', 'pressprimer-assignment' ),
			__( 'Grading', 'pressprimer-assignment' ),
			PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL,
			'pressprimer-assignment-grading',
			[ $this, 'render_grading' ]
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
	 * Loads CSS and JavaScript on PressPrimer Assignment admin pages.
	 * React bundles are loaded conditionally per page.
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

		// Conditionally enqueue React bundles per page.
		$this->maybe_enqueue_react_bundle( $current_page );
	}

	/**
	 * Conditionally enqueue React bundles for specific admin pages
	 *
	 * Loads the compiled React bundle and its auto-generated asset
	 * dependencies for pages that use React-based interfaces.
	 *
	 * @since 1.0.0
	 *
	 * @param string $current_page Current admin page slug.
	 */
	private function maybe_enqueue_react_bundle( $current_page ) {
		$bundles = [
			'pressprimer-assignment'         => 'dashboard',
			'pressprimer-assignment-reports' => 'reports',
		];

		if ( ! isset( $bundles[ $current_page ] ) ) {
			return;
		}

		$bundle_name = $bundles[ $current_page ];
		$asset_file  = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'build/' . $bundle_name . '.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'ppa-' . $bundle_name,
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'build/' . $bundle_name . '.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Enqueue the CSS if it exists.
		// @wordpress/scripts outputs CSS as style-{name}.css, not {name}.css.
		$css_file = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'build/style-' . $bundle_name . '.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'ppa-' . $bundle_name,
				PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'build/style-' . $bundle_name . '.css',
				[ 'ppa-admin' ],
				$asset['version']
			);
		}

		// Localize bundle-specific data.
		if ( 'dashboard' === $bundle_name ) {
			$this->localize_dashboard_data();
		}
	}

	/**
	 * Localize dashboard data for the React app
	 *
	 * Passes PHP-derived metadata to the dashboard React bundle
	 * via wp_localize_script(). Mirrors the Quiz dashboard pattern.
	 *
	 * @since 1.0.0
	 */
	private function localize_dashboard_data() {
		// Determine if user is a teacher (not a full admin).
		$is_teacher = ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL );

		/**
		 * Filters the plugin name displayed in the dashboard.
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

		/**
		 * Filters the dashboard logo URL.
		 *
		 * Used by Enterprise addon for white-label branding.
		 *
		 * @since 1.0.0
		 *
		 * @param string $logo_url Default logo URL.
		 */
		$dashboard_logo = apply_filters(
			'pressprimer_assignment_dashboard_logo',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/images/PressPrimer-Logo-White.svg'
		);

		/**
		 * Filters the dashboard welcome text.
		 *
		 * Used by Enterprise addon for white-label branding.
		 *
		 * @since 1.0.0
		 *
		 * @param string $text Default welcome text.
		 * @param string $name The plugin name (filtered).
		 */
		$welcome_text = apply_filters(
			'pressprimer_assignment_dashboard_welcome_text',
			/* translators: %s: plugin name */
			sprintf( __( 'Welcome to %s! Here\'s an overview of recent assignment activity.', 'pressprimer-assignment' ), $plugin_name ),
			$plugin_name
		);

		wp_localize_script(
			'ppa-dashboard',
			'pressprimerAssignmentDashboardData',
			[
				'pluginUrl'     => PRESSPRIMER_ASSIGNMENT_PLUGIN_URL,
				'isTeacher'     => $is_teacher,
				'pluginName'    => $plugin_name,
				'dashboardLogo' => $dashboard_logo,
				'welcomeText'   => $welcome_text,
				'urls'          => [
					'create_assignment' => admin_url( 'admin.php?page=pressprimer-assignment-assignments&action=new' ),
					'submissions'       => admin_url( 'admin.php?page=pressprimer-assignment-submissions' ),
					'grading'           => admin_url( 'admin.php?page=pressprimer-assignment-grading' ),
					'reports'           => admin_url( 'admin.php?page=pressprimer-assignment-reports' ),
				],
			]
		);
	}

	/**
	 * Render dashboard page
	 *
	 * Displays the main dashboard with overview and statistics.
	 *
	 * @since 1.0.0
	 */
	public function render_dashboard() {
		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'pressprimer-assignment' ),
				esc_html__( 'Permission Denied', 'pressprimer-assignment' ),
				[ 'response' => 403 ]
			);
		}

		echo '<div id="ppa-dashboard-root" class="ppa-admin-react-root"></div>';
	}

	/**
	 * Render assignments page
	 *
	 * @since 1.0.0
	 */
	public function render_assignments() {
		if ( isset( $this->sub_admins['assignments'] ) ) {
			$this->sub_admins['assignments']->render();
		}
	}

	/**
	 * Render submissions page
	 *
	 * @since 1.0.0
	 */
	public function render_submissions() {
		if ( isset( $this->sub_admins['submissions'] ) ) {
			$this->sub_admins['submissions']->render();
		}
	}

	/**
	 * Render grading queue page
	 *
	 * @since 1.0.0
	 */
	public function render_grading() {
		if ( isset( $this->sub_admins['grading'] ) ) {
			$this->sub_admins['grading']->render();
		}
	}

	/**
	 * Render categories page
	 *
	 * @since 1.0.0
	 */
	public function render_categories() {
		if ( isset( $this->sub_admins['categories'] ) ) {
			$this->sub_admins['categories']->render();
		}
	}

	/**
	 * Render reports page
	 *
	 * @since 1.0.0
	 */
	public function render_reports() {
		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_VIEW_REPORTS ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'pressprimer-assignment' ),
				esc_html__( 'Permission Denied', 'pressprimer-assignment' ),
				[ 'response' => 403 ]
			);
		}

		echo '<div id="ppa-reports-root" class="ppa-admin-react-root"></div>';
	}

	/**
	 * Render settings page
	 *
	 * @since 1.0.0
	 */
	public function render_settings() {
		if ( isset( $this->sub_admins['settings'] ) ) {
			$this->sub_admins['settings']->render();
		}
	}

	/**
	 * Add pending count badge to Grading menu item
	 *
	 * Appends the WordPress admin badge markup to the Grading
	 * submenu label showing how many submissions await grading.
	 *
	 * @since 1.0.0
	 */
	public function add_grading_badge() {
		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL ) ) {
			return;
		}

		if ( ! class_exists( 'PressPrimer_Assignment_Grading_Queue_Service' ) ) {
			return;
		}

		$count = PressPrimer_Assignment_Grading_Queue_Service::get_pending_count();

		if ( $count < 1 ) {
			return;
		}

		global $submenu;

		if ( ! isset( $submenu['pressprimer-assignment'] ) ) {
			return;
		}

		foreach ( $submenu['pressprimer-assignment'] as $key => $item ) {
			if ( 'pressprimer-assignment-grading' === $item[2] ) {
				$submenu['pressprimer-assignment'][ $key ][0] .= sprintf( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress core pattern for admin menu badges.
					' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>',
					$count
				);
				break;
			}
		}
	}

	/**
	 * Get the menu icon as a base64-encoded SVG
	 *
	 * Returns a document icon for the admin menu.
	 *
	 * @since 1.0.0
	 *
	 * @return string Base64-encoded SVG data URI or custom icon URL.
	 */
	private function get_menu_icon() {
		/**
		 * Filters the admin menu icon.
		 *
		 * Used by Enterprise addon for white-label branding.
		 *
		 * @since 1.0.0
		 *
		 * @param string $icon_url Empty string (use default) or custom icon URL.
		 */
		$custom_icon = apply_filters( 'pressprimer_assignment_plugin_icon', '' );

		if ( ! empty( $custom_icon ) ) {
			return $custom_icon;
		}

		// Document/assignment icon SVG.
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">'
			. '<path fill="#9CA1A7" d="M14 2H6C4.9 2 4 2.9 4 4v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6z'
			. 'M6 20V4h7v5h5v11H6z'
			. 'M8 15h8v2H8v-2z'
			. 'M8 11h8v2H8v-2z"/>'
			. '</svg>';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for data URI in add_menu_page().
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}
