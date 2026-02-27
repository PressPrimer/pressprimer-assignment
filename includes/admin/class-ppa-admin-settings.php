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
		// REST endpoint handles save/load. No admin_init hooks needed.
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
			'ppaSettingsData',
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
		$settings['appearance_theme'] = get_option( 'ppa_frontend_theme', 'default' );

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
				'advanced'    => [
					'label' => __( 'Advanced', 'pressprimer-assignment' ),
					'order' => 100,
				],
			]
		);

		/**
		 * Filters the settings page header mascot image URL.
		 *
		 * Used by Enterprise addon for white-label branding.
		 *
		 * @since 1.0.0
		 *
		 * @param string $mascot_url Default mascot image URL.
		 */
		$settings_mascot = apply_filters(
			'pressprimer_assignment_settings_header_mascot',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/images/construction-mascot.png'
		);

		return [
			'pluginUrl'      => PRESSPRIMER_ASSIGNMENT_PLUGIN_URL,
			'settingsMascot' => $settings_mascot,
			'settings'       => $settings,
			'settingsTabs'   => $settings_tabs,
			'defaults'       => [
				'siteName'   => get_bloginfo( 'name' ),
				'adminEmail' => get_bloginfo( 'admin_email' ),
			],
			'pages'          => $this->get_pages_list(),
			'systemInfo'     => [
				'pluginVersion' => PRESSPRIMER_ASSIGNMENT_VERSION,
				'dbVersion'     => get_option( 'pressprimer_assignment_db_version', 'Not set' ),
				'wpVersion'     => get_bloginfo( 'version' ),
				'phpVersion'    => PHP_VERSION,
			],
			'lmsStatus'      => $this->get_lms_status(),
		];
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
}
