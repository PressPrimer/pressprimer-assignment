<?php
/**
 * PDF text extraction service
 *
 * Provides PDF text extraction checking for upload validation.
 * Used during file upload to determine if a PDF contains extractable
 * text or is a scanned image (which may affect future AI features).
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
 * Checks whether PDF files contain extractable text using
 * available system tools (pdftotext) or basic PHP fallback.
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
	 * Maximum bytes to read for PHP fallback extraction
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_READ_BYTES = 5242880;

	/**
	 * Check if a PDF has extractable text
	 *
	 * Attempts text extraction using available methods and returns
	 * the result. A PDF is considered "extractable" if at least
	 * MIN_WORD_COUNT words can be extracted.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Full path to PDF file.
	 * @return array {
	 *     Extraction result.
	 *
	 *     @type bool   $extractable Whether text was successfully extracted.
	 *     @type int    $word_count  Number of words extracted.
	 *     @type string $method      Extraction method used ('pdftotext', 'php', 'none').
	 * }
	 */
	public static function check_text_extractable( $file_path ) {
		$default = [
			'extractable' => false,
			'word_count'  => 0,
			'method'      => 'none',
		];

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return $default;
		}

		// Verify it's actually a PDF by checking magic bytes.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading first 5 bytes for magic byte check.
		$header = file_get_contents( $file_path, false, null, 0, 5 );

		if ( '%PDF-' !== $header ) {
			return $default;
		}

		// Try pdftotext first (most reliable).
		$result = self::extract_via_pdftotext( $file_path );

		if ( false !== $result ) {
			return self::evaluate_extraction( $result, 'pdftotext' );
		}

		// Fallback to basic PHP extraction.
		$result = self::extract_via_php( $file_path );

		if ( false !== $result ) {
			return self::evaluate_extraction( $result, 'php' );
		}

		return $default;
	}

	/**
	 * Evaluate extracted text and return structured result
	 *
	 * @since 1.0.0
	 *
	 * @param string $text   Extracted text.
	 * @param string $method Extraction method used.
	 * @return array Structured extraction result.
	 */
	private static function evaluate_extraction( $text, $method ) {
		// Clean whitespace.
		$clean_text = trim( preg_replace( '/\s+/', ' ', $text ) );
		$word_count = str_word_count( $clean_text );

		return [
			'extractable' => $word_count >= self::MIN_WORD_COUNT,
			'word_count'  => $word_count,
			'method'      => $method,
		];
	}

	/**
	 * Extract text using pdftotext command-line tool
	 *
	 * Uses the poppler-utils pdftotext binary if available on the server.
	 * This is the most reliable extraction method.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Full path to PDF file.
	 * @return string|false Extracted text or false if pdftotext unavailable.
	 */
	private static function extract_via_pdftotext( $file_path ) {
		// Check if exec is available.
		if ( ! function_exists( 'exec' ) ) {
			return false;
		}

		// Check if disabled by PHP configuration.
		$disabled = explode( ',', ini_get( 'disable_functions' ) );
		$disabled = array_map( 'trim', $disabled );

		if ( in_array( 'exec', $disabled, true ) ) {
			return false;
		}

		// Check if pdftotext is installed.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Checking for pdftotext availability.
		exec( 'which pdftotext 2>/dev/null', $which_output, $return_var );

		if ( 0 !== $return_var ) {
			return false;
		}

		// Create temp file for output.
		$temp_file = wp_tempnam( 'ppa_pdf_' );

		if ( ! $temp_file ) {
			return false;
		}

		// Extract text (first 5 pages max to limit processing time).
		$command = sprintf(
			'pdftotext -layout -l 5 %s %s 2>/dev/null',
			escapeshellarg( $file_path ),
			escapeshellarg( $temp_file )
		);

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Extracting text from PDF for extractability check.
		exec( $command, $exec_output, $return_var );

		if ( 0 !== $return_var || ! file_exists( $temp_file ) ) {
			wp_delete_file( $temp_file );
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading temp file with extracted PDF text.
		$text = file_get_contents( $temp_file );
		wp_delete_file( $temp_file );

		if ( false === $text ) {
			return false;
		}

		return $text;
	}

	/**
	 * Basic PHP-based PDF text extraction (fallback)
	 *
	 * Attempts to extract text from PDF stream objects using
	 * simple pattern matching. Less reliable than pdftotext
	 * but works without external dependencies.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Full path to PDF file.
	 * @return string|false Extracted text or false on failure.
	 */
	private static function extract_via_php( $file_path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading PDF binary content for text extraction.
		$content = file_get_contents( $file_path, false, null, 0, self::MAX_READ_BYTES );

		if ( false === $content ) {
			return false;
		}

		$text = '';

		// Method 1: Extract text from Tj/TJ operators (most common).
		if ( preg_match_all( '/\(([^)]+)\)\s*Tj/s', $content, $matches ) ) {
			$text .= implode( ' ', $matches[1] );
		}

		// Method 2: Extract from TJ arrays.
		if ( preg_match_all( '/\[(.*?)\]\s*TJ/s', $content, $matches ) ) {
			foreach ( $matches[1] as $match ) {
				if ( preg_match_all( '/\(([^)]*)\)/', $match, $inner ) ) {
					$text .= ' ' . implode( '', $inner[1] );
				}
			}
		}

		if ( empty( trim( $text ) ) ) {
			return false;
		}

		// Clean up common PDF encoding artifacts.
		$text = preg_replace( '/\\\\(\d{3})/', '', $text );
		$text = str_replace( [ '\\(', '\\)', '\\\\' ], [ '(', ')', '\\' ], $text );

		return $text;
	}
}
