<?php
/**
 * Database schema
 *
 * Defines all database table structures for PressPrimer Assignment.
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
 * Schema class
 *
 * Provides SQL definitions for all plugin database tables.
 * Uses dbDelta-compatible syntax for safe schema updates.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Schema {

	/**
	 * Get the complete schema SQL
	 *
	 * Returns SQL for creating all plugin tables.
	 * Uses dbDelta-compatible syntax.
	 *
	 * @since 1.0.0
	 *
	 * @return string SQL for all table creation.
	 */
	public static function get_schema() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql  = self::get_assignments_table( $charset_collate );
		$sql .= self::get_submissions_table( $charset_collate );
		$sql .= self::get_submission_files_table( $charset_collate );
		$sql .= self::get_categories_table( $charset_collate );
		$sql .= self::get_assignment_tax_table( $charset_collate );

		return $sql;
	}

	/**
	 * Get assignments table schema
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL for assignments table.
	 */
	private static function get_assignments_table( $charset_collate ) {
		global $wpdb;

		return "CREATE TABLE {$wpdb->prefix}ppa_assignments (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			uuid CHAR(36) NOT NULL,
			title VARCHAR(255) NOT NULL,
			description TEXT DEFAULT NULL,
			instructions LONGTEXT DEFAULT NULL,
			grading_guidelines TEXT DEFAULT NULL,
			max_points DECIMAL(10,2) NOT NULL DEFAULT 100.00,
			passing_score DECIMAL(10,2) NOT NULL DEFAULT 60.00,
			allow_resubmission TINYINT(1) NOT NULL DEFAULT 0,
			max_resubmissions INT UNSIGNED DEFAULT 1,
			allowed_file_types TEXT DEFAULT NULL,
			max_file_size INT UNSIGNED NOT NULL DEFAULT 5242880,
			max_files INT UNSIGNED NOT NULL DEFAULT 5,
			submission_type ENUM('file', 'text', 'either') NOT NULL DEFAULT 'file',
			status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
			theme VARCHAR(50) NOT NULL DEFAULT 'default',
			author_id BIGINT UNSIGNED NOT NULL,
			notification_email VARCHAR(500) DEFAULT NULL,
			submission_count INT UNSIGNED NOT NULL DEFAULT 0,
			graded_count INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY author_id (author_id),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;\n";
	}

	/**
	 * Get submissions table schema
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL for submissions table.
	 */
	private static function get_submissions_table( $charset_collate ) {
		global $wpdb;

		return "CREATE TABLE {$wpdb->prefix}ppa_submissions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			uuid CHAR(36) NOT NULL,
			assignment_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			submission_number INT UNSIGNED NOT NULL DEFAULT 1,
			status ENUM('draft', 'submitted', 'grading', 'graded', 'returned') NOT NULL DEFAULT 'draft',
			student_notes TEXT DEFAULT NULL,
			text_content LONGTEXT DEFAULT NULL,
			word_count INT UNSIGNED DEFAULT NULL,
			submitted_at DATETIME DEFAULT NULL,
			graded_at DATETIME DEFAULT NULL,
			returned_at DATETIME DEFAULT NULL,
			grader_id BIGINT UNSIGNED DEFAULT NULL,
			grading_time_seconds INT UNSIGNED DEFAULT NULL,
			score DECIMAL(10,2) DEFAULT NULL,
			feedback LONGTEXT DEFAULT NULL,
			passed TINYINT(1) DEFAULT NULL,
			file_count INT UNSIGNED NOT NULL DEFAULT 0,
			total_file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
			meta_json TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			UNIQUE KEY assignment_user_number (assignment_id, user_id, submission_number),
			KEY assignment_id (assignment_id),
			KEY user_id (user_id),
			KEY status (status),
			KEY submitted_at (submitted_at),
			KEY graded_at (graded_at),
			KEY grader_id (grader_id)
		) $charset_collate;\n";
	}

	/**
	 * Get submission files table schema
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL for submission files table.
	 */
	private static function get_submission_files_table( $charset_collate ) {
		global $wpdb;

		return "CREATE TABLE {$wpdb->prefix}ppa_submission_files (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			submission_id BIGINT UNSIGNED NOT NULL,
			original_filename VARCHAR(255) NOT NULL,
			stored_filename VARCHAR(255) NOT NULL,
			file_path TEXT NOT NULL,
			file_size BIGINT UNSIGNED NOT NULL,
			mime_type VARCHAR(100) NOT NULL,
			file_extension VARCHAR(20) NOT NULL,
			file_hash CHAR(64) NOT NULL,
			text_extractable TINYINT(1) DEFAULT NULL,
			extracted_text LONGTEXT DEFAULT NULL,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY submission_id (submission_id),
			KEY file_hash (file_hash),
			KEY uploaded_at (uploaded_at)
		) $charset_collate;\n";
	}

	/**
	 * Get categories table schema
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL for categories table.
	 */
	private static function get_categories_table( $charset_collate ) {
		global $wpdb;

		return "CREATE TABLE {$wpdb->prefix}ppa_categories (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(200) NOT NULL,
			slug VARCHAR(200) NOT NULL,
			description TEXT DEFAULT NULL,
			parent_id BIGINT UNSIGNED DEFAULT NULL,
			taxonomy ENUM('category', 'tag') NOT NULL DEFAULT 'category',
			assignment_count INT UNSIGNED NOT NULL DEFAULT 0,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug_taxonomy (slug, taxonomy),
			KEY parent_id (parent_id),
			KEY taxonomy (taxonomy),
			KEY name (name)
		) $charset_collate;\n";
	}

	/**
	 * Get assignment taxonomy table schema
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL for assignment taxonomy table.
	 */
	private static function get_assignment_tax_table( $charset_collate ) {
		global $wpdb;

		return "CREATE TABLE {$wpdb->prefix}ppa_assignment_tax (
			assignment_id BIGINT UNSIGNED NOT NULL,
			category_id BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY  (assignment_id, category_id),
			KEY category_id (category_id)
		) $charset_collate;\n";
	}
}
