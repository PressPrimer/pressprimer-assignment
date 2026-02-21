<?php
/**
 * Plugin deactivation handler
 *
 * Handles tasks that run when the plugin is deactivated.
 *
 * @package PressPrimer_Assignment
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivator class
 *
 * Contains all functionality for plugin deactivation.
 * Performs cleanup tasks that should run when plugin is deactivated.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Deactivator {

	/**
	 * Deactivate the plugin
	 *
	 * Runs when the plugin is deactivated.
	 * Cleans up temporary data and flushes rewrite rules.
	 *
	 * Note: This does NOT delete database tables or permanent data.
	 * That only happens on uninstall. See uninstall.php.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		// Clear transients
		self::clear_plugin_transients();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Set deactivation flag
		update_option( 'pressprimer_assignment_deactivation_time', current_time( 'timestamp' ) );
	}

	/**
	 * Clear plugin transients
	 *
	 * Removes all transients created by the plugin.
	 *
	 * @since 1.0.0
	 */
	private static function clear_plugin_transients() {
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

		// If using site transients (multisite)
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
}
