<?php
/**
 * Submission file model
 *
 * Represents a file uploaded as part of a submission.
 *
 * @package PressPrimer_Assignment
 * @subpackage Models
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Submission file model class
 *
 * Handles CRUD operations for submission files, including
 * file integrity verification and download URL generation.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Submission_File extends PressPrimer_Assignment_Model {

	/**
	 * Submission ID
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $submission_id = 0;

	/**
	 * Original filename as uploaded
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $original_filename = '';

	/**
	 * Secure filename on disk
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $stored_filename = '';

	/**
	 * Relative path from uploads directory
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $file_path = '';

	/**
	 * File size in bytes
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $file_size = 0;

	/**
	 * MIME type
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $mime_type = '';

	/**
	 * File extension
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $file_extension = '';

	/**
	 * SHA-256 hash for integrity verification
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $file_hash = '';

	/**
	 * Sort order
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $sort_order = 0;

	/**
	 * Upload timestamp
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $uploaded_at = '';

	/**
	 * Get table name
	 *
	 * @since 1.0.0
	 *
	 * @return string Table name without prefix.
	 */
	protected static function get_table_name() {
		return 'ppa_submission_files';
	}

	/**
	 * Get fillable fields
	 *
	 * @since 1.0.0
	 *
	 * @return array Field names that can be mass-assigned.
	 */
	protected static function get_fillable_fields() {
		return [
			'submission_id',
			'original_filename',
			'stored_filename',
			'file_path',
			'file_size',
			'mime_type',
			'file_extension',
			'file_hash',
			'sort_order',
		];
	}

	/**
	 * Get queryable fields
	 *
	 * Override to include uploaded_at (not in fillable but queryable).
	 *
	 * @since 1.0.0
	 *
	 * @return array Field names safe for use in queries.
	 */
	protected static function get_queryable_fields() {
		$fields   = parent::get_queryable_fields();
		$fields[] = 'uploaded_at';

		return $fields;
	}

	/**
	 * Create new submission file record
	 *
	 * Validates input and creates a new file record.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data File data.
	 * @return int|WP_Error File record ID on success, WP_Error on failure.
	 */
	public static function create( array $data ) {
		// Validate required fields.
		$required = [ 'submission_id', 'original_filename', 'stored_filename', 'file_path', 'file_size', 'mime_type', 'file_extension', 'file_hash' ];

		foreach ( $required as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return new WP_Error(
					'ppa_missing_field',
					/* translators: %s: field name */
					sprintf( __( 'Required field "%s" is missing.', 'pressprimer-assignment' ), $field )
				);
			}
		}

		// Validate submission_id.
		$data['submission_id'] = absint( $data['submission_id'] );
		if ( 0 === $data['submission_id'] ) {
			return new WP_Error(
				'ppa_invalid_submission',
				__( 'Invalid submission ID.', 'pressprimer-assignment' )
			);
		}

		// Validate file_size.
		$data['file_size'] = absint( $data['file_size'] );
		if ( 0 === $data['file_size'] ) {
			return new WP_Error(
				'ppa_invalid_file_size',
				__( 'File size must be greater than zero.', 'pressprimer-assignment' )
			);
		}

		// Validate file_hash length (SHA-256 = 64 hex chars).
		if ( 64 !== strlen( $data['file_hash'] ) ) {
			return new WP_Error(
				'ppa_invalid_hash',
				__( 'File hash must be a valid SHA-256 hash.', 'pressprimer-assignment' )
			);
		}

		// Call parent create.
		$file_id = parent::create( $data );

		// Fire action hook.
		if ( ! is_wp_error( $file_id ) ) {
			/**
			 * Fires after a submission file record is created.
			 *
			 * @since 1.0.0
			 *
			 * @param int   $file_id The file record ID.
			 * @param array $data    The file data.
			 */
			do_action( 'pressprimer_assignment_file_created', $file_id, $data );
		}

		return $file_id;
	}

	/**
	 * Get files for a submission
	 *
	 * @since 1.0.0
	 *
	 * @param int $submission_id Submission ID.
	 * @return array Array of Submission_File instances, ordered by sort_order.
	 */
	public static function get_for_submission( $submission_id ) {
		$submission_id = absint( $submission_id );

		return static::find(
			[
				'where'    => [ 'submission_id' => $submission_id ],
				'order_by' => 'sort_order',
				'order'    => 'ASC',
			]
		);
	}

	/**
	 * Get download URL for this file
	 *
	 * Generates a secure URL that routes through WordPress
	 * for permission checking before serving the file.
	 *
	 * @since 1.0.0
	 *
	 * @return string Download URL.
	 */
	public function get_download_url() {
		return add_query_arg(
			[
				'ppa_download' => 1,
				'file_id'      => $this->id,
				'_wpnonce'     => wp_create_nonce( 'ppa_download_' . $this->id ),
			],
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Verify file integrity
	 *
	 * Compares the stored hash with the actual file hash.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True if integrity verified, WP_Error on failure.
	 */
	public function verify_integrity() {
		$full_path = $this->get_full_path();

		if ( ! file_exists( $full_path ) ) {
			return new WP_Error(
				'ppa_file_not_found',
				__( 'File not found on disk.', 'pressprimer-assignment' )
			);
		}

		$current_hash = hash_file( 'sha256', $full_path );

		if ( $current_hash !== $this->file_hash ) {
			return new WP_Error(
				'ppa_integrity_failed',
				__( 'File integrity check failed. The file may have been modified.', 'pressprimer-assignment' )
			);
		}

		return true;
	}

	/**
	 * Get the full filesystem path to the file
	 *
	 * @since 1.0.0
	 *
	 * @return string Full filesystem path.
	 */
	public function get_full_path() {
		$upload_dir = wp_upload_dir();

		return trailingslashit( $upload_dir['basedir'] ) . $this->file_path;
	}

	/**
	 * Get human-readable file size
	 *
	 * @since 1.0.0
	 *
	 * @return string Formatted file size (e.g., "2.5 MB").
	 */
	public function get_formatted_size() {
		return size_format( $this->file_size );
	}

	/**
	 * Delete file record and physical file
	 *
	 * Removes both the database record and the file from disk.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete() {
		// Capture data for hooks.
		$file_id       = $this->id;
		$submission_id = $this->submission_id;

		// Attempt to delete the physical file.
		$full_path = $this->get_full_path();
		if ( file_exists( $full_path ) ) {
			wp_delete_file( $full_path );
		}

		$result = parent::delete();

		// Fire action hook.
		if ( true === $result ) {
			/**
			 * Fires after a submission file is deleted.
			 *
			 * @since 1.0.0
			 *
			 * @param int $file_id       The file record ID.
			 * @param int $submission_id  The submission ID.
			 */
			do_action( 'pressprimer_assignment_file_deleted', $file_id, $submission_id );
		}

		return $result;
	}
}
