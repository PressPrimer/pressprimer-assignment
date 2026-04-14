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
		if ( version_compare( $from_version, '1.1.0', '<' ) ) {
			self::migrate_to_1_1_0();
		}
		if ( version_compare( $from_version, '1.2.0', '<' ) ) {
			self::migrate_to_1_2_0();
		}
		if ( version_compare( $from_version, '1.3.0', '<' ) ) {
			self::migrate_to_1_3_0();
		}
		if ( version_compare( $from_version, '1.4.0', '<' ) ) {
			self::migrate_to_1_4_0();
		}
		if ( version_compare( $from_version, '1.6.0', '<' ) ) {
			self::migrate_to_1_6_0();
		}
		if ( version_compare( $from_version, '1.7.0', '<' ) ) {
			self::migrate_to_1_7_0();
		}
	}

	/**
	 * Migration to 1.1.0
	 *
	 * Adds text submission fields for the revised v1.0 scope:
	 * - submission_type on assignments
	 * - text_content, word_count on submissions
	 * - text_extractable on submission_files
	 *
	 * @since 1.0.0
	 */
	private static function migrate_to_1_1_0() {
		global $wpdb;

		$assignments_table = $wpdb->prefix . 'ppa_assignments';
		$submissions_table = $wpdb->prefix . 'ppa_submissions';
		$files_table       = $wpdb->prefix . 'ppa_submission_files';

		// Add submission_type to assignments.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$column_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $assignments_table, 'submission_type' )
		);
		if ( ! $column_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$assignments_table} ADD COLUMN submission_type ENUM('file', 'text', 'either') NOT NULL DEFAULT 'file' AFTER status" );
		}

		// Add text submission fields to submissions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$column_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $submissions_table, 'text_content' )
		);
		if ( ! $column_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$submissions_table} ADD COLUMN text_content LONGTEXT DEFAULT NULL AFTER student_notes" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$submissions_table} ADD COLUMN word_count INT UNSIGNED DEFAULT NULL AFTER text_content" );
		}

		// Add text_extractable to files.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$column_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $files_table, 'text_extractable' )
		);
		if ( ! $column_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$files_table} ADD COLUMN text_extractable TINYINT(1) DEFAULT NULL AFTER file_hash" );
		}
	}

	/**
	 * Migration to 1.2.0
	 *
	 * Adds extracted_text column to submission_files for storing
	 * full PDF text extraction results (populated asynchronously).
	 *
	 * @since 1.0.0
	 */
	private static function migrate_to_1_2_0() {
		global $wpdb;

		$files_table = $wpdb->prefix . 'ppa_submission_files';

		// Add extracted_text column.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$column_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $files_table, 'extracted_text' )
		);
		if ( ! $column_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$files_table} ADD COLUMN extracted_text LONGTEXT DEFAULT NULL AFTER text_extractable" );
		}
	}

	/**
	 * Migration to 1.3.0
	 *
	 * Adds notification_email column to assignments table for
	 * per-assignment email notification recipients.
	 *
	 * @since 1.0.0
	 */
	private static function migrate_to_1_3_0() {
		global $wpdb;

		$assignments_table = $wpdb->prefix . 'ppa_assignments';

		// Add notification_email to assignments.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$column_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $assignments_table, 'notification_email' )
		);
		if ( ! $column_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$assignments_table} ADD COLUMN notification_email VARCHAR(500) DEFAULT NULL AFTER author_id" );
		}
	}

	/**
	 * Migration to 1.4.0
	 *
	 * Adds grading_time_seconds column to submissions table for
	 * tracking active grading time per submission.
	 *
	 * @since 1.0.0
	 */
	private static function migrate_to_1_4_0() {
		global $wpdb;

		$submissions_table = $wpdb->prefix . 'ppa_submissions';

		// Add grading_time_seconds to submissions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$column_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $submissions_table, 'grading_time_seconds' )
		);
		if ( ! $column_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$submissions_table} ADD COLUMN grading_time_seconds INT UNSIGNED DEFAULT NULL AFTER grader_id" );
		}
	}

	/**
	 * Migration to 1.6.0
	 *
	 * Adds theme column to assignments table for per-assignment
	 * theme selection (overrides the global default theme).
	 *
	 * @since 1.0.0
	 */
	private static function migrate_to_1_6_0() {
		global $wpdb;

		$assignments_table = $wpdb->prefix . 'ppa_assignments';

		// Add theme column to assignments.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$column_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $assignments_table, 'theme' )
		);
		if ( ! $column_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$assignments_table} ADD COLUMN theme VARCHAR(50) NOT NULL DEFAULT 'default' AFTER status" );
		}
	}

	/**
	 * Migration to 1.7.0
	 *
	 * Recalculates assignment_count for all categories.
	 * Previous versions did not update counts when categories
	 * were assigned via the REST API, leaving stale zeros.
	 *
	 * @since 1.0.0
	 */
	private static function migrate_to_1_7_0() {
		if ( class_exists( 'PressPrimer_Assignment_Category' ) ) {
			PressPrimer_Assignment_Category::update_counts( null );
		}
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

		$tables = self::get_required_tables();

		/**
		 * Filter the list of database tables shown on the Status page.
		 *
		 * Addons can append their own table names (with $wpdb->prefix)
		 * so they appear alongside the core tables.
		 *
		 * @since 2.0.0
		 *
		 * @param string[] $tables Array of full table names including prefix.
		 */
		$tables = apply_filters( 'pressprimer_assignment_status_tables', $tables );

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
