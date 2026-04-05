<?php
/**
 * Admin settings page
 *
 * Handles the plugin settings and configuration interface.
 * Renders a React-based settings panel matching the Quiz pattern:
 * vertical tabs with sections, saved via REST API.
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
 * Admin settings class
 *
 * Manages the settings admin page with a React settings panel.
 * Settings are stored in a single option and managed via REST API.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Admin_Settings {

	/**
	 * Option name for all settings
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_NAME = 'pressprimer_assignment_settings';

	/**
	 * Settings page slug
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PAGE_SLUG = 'pressprimer-assignment-settings';

	/**
	 * Initialize settings admin
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// AJAX handler for database table repair.
		add_action( 'wp_ajax_pressprimer_assignment_repair_database_tables', [ $this, 'ajax_repair_database_tables' ] );
	}

	/**
	 * Render settings page
	 *
	 * Outputs the React root element and enqueues the settings panel bundle.
	 *
	 * @since 1.0.0
	 */
	public function render() {
		if ( ! current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_SETTINGS ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'pressprimer-assignment' ),
				esc_html__( 'Permission Denied', 'pressprimer-assignment' ),
				[ 'response' => 403 ]
			);
		}

		// Enqueue the React settings panel.
		$this->enqueue_react_settings();

		?>
		<!-- React Settings Root -->
		<div id="ppa-settings-root"></div>
		<?php
	}

	/**
	 * Enqueue React settings panel
	 *
	 * Loads the compiled React bundle and localizes settings data.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_react_settings() {
		// Enqueue WordPress media library for logo selection.
		wp_enqueue_media();

		// Enqueue built React app.
		$asset_file = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'build/settings-panel.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : [
			'dependencies' => [ 'wp-element', 'wp-i18n', 'wp-api-fetch' ],
			'version'      => PRESSPRIMER_ASSIGNMENT_VERSION,
		];

		wp_enqueue_script(
			'ppa-settings-panel',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'build/settings-panel.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Enqueue the CSS if it exists.
		$css_file = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'build/style-settings-panel.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'ppa-settings-panel',
				PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'build/style-settings-panel.css',
				[],
				$asset['version']
			);
		}

		// Prepare settings data for React.
		$settings_data = $this->get_settings_data_for_react();

		wp_localize_script(
			'ppa-settings-panel',
			'pressprimerAssignmentSettingsData',
			$settings_data
		);
	}

	/**
	 * Get settings data for React
	 *
	 * Builds the data object passed to the React settings panel.
	 *
	 * @since 1.0.0
	 *
	 * @return array Settings data.
	 */
	private function get_settings_data_for_react() {
		$settings = get_option( self::OPTION_NAME, [] );

		// Ensure remove_data_on_uninstall defaults to false.
		$remove_data_value = false;
		if ( isset( $settings['remove_data_on_uninstall'] ) ) {
			$remove_data_value = ( true === $settings['remove_data_on_uninstall']
				|| '1' === $settings['remove_data_on_uninstall']
				|| 1 === $settings['remove_data_on_uninstall'] );
		}
		$settings['remove_data_on_uninstall'] = $remove_data_value ? 1 : 0;

		// Include appearance theme from its separate option.
		$settings['appearance_theme'] = get_option( 'pressprimer_assignment_frontend_theme', 'default' );

		/**
		 * Filter the settings tabs displayed on the settings page.
		 *
		 * Premium addons can add their own tabs to the settings interface.
		 *
		 * @since 1.0.0
		 *
		 * @param array $tabs Default settings tabs.
		 */
		$settings_tabs = apply_filters(
			'pressprimer_assignment_settings_tabs',
			[
				'general'     => [
					'label' => __( 'General', 'pressprimer-assignment' ),
					'order' => 10,
				],
				'appearance'  => [
					'label' => __( 'Appearance', 'pressprimer-assignment' ),
					'order' => 20,
				],
				'email'       => [
					'label' => __( 'Email', 'pressprimer-assignment' ),
					'order' => 30,
				],
				'integration' => [
					'label' => __( 'Integrations', 'pressprimer-assignment' ),
					'order' => 50,
				],
				'status'      => [
					'label' => __( 'Status', 'pressprimer-assignment' ),
					'order' => 100,
				],
				'advanced'    => [
					'label' => __( 'Advanced', 'pressprimer-assignment' ),
					'order' => 110,
				],
			]
		);

		/**
		 * Filter the mascot image URL used in React admin page headers.
		 *
		 * Enterprise addon hooks this to return a replacement image or empty string.
		 *
		 * @since 2.0.0
		 *
		 * @param string $url Default: the PressPrimer mascot image URL.
		 */
		$settings_mascot = apply_filters(
			'pressprimer_assignment_mascot_url',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/images/construction-mascot.png'
		);

		/**
		 * Filters the settings page header mascot image URL.
		 *
		 * Used by Enterprise addon for white-label branding.
		 * Kept for backward compatibility; prefer pressprimer_assignment_mascot_url.
		 *
		 * @since 1.0.0
		 *
		 * @param string $mascot_url Default mascot image URL.
		 */
		$settings_mascot = apply_filters( 'pressprimer_assignment_settings_header_mascot', $settings_mascot );

		/**
		 * Filter the header background color used in React admin page headers.
		 *
		 * Enterprise addon hooks this to return a custom hex color.
		 *
		 * @since 2.0.0
		 *
		 * @param string $color Default: '#334155' (the PressPrimer dark slate).
		 */
		$header_bg_color = apply_filters( 'pressprimer_assignment_header_bg_color', '#334155' );

		// Fire per-tab action hooks for addons to register their settings.
		foreach ( array_keys( $settings_tabs ) as $tab_key ) {
			/**
			 * Fires when building settings data for a specific tab.
			 *
			 * Addons can use this action to register REST routes or enqueue
			 * scripts needed for their settings tab content.
			 *
			 * @since 2.0.0
			 *
			 * @param array $settings Current settings values.
			 */
			do_action( "pressprimer_assignment_settings_tab_{$tab_key}", $settings );
		}

		// Collect database table status.
		$table_status = [];
		if ( class_exists( 'PressPrimer_Assignment_Migrator' ) ) {
			$table_status = PressPrimer_Assignment_Migrator::get_table_status();
		}

		// Get active theme info.
		$theme        = wp_get_theme();
		$active_theme = $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' );

		$data = [
			'pluginUrl'      => PRESSPRIMER_ASSIGNMENT_PLUGIN_URL,
			'settingsMascot' => $settings_mascot,
			'headerBgColor'  => $header_bg_color,
			'settings'       => $settings,
			'settingsTabs'   => $settings_tabs,
			'defaults'       => [
				'siteName'   => get_bloginfo( 'name' ),
				'adminEmail' => get_bloginfo( 'admin_email' ),
			],
			'pages'          => $this->get_pages_list(),
			'systemInfo'     => [
				'pluginVersion'            => PRESSPRIMER_ASSIGNMENT_VERSION,
				'dbVersion'                => get_option( 'pressprimer_assignment_db_version', 'Not set' ),
				'wpVersion'                => get_bloginfo( 'version' ),
				'siteUrl'                  => get_site_url(),
				'memoryLimit'              => ini_get( 'memory_limit' ),
				'phpVersion'               => PHP_VERSION,
				'postMaxSize'              => ini_get( 'post_max_size' ),
				'uploadMaxFilesize'        => ini_get( 'upload_max_filesize' ),
				'maxExecutionTime'         => ini_get( 'max_execution_time' ),
				'mysqlVersion'             => $this->get_mysql_version(),
				'isMultisite'              => is_multisite(),
				'activeTheme'              => $active_theme,
				'addonVersions'            => [
					'educator'   => defined( 'PRESSPRIMER_ASSIGNMENT_EDUCATOR_VERSION' ) ? PRESSPRIMER_ASSIGNMENT_EDUCATOR_VERSION : null,
					'school'     => defined( 'PRESSPRIMER_ASSIGNMENT_SCHOOL_VERSION' ) ? PRESSPRIMER_ASSIGNMENT_SCHOOL_VERSION : null,
					'enterprise' => defined( 'PRESSPRIMER_ASSIGNMENT_ENTERPRISE_VERSION' ) ? PRESSPRIMER_ASSIGNMENT_ENTERPRISE_VERSION : null,
				],
				'totalAssignments'         => $this->get_count( 'ppa_assignments' ),
				'totalSubmissions'         => $this->get_count( 'ppa_submissions', "status != 'draft'" ),
				'totalFiles'               => $this->get_count( 'ppa_submission_files' ),
				'totalCategories'          => $this->get_count( 'ppa_categories' ),
				'fileHandlingCapabilities' => $this->get_file_handling_capabilities(),
				'activePlugins'            => $this->get_active_plugins_list(),
			],
			'lmsStatus'      => $this->get_lms_status(),
			'databaseTables' => $table_status,
			'nonces'         => [
				'repairTables' => wp_create_nonce( 'pressprimer_assignment_repair_tables_nonce' ),
			],
		];

		/**
		 * Filter the settings page localized data before it is sent to React.
		 *
		 * Addons can use this to inject additional data for their settings tabs.
		 *
		 * @since 2.0.0
		 *
		 * @param array $data Settings page data.
		 */
		return apply_filters( 'pressprimer_assignment_settings_data', $data );
	}

	/**
	 * Get LMS detection status for React
	 *
	 * Checks which LMS plugins are active to pre-populate the
	 * Integrations tab before async REST calls complete.
	 *
	 * @since 1.0.0
	 *
	 * @return array LMS status data.
	 */
	private function get_lms_status() {
		$status = [];

		// LearnDash.
		$status['learndash'] = [
			'active'  => defined( 'LEARNDASH_VERSION' ),
			'version' => defined( 'LEARNDASH_VERSION' ) ? LEARNDASH_VERSION : null,
		];

		// Tutor LMS.
		$status['tutorlms'] = [
			'active'  => defined( 'TUTOR_VERSION' ),
			'version' => defined( 'TUTOR_VERSION' ) ? TUTOR_VERSION : null,
		];

		// LifterLMS.
		$status['lifterlms'] = [
			'active'  => defined( 'LLMS_PLUGIN_FILE' ),
			'version' => defined( 'LLMS_PLUGIN_VERSION' ) ? LLMS_PLUGIN_VERSION : null,
		];

		// LearnPress.
		$status['learnpress'] = [
			'active'  => defined( 'LEARNPRESS_VERSION' ),
			'version' => defined( 'LEARNPRESS_VERSION' ) ? LEARNPRESS_VERSION : null,
		];

		return $status;
	}

	/**
	 * Get list of published pages for page selector dropdowns
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of pages with id and title.
	 */
	private function get_pages_list() {
		$pages = get_pages(
			[
				'post_status' => 'publish',
				'sort_column' => 'post_title',
				'sort_order'  => 'ASC',
			]
		);

		$list = [];
		foreach ( $pages as $page ) {
			$list[] = [
				'id'    => $page->ID,
				'title' => $page->post_title,
			];
		}

		return $list;
	}

	/**
	 * Get MySQL version
	 *
	 * @since 1.0.0
	 *
	 * @return string MySQL version string.
	 */
	private function get_mysql_version() {
		global $wpdb;

		return $wpdb->db_version();
	}

	/**
	 * Get row count for a plugin table
	 *
	 * Returns the count of rows in a plugin table, optionally filtered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $table_name Table name without prefix (e.g., 'ppa_assignments').
	 * @param string $where      Optional WHERE clause (without 'WHERE' keyword).
	 * @return int Row count.
	 */
	private function get_count( $table_name, $where = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . $table_name;

		// Verify table exists before querying.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( $table !== $exists ) {
			return 0;
		}

		if ( ! empty( $where ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
	}

	/**
	 * Get file handling capabilities
	 *
	 * Reports on the server's ability to handle file uploads, previews,
	 * and text extraction for assignment submissions.
	 *
	 * @since 1.0.0
	 *
	 * @return array File handling capabilities.
	 */
	private function get_file_handling_capabilities() {
		$capabilities = [];

		// File upload security: finfo extension for MIME detection.
		$capabilities['finfo'] = function_exists( 'finfo_open' );

		// PDF text extraction: Smalot PDF Parser library.
		$capabilities['pdfParserLibrary'] = class_exists( '\\Smalot\\PdfParser\\Parser' );

		// PDF text extraction: pdftotext command-line tool.
		$pdftotext_available = false;
		if ( function_exists( 'exec' ) && ! in_array( 'exec', array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) ), true ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Checking pdftotext availability.
			$which_result = @exec( 'which pdftotext 2>/dev/null' );
			if ( ! empty( $which_result ) && file_exists( $which_result ) ) {
				$pdftotext_available = true;
			}
		}
		$capabilities['pdftotext'] = $pdftotext_available;

		// DOCX preview: ZipArchive for reading .docx files.
		$capabilities['zipArchive'] = class_exists( 'ZipArchive' );

		// Image handling: GD library for image processing.
		$capabilities['gd'] = extension_loaded( 'gd' );

		return $capabilities;
	}

	/**
	 * Get list of active plugins
	 *
	 * Returns a simplified list of active plugin names and versions.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of "Plugin Name Version" strings.
	 */
	private function get_active_plugins_list() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', [] );
		$list           = [];

		foreach ( $active_plugins as $plugin_path ) {
			if ( ! isset( $all_plugins[ $plugin_path ] ) ) {
				continue;
			}

			$plugin_data = $all_plugins[ $plugin_path ];

			if ( ! empty( $plugin_data['Name'] ) ) {
				$version = ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : '';
				$list[]  = trim( $plugin_data['Name'] . ' ' . $version );
			}
		}

		return $list;
	}

	/**
	 * AJAX handler: Repair database tables
	 *
	 * Recreates missing database tables and returns the updated status.
	 *
	 * @since 1.0.0
	 */
	public function ajax_repair_database_tables() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pressprimer_assignment_repair_tables_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-assignment' ) ] );
		}

		// Verify capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'pressprimer-assignment' ) ] );
		}

		// Attempt repair.
		if ( ! class_exists( 'PressPrimer_Assignment_Migrator' ) ) {
			wp_send_json_error( [ 'message' => __( 'Migrator class not available.', 'pressprimer-assignment' ) ] );
		}

		$result = PressPrimer_Assignment_Migrator::repair_tables();

		if ( $result['success'] ) {
			$repaired_names = array_map(
				function ( $name ) {
					global $wpdb;
					return str_replace( $wpdb->prefix, '', $name );
				},
				$result['repaired']
			);

			wp_send_json_success(
				[
					'message'     => sprintf(
						/* translators: %d: number of tables repaired */
						__( '%d table(s) repaired successfully.', 'pressprimer-assignment' ),
						count( $result['repaired'] )
					),
					'repaired'    => $repaired_names,
					'tableStatus' => PressPrimer_Assignment_Migrator::get_table_status(),
				]
			);
		} else {
			wp_send_json_error(
				[
					'message'     => __( 'Some tables could not be repaired.', 'pressprimer-assignment' ),
					'tableStatus' => PressPrimer_Assignment_Migrator::get_table_status(),
				]
			);
		}
	}
}
