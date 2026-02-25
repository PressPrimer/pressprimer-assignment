<?php
/**
 * Plugin Name:       PressPrimer Assignment
 * Plugin URI:        https://pressprimer.com/assignment
 * Description:       Comprehensive assignment management for WordPress educators.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            PressPrimer
 * Author URI:        https://pressprimer.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pressprimer-assignment
 * Domain Path:       /languages
 *
 * @package PressPrimer_Assignment
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
define( 'PRESSPRIMER_ASSIGNMENT_VERSION', '1.0.0' );
define( 'PRESSPRIMER_ASSIGNMENT_PLUGIN_FILE', __FILE__ );
define( 'PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PRESSPRIMER_ASSIGNMENT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PRESSPRIMER_ASSIGNMENT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'PRESSPRIMER_ASSIGNMENT_DB_VERSION', '1.2.0' );

// Composer autoloader (for vendor dependencies)
if ( file_exists( PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
	require_once PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'vendor/autoload.php';
}

// Autoloader
require_once PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'includes/class-ppa-autoloader.php';
PressPrimer_Assignment_Autoloader::register();

// Activation/Deactivation hooks
register_activation_hook( __FILE__, [ 'PressPrimer_Assignment_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'PressPrimer_Assignment_Deactivator', 'deactivate' ] );

// Multisite: Hook for new site creation to set up tables
add_action( 'wp_initialize_site', [ 'PressPrimer_Assignment_Activator', 'activate_new_site' ], 10, 1 );

/**
 * Initialize plugin
 *
 * Initializes the main plugin class.
 * Hooked to 'init' to comply with WordPress 6.7+ translation loading requirements.
 *
 * @since 1.0.0
 */
function pressprimer_assignment_init() {
	// Initialize main plugin class
	$plugin = PressPrimer_Assignment_Plugin::get_instance();
	$plugin->run();
}
add_action( 'init', 'pressprimer_assignment_init', 0 );

/**
 * Get the addon manager instance
 *
 * Returns the singleton instance of the addon manager for addon registration
 * and compatibility checking.
 *
 * @since 1.0.0
 *
 * @return PressPrimer_Assignment_Addon_Manager The addon manager instance.
 */
function pressprimer_assignment_addon_manager() {
	return PressPrimer_Assignment_Addon_Manager::get_instance();
}

/**
 * Register a premium addon
 *
 * Helper function for addons to register themselves with the addon manager.
 *
 * Example usage:
 * ```php
 * add_action( 'pressprimer_assignment_register_addons', function() {
 *     pressprimer_assignment_register_addon( 'ppa-educator', [
 *         'name'     => 'PressPrimer Assignment Educator',
 *         'version'  => '1.0.0',
 *         'file'     => __FILE__,
 *         'requires' => '1.0.0',
 *         'tier'     => 'educator',
 *     ] );
 * } );
 * ```
 *
 * @since 1.0.0
 *
 * @param string $slug   Unique addon identifier.
 * @param array  $config Addon configuration array.
 * @return bool True on success, false if already registered.
 */
function pressprimer_assignment_register_addon( $slug, $config ) {
	return pressprimer_assignment_addon_manager()->register( $slug, $config );
}

/**
 * Check if a premium addon is active
 *
 * Use this to conditionally enable features that depend on premium addons.
 *
 * @since 1.0.0
 *
 * @param string $slug Addon slug to check.
 * @return bool True if addon is registered and compatible.
 */
function pressprimer_assignment_addon_active( $slug ) {
	return pressprimer_assignment_addon_manager()->is_active( $slug );
}
