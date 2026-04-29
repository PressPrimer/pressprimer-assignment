<?php
/**
 * ODT text extraction service
 *
 * Provides ODT text extraction for assignment submissions using
 * native PHP ZipArchive + XMLReader. Streams through content.xml
 * extracting text from <text:p>, <text:h>, and <text:span> nodes.
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
 * ODT service class
 *
 * Extracts text from ODT files using ZipArchive + XMLReader.
 * Supports both synchronous quick checks and asynchronous full
 * extraction via WP Cron.
 *
 * @since 2.0.0
 */
class PressPrimer_Assignment_ODT_Service {

	/**
	 * Minimum word count to consider an ODT as having extractable text
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
	const METHOD = 'native-odt';

	/**
	 * ODF text namespace URI
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const NS_TEXT = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';

	// =========================================================================
	// Public API.
	// =========================================================================

	/**
	 * Extract text from an ODT file
	 *
	 * Uses ZipArchive to open the ODT (which is a ZIP) and
	 * XMLReader to stream-parse content.xml, extracting text
	 * from paragraph and heading elements.
	 *
	 * @since 2.0.0
	 *
	 * @param string $file_path Full path to ODT file.
	 * @return string|WP_Error Extracted text or WP_Error on failure.
	 */
	public function extract_text( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new WP_Error(
				'pressprimer_assignment_file_not_found',
				__( 'ODT file not found or not readable.', 'pressprimer-assignment' )
			);
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error(
				'pressprimer_assignment_zip_unavailable',
				__( 'ZipArchive extension is not available.', 'pressprimer-assignment' )
			);
		}

		if ( ! class_exists( 'XMLReader' ) ) {
			return new WP_Error(
				'pressprimer_assignment_xmlreader_unavailable',
				__( 'XMLReader extension is not available.', 'pressprimer-assignment' )
			);
		}

		$zip = new ZipArchive();
		$res = $zip->open( $file_path );

		if ( true !== $res ) {
			return new WP_Error(
				'pressprimer_assignment_zip_open_failed',
				__( 'Failed to open ODT file. It may be corrupt or password-protected.', 'pressprimer-assignment' )
			);
		}

		$xml_content = $zip->getFromName( 'content.xml' );
		$zip->close();

		if ( false === $xml_content || '' === $xml_content ) {
			return new WP_Error(
				'pressprimer_assignment_odt_no_content',
				__( 'ODT file does not contain content.xml. It may be corrupt.', 'pressprimer-assignment' )
			);
		}

		$text = $this->parse_content_xml( $xml_content );

		if ( '' === trim( $text ) ) {
			return new WP_Error(
				'pressprimer_assignment_odt_extraction_failed',
				__( 'Unable to extract readable text from this ODT file.', 'pressprimer-assignment' )
			);
		}

		return $text;
	}

	/**
	 * Check if an ODT has extractable text (quick check)
	 *
	 * Performs extraction to determine whether the ODT contains
	 * readable text. Used during file upload.
	 *
	 * @since 2.0.0
	 *
	 * @param string $file_path Full path to ODT file.
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

		wp_schedule_single_event( time(), 'pressprimer_assignment_extract_odt_text', array( $file_id ) );
	}

	/**
	 * Process a scheduled full text extraction
	 *
	 * WP Cron callback that performs full text extraction on an ODT
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

		if ( ! $file || 'odt' !== strtolower( $file->file_extension ) ) {
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
	 * Parse content.xml to extract text using XMLReader
	 *
	 * Streams through the XML extracting text from <text:p> and
	 * <text:h> elements. Each paragraph/heading produces one line
	 * of output. Handles <text:tab> and <text:line-break> inline
	 * elements.
	 *
	 * @since 2.0.0
	 *
	 * @param string $xml_content Raw XML content from content.xml.
	 * @return string Extracted plain text.
	 */
	private function parse_content_xml( $xml_content ) {
		$reader = new XMLReader();

		// Suppress XML parsing warnings for malformed documents.
		$prev = libxml_use_internal_errors( true );

		// Use a data URI to load the string via XMLReader.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentional suppression of XML parse warnings.
		$opened = @$reader->XML( $xml_content, 'UTF-8', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );

		if ( ! $opened ) {
			libxml_clear_errors();
			libxml_use_internal_errors( $prev );
			return '';
		}

		$lines    = array();
		$in_block = false;
		$current  = '';

		while ( $reader->read() ) {
			$ns    = $reader->namespaceURI;
			$local = $reader->localName;

			// Start of a paragraph or heading.
			if ( XMLReader::ELEMENT === $reader->nodeType && self::NS_TEXT === $ns ) {
				if ( 'p' === $local || 'h' === $local ) {
					$in_block = true;
					$current  = '';
				} elseif ( $in_block && 'tab' === $local ) {
					$current .= "\t";
				} elseif ( $in_block && 'line-break' === $local ) {
					$current .= "\n";
				} elseif ( $in_block && 's' === $local ) {
					// <text:s> represents a space; text:c attribute = count.
					$count    = $reader->getAttribute( 'text:c' );
					$count    = $count ? (int) $count : 1;
					$current .= str_repeat( ' ', $count );
				}
			}

			// Text content within a block.
			if ( $in_block && XMLReader::TEXT === $reader->nodeType ) {
				$current .= $reader->value;
			}

			// Significant whitespace within a block.
			if ( $in_block && XMLReader::SIGNIFICANT_WHITESPACE === $reader->nodeType ) {
				$current .= $reader->value;
			}

			// End of a paragraph or heading.
			if ( XMLReader::END_ELEMENT === $reader->nodeType && self::NS_TEXT === $ns ) {
				if ( 'p' === $local || 'h' === $local ) {
					$lines[]  = $current;
					$in_block = false;
					$current  = '';
				}
			}
		}

		$reader->close();
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		return implode( "\n", $lines );
	}
}
