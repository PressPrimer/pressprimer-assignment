<?php
/**
 * Extraction dispatcher
 *
 * Routes file uploads to the appropriate extraction service based on
 * file extension. Provides a single entry point for the submission
 * handler and re-extract flows.
 *
 * @package PressPrimer_Assignment
 * @subpackage Services
 * @since 2.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extraction dispatcher class
 *
 * Static dispatcher that maps file extensions to extraction services.
 * All extraction flows route through this class so that callers do
 * not need to know which service handles which format.
 *
 * @since 2.0.0
 */
class PressPrimer_Assignment_Extraction_Dispatcher {

	/**
	 * File types that support text extraction
	 *
	 * Maps file extension to an array containing the service class
	 * name. Extensions not in this map are treated as non-extractable.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	private static $extractable_types = array(
		'pdf'  => 'PressPrimer_Assignment_PDF_Service',
		'docx' => 'PressPrimer_Assignment_DOCX_Service',
		'pptx' => 'PressPrimer_Assignment_PPTX_Text_Service',
		'odt'  => 'PressPrimer_Assignment_ODT_Service',
		'rtf'  => 'PressPrimer_Assignment_RTF_Service',
		'txt'  => 'PressPrimer_Assignment_Text_Service',
	);

	/**
	 * Handle a newly uploaded file
	 *
	 * Runs the quick extractability check synchronously, sets the
	 * text_extractable flag, and schedules the full async extraction
	 * via WP Cron.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $file_id   Submission file record ID.
	 * @param string $extension File extension (lowercase, no dot).
	 * @return array {
	 *     Quick-check result for the upload response.
	 *
	 *     @type bool        $extractable  Whether text was found.
	 *     @type int         $word_count   Words found in quick check.
	 *     @type string      $method       Extraction method identifier.
	 *     @type string|null $text_preview Truncated preview text (max 1000 chars), or null.
	 * }
	 */
	public static function handle_upload( $file_id, $extension ) {
		$file_id   = absint( $file_id );
		$extension = strtolower( $extension );

		$result = array(
			'extractable'  => false,
			'word_count'   => 0,
			'method'       => 'none',
			'text_preview' => null,
		);

		if ( 0 === $file_id ) {
			return $result;
		}

		/**
		 * Filter the list of extractable file types
		 *
		 * Allows addons to add support for new file types by registering
		 * their own extraction service class.
		 *
		 * @since 2.0.0
		 *
		 * @param array $types Map of extension => service class name.
		 */
		$types = apply_filters( 'pressprimer_assignment_extractable_file_types', self::$extractable_types );

		// Non-extractable file type (images, etc.).
		if ( ! isset( $types[ $extension ] ) ) {
			$file = PressPrimer_Assignment_Submission_File::get( $file_id );
			if ( $file ) {
				$file->text_extractable  = 0;
				$file->extraction_method = 'none';
				$file->extracted_at      = current_time( 'mysql', true );

				// Determine quality based on file type.
				$image_types = array( 'jpg', 'jpeg', 'png', 'gif' );
				if ( in_array( $extension, $image_types, true ) ) {
					$file->extraction_quality = PressPrimer_Assignment_Extraction_Quality::QUALITY_FAILED;
					$file->extraction_error   = __( 'Image files do not contain extractable text.', 'pressprimer-assignment' );
				} else {
					$file->extraction_quality = PressPrimer_Assignment_Extraction_Quality::QUALITY_FAILED;
					$file->extraction_error   = __( 'This file type does not support text extraction.', 'pressprimer-assignment' );
				}

				$file->extracted_text_length = 0;
				$file->extracted_word_count  = 0;
				$file->save();
			}

			return $result;
		}

		$service_class = $types[ $extension ];

		if ( ! class_exists( $service_class ) ) {
			return $result;
		}

		$file = PressPrimer_Assignment_Submission_File::get( $file_id );

		if ( ! $file ) {
			return $result;
		}

		$full_path = $file->get_full_path();

		if ( ! file_exists( $full_path ) ) {
			return $result;
		}

		// Extraction is best-effort and must NEVER block an upload. A
		// parser can crash with a plain \Error on hostile files or on
		// hardened hosts (e.g. tmpfile() in disable_functions turns a
		// pdfparser decode fallback into an undefined-function Error),
		// so everything touching a service is Throwable-guarded — the
		// upload succeeds and the file is marked extraction-failed.
		try {
			// Run quick extractability check.
			$service = new $service_class();
			$check   = $service->check_text_extractable( $full_path );

			$file->text_extractable = $check['extractable'] ? 1 : 0;
			$file->save();

			$result['extractable'] = $check['extractable'];
			$result['word_count']  = $check['word_count'];
			$result['method']      = $check['method'];

			// Generate text preview for the frontend response.
			if ( $check['extractable'] ) {
				$preview_text = null;

				if ( 'pdf' === $extension ) {
					// PDF service supports page-limited extraction for preview.
					$preview_text = $service->extract_text(
						$full_path,
						PressPrimer_Assignment_PDF_Service::QUICK_CHECK_PAGES
					);
				} else {
					// Other services extract everything — just truncate.
					$preview_text = $service->extract_text( $full_path );
				}

				if ( ! is_wp_error( $preview_text ) && '' !== trim( $preview_text ) ) {
					$preview_text = trim( $preview_text );
					if ( mb_strlen( $preview_text ) > 1000 ) {
						$preview_text = mb_substr( $preview_text, 0, 1000 );
					}
					$result['text_preview'] = $preview_text;
				}
			}

			// Schedule full async extraction.
			$service_class::schedule_full_extraction( $file_id );
		} catch ( \Throwable $e ) {
			// No async retry: the full pipeline shares the code that just
			// crashed, so scheduling it would only fatal a cron tick too.
			self::mark_extraction_failed( $file, $e );

			$result['extractable']  = false;
			$result['word_count']   = 0;
			$result['method']       = 'none';
			$result['text_preview'] = null;
		}

		return $result;
	}

	/**
	 * Record a crashed extraction attempt on the file record
	 *
	 * The file itself is fine — only the text-extraction pipeline died —
	 * so the record carries a failed quality status and a human-readable
	 * note for the grading screen, and the crash reason goes to the
	 * error log for the site owner.
	 *
	 * @since 2.2.0
	 *
	 * @param PressPrimer_Assignment_Submission_File $file Submission file record.
	 * @param \Throwable                             $e    The crash.
	 */
	private static function mark_extraction_failed( $file, $e ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Deliberate diagnostics: extraction crashes are swallowed by design and this is their only trace.
		error_log(
			sprintf(
				'PressPrimer Assignment: text extraction crashed for file #%d (%s: %s in %s:%d). The upload itself was not affected.',
				(int) $file->id,
				get_class( $e ),
				$e->getMessage(),
				$e->getFile(),
				$e->getLine()
			)
		);

		$file->text_extractable      = 0;
		$file->extraction_method     = 'none';
		$file->extraction_quality    = PressPrimer_Assignment_Extraction_Quality::QUALITY_FAILED;
		$file->extraction_error      = __( 'Text extraction failed unexpectedly on this server. The uploaded file itself is unaffected.', 'pressprimer-assignment' );
		$file->extracted_at          = current_time( 'mysql', true );
		$file->extracted_text_length = 0;
		$file->extracted_word_count  = 0;
		$file->save();
	}

	/**
	 * Run a service's full extraction pipeline with a crash guard
	 *
	 * The cron and re-extract entry point: a parser crash marks the
	 * file failed instead of fataling the request.
	 *
	 * @since 2.2.0
	 *
	 * @param string $service_class Extraction service class name.
	 * @param int    $file_id       Submission file record ID.
	 * @return bool True when extraction completed without crashing.
	 */
	public static function run_guarded( $service_class, $file_id ) {
		$file_id = absint( $file_id );

		if ( 0 === $file_id || ! class_exists( $service_class ) ) {
			return false;
		}

		try {
			$service_class::process_scheduled_extraction( $file_id );
			return true;
		} catch ( \Throwable $e ) {
			$file = PressPrimer_Assignment_Submission_File::get( $file_id );
			if ( $file ) {
				self::mark_extraction_failed( $file, $e );
			}
			return false;
		}
	}

	/**
	 * Run synchronous extraction for re-extract flow
	 *
	 * Called by the re-extract REST endpoint to perform extraction
	 * immediately (not via cron). Updates the file record with
	 * the new extraction results.
	 *
	 * @since 2.0.0
	 *
	 * @param int $file_id Submission file record ID.
	 * @return array|WP_Error {
	 *     Extraction result on success, WP_Error on failure.
	 *
	 *     @type string $method  Extraction method used.
	 *     @type int    $quality Quality score (0-3).
	 *     @type string $error   Error message if extraction failed.
	 * }
	 */
	public static function re_extract( $file_id ) {
		$file_id = absint( $file_id );

		if ( 0 === $file_id ) {
			return new WP_Error(
				'pressprimer_assignment_invalid_file',
				__( 'Invalid file ID.', 'pressprimer-assignment' )
			);
		}

		$file = PressPrimer_Assignment_Submission_File::get( $file_id );

		if ( ! $file ) {
			return new WP_Error(
				'pressprimer_assignment_file_not_found',
				__( 'File record not found.', 'pressprimer-assignment' )
			);
		}

		$extension = strtolower( $file->file_extension );

		/**
		 * Filter the list of extractable file types
		 *
		 * @since 2.0.0
		 *
		 * @param array $types Map of extension => service class name.
		 */
		$types = apply_filters( 'pressprimer_assignment_extractable_file_types', self::$extractable_types );

		if ( ! isset( $types[ $extension ] ) ) {
			return new WP_Error(
				'pressprimer_assignment_unsupported_type',
				__( 'This file type does not support text extraction.', 'pressprimer-assignment' )
			);
		}

		// Run the full flow (extract, sanitise, score, store) through the
		// crash guard — a parser failure becomes a stored failed status
		// in the returned payload, never a fatal on the REST request.
		$service_class = $types[ $extension ];
		self::run_guarded( $service_class, $file_id );

		// Reload the file to get the updated values.
		$file = PressPrimer_Assignment_Submission_File::get( $file_id );

		return array(
			'method'  => $file->extraction_method,
			'quality' => (int) $file->extraction_quality,
			'error'   => $file->extraction_error,
		);
	}

	/**
	 * Get the list of extractable file types
	 *
	 * Returns the filtered map of extension => service class.
	 *
	 * @since 2.0.0
	 *
	 * @return array Map of extension => service class name.
	 */
	public static function get_extractable_types() {
		/**
		 * Filter the list of extractable file types
		 *
		 * @since 2.0.0
		 *
		 * @param array $types Map of extension => service class name.
		 */
		return apply_filters( 'pressprimer_assignment_extractable_file_types', self::$extractable_types );
	}

	/**
	 * Check if a file extension supports text extraction
	 *
	 * @since 2.0.0
	 *
	 * @param string $extension File extension (lowercase, no dot).
	 * @return bool True if extraction is supported.
	 */
	public static function is_extractable( $extension ) {
		$types = self::get_extractable_types();
		return isset( $types[ strtolower( $extension ) ] );
	}
}
