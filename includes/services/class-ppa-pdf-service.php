<?php
/**
 * PDF text extraction service
 *
 * Provides robust PDF text extraction for assignment submissions.
 * Uses Smalot\PdfParser as the primary method with pdftotext as fallback.
 * Includes garbage text filtering to ensure extraction quality.
 *
 * Two-tier extraction strategy:
 * - Quick check (first 3 pages) during upload for the text_extractable flag.
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
 * Extracts text from PDF files using multiple methods with
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
	 * Temporary files to clean up on shutdown
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $temp_files = [];

	/**
	 * Constructor
	 *
	 * Registers cleanup handler for temporary files.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		register_shutdown_function( [ $this, 'cleanup_temp_files' ] );
	}

	// =========================================================================
	// Public API.
	// =========================================================================

	/**
	 * Extract text from a PDF file
	 *
	 * Attempts extraction using Smalot\PdfParser first, then
	 * pdftotext as fallback. Returns WP_Error if neither works.
	 * Does NOT fall back to basic PHP regex extraction — that
	 * method produces too much garbage.
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
				'ppa_file_not_found',
				__( 'PDF file not found or not readable.', 'pressprimer-assignment' )
			);
		}

		// Verify it's actually a PDF by checking magic bytes.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading first 5 bytes for magic byte check.
		$header = file_get_contents( $file_path, false, null, 0, 5 );

		if ( '%PDF-' !== $header ) {
			return new WP_Error(
				'ppa_not_a_pdf',
				__( 'File is not a valid PDF.', 'pressprimer-assignment' )
			);
		}

		// Method 1: Try Smalot\PdfParser if available.
		if ( class_exists( '\\Smalot\\PdfParser\\Parser' ) ) {
			$text = $this->extract_with_smalot( $file_path, $max_pages );
			if ( ! is_wp_error( $text ) && ! empty( trim( $text ) ) ) {
				return $text;
			}
		}

		// Method 2: Try pdftotext command-line tool.
		$text = $this->extract_with_pdftotext( $file_path, $max_pages );
		if ( ! is_wp_error( $text ) && ! empty( trim( $text ) ) ) {
			$text = $this->filter_garbage_text( $text );
			if ( ! empty( trim( $text ) ) ) {
				return $text;
			}
		}

		// Do NOT fall back to basic PHP extraction — it produces too much garbage.
		return new WP_Error(
			'ppa_pdf_extraction_failed',
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

		// Determine which method succeeded.
		$method = 'pdftotext';
		if ( class_exists( '\\Smalot\\PdfParser\\Parser' ) ) {
			$method = 'smalot';
		}

		return [
			'extractable' => $word_count >= self::MIN_WORD_COUNT,
			'word_count'  => $word_count,
			'method'      => $method,
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

		wp_schedule_single_event( time(), 'ppa_extract_pdf_text', [ $file_id ] );
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
			return;
		}

		// Extract all pages.
		$service = new self();
		$text    = $service->extract_text( $full_path, 0 );

		if ( is_wp_error( $text ) ) {
			// Extraction failed — ensure flag reflects this.
			$file->text_extractable = 0;
			$file->save();
			return;
		}

		// Store the extracted text.
		$file->extracted_text   = $text;
		$file->text_extractable = 1;
		$file->save();
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
				'ppa_pdf_parser_error',
				$e->getMessage()
			);
		}
	}

	/**
	 * Extract PDF text using pdftotext command-line tool
	 *
	 * Fallback extraction method using poppler-utils pdftotext.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Path to PDF file.
	 * @param int    $max_pages Maximum pages to extract (0 = all pages).
	 * @return string|WP_Error Extracted text or WP_Error.
	 */
	private function extract_with_pdftotext( $file_path, $max_pages = 0 ) {
		$pdftotext_path = $this->find_executable( 'pdftotext' );

		if ( ! $pdftotext_path ) {
			return new WP_Error(
				'ppa_pdftotext_not_found',
				__( 'pdftotext command not available.', 'pressprimer-assignment' )
			);
		}

		// Create temp output file.
		$temp_output        = wp_tempnam( 'ppa_pdf_' );
		$this->temp_files[] = $temp_output;

		// Build command with proper escaping.
		if ( $max_pages > 0 ) {
			$command = sprintf(
				'%s -layout -l %d %s %s 2>&1',
				escapeshellcmd( $pdftotext_path ),
				(int) $max_pages,
				escapeshellarg( $file_path ),
				escapeshellarg( $temp_output )
			);
		} else {
			$command = sprintf(
				'%s -layout %s %s 2>&1',
				escapeshellcmd( $pdftotext_path ),
				escapeshellarg( $file_path ),
				escapeshellarg( $temp_output )
			);
		}

		// Execute command.
		$output     = [];
		$return_var = 0;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Extracting text from PDF.
		exec( $command, $output, $return_var );

		if ( 0 !== $return_var ) {
			return new WP_Error(
				'ppa_pdftotext_error',
				__( 'pdftotext command failed.', 'pressprimer-assignment' )
			);
		}

		// Read the output file.
		if ( ! file_exists( $temp_output ) ) {
			return new WP_Error(
				'ppa_pdftotext_no_output',
				__( 'pdftotext did not produce output.', 'pressprimer-assignment' )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading temp file with extracted PDF text.
		$text = file_get_contents( $temp_output );

		// Clean up temp file immediately.
		wp_delete_file( $temp_output );

		if ( false === $text ) {
			return new WP_Error(
				'ppa_pdftotext_read_error',
				__( 'Could not read pdftotext output.', 'pressprimer-assignment' )
			);
		}

		return $text;
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

	// =========================================================================
	// Utility methods.
	// =========================================================================

	/**
	 * Find an executable in common system paths
	 *
	 * Searches standard locations for a command-line tool, with
	 * fallback to the `which` command.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Executable name (e.g., 'pdftotext').
	 * @return string|false Full path to executable or false if not found.
	 */
	private function find_executable( $name ) {
		// Common paths to check.
		$paths = [
			'/usr/bin/' . $name,
			'/usr/local/bin/' . $name,
			'/opt/homebrew/bin/' . $name,
			'/opt/local/bin/' . $name,
		];

		foreach ( $paths as $path ) {
			if ( file_exists( $path ) && is_executable( $path ) ) {
				return $path;
			}
		}

		// Try 'which' command as fallback.
		if ( function_exists( 'exec' ) && ! in_array( 'exec', array_map( 'trim', explode( ',', ini_get( 'disable_functions' ) ) ), true ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Finding pdftotext executable.
			$which_result = @exec( 'which ' . escapeshellarg( $name ) . ' 2>/dev/null' );

			if ( ! empty( $which_result ) && file_exists( $which_result ) ) {
				return $which_result;
			}
		}

		return false;
	}

	/**
	 * Clean up temporary files
	 *
	 * Removes any temporary files created during extraction.
	 * Registered as a shutdown function in the constructor.
	 *
	 * @since 1.0.0
	 */
	public function cleanup_temp_files() {
		foreach ( $this->temp_files as $file ) {
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}
		$this->temp_files = [];
	}
}
