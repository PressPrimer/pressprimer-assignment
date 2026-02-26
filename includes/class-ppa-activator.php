<?php
/**
 * Plugin activation handler
 *
 * Handles tasks that run when the plugin is activated.
 *
 * @package PressPrimer_Assignment
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activator class
 *
 * Contains all functionality for plugin activation.
 * Sets up database tables, default options, and capabilities.
 * Supports both single site and multisite network activation.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Activator {

	/**
	 * Activate the plugin
	 *
	 * Runs when the plugin is activated.
	 * Sets up database tables, default options, and user capabilities.
	 * Handles both single site and network-wide activation.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $network_wide Whether the plugin is being activated network-wide.
	 */
	public static function activate( $network_wide = false ) {
		// Handle network-wide activation in multisite
		if ( is_multisite() && $network_wide ) {
			self::activate_for_network();
			return;
		}

		// Single site activation
		self::activate_single_site();
	}

	/**
	 * Activate for entire network
	 *
	 * Runs activation on all sites in a multisite network.
	 *
	 * @since 1.0.0
	 */
	private static function activate_for_network() {
		global $wpdb;

		// Get all site IDs
		$site_ids = get_sites( [ 'fields' => 'ids' ] );

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			self::activate_single_site();
			restore_current_blog();
		}
	}

	/**
	 * Activate for a single site
	 *
	 * Performs all activation tasks for one site.
	 *
	 * @since 1.0.0
	 */
	private static function activate_single_site() {
		// Check WordPress version
		if ( version_compare( get_bloginfo( 'version' ), '6.4', '<' ) ) {
			wp_die(
				'PressPrimer Assignment requires WordPress 6.4 or higher.',
				'Plugin Activation Error',
				[ 'back_link' => true ]
			);
		}

		// Check PHP version
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			wp_die(
				'PressPrimer Assignment requires PHP 7.4 or higher.',
				'Plugin Activation Error',
				[ 'back_link' => true ]
			);
		}

		// Set default options
		self::set_default_options();

		// Ensure database tables exist - always check on activation
		// This handles both fresh installs and reinstalls after data removal
		self::ensure_database_tables();

		// Run database migrations for version upgrades
		if ( class_exists( 'PressPrimer_Assignment_Migrator' ) ) {
			PressPrimer_Assignment_Migrator::maybe_migrate();
		}

		// Setup capabilities
		if ( class_exists( 'PressPrimer_Assignment_Capabilities' ) ) {
			PressPrimer_Assignment_Capabilities::setup_capabilities();
		}

		// Create default pages.
		self::create_default_pages();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Set activation flag
		update_option( 'pressprimer_assignment_activation_time', current_time( 'timestamp' ) );
		update_option( 'pressprimer_assignment_version', PRESSPRIMER_ASSIGNMENT_VERSION );
	}

	/**
	 * Ensure database tables exist
	 *
	 * Checks if tables are missing and creates them if needed.
	 * This runs on every activation to handle reinstalls after data removal.
	 *
	 * @since 1.0.0
	 */
	private static function ensure_database_tables() {
		global $wpdb;

		// Check if at least one critical table exists
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->prefix . 'ppa_assignments'
			)
		);

		// If tables don't exist, force create them
		if ( ! $table_exists ) {
			// Load WordPress upgrade functions
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			// Load and run schema
			if ( class_exists( 'PressPrimer_Assignment_Schema' ) ) {
				$sql = PressPrimer_Assignment_Schema::get_schema();
				dbDelta( $sql );

				// Update database version
				update_option( 'pressprimer_assignment_db_version', PRESSPRIMER_ASSIGNMENT_DB_VERSION );
			}
		}
	}

	/**
	 * Set default options
	 *
	 * Creates default plugin settings if they don't exist.
	 *
	 * @since 1.0.0
	 */
	private static function set_default_options() {
		// Default settings
		$default_settings = [
			'default_max_points'       => 100,
			'email_from_name'          => get_bloginfo( 'name' ),
			'email_from_address'       => get_bloginfo( 'admin_email' ),
			'remove_data_on_uninstall' => false, // Keep data by default for safety
		];

		$existing_settings = get_option( 'pressprimer_assignment_settings' );

		if ( false === $existing_settings ) {
			// Fresh install - set all defaults
			add_option( 'pressprimer_assignment_settings', $default_settings );
		} else {
			// Existing install - ALWAYS reset remove_data_on_uninstall to false on activation
			// This is a critical safety measure to prevent accidental data loss
			$existing_settings['remove_data_on_uninstall'] = false;
			update_option( 'pressprimer_assignment_settings', $existing_settings );
		}
	}

	/**
	 * Create default pages
	 *
	 * Creates a "My Submissions" page with the shortcode on first activation.
	 * Stores the page ID in settings so it can be referenced by the email service.
	 * Only creates the page once; skips if the setting already has a valid page.
	 *
	 * @since 1.0.0
	 */
	private static function create_default_pages() {
		$settings = get_option( 'pressprimer_assignment_settings', [] );

		// Check if a page is already mapped and still exists.
		if ( ! empty( $settings['my_submissions_page_id'] ) ) {
			$existing = get_post( absint( $settings['my_submissions_page_id'] ) );
			if ( $existing && 'trash' !== $existing->post_status ) {
				return;
			}
		}

		// Create the page.
		$page_id = wp_insert_post(
			[
				'post_type'    => 'page',
				'post_title'   => __( 'My Submissions', 'pressprimer-assignment' ),
				'post_name'    => 'my-submissions',
				'post_content' => '<!-- wp:shortcode -->[ppa_my_submissions]<!-- /wp:shortcode -->',
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id() ?: 1,
			]
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			$settings['my_submissions_page_id'] = $page_id;
			update_option( 'pressprimer_assignment_settings', $settings );
		}
	}

	/**
	 * Activate for a new site in multisite
	 *
	 * Called when a new site is created in a multisite network
	 * and the plugin is network-activated.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Site $new_site New site object.
	 */
	public static function activate_new_site( $new_site ) {
		// Only run if plugin is network-activated
		if ( ! is_plugin_active_for_network( PRESSPRIMER_ASSIGNMENT_PLUGIN_BASENAME ) ) {
			return;
		}

		switch_to_blog( $new_site->blog_id );
		self::activate_single_site();
		restore_current_blog();
	}
}
