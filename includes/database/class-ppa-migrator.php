<?php
/**
 * Database migrator
 *
 * Handles database schema migrations and updates.
 *
 * @package PressPrimer_Assignment
 * @subpackage Database
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrator class
 *
 * Manages database schema creation and updates using WordPress dbDelta.
 * Checks version and only runs migrations when needed.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Migrator {

	/**
	 * Option name for storing database version
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DB_VERSION_OPTION = 'pressprimer_assignment_db_version';

	/**
	 * Maybe run migrations
	 *
	 * Checks if database needs to be updated and runs migrations if necessary.
	 * Safe to call multiple times - only runs when version changes.
	 *
	 * @since 1.0.0
	 */
	public static function maybe_migrate() {
		$current_version = get_option( self::DB_VERSION_OPTION, '0' );
		$target_version  = PRESSPRIMER_ASSIGNMENT_DB_VERSION;

		// Check if migration is needed.
		if ( version_compare( $current_version, $target_version, '<' ) ) {
			self::run_migrations( $current_version, $target_version );
		}
	}

	/**
	 * Run migrations
	 *
	 * Executes database migrations from current version to target version.
	 *
	 * @since 1.0.0
	 *
	 * @param string $from_version Current database version.
	 * @param string $to_version   Target database version.
	 */
	private static function run_migrations( $from_version, $to_version ) {
		// Run schema updates (includes dbDelta call).
		self::update_schema();

		// Run version-specific data migrations.
		self::run_data_migrations( $from_version, $to_version );

		// Update database version.
		update_option( self::DB_VERSION_OPTION, $to_version );

		// Log migration.
		self::log_migration( $from_version, $to_version );
	}

	/**
	 * Update database schema
	 *
	 * Runs dbDelta to create or update all database tables.
	 *
	 * @since 1.0.0
	 */
	private static function update_schema() {
		// Get schema SQL.
		$sql = PressPrimer_Assignment_Schema::get_schema();

		// Load WordPress upgrade functions and immediately use dbDelta.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify critical tables exist.
		self::verify_tables();
	}

	/**
	 * Run data migrations
	 *
	 * Runs version-specific data migrations for upgrades.
	 * This is where we handle data transformations between versions.
	 *
	 * @since 1.0.0
	 *
	 * @param string $from_version Version migrating from.
	 * @param string $to_version   Version migrating to.
	 */
	private static function run_data_migrations( $from_version, $to_version ) {
		// Future version-specific data migrations go here.
	}

	/**
	 * Verify tables exist
	 *
	 * Checks that all critical database tables were created successfully.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if all tables exist, false otherwise.
	 */
	private static function verify_tables() {
		global $wpdb;

		$required_tables = self::get_required_tables();
		$missing_tables  = [];

		foreach ( $required_tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

			if ( $table !== $table_exists ) {
				$missing_tables[] = $table;
			}
		}

		if ( ! empty( $missing_tables ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Log migration
	 *
	 * Records migration in plugin options.
	 *
	 * @since 1.0.0
	 *
	 * @param string $from_version Version migrated from.
	 * @param string $to_version   Version migrated to.
	 */
	private static function log_migration( $from_version, $to_version ) {
		// Get migration history.
		$history = get_option( 'pressprimer_assignment_migration_history', [] );

		// Add this migration.
		$history[] = [
			'from'      => $from_version,
			'to'        => $to_version,
			'timestamp' => current_time( 'mysql' ),
		];

		// Keep last 10 migrations.
		$history = array_slice( $history, -10 );

		// Save history.
		update_option( 'pressprimer_assignment_migration_history', $history );
	}

	/**
	 * Get current database version
	 *
	 * Returns the currently installed database version.
	 *
	 * @since 1.0.0
	 *
	 * @return string Database version.
	 */
	public static function get_current_version() {
		return get_option( self::DB_VERSION_OPTION, '0' );
	}

	/**
	 * Get migration history
	 *
	 * Returns array of past migrations.
	 *
	 * @since 1.0.0
	 *
	 * @return array Migration history.
	 */
	public static function get_migration_history() {
		return get_option( 'pressprimer_assignment_migration_history', [] );
	}

	/**
	 * Force migration
	 *
	 * Forces a migration to run regardless of version.
	 * Useful for debugging or manual repairs.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if migration successful.
	 */
	public static function force_migration() {
		// Verify capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$current_version = self::get_current_version();
		$target_version  = PRESSPRIMER_ASSIGNMENT_DB_VERSION;

		self::run_migrations( $current_version, $target_version );

		return true;
	}

	/**
	 * Get list of required tables
	 *
	 * Returns array of all table names required by the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of table names with wpdb prefix.
	 */
	public static function get_required_tables() {
		global $wpdb;

		return [
			$wpdb->prefix . 'ppa_assignments',
			$wpdb->prefix . 'ppa_submissions',
			$wpdb->prefix . 'ppa_submission_files',
			$wpdb->prefix . 'ppa_categories',
			$wpdb->prefix . 'ppa_assignment_tax',
		];
	}

	/**
	 * Get table status
	 *
	 * Returns status information for all plugin tables.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of table status info with keys: name, exists, row_count.
	 */
	public static function get_table_status() {
		global $wpdb;

		$tables  = self::get_required_tables();
		$results = [];

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$table_exists = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
			);

			$status = [
				'name'      => $table,
				'exists'    => ( $table === $table_exists ),
				'row_count' => 0,
			];

			// Get row count if table exists.
			if ( $status['exists'] ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$count               = $wpdb->get_var(
					$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table )
				);
				$status['row_count'] = (int) $count;
			}

			$results[] = $status;
		}

		return $results;
	}

	/**
	 * Check if any tables are missing
	 *
	 * Returns true if any required tables do not exist.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if tables are missing.
	 */
	public static function has_missing_tables() {
		$table_status = self::get_table_status();

		foreach ( $table_status as $table ) {
			if ( ! $table['exists'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Repair tables
	 *
	 * Recreates any missing database tables.
	 * Does not modify existing tables or data.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array with 'success' boolean and 'repaired' array of table names.
	 */
	public static function repair_tables() {
		// Verify capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			return [
				'success'  => false,
				'repaired' => [],
				'error'    => __( 'Permission denied.', 'pressprimer-assignment' ),
			];
		}

		// Get current status to know what's missing.
		$before_status = self::get_table_status();
		$was_missing   = [];

		foreach ( $before_status as $table ) {
			if ( ! $table['exists'] ) {
				$was_missing[] = $table['name'];
			}
		}

		// Run dbDelta to create missing tables.
		if ( class_exists( 'PressPrimer_Assignment_Schema' ) ) {
			$sql = PressPrimer_Assignment_Schema::get_schema();

			// Load WordPress upgrade functions and immediately use dbDelta.
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		// Check which tables were actually repaired.
		$after_status = self::get_table_status();
		$repaired     = [];

		foreach ( $after_status as $table ) {
			if ( $table['exists'] && in_array( $table['name'], $was_missing, true ) ) {
				$repaired[] = $table['name'];
			}
		}

		// Update database version if tables were repaired.
		if ( ! empty( $repaired ) ) {
			update_option( self::DB_VERSION_OPTION, PRESSPRIMER_ASSIGNMENT_DB_VERSION );
		}

		return [
			'success'  => count( $repaired ) === count( $was_missing ),
			'repaired' => $repaired,
		];
	}
}
