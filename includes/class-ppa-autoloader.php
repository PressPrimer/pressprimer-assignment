<?php
/**
 * Autoloader
 *
 * Handles automatic class loading for PressPrimer Assignment plugin classes.
 *
 * @package PressPrimer_Assignment
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autoloader class
 *
 * Automatically loads PressPrimer Assignment classes when they are instantiated.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Autoloader {

	/**
	 * Class to file mapping for subdirectories
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $directories = [
		'models',
		'admin',
		'api',
		'frontend',
		'services',
		'integrations',
		'database',
		'utilities',
		'blocks',
	];

	/**
	 * Register the autoloader
	 *
	 * Registers the autoload function with PHP's SPL autoloader.
	 *
	 * @since 1.0.0
	 */
	public static function register() {
		spl_autoload_register( [ __CLASS__, 'autoload' ] );
	}

	/**
	 * Autoload a class
	 *
	 * Converts class name to file path and includes the file if it exists.
	 * Only handles classes with PressPrimer_Assignment_ prefix.
	 *
	 * @since 1.0.0
	 *
	 * @param string $class The class name to autoload.
	 */
	public static function autoload( $class ) {
		// Only handle our classes
		if ( 0 !== strpos( $class, 'PressPrimer_Assignment_' ) ) {
			return;
		}

		// Convert class name to file name
		// PressPrimer_Assignment_Submission -> class-ppa-submission.php
		// Remove the PressPrimer_Assignment_ prefix and convert to ppa- format for file names
		$class_without_prefix = substr( $class, strlen( 'PressPrimer_Assignment_' ) );
		$file                 = 'class-ppa-' . strtolower( str_replace( '_', '-', $class_without_prefix ) ) . '.php';

		// Check in includes root
		$path = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'includes/' . $file;
		if ( file_exists( $path ) ) {
			require_once $path;
			return;
		}

		// Check in subdirectories
		foreach ( self::$directories as $dir ) {
			$path = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'includes/' . $dir . '/' . $file;
			if ( file_exists( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
}
