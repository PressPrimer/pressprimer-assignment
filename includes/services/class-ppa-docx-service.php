<?php
/**
 * DOCX text extraction service
 *
 * Provides DOCX text extraction for assignment submissions using
 * native PHP ZipArchive + DOMDocument. Walks <w:t> text nodes
 * in word/document.xml to produce plain text.
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
 * DOCX service class
 *
 * Extracts text from DOCX files using ZipArchive + DOMDocument.
 * Supports both synchronous quick checks and asynchronous full
 * extraction via WP Cron.
 *
 * @since 2.0.0
 */
class PressPrimer_Assignment_DOCX_Service {

	/**
	 * Minimum word count to consider a DOCX as having extractable text
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
	const METHOD = 'native-docx';

	// =========================================================================
	// Public API.
	// =========================================================================

	/**
	 * Extract text from a DOCX file
	 *
	 * Uses ZipArchive to open the DOCX (which is a ZIP) and
	 * DOMDocument to parse word/document.xml, walking all <w:t>
	 * text nodes.
	 *
	 * @since 2.0.0
	 *
	 * @param string $file_path Full path to DOCX file.
	 * @return string|WP_Error Extracted text or WP_Error on failure.
	 */
	public function extract_text( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new WP_Error(
				'pressprimer_assignment_file_not_found',
				__( 'DOCX file not found or not readable.', 'pressprimer-assignment' )
			);
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error(
				'pressprimer_assignment_zip_unavailable',
				__( 'ZipArchive extension is not available.', 'pressprimer-assignment' )
			);
		}

		if ( ! class_exists( 'DOMDocument' ) ) {
			return new WP_Error(
				'pressprimer_assignment_dom_unavailable',
				__( 'DOMDocument extension is not available.', 'pressprimer-assignment' )
			);
		}

		$zip = new ZipArchive();
		$res = $zip->open( $file_path );

		if ( true !== $res ) {
			return new WP_Error(
				'pressprimer_assignment_zip_open_failed',
				__( 'Failed to open DOCX file. It may be corrupt or password-protected.', 'pressprimer-assignment' )
			);
		}

		$xml_content = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( false === $xml_content || '' === $xml_content ) {
			return new WP_Error(
				'pressprimer_assignment_docx_no_document',
				__( 'DOCX file does not contain word/document.xml. It may be corrupt.', 'pressprimer-assignment' )
			);
		}

		$text = $this->parse_document_xml( $xml_content );

		if ( '' === trim( $text ) ) {
			return new WP_Error(
				'pressprimer_assignment_docx_extraction_failed',
				__( 'Unable to extract readable text from this DOCX file.', 'pressprimer-assignment' )
			);
		}

		return $text;
	}

	/**
	 * Check if a DOCX has extractable text (quick check)
	 *
	 * Performs extraction to determine whether the DOCX contains
	 * readable text. Used during file upload.
	 *
	 * @since 2.0.0
	 *
	 * @param string $file_path Full path to DOCX file.
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

		wp_schedule_single_event( time(), 'pressprimer_assignment_extract_docx_text', array( $file_id ) );
	}

	/**
	 * Process a scheduled full text extraction
	 *
	 * WP Cron callback that performs full text extraction on a DOCX
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

		if ( ! $file || 'docx' !== strtolower( $file->file_extension ) ) {
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
	 * Parse word/document.xml content to extract text
	 *
	 * Walks the DOM tree extracting text from <w:t> nodes,
	 * handling paragraph (<w:p>) and line break (<w:br>) boundaries.
	 *
	 * @since 2.0.0
	 *
	 * @param string $xml_content Raw XML content from word/document.xml.
	 * @return string Extracted plain text.
	 */
	private function parse_document_xml( $xml_content ) {
		$doc = new DOMDocument();

		// Suppress XML parsing warnings for malformed documents.
		$prev = libxml_use_internal_errors( true );
		$doc->loadXML( $xml_content, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		$paragraphs = $doc->getElementsByTagNameNS(
			'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
			'p'
		);

		$lines = array();

		foreach ( $paragraphs as $paragraph ) {
			$line    = $this->extract_paragraph_text( $paragraph, $doc );
			$lines[] = $line;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Extract text from a single <w:p> paragraph element
	 *
	 * Walks child nodes to find <w:t> text nodes and <w:br>
	 * line breaks within the paragraph.
	 *
	 * @since 2.0.0
	 *
	 * @param DOMElement  $paragraph The <w:p> element.
	 * @param DOMDocument $doc       The parent document.
	 * @return string Extracted text for this paragraph.
	 */
	private function extract_paragraph_text( $paragraph, $doc ) {
		$text_parts = array();

		// Walk all descendant <w:t> and <w:br> nodes.
		$runs = $paragraph->getElementsByTagNameNS(
			'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
			'r'
		);

		foreach ( $runs as $run ) {
			$children = $run->childNodes;

			foreach ( $children as $child ) {
				if ( XML_ELEMENT_NODE !== $child->nodeType ) {
					continue;
				}

				$local_name = $child->localName;

				if ( 't' === $local_name ) {
					$text_parts[] = $child->textContent;
				} elseif ( 'br' === $local_name ) {
					$text_parts[] = "\n";
				} elseif ( 'tab' === $local_name ) {
					$text_parts[] = "\t";
				}
			}
		}

		return implode( '', $text_parts );
	}
}
