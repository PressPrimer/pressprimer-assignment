<?php
/**
 * Uninstall handler
 *
 * Handles complete removal of plugin data when uninstalled.
 * This file is called by WordPress when the user deletes the plugin.
 *
 * IMPORTANT: By default, this script preserves ALL data to prevent accidental data loss.
 * Data is only removed if the user has explicitly enabled "Remove all data on uninstall"
 * in the plugin settings page. This includes:
 * - Database tables
 * - Options
 * - User meta
 * - Post meta
 * - Capabilities and roles
 * - Transients
 *
 * @package PressPrimer_Assignment
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Define plugin path constant if not already defined
if ( ! defined( 'PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH' ) ) {
	define( 'PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

/**
 * Remove plugin data
 *
 * Only runs if user has explicitly opted in to remove all data.
 * By default, all data is preserved to prevent accidental data loss.
 */
function pressprimer_assignment_uninstall() {
	global $wpdb;

	// Get settings - use empty array as default
	$settings = get_option( 'pressprimer_assignment_settings', [] );

	// Check if user has EXPLICITLY opted to remove data on uninstall
	// Default behavior is to KEEP all data for safety
	// CRITICAL: Only delete data if the setting is EXPLICITLY set to boolean true
	$remove_data = false;
	if ( is_array( $settings ) && isset( $settings['remove_data_on_uninstall'] ) ) {
		$remove_data = ( true === $settings['remove_data_on_uninstall'] || '1' === $settings['remove_data_on_uninstall'] || 1 === $settings['remove_data_on_uninstall'] );
	}

	if ( ! $remove_data ) {
		// Keep all data - exit without removing anything
		return;
	}

	// User has explicitly chosen to remove all data
	// Proceed with complete removal

	// Remove all database tables
	pressprimer_assignment_drop_tables();

	// Remove all options
	pressprimer_assignment_remove_options();

	// Remove all user meta
	pressprimer_assignment_remove_user_meta();

	// Remove all post meta
	pressprimer_assignment_remove_post_meta();

	// Remove capabilities and roles
	pressprimer_assignment_remove_capabilities();

	// Clear any remaining transients
	pressprimer_assignment_clear_transients();
}

/**
 * Drop all plugin database tables
 *
 * Handles both single site and multisite installations.
 * For multisite, drops tables for all sites in the network.
 *
 * @since 1.0.0
 */
function pressprimer_assignment_drop_tables() {
	global $wpdb;

	// Table names without prefix (ppa_ short prefix for DB tables)
	$table_names = [
		'ppa_assignments',
		'ppa_submissions',
		'ppa_submission_files',
		'ppa_categories',
	];

	if ( is_multisite() ) {
		// Get all site IDs in the network
		$site_ids = get_sites( [ 'fields' => 'ids' ] );

		foreach ( $site_ids as $site_id ) {
			$prefix = $wpdb->get_blog_prefix( $site_id );

			foreach ( $table_names as $table_name ) {
				$full_table_name = $prefix . $table_name;
				$wpdb->query( "DROP TABLE IF EXISTS {$full_table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
	} else {
		// Single site installation
		foreach ( $table_names as $table_name ) {
			$full_table_name = $wpdb->prefix . $table_name;
			$wpdb->query( "DROP TABLE IF EXISTS {$full_table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}
}

/**
 * Remove all plugin options
 *
 * Handles both single site and multisite installations.
 * For multisite, removes options from all sites and network options.
 *
 * @since 1.0.0
 */
function pressprimer_assignment_remove_options() {
	global $wpdb;

	if ( is_multisite() ) {
		// Get all site IDs in the network
		$site_ids = get_sites( [ 'fields' => 'ids' ] );

		foreach ( $site_ids as $site_id ) {
			$prefix = $wpdb->get_blog_prefix( $site_id );

			// Delete all options that start with pressprimer_assignment_ for this site
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$prefix}options WHERE option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->esc_like( 'pressprimer_assignment_' ) . '%'
				)
			);
		}

		// Delete network-wide site options
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->sitemeta}
				WHERE meta_key LIKE %s",
				$wpdb->esc_like( 'pressprimer_assignment_' ) . '%'
			)
		);
	} else {
		// Single site - delete all options that start with pressprimer_assignment_
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s",
				$wpdb->esc_like( 'pressprimer_assignment_' ) . '%'
			)
		);
	}
}

/**
 * Remove all plugin user meta
 *
 * @since 1.0.0
 */
function pressprimer_assignment_remove_user_meta() {
	global $wpdb;

	// Delete all user meta that starts with pressprimer_assignment_
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta}
			WHERE meta_key LIKE %s",
			$wpdb->esc_like( 'pressprimer_assignment_' ) . '%'
		)
	);
}

/**
 * Remove all plugin post meta
 *
 * @since 1.0.0
 */
function pressprimer_assignment_remove_post_meta() {
	global $wpdb;

	// Delete all post meta that starts with pressprimer_assignment_
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta}
			WHERE meta_key LIKE %s",
			$wpdb->esc_like( 'pressprimer_assignment_' ) . '%'
		)
	);
}

/**
 * Remove capabilities from all roles
 *
 * Handles both single site and multisite installations.
 * For multisite, removes capabilities from all sites.
 *
 * @since 1.0.0
 */
function pressprimer_assignment_remove_capabilities() {
	if ( is_multisite() ) {
		// Get all site IDs in the network
		$site_ids = get_sites( [ 'fields' => 'ids' ] );

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			pressprimer_assignment_remove_site_capabilities();
			restore_current_blog();
		}
	} else {
		pressprimer_assignment_remove_site_capabilities();
	}
}

/**
 * Remove capabilities from roles for a single site
 *
 * @since 1.0.0
 */
function pressprimer_assignment_remove_site_capabilities() {
	// Remove capabilities from administrator
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->remove_cap( 'pressprimer_assignment_manage_all' );
		$admin->remove_cap( 'pressprimer_assignment_manage_own' );
		$admin->remove_cap( 'pressprimer_assignment_view_submissions_all' );
		$admin->remove_cap( 'pressprimer_assignment_view_submissions_own' );
		$admin->remove_cap( 'pressprimer_assignment_submit' );
		$admin->remove_cap( 'pressprimer_assignment_manage_settings' );
	}

	// Remove capabilities from subscriber
	$subscriber = get_role( 'subscriber' );
	if ( $subscriber ) {
		$subscriber->remove_cap( 'pressprimer_assignment_submit' );
	}
}

/**
 * Clear all plugin transients
 *
 * @since 1.0.0
 */
function pressprimer_assignment_clear_transients() {
	global $wpdb;

	// Delete all transients that start with pressprimer_assignment_
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE %s
			OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_pressprimer_assignment_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_pressprimer_assignment_' ) . '%'
		)
	);

	// If multisite, delete site transients
	if ( is_multisite() ) {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->sitemeta}
				WHERE meta_key LIKE %s
				OR meta_key LIKE %s",
				$wpdb->esc_like( '_site_transient_pressprimer_assignment_' ) . '%',
				$wpdb->esc_like( '_site_transient_timeout_pressprimer_assignment_' ) . '%'
			)
		);
	}
}

// Run the uninstall
pressprimer_assignment_uninstall();
