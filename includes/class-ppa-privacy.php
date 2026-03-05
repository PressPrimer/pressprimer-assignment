<?php
/**
 * Privacy API integration
 *
 * Registers personal data exporters and erasers with the
 * WordPress Privacy API (Tools > Export/Erase Personal Data).
 *
 * @package PressPrimer_Assignment
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Privacy class
 *
 * Implements WordPress Privacy API hooks for exporting and
 * erasing personal data stored by the assignment plugin.
 *
 * Personal data stored:
 * - Submissions (text content, notes, scores, feedback)
 * - Uploaded files (filenames, paths, metadata)
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Privacy {

	/**
	 * Number of records to process per batch.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const BATCH_SIZE = 50;

	/**
	 * Initialize privacy hooks
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', [ __CLASS__, 'register_exporters' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ __CLASS__, 'register_erasers' ] );
	}

	/**
	 * Register personal data exporters
	 *
	 * @since 1.0.0
	 *
	 * @param array $exporters Registered exporters.
	 * @return array Modified exporters.
	 */
	public static function register_exporters( $exporters ) {
		$exporters['pressprimer-assignment-submissions'] = [
			'exporter_friendly_name' => __( 'PressPrimer Assignment Submissions', 'pressprimer-assignment' ),
			'callback'               => [ __CLASS__, 'export_submissions' ],
		];

		return $exporters;
	}

	/**
	 * Register personal data erasers
	 *
	 * @since 1.0.0
	 *
	 * @param array $erasers Registered erasers.
	 * @return array Modified erasers.
	 */
	public static function register_erasers( $erasers ) {
		$erasers['pressprimer-assignment-submissions'] = [
			'eraser_friendly_name' => __( 'PressPrimer Assignment Submissions', 'pressprimer-assignment' ),
			'callback'             => [ __CLASS__, 'erase_submissions' ],
		];

		return $erasers;
	}

	/**
	 * Export personal data for a user
	 *
	 * Exports all submission data including text content, scores,
	 * feedback, and uploaded file metadata.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email_address User email address.
	 * @param int    $page          Page number for batched processing.
	 * @return array Export response with 'data' and 'done' keys.
	 */
	public static function export_submissions( $email_address, $page = 1 ) {
		global $wpdb;

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return [
				'data' => [],
				'done' => true,
			];
		}

		$export_items = [];
		$offset       = ( $page - 1 ) * self::BATCH_SIZE;

		$submissions_table = $wpdb->prefix . 'ppa_submissions';
		$assignments_table = $wpdb->prefix . 'ppa_assignments';
		$files_table       = $wpdb->prefix . 'ppa_submission_files';

		// Get submissions for this user with assignment titles.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$submissions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, a.title AS assignment_title
				FROM {$submissions_table} s
				LEFT JOIN {$assignments_table} a ON s.assignment_id = a.id
				WHERE s.user_id = %d
				ORDER BY s.created_at ASC
				LIMIT %d OFFSET %d",
				$user->ID,
				self::BATCH_SIZE,
				$offset
			)
		);

		if ( empty( $submissions ) ) {
			return [
				'data' => [],
				'done' => true,
			];
		}

		foreach ( $submissions as $submission ) {
			$data = [];

			$data[] = [
				'name'  => __( 'Assignment', 'pressprimer-assignment' ),
				'value' => $submission->assignment_title ?: __( '(Deleted)', 'pressprimer-assignment' ),
			];

			$data[] = [
				'name'  => __( 'Submission Number', 'pressprimer-assignment' ),
				'value' => $submission->submission_number,
			];

			$data[] = [
				'name'  => __( 'Status', 'pressprimer-assignment' ),
				'value' => $submission->status,
			];

			if ( ! empty( $submission->student_notes ) ) {
				$data[] = [
					'name'  => __( 'Student Notes', 'pressprimer-assignment' ),
					'value' => $submission->student_notes,
				];
			}

			if ( ! empty( $submission->text_content ) ) {
				$data[] = [
					'name'  => __( 'Text Submission', 'pressprimer-assignment' ),
					'value' => $submission->text_content,
				];
			}

			if ( null !== $submission->score ) {
				$data[] = [
					'name'  => __( 'Score', 'pressprimer-assignment' ),
					'value' => $submission->score,
				];
			}

			if ( ! empty( $submission->feedback ) ) {
				$data[] = [
					'name'  => __( 'Feedback', 'pressprimer-assignment' ),
					'value' => $submission->feedback,
				];
			}

			if ( null !== $submission->passed ) {
				$data[] = [
					'name'  => __( 'Passed', 'pressprimer-assignment' ),
					'value' => $submission->passed ? __( 'Yes', 'pressprimer-assignment' ) : __( 'No', 'pressprimer-assignment' ),
				];
			}

			if ( ! empty( $submission->submitted_at ) ) {
				$data[] = [
					'name'  => __( 'Submitted At', 'pressprimer-assignment' ),
					'value' => $submission->submitted_at,
				];
			}

			if ( ! empty( $submission->graded_at ) ) {
				$data[] = [
					'name'  => __( 'Graded At', 'pressprimer-assignment' ),
					'value' => $submission->graded_at,
				];
			}

			// Get uploaded files for this submission.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$files = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT original_filename, file_size, mime_type, uploaded_at
					FROM {$files_table}
					WHERE submission_id = %d
					ORDER BY sort_order ASC",
					$submission->id
				)
			);

			if ( ! empty( $files ) ) {
				$file_list = [];
				foreach ( $files as $file ) {
					$file_list[] = sprintf(
						'%s (%s, %s)',
						$file->original_filename,
						$file->mime_type,
						size_format( $file->file_size )
					);
				}

				$data[] = [
					'name'  => __( 'Uploaded Files', 'pressprimer-assignment' ),
					'value' => implode( ', ', $file_list ),
				];
			}

			$export_items[] = [
				'group_id'          => 'pressprimer-assignment-submissions',
				'group_label'       => __( 'Assignment Submissions', 'pressprimer-assignment' ),
				'group_description' => __( 'Submissions and grades from PressPrimer Assignment.', 'pressprimer-assignment' ),
				'item_id'           => 'submission-' . $submission->id,
				'data'              => $data,
			];
		}

		// Check if there are more records to process.
		$done = count( $submissions ) < self::BATCH_SIZE;

		return [
			'data' => $export_items,
			'done' => $done,
		];
	}

	/**
	 * Erase personal data for a user
	 *
	 * Removes all submissions, uploaded files (both database records
	 * and physical files on disk), and associated metadata.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email_address User email address.
	 * @param int    $page          Page number for batched processing.
	 * @return array Erasure response with status keys.
	 */
	public static function erase_submissions( $email_address, $page = 1 ) {
		global $wpdb;

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return [
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => [],
				'done'           => true,
			];
		}

		$submissions_table = $wpdb->prefix . 'ppa_submissions';
		$files_table       = $wpdb->prefix . 'ppa_submission_files';
		$items_removed     = 0;
		$offset            = ( $page - 1 ) * self::BATCH_SIZE;

		// Get a batch of submissions for this user.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$submissions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM {$submissions_table}
				WHERE user_id = %d
				ORDER BY id ASC
				LIMIT %d OFFSET %d",
				$user->ID,
				self::BATCH_SIZE,
				$offset
			)
		);

		if ( empty( $submissions ) ) {
			return [
				'items_removed'  => 0 === $offset ? false : true,
				'items_retained' => false,
				'messages'       => [],
				'done'           => true,
			];
		}

		$upload_dir  = wp_upload_dir();
		$upload_base = trailingslashit( $upload_dir['basedir'] );

		foreach ( $submissions as $submission ) {
			// Get files for this submission.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$files = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, file_path FROM {$files_table}
					WHERE submission_id = %d",
					$submission->id
				)
			);

			// Delete physical files.
			foreach ( $files as $file ) {
				$full_path = $upload_base . $file->file_path;
				if ( file_exists( $full_path ) ) {
					wp_delete_file( $full_path );
				}
			}

			// Delete file database records.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete(
				$files_table,
				[ 'submission_id' => $submission->id ],
				[ '%d' ]
			);

			// Delete the submission record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete(
				$submissions_table,
				[ 'id' => $submission->id ],
				[ '%d' ]
			);

			++$items_removed;
		}

		// Check if there are more records.
		$done = count( $submissions ) < self::BATCH_SIZE;

		return [
			'items_removed'  => (bool) $items_removed,
			'items_retained' => false,
			'messages'       => [],
			'done'           => $done,
		];
	}
}
