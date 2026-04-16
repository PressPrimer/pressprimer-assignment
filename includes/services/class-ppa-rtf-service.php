<?php
/**
 * RTF text extraction service
 *
 * Provides RTF text extraction for assignment submissions using
 * the henck/rtf-to-html library. Converts RTF to HTML, then strips
 * tags to produce plain text.
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
 * RTF service class
 *
 * Extracts text from RTF files using henck/rtf-to-html for
 * RTF → HTML conversion, then wp_strip_all_tags() for HTML → text.
 *
 * @since 2.0.0
 */
class PressPrimer_Assignment_RTF_Service {

	/**
	 * Minimum word count to consider an RTF as having extractable text
	 *
	 * @since 2.0.0
	 * @var int
	 */
	const MIN_WORD_COUNT = 5;

	/**
	 * Extraction method identifier
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const METHOD = 'henck-rtf';

	// =========================================================================
	// Public API.
	// =========================================================================

	/**
	 * Extract text from an RTF file
	 *
	 * Uses henck/rtf-to-html to convert RTF to HTML, then strips
	 * tags to produce plain text.
	 *
	 * @since 2.0.0
	 *
	 * @param string $file_path Full path to RTF file.
	 * @return string|WP_Error Extracted text or WP_Error on failure.
	 */
	public function extract_text( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new WP_Error(
				'pressprimer_assignment_file_not_found',
				__( 'RTF file not found or not readable.', 'pressprimer-assignment' )
			);
		}

		if ( ! class_exists( '\\RtfHtmlPhp\\Document' ) ) {
			return new WP_Error(
				'pressprimer_assignment_rtf_parser_unavailable',
				__( 'RTF text extraction library is not available.', 'pressprimer-assignment' )
			);
		}

		// Verify it looks like an RTF file by checking magic bytes.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading first 5 bytes for magic byte check.
		$header = file_get_contents( $file_path, false, null, 0, 5 );

		if ( '{\rtf' !== substr( $header, 0, 4 ) ) {
			return new WP_Error(
				'pressprimer_assignment_not_an_rtf',
				__( 'File is not a valid RTF document.', 'pressprimer-assignment' )
			);
		}

		return $this->extract_with_henck( $file_path );
	}

	/**
	 * Check if an RTF has extractable text (quick check)
	 *
	 * Performs extraction to determine whether the RTF contains
	 * readable text. Used during file upload.
	 *
	 * @since 2.0.0
	 *
	 * @param string $file_path Full path to RTF file.
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

		wp_schedule_single_event( time(), 'pressprimer_assignment_extract_rtf_text', array( $file_id ) );
	}

	/**
	 * Process a scheduled full text extraction
	 *
	 * WP Cron callback that performs full text extraction on an RTF
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

		if ( ! $file || 'rtf' !== strtolower( $file->file_extension ) ) {
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

	// =========================================================================
	// Private extraction methods.
	// =========================================================================

	/**
	 * Extract text from RTF using henck/rtf-to-html
	 *
	 * Parses the RTF file into an HTML string, then strips all
	 * HTML tags to produce plain text.
	 *
	 * @since 2.0.0
	 *
	 * @param string $file_path Path to RTF file.
	 * @return string|WP_Error Extracted text or WP_Error.
	 */
	private function extract_with_henck( $file_path ) {
		try {
			// Read the RTF content.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file for parsing.
			$rtf_content = file_get_contents( $file_path );

			if ( false === $rtf_content || '' === $rtf_content ) {
				return new WP_Error(
					'pressprimer_assignment_rtf_read_failed',
					__( 'Failed to read RTF file content.', 'pressprimer-assignment' )
				);
			}

			// Parse RTF to Document object.
			$document = new \RtfHtmlPhp\Document( $rtf_content );

			// Convert to HTML.
			$formatter = new \RtfHtmlPhp\Html\HtmlFormatter();
			$html      = $formatter->format( $document );

			if ( empty( $html ) ) {
				return new WP_Error(
					'pressprimer_assignment_rtf_extraction_failed',
					__( 'Unable to extract readable text from this RTF file.', 'pressprimer-assignment' )
				);
			}

			// Strip HTML tags to get plain text.
			$text = wp_strip_all_tags( $html );

			// Decode HTML entities.
			$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

			if ( '' === trim( $text ) ) {
				return new WP_Error(
					'pressprimer_assignment_rtf_extraction_failed',
					__( 'Unable to extract readable text from this RTF file.', 'pressprimer-assignment' )
				);
			}

			return $text;
		} catch ( \Exception $e ) {
			return new WP_Error(
				'pressprimer_assignment_rtf_parser_error',
				$e->getMessage()
			);
		}
	}
}
