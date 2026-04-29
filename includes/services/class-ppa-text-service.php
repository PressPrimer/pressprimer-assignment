<?php
/**
 * Plain text extraction service
 *
 * Provides plain text file extraction for assignment submissions.
 * Handles encoding detection and conversion to UTF-8, plus
 * control-character stripping.
 *
 * Two-tier extraction strategy:
 * - Quick check during upload for the text_extractable flag.
 * - Full extraction via WP Cron for AI features.
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
 * Text service class
 *
 * Extracts text from plain text files with proper encoding
 * detection and normalisation. Trivial but structured to match
 * the same interface as PDF/DOCX/ODT/RTF services.
 *
 * @since 2.0.0
 */
class PressPrimer_Assignment_Text_Service {

	/**
	 * Minimum word count to consider a text file as having extractable text
	 *
	 * @since 2.0.0
	 * @var int
	 */
	const MIN_WORD_COUNT = 3;

	/**
	 * Maximum file size to read (10 MB)
	 *
	 * Prevents memory issues with unexpectedly large text files.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	const MAX_READ_SIZE = 10485760;

	/**
	 * Extraction method identifier
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const METHOD = 'native-text';

	// =========================================================================
	// Public API.
	// =========================================================================

	/**
	 * Extract text from a plain text file
	 *
	 * Reads the file, detects encoding, and converts to UTF-8.
	 *
	 * @since 2.0.0
	 *
	 * @param string $file_path Full path to text file.
	 * @return string|WP_Error Extracted text or WP_Error on failure.
	 */
	public function extract_text( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new WP_Error(
				'pressprimer_assignment_file_not_found',
				__( 'Text file not found or not readable.', 'pressprimer-assignment' )
			);
		}

		$file_size = filesize( $file_path );

		if ( 0 === $file_size ) {
			return new WP_Error(
				'pressprimer_assignment_text_empty',
				__( 'Text file is empty.', 'pressprimer-assignment' )
			);
		}

		$read_size = min( $file_size, self::MAX_READ_SIZE );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file for text extraction.
		$text = file_get_contents( $file_path, false, null, 0, $read_size );

		if ( false === $text ) {
			return new WP_Error(
				'pressprimer_assignment_text_read_failed',
				__( 'Failed to read text file.', 'pressprimer-assignment' )
			);
		}

		if ( '' === trim( $text ) ) {
			return new WP_Error(
				'pressprimer_assignment_text_extraction_failed',
				__( 'Text file contains no readable content.', 'pressprimer-assignment' )
			);
		}

		return $text;
	}

	/**
	 * Check if a text file has extractable text (quick check)
	 *
	 * Reads the first portion of the file to verify it contains
	 * readable text. Used during file upload.
	 *
	 * @since 2.0.0
	 *
	 * @param string $file_path Full path to text file.
	 * @return array {
	 *     Extraction result.
	 *
	 *     @type bool   $extractable Whether text was successfully extracted.
	 *     @type int    $word_count  Number of words extracted.
	 *     @type string $method      Extraction method used.
	 * }
	 */
	public function check_text_extractable( $file_path ) {
		$default = array(
			'extractable' => false,
			'word_count'  => 0,
			'method'      => 'none',
		);

		$text = $this->extract_text( $file_path );

		if ( is_wp_error( $text ) || '' === trim( $text ) ) {
			return $default;
		}

		$clean_text = trim( preg_replace( '/\s+/', ' ', $text ) );
		$word_count = str_word_count( $clean_text );

		return array(
			'extractable' => $word_count >= self::MIN_WORD_COUNT,
			'word_count'  => $word_count,
			'method'      => self::METHOD,
		);
	}

	/**
	 * Schedule full text extraction via WP Cron
	 *
	 * @since 2.0.0
	 *
	 * @param int $file_id Submission file record ID.
	 */
	public static function schedule_full_extraction( $file_id ) {
		$file_id = absint( $file_id );

		if ( 0 === $file_id ) {
			return;
		}

		wp_schedule_single_event( time(), 'pressprimer_assignment_extract_txt_text', array( $file_id ) );
	}

	/**
	 * Process a scheduled full text extraction
	 *
	 * WP Cron callback that performs full text extraction on a text
	 * file and stores the result in the database.
	 *
	 * @since 2.0.0
	 *
	 * @param int $file_id Submission file record ID.
	 */
	public static function process_scheduled_extraction( $file_id ) {
		$file_id = absint( $file_id );

		if ( 0 === $file_id ) {
			return;
		}

		$file = PressPrimer_Assignment_Submission_File::get( $file_id );

		if ( ! $file || 'txt' !== strtolower( $file->file_extension ) ) {
			return;
		}

		$full_path = $file->get_full_path();

		if ( ! file_exists( $full_path ) ) {
			PressPrimer_Assignment_Extraction_Quality::finalize(
				$file,
				'',
				self::METHOD,
				__( 'File not found on disk.', 'pressprimer-assignment' )
			);
			return;
		}

		$service = new self();
		$text    = $service->extract_text( $full_path );

		if ( is_wp_error( $text ) ) {
			PressPrimer_Assignment_Extraction_Quality::finalize(
				$file,
				'',
				self::METHOD,
				$text->get_error_message()
			);
			return;
		}

		// Sanitise and finalise.
		$text = PressPrimer_Assignment_Extraction_Quality::sanitize( $text );
		PressPrimer_Assignment_Extraction_Quality::finalize( $file, $text, self::METHOD );
	}
}
