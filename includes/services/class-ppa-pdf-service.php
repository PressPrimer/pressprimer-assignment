<?php
/**
 * PDF text extraction service
 *
 * Provides PDF text extraction for assignment submissions using
 * Smalot\PdfParser. Includes garbage text filtering to ensure
 * extraction quality.
 *
 * Two-tier extraction strategy:
 * - Quick check (first few pages) during upload for the text_extractable flag.
 * - Full extraction (all pages) via WP Cron for AI features.
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
 * PDF service class
 *
 * Extracts text from PDF files using Smalot\PdfParser with
 * quality filtering. Supports both synchronous quick checks
 * and asynchronous full extraction via WP Cron.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_PDF_Service {

	/**
	 * Minimum word count to consider a PDF as having extractable text
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MIN_WORD_COUNT = 10;

	/**
	 * Number of pages to check during quick extractability check
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const QUICK_CHECK_PAGES = 10;

	/**
	 * Extraction method identifier
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const METHOD = 'smalot-pdf';

	// =========================================================================
	// Public API.
	// =========================================================================

	/**
	 * Extract text from a PDF file
	 *
	 * Uses Smalot\PdfParser to extract text content from PDF files.
	 * Returns WP_Error if extraction fails or the library is unavailable.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Full path to PDF file.
	 * @param int    $max_pages Maximum pages to extract (0 = all pages).
	 * @return string|WP_Error Extracted text or WP_Error on failure.
	 */
	public function extract_text( $file_path, $max_pages = 0 ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new WP_Error(
				'pressprimer_assignment_file_not_found',
				__( 'PDF file not found or not readable.', 'pressprimer-assignment' )
			);
		}

		// Verify it's actually a PDF by checking magic bytes.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading first 5 bytes for magic byte check.
		$header = file_get_contents( $file_path, false, null, 0, 5 );

		if ( '%PDF-' !== $header ) {
			return new WP_Error(
				'pressprimer_assignment_not_a_pdf',
				__( 'File is not a valid PDF.', 'pressprimer-assignment' )
			);
		}

		if ( ! class_exists( '\\Smalot\\PdfParser\\Parser' ) ) {
			return new WP_Error(
				'pressprimer_assignment_pdf_parser_unavailable',
				__( 'PDF text extraction library is not available.', 'pressprimer-assignment' )
			);
		}

		$text = $this->extract_with_smalot( $file_path, $max_pages );

		if ( ! is_wp_error( $text ) && ! empty( trim( $text ) ) ) {
			return $text;
		}

		return new WP_Error(
			'pressprimer_assignment_pdf_extraction_failed',
			__( 'Unable to extract readable text from this PDF. The file may be a scanned image or use embedded fonts that cannot be read.', 'pressprimer-assignment' )
		);
	}

	/**
	 * Check if a PDF has extractable text (quick check)
	 *
	 * Performs a fast extraction of the first few pages to determine
	 * whether the PDF contains readable text. Used during file upload.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Full path to PDF file.
	 * @return array {
	 *     Extraction result.
	 *
	 *     @type bool   $extractable Whether text was successfully extracted.
	 *     @type int    $word_count  Number of words extracted.
	 *     @type string $method      Extraction method used ('smalot', 'pdftotext', 'none').
	 * }
	 */
	public function check_text_extractable( $file_path ) {
		$default = [
			'extractable' => false,
			'word_count'  => 0,
			'method'      => 'none',
		];

		$text = $this->extract_text( $file_path, self::QUICK_CHECK_PAGES );

		if ( is_wp_error( $text ) || empty( trim( $text ) ) ) {
			return $default;
		}

		// Clean whitespace and count words.
		$clean_text = trim( preg_replace( '/\s+/', ' ', $text ) );
		$word_count = str_word_count( $clean_text );

		return [
			'extractable' => $word_count >= self::MIN_WORD_COUNT,
			'word_count'  => $word_count,
			'method'      => 'smalot',
		];
	}

	/**
	 * Schedule full text extraction via WP Cron
	 *
	 * Schedules an asynchronous cron event to extract all text from
	 * a PDF file. This keeps uploads fast while ensuring full text
	 * is available for AI features.
	 *
	 * @since 1.0.0
	 *
	 * @param int $file_id Submission file record ID.
	 */
	public static function schedule_full_extraction( $file_id ) {
		$file_id = absint( $file_id );

		if ( 0 === $file_id ) {
			return;
		}

		wp_schedule_single_event( time(), 'pressprimer_assignment_extract_pdf_text', [ $file_id ] );
	}

	/**
	 * Process a scheduled full text extraction
	 *
	 * WP Cron callback that performs full text extraction on a PDF file
	 * and stores the result in the database.
	 *
	 * @since 1.0.0
	 *
	 * @param int $file_id Submission file record ID.
	 */
	public static function process_scheduled_extraction( $file_id ) {
		$file_id = absint( $file_id );

		if ( 0 === $file_id ) {
			return;
		}

		$file = PressPrimer_Assignment_Submission_File::get( $file_id );

		if ( ! $file || 'pdf' !== strtolower( $file->file_extension ) ) {
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

		// Extract all pages.
		$service = new self();
		$text    = $service->extract_text( $full_path, 0 );

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
	 * Extract PDF text using Smalot\PdfParser
	 *
	 * Primary extraction method. Handles complex PDF structures
	 * and custom font encodings better than basic methods.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Path to PDF file.
	 * @param int    $max_pages Maximum pages to extract (0 = all pages).
	 * @return string|WP_Error Extracted text or WP_Error.
	 */
	private function extract_with_smalot( $file_path, $max_pages = 0 ) {
		try {
			// Exclude image binary data from text extraction.
			$config = new \Smalot\PdfParser\Config();
			$config->setRetainImageContent( false );

			$parser     = new \Smalot\PdfParser\Parser( [], $config );
			$pdf        = $parser->parseFile( $file_path );
			$page_limit = $max_pages > 0 ? $max_pages : null;
			$text       = $pdf->getText( $page_limit );

			// Filter out garbage/binary content.
			$text = $this->filter_garbage_text( $text );

			return $text;
		} catch ( \Exception $e ) {
			return new WP_Error(
				'pressprimer_assignment_pdf_parser_error',
				$e->getMessage()
			);
		}
	}

	// =========================================================================
	// Text quality filtering.
	// =========================================================================

	/**
	 * Filter garbage text from PDF extraction
	 *
	 * PDFs with custom font encodings can produce unreadable characters.
	 * This method filters out lines that appear to be garbage by checking
	 * for actual recognizable words with vowels (real English words have vowels).
	 *
	 * Ported from PressPrimer Quiz where it proved effective at filtering
	 * out binary data, font encoding artifacts, and other extraction noise.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Raw extracted text.
	 * @return string Filtered text.
	 */
	private function filter_garbage_text( $text ) {
		$lines            = explode( "\n", $text );
		$filtered         = [];
		$total_real_words = 0;
		$total_lines      = 0;

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			$line_length = mb_strlen( $line );

			// Count words that look like real English (contain at least one vowel).
			// This filters out garbage like "bbLb", "CHsss", "qY33" etc.
			preg_match_all( '/\b[a-zA-Z]{2,}\b/', $line, $words );
			$real_word_count = 0;
			if ( ! empty( $words[0] ) ) {
				foreach ( $words[0] as $word ) {
					// Real English words contain vowels (including 'y' as vowel).
					if ( preg_match( '/[aeiouyAEIOUY]/', $word ) ) {
						++$real_word_count;
					}
				}
			}

			// Skip lines that are long but have very few real words.
			if ( $line_length > 30 && $real_word_count < 3 ) {
				continue;
			}

			// Skip lines with high density of special characters (>25%).
			$special_chars = preg_match_all( '/[^a-zA-Z0-9\s\.,!?\'\"-]/', $line );
			if ( $line_length > 15 && ( $special_chars / $line_length ) > 0.25 ) {
				continue;
			}

			// Skip lines that look like encoded data.
			if ( preg_match( '/[{}\[\]|\\\\<>~`^@#$%&*+=]{3,}/', $line ) ) {
				continue;
			}

			// Skip lines with excessive repeated characters.
			if ( preg_match( '/(.)\1{4,}/', $line ) ) {
				continue;
			}

			// Skip lines that are mostly uppercase consonants (common in garbage).
			$uppercase_consonants = preg_match_all( '/[BCDFGHJKLMNPQRSTVWXZ]/', $line );
			if ( $line_length > 20 && ( $uppercase_consonants / $line_length ) > 0.3 ) {
				continue;
			}

			$filtered[]        = $line;
			$total_real_words += $real_word_count;
			++$total_lines;
		}

		$result = implode( "\n", $filtered );

		// If we have very few real words overall, the extraction likely failed.
		if ( $total_lines > 5 && ( $total_real_words / $total_lines ) < 2 ) {
			return '';
		}

		// If total real word count is too low for the amount of text, reject it.
		if ( strlen( $result ) > 300 && $total_real_words < 30 ) {
			return '';
		}

		return $result;
	}
}
