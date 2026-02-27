<?php
/**
 * File service
 *
 * Handles file upload validation, secure storage, and retrieval
 * for assignment submissions.
 *
 * @package PressPrimer_Assignment
 * @subpackage Services
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * File service class
 *
 * Provides secure file upload handling with 6-layer validation,
 * protected storage, integrity verification, and permission-based serving.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_File_Service {

	/**
	 * Upload subdirectory within wp-content/uploads
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const UPLOAD_DIR = 'ppa-submissions';

	/**
	 * Default maximum file size in bytes (10 MB)
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const DEFAULT_MAX_FILE_SIZE = 10485760;

	/**
	 * Default allowed file extensions
	 *
	 * @since 1.0.0
	 * @var array
	 */
	const DEFAULT_ALLOWED_EXTENSIONS = [
		'pdf',
		'docx',
		'txt',
		'rtf',
		'odt',
		'jpg',
		'jpeg',
		'png',
		'gif',
	];

	/**
	 * Map of allowed MIME types to their extensions
	 *
	 * @since 1.0.0
	 * @var array
	 */
	const ALLOWED_MIME_TYPES = [
		'application/pdf'                         => [ 'pdf' ],
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => [ 'docx' ],
		'text/plain'                              => [ 'txt' ],
		'text/x-c'                                => [ 'txt' ],
		'application/rtf'                         => [ 'rtf' ],
		'text/rtf'                                => [ 'rtf' ],
		'application/vnd.oasis.opendocument.text' => [ 'odt' ],
		'image/jpeg'                              => [ 'jpg', 'jpeg' ],
		'image/png'                               => [ 'png' ],
		'image/gif'                               => [ 'gif' ],
	];

	/**
	 * Dangerous file extensions that should always be rejected
	 *
	 * @since 1.0.0
	 * @var array
	 */
	const DANGEROUS_EXTENSIONS = [
		'php',
		'phtml',
		'php3',
		'php4',
		'php5',
		'php7',
		'php8',
		'phps',
		'phar',
		'js',
		'exe',
		'sh',
		'bat',
		'cmd',
		'com',
		'scr',
		'msi',
		'vbs',
		'jar',
		'cgi',
		'pl',
		'py',
		'rb',
	];

	/**
	 * Validate an uploaded file against assignment rules
	 *
	 * Performs 6-layer validation:
	 * 1. Upload error checking
	 * 2. Filename sanitization
	 * 3. Extension validation
	 * 4. Double extension prevention
	 * 5. MIME type verification (finfo)
	 * 6. File size checking
	 *
	 * @since 1.0.0
	 *
	 * @param array                             $file       $_FILES array element.
	 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
	 * @return true|WP_Error True if valid, WP_Error on failure.
	 */
	public function validate_file( $file, $assignment ) {
		// Layer 1: Check upload error code.
		$error_check = $this->check_upload_error( $file );
		if ( is_wp_error( $error_check ) ) {
			return $error_check;
		}

		// Verify this is a legitimate upload.
		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error(
				'ppa_invalid_upload',
				__( 'Invalid file upload.', 'pressprimer-assignment' )
			);
		}

		// Layer 2: Sanitize and validate filename.
		$filename = sanitize_file_name( $file['name'] );

		if ( empty( $filename ) ) {
			return new WP_Error(
				'ppa_empty_filename',
				__( 'Filename is empty or invalid.', 'pressprimer-assignment' )
			);
		}

		// Layer 3: Validate extension against allowed types.
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$allowed   = $assignment->get_allowed_file_types();

		if ( ! in_array( $extension, $allowed, true ) ) {
			return new WP_Error(
				'ppa_invalid_extension',
				sprintf(
					/* translators: %s: comma-separated list of allowed file extensions */
					__( 'File type not allowed. Accepted types: %s', 'pressprimer-assignment' ),
					implode( ', ', $allowed )
				)
			);
		}

		// Layer 4: Check for double extensions (e.g., file.php.pdf).
		$double_ext_check = $this->check_dangerous_extensions( $filename );
		if ( is_wp_error( $double_ext_check ) ) {
			return $double_ext_check;
		}

		// Layer 5: Verify MIME type using finfo.
		$mime_check = $this->verify_mime_type( $file['tmp_name'], $extension );
		if ( is_wp_error( $mime_check ) ) {
			return $mime_check;
		}

		// Layer 6: Check file size.
		$max_size = $assignment->max_file_size ? $assignment->max_file_size : self::DEFAULT_MAX_FILE_SIZE;

		if ( $file['size'] > $max_size ) {
			return new WP_Error(
				'ppa_file_too_large',
				sprintf(
					/* translators: %s: maximum file size */
					__( 'File size exceeds the maximum limit of %s.', 'pressprimer-assignment' ),
					size_format( $max_size )
				)
			);
		}

		// Check for zero-byte files.
		if ( 0 === $file['size'] ) {
			return new WP_Error(
				'ppa_empty_file',
				__( 'The uploaded file is empty.', 'pressprimer-assignment' )
			);
		}

		return true;
	}

	/**
	 * Store an uploaded file securely
	 *
	 * Moves the file to a protected directory, generates a secure filename,
	 * creates a database record, and updates submission statistics.
	 *
	 * @since 1.0.0
	 *
	 * @param array $file          $_FILES array element.
	 * @param int   $submission_id Submission ID.
	 * @return int|WP_Error File record ID on success, WP_Error on failure.
	 */
	public function store_file( $file, $submission_id ) {
		$submission_id = absint( $submission_id );

		if ( 0 === $submission_id ) {
			return new WP_Error(
				'ppa_invalid_submission',
				__( 'Invalid submission ID.', 'pressprimer-assignment' )
			);
		}

		// Get secure upload directory.
		$upload_dir = $this->get_upload_directory();
		if ( is_wp_error( $upload_dir ) ) {
			return $upload_dir;
		}

		// Prepare file info.
		$original_filename = sanitize_file_name( $file['name'] );
		$extension         = strtolower( pathinfo( $original_filename, PATHINFO_EXTENSION ) );

		// Generate secure filename: {hash}_{timestamp}.{extension}.
		$stored_filename = wp_hash( $original_filename . wp_rand() ) . '_' . time() . '.' . $extension;

		// Create date-based subdirectory (YYYY/MM).
		$date_subdir = gmdate( 'Y' ) . '/' . gmdate( 'm' );
		$target_dir  = trailingslashit( $upload_dir['path'] ) . $date_subdir;

		if ( ! wp_mkdir_p( $target_dir ) ) {
			return new WP_Error(
				'ppa_dir_creation_failed',
				__( 'Failed to create upload directory.', 'pressprimer-assignment' )
			);
		}

		// Add index.php to the date subdirectory.
		$this->create_index_file( $target_dir );

		// Also ensure the year directory has index.php.
		$year_dir = trailingslashit( $upload_dir['path'] ) . gmdate( 'Y' );
		$this->create_index_file( $year_dir );

		// Move uploaded file.
		$target_path = trailingslashit( $target_dir ) . $stored_filename;

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- move_uploaded_file may warn on permission issues.
		if ( ! @move_uploaded_file( $file['tmp_name'], $target_path ) ) {
			return new WP_Error(
				'ppa_move_failed',
				__( 'Failed to move uploaded file.', 'pressprimer-assignment' )
			);
		}

		// Set appropriate file permissions.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Setting secure permissions on uploaded file.
		chmod( $target_path, 0644 );

		// Calculate SHA-256 hash for integrity verification.
		$file_hash = hash_file( 'sha256', $target_path );

		if ( false === $file_hash ) {
			wp_delete_file( $target_path );
			return new WP_Error(
				'ppa_hash_failed',
				__( 'Failed to calculate file hash.', 'pressprimer-assignment' )
			);
		}

		// Detect MIME type from the stored file.
		$mime_type = $this->detect_mime_type( $target_path );

		if ( is_wp_error( $mime_type ) ) {
			wp_delete_file( $target_path );
			return $mime_type;
		}

		// Build relative path for database storage.
		$relative_path = self::UPLOAD_DIR . '/' . $date_subdir . '/' . $stored_filename;

		// Get current file count for sort order.
		$existing_files = PressPrimer_Assignment_Submission_File::get_for_submission( $submission_id );
		$sort_order     = count( $existing_files );

		// Create database record.
		$file_id = PressPrimer_Assignment_Submission_File::create(
			[
				'submission_id'     => $submission_id,
				'original_filename' => $original_filename,
				'stored_filename'   => $stored_filename,
				'file_path'         => $relative_path,
				'file_size'         => $file['size'],
				'mime_type'         => $mime_type,
				'file_extension'    => $extension,
				'file_hash'         => $file_hash,
				'sort_order'        => $sort_order,
			]
		);

		if ( is_wp_error( $file_id ) ) {
			wp_delete_file( $target_path );
			return $file_id;
		}

		// Update submission file statistics.
		$submission = PressPrimer_Assignment_Submission::get( $submission_id );
		if ( $submission ) {
			$submission->update_file_stats();
		}

		/**
		 * Fires after a file is successfully uploaded and stored.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $file_id       The file record ID.
		 * @param int   $submission_id The submission ID.
		 * @param array $file          The original $_FILES array element.
		 */
		do_action( 'pressprimer_assignment_file_uploaded', $file_id, $submission_id, $file );

		return $file_id;
	}

	/**
	 * Get secure upload directory
	 *
	 * Creates the ppa-submissions directory with .htaccess protection
	 * and index.php silence files.
	 *
	 * @since 1.0.0
	 *
	 * @return array|WP_Error Array with 'path' and 'url' keys, or WP_Error.
	 */
	public function get_upload_directory() {
		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			return new WP_Error(
				'ppa_upload_dir_error',
				$upload_dir['error']
			);
		}

		$ppa_dir = trailingslashit( $upload_dir['basedir'] ) . self::UPLOAD_DIR;

		// Create directory if it doesn't exist.
		if ( ! wp_mkdir_p( $ppa_dir ) ) {
			return new WP_Error(
				'ppa_dir_creation_failed',
				__( 'Failed to create upload directory.', 'pressprimer-assignment' )
			);
		}

		// Create .htaccess to deny direct access.
		$htaccess_path = trailingslashit( $ppa_dir ) . '.htaccess';
		if ( ! file_exists( $htaccess_path ) ) {
			$htaccess_content = "Order deny,allow\nDeny from all\n";

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing .htaccess for security.
			file_put_contents( $htaccess_path, $htaccess_content );
		}

		// Create index.php silence file.
		$this->create_index_file( $ppa_dir );

		return [
			'path' => $ppa_dir,
			'url'  => trailingslashit( $upload_dir['baseurl'] ) . self::UPLOAD_DIR,
		];
	}

	/**
	 * Delete a file
	 *
	 * Removes the physical file and database record, then updates
	 * submission statistics.
	 *
	 * @since 1.0.0
	 *
	 * @param int $file_id File record ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete_file( $file_id ) {
		$file_id = absint( $file_id );

		$file = PressPrimer_Assignment_Submission_File::get( $file_id );
		if ( ! $file ) {
			return new WP_Error(
				'ppa_file_not_found',
				__( 'File record not found.', 'pressprimer-assignment' )
			);
		}

		$submission_id = $file->submission_id;

		// Delete the file (handles both physical file and DB record).
		$result = $file->delete();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update submission file statistics.
		$submission = PressPrimer_Assignment_Submission::get( $submission_id );
		if ( $submission ) {
			$submission->update_file_stats();
		}

		return true;
	}

	/**
	 * Serve a file for download
	 *
	 * Checks user permissions, optionally verifies file integrity,
	 * and streams the file to the browser.
	 *
	 * @since 1.0.0
	 *
	 * @param int  $file_id         File record ID.
	 * @param bool $verify_integrity Whether to verify file hash before serving.
	 * @return void|WP_Error Outputs file or returns WP_Error.
	 */
	public function serve_file( $file_id, $verify_integrity = false ) {
		$file_id = absint( $file_id );

		$file = PressPrimer_Assignment_Submission_File::get( $file_id );
		if ( ! $file ) {
			return new WP_Error(
				'ppa_file_not_found',
				__( 'File not found.', 'pressprimer-assignment' )
			);
		}

		// Get the submission to check permissions.
		$submission = PressPrimer_Assignment_Submission::get( $file->submission_id );
		if ( ! $submission ) {
			return new WP_Error(
				'ppa_submission_not_found',
				__( 'Submission not found.', 'pressprimer-assignment' )
			);
		}

		// Check permissions: user must be the submitter, the grader, or an admin.
		$current_user_id = get_current_user_id();
		$can_access      = false;

		if ( $current_user_id === (int) $submission->user_id ) {
			$can_access = true;
		} elseif ( $submission->grader_id && $current_user_id === (int) $submission->grader_id ) {
			$can_access = true;
		} elseif ( current_user_can( 'manage_options' ) ) {
			$can_access = true;
		}

		/**
		 * Filters whether a user can access a submission file.
		 *
		 * @since 1.0.0
		 *
		 * @param bool                                    $can_access Whether the user can access the file.
		 * @param PressPrimer_Assignment_Submission_File $file       The file instance.
		 * @param PressPrimer_Assignment_Submission      $submission The submission instance.
		 * @param int                                     $current_user_id Current user ID.
		 */
		$can_access = apply_filters( 'pressprimer_assignment_can_access_file', $can_access, $file, $submission, $current_user_id );

		if ( ! $can_access ) {
			return new WP_Error(
				'ppa_access_denied',
				__( 'You do not have permission to access this file.', 'pressprimer-assignment' )
			);
		}

		// Get full path.
		$full_path = $file->get_full_path();

		if ( ! file_exists( $full_path ) ) {
			return new WP_Error(
				'ppa_file_missing',
				__( 'File not found on server.', 'pressprimer-assignment' )
			);
		}

		// Optionally verify file integrity.
		if ( $verify_integrity ) {
			$integrity = $file->verify_integrity();
			if ( is_wp_error( $integrity ) ) {
				return $integrity;
			}
		}

		// Set headers and output file.
		$this->send_file_headers( $file );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Serving file for download.
		readfile( $full_path );
		exit;
	}

	/**
	 * Check upload error code
	 *
	 * Translates PHP upload error codes to user-friendly messages.
	 *
	 * @since 1.0.0
	 *
	 * @param array $file $_FILES array element.
	 * @return true|WP_Error True if no error, WP_Error otherwise.
	 */
	private function check_upload_error( $file ) {
		if ( ! isset( $file['error'] ) || is_array( $file['error'] ) ) {
			return new WP_Error(
				'ppa_upload_error',
				__( 'Invalid file upload.', 'pressprimer-assignment' )
			);
		}

		switch ( $file['error'] ) {
			case UPLOAD_ERR_OK:
				return true;

			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return new WP_Error(
					'ppa_file_too_large',
					__( 'The uploaded file exceeds the server upload limit.', 'pressprimer-assignment' )
				);

			case UPLOAD_ERR_PARTIAL:
				return new WP_Error(
					'ppa_upload_partial',
					__( 'The file was only partially uploaded. Please try again.', 'pressprimer-assignment' )
				);

			case UPLOAD_ERR_NO_FILE:
				return new WP_Error(
					'ppa_no_file',
					__( 'No file was uploaded.', 'pressprimer-assignment' )
				);

			case UPLOAD_ERR_NO_TMP_DIR:
			case UPLOAD_ERR_CANT_WRITE:
				return new WP_Error(
					'ppa_server_error',
					__( 'Server configuration error. Please contact the administrator.', 'pressprimer-assignment' )
				);

			case UPLOAD_ERR_EXTENSION:
				return new WP_Error(
					'ppa_blocked_extension',
					__( 'File upload was blocked by a server extension.', 'pressprimer-assignment' )
				);

			default:
				return new WP_Error(
					'ppa_unknown_error',
					__( 'An unknown upload error occurred.', 'pressprimer-assignment' )
				);
		}
	}

	/**
	 * Check for dangerous file extensions
	 *
	 * Prevents double extension attacks (e.g., file.php.pdf)
	 * and blocks known dangerous extensions anywhere in the filename.
	 *
	 * @since 1.0.0
	 *
	 * @param string $filename Sanitized filename.
	 * @return true|WP_Error True if safe, WP_Error if dangerous.
	 */
	private function check_dangerous_extensions( $filename ) {
		// Build regex pattern from dangerous extensions.
		$pattern = '/\.(' . implode( '|', self::DANGEROUS_EXTENSIONS ) . ')[.\s]*$/i';

		// Check for dangerous extensions anywhere before the final extension.
		$parts = explode( '.', $filename );

		if ( count( $parts ) > 2 ) {
			// Check all parts except the last (which is the actual extension).
			array_pop( $parts );
			array_shift( $parts ); // Remove name before first dot.

			foreach ( $parts as $part ) {
				if ( in_array( strtolower( $part ), self::DANGEROUS_EXTENSIONS, true ) ) {
					return new WP_Error(
						'ppa_dangerous_extension',
						__( 'File contains a potentially dangerous extension.', 'pressprimer-assignment' )
					);
				}
			}
		}

		// Also check full filename with the regex pattern.
		if ( preg_match( $pattern, $filename ) ) {
			return new WP_Error(
				'ppa_dangerous_extension',
				__( 'File contains a potentially dangerous extension.', 'pressprimer-assignment' )
			);
		}

		return true;
	}

	/**
	 * Verify MIME type using finfo
	 *
	 * Checks that the file's actual content matches the expected
	 * MIME type for the given extension.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tmp_path  Path to temporary upload file.
	 * @param string $extension File extension.
	 * @return true|WP_Error True if MIME matches, WP_Error otherwise.
	 */
	private function verify_mime_type( $tmp_path, $extension ) {
		if ( ! function_exists( 'finfo_open' ) ) {
			// If finfo is not available, skip this check.
			// Other validation layers still protect against malicious uploads.
			return true;
		}

		$finfo         = finfo_open( FILEINFO_MIME_TYPE );
		$detected_mime = finfo_file( $finfo, $tmp_path );
		finfo_close( $finfo );

		if ( false === $detected_mime ) {
			return new WP_Error(
				'ppa_mime_detection_failed',
				__( 'Unable to verify file content type.', 'pressprimer-assignment' )
			);
		}

		// Check if detected MIME type is in our allowed list.
		if ( ! isset( self::ALLOWED_MIME_TYPES[ $detected_mime ] ) ) {
			return new WP_Error(
				'ppa_invalid_mime',
				__( 'File content does not match an allowed type.', 'pressprimer-assignment' )
			);
		}

		// Verify the detected MIME type is valid for this extension.
		$valid_extensions = self::ALLOWED_MIME_TYPES[ $detected_mime ];

		if ( ! in_array( $extension, $valid_extensions, true ) ) {
			return new WP_Error(
				'ppa_mime_mismatch',
				__( 'File content does not match the file extension.', 'pressprimer-assignment' )
			);
		}

		return true;
	}

	/**
	 * Detect MIME type of a file
	 *
	 * Uses multiple methods to determine the MIME type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Path to file.
	 * @return string|WP_Error MIME type string or WP_Error.
	 */
	private function detect_mime_type( $file_path ) {
		// Try WordPress function first.
		$wp_check = wp_check_filetype_and_ext( $file_path, basename( $file_path ) );

		if ( ! empty( $wp_check['type'] ) ) {
			return $wp_check['type'];
		}

		// Fallback to finfo.
		if ( function_exists( 'finfo_open' ) ) {
			$finfo     = finfo_open( FILEINFO_MIME_TYPE );
			$mime_type = finfo_file( $finfo, $file_path );
			finfo_close( $finfo );

			if ( false !== $mime_type ) {
				return $mime_type;
			}
		}

		// Fallback to mime_content_type.
		if ( function_exists( 'mime_content_type' ) ) {
			$mime_type = mime_content_type( $file_path );

			if ( false !== $mime_type ) {
				return $mime_type;
			}
		}

		return new WP_Error(
			'ppa_mime_detection_failed',
			__( 'Unable to determine file type.', 'pressprimer-assignment' )
		);
	}

	/**
	 * Send file download headers
	 *
	 * Sets appropriate HTTP headers for file download.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Submission_File $file File instance.
	 */
	private function send_file_headers( $file ) {
		// Prevent caching of downloaded files.
		nocache_headers();

		header( 'Content-Type: ' . $file->mime_type );
		header( 'Content-Disposition: attachment; filename="' . $file->original_filename . '"' );
		header( 'Content-Length: ' . $file->file_size );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'X-Content-Type-Options: nosniff' );
	}

	/**
	 * Create an index.php silence file in a directory
	 *
	 * Prevents directory listing in case .htaccess is bypassed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $directory Directory path.
	 */
	private function create_index_file( $directory ) {
		$index_path = trailingslashit( $directory ) . 'index.php';

		if ( ! file_exists( $index_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Creating index.php for security.
			file_put_contents( $index_path, "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * Clean up orphaned files
	 *
	 * Removes physical files that no longer have database records.
	 * Intended for maintenance/cron use.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array with 'removed' count and 'errors' array.
	 */
	public function cleanup_orphaned_files() {
		$upload_dir = $this->get_upload_directory();

		if ( is_wp_error( $upload_dir ) ) {
			return [
				'removed' => 0,
				'errors'  => [ $upload_dir->get_error_message() ],
			];
		}

		$removed = 0;
		$errors  = [];

		// Scan for files in the upload directory.
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(
				$upload_dir['path'],
				RecursiveDirectoryIterator::SKIP_DOTS
			)
		);

		global $wpdb;
		$files_table = $wpdb->prefix . 'ppa_submission_files';

		foreach ( $iterator as $file_info ) {
			if ( $file_info->isDir() ) {
				continue;
			}

			$filename = $file_info->getFilename();

			// Skip protection files.
			if ( in_array( $filename, [ '.htaccess', 'index.php' ], true ) ) {
				continue;
			}

			// Check if this file exists in the database.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$files_table} WHERE stored_filename = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$filename
				)
			);

			if ( ! $exists ) {
				if ( wp_delete_file( $file_info->getPathname() ) || ! file_exists( $file_info->getPathname() ) ) {
					++$removed;
				} else {
					$errors[] = $file_info->getPathname();
				}
			}
		}

		return [
			'removed' => $removed,
			'errors'  => $errors,
		];
	}
}
