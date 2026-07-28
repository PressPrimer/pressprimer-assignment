<?php
/**
 * PPTX text extraction service
 *
 * Provides PowerPoint (.pptx) text extraction for assignment submissions
 * using native PHP ZipArchive + DOMDocument. Walks <a:t> text nodes in
 * ppt/slides/slideN.xml and ppt/notesSlides/notesSlideN.xml, preserving
 * slide order with slide-number markers, so slide titles, body text,
 * bullets, and speaker notes all feed the extracted-text pipeline the
 * addons consume (School AI grading works on presentations unchanged).
 *
 * Two-tier extraction strategy (matching the DOCX/ODT services):
 * - Quick check during upload for the text_extractable flag.
 * - Full extraction via WP Cron for AI features.
 *
 * @package PressPrimer_Assignment
 * @subpackage Services
 * @since 2.2.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PPTX text service class
 *
 * @since 2.2.0
 */
class PressPrimer_Assignment_PPTX_Text_Service {

	/**
	 * Minimum word count to consider a PPTX as having extractable text
	 *
	 * @since 2.2.0
	 * @var int
	 */
	const MIN_WORD_COUNT = 5;

	/**
	 * Extraction method identifier
	 *
	 * @since 2.2.0
	 * @var string
	 */
	const METHOD = 'native-pptx';

	/**
	 * DrawingML namespace containing <a:p> and <a:t> nodes
	 *
	 * @since 2.2.0
	 * @var string
	 */
	const DRAWINGML_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';

	// =========================================================================
	// Public API.
	// =========================================================================

	/**
	 * Extract text from a PPTX file
	 *
	 * Enumerates ppt/slides/slideN.xml entries in slide-number order,
	 * pulling every <a:t> text node (titles, body, bullets) and the
	 * matching ppt/notesSlides/notesSlideN.xml speaker notes, with
	 * slide-number markers in the output.
	 *
	 * A decompressed-size ceiling guards against zip bombs: the summed
	 * uncompressed size of the XML entries this method reads may not
	 * exceed the site's max upload setting. Media entries (images,
	 * video) are never inflated.
	 *
	 * @since 2.2.0
	 *
	 * @param string $file_path Full path to PPTX file.
	 * @return string|WP_Error Extracted text or WP_Error on failure.
	 */
	public function extract_text( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new WP_Error(
				'pressprimer_assignment_file_not_found',
				__( 'PPTX file not found or not readable.', 'pressprimer-assignment' )
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
				__( 'Failed to open PPTX file. It may be corrupt or password-protected.', 'pressprimer-assignment' )
			);
		}

		// Index slide and notes entries by slide number, tracking the
		// summed uncompressed size of everything we intend to inflate.
		$slides      = [];
		$notes       = [];
		$total_size  = 0;
		$ceiling     = $this->get_decompressed_ceiling();
		$entry_count = $zip->numFiles;

		for ( $i = 0; $i < $entry_count; $i++ ) {
			$stat = $zip->statIndex( $i );

			if ( false === $stat ) {
				continue;
			}

			if ( preg_match( '#^ppt/slides/slide(\d+)\.xml$#', $stat['name'], $matches ) ) {
				$slides[ (int) $matches[1] ] = $stat['name'];
			} elseif ( preg_match( '#^ppt/notesSlides/notesSlide(\d+)\.xml$#', $stat['name'], $matches ) ) {
				$notes[ (int) $matches[1] ] = $stat['name'];
			} else {
				continue;
			}

			$total_size += (int) $stat['size'];

			if ( $total_size > $ceiling ) {
				$zip->close();
				return new WP_Error(
					'pressprimer_assignment_pptx_too_large',
					__( 'The presentation contents are too large to extract text from.', 'pressprimer-assignment' )
				);
			}
		}

		if ( empty( $slides ) ) {
			$zip->close();
			return new WP_Error(
				'pressprimer_assignment_pptx_no_slides',
				__( 'PPTX file does not contain any slides. It may be corrupt.', 'pressprimer-assignment' )
			);
		}

		ksort( $slides );

		$sections = [];

		foreach ( $slides as $slide_number => $entry_name ) {
			$slide_xml = $zip->getFromName( $entry_name );

			if ( false === $slide_xml || '' === $slide_xml ) {
				continue;
			}

			$slide_text = $this->parse_slide_xml( $slide_xml );

			if ( '' !== trim( $slide_text ) ) {
				$sections[] = sprintf(
					/* translators: %d: slide number (marker line in extracted presentation text) */
					__( '[Slide %d]', 'pressprimer-assignment' ),
					$slide_number
				) . "\n" . trim( $slide_text );
			}

			// Speaker notes for this slide, in place, right after it.
			if ( isset( $notes[ $slide_number ] ) ) {
				$notes_xml = $zip->getFromName( $notes[ $slide_number ] );

				if ( false !== $notes_xml && '' !== $notes_xml ) {
					$notes_text = $this->parse_slide_xml( $notes_xml );

					if ( '' !== trim( $notes_text ) ) {
						$sections[] = sprintf(
							/* translators: %d: slide number (marker line in extracted presentation text) */
							__( '[Slide %d notes]', 'pressprimer-assignment' ),
							$slide_number
						) . "\n" . trim( $notes_text );
					}
				}
			}
		}

		$zip->close();

		$text = implode( "\n\n", $sections );

		if ( '' === trim( $text ) ) {
			return new WP_Error(
				'pressprimer_assignment_pptx_extraction_failed',
				__( 'Unable to extract readable text from this presentation.', 'pressprimer-assignment' )
			);
		}

		return $text;
	}

	/**
	 * Check if a PPTX has extractable text (quick check)
	 *
	 * @since 2.2.0
	 *
	 * @param string $file_path Full path to PPTX file.
	 * @return array {
	 *     Extraction result.
	 *
	 *     @type bool   $extractable Whether text was successfully extracted.
	 *     @type int    $word_count  Number of words extracted.
	 *     @type string $method      Extraction method used.
	 * }
	 */
	public function check_text_extractable( $file_path ) {
		$default = [
			'extractable' => false,
			'word_count'  => 0,
			'method'      => 'none',
		];

		$text = $this->extract_text( $file_path );

		if ( is_wp_error( $text ) || '' === trim( $text ) ) {
			return $default;
		}

		$clean_text = trim( preg_replace( '/\s+/', ' ', $text ) );
		$word_count = str_word_count( $clean_text );

		return [
			'extractable' => $word_count >= self::MIN_WORD_COUNT,
			'word_count'  => $word_count,
			'method'      => self::METHOD,
		];
	}

	/**
	 * Schedule full text extraction via WP Cron
	 *
	 * @since 2.2.0
	 *
	 * @param int $file_id Submission file record ID.
	 */
	public static function schedule_full_extraction( $file_id ) {
		$file_id = absint( $file_id );

		if ( 0 === $file_id ) {
			return;
		}

		wp_schedule_single_event( time(), 'pressprimer_assignment_extract_pptx_text', [ $file_id ] );
	}

	/**
	 * Process a scheduled full text extraction
	 *
	 * WP Cron callback that performs full text extraction on a PPTX
	 * file and stores the result in the database. Malformed or
	 * password-protected files finalize with an error and empty text —
	 * never a fatal.
	 *
	 * @since 2.2.0
	 *
	 * @param int $file_id Submission file record ID.
	 */
	public static function process_scheduled_extraction( $file_id ) {
		$file_id = absint( $file_id );

		if ( 0 === $file_id ) {
			return;
		}

		$file = PressPrimer_Assignment_Submission_File::get( $file_id );

		if ( ! $file || 'pptx' !== strtolower( $file->file_extension ) ) {
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

		// Sanitise, let addons adjust, and finalise.
		$text = PressPrimer_Assignment_Extraction_Quality::sanitize( $text );

		/**
		 * Filters the text extracted from a PPTX submission file.
		 *
		 * Fires before the text is stored, so addon consumers of the
		 * extracted-text pipeline (e.g. School AI grading) receive the
		 * filtered value.
		 *
		 * @since 2.2.0
		 *
		 * @param string $text    Extracted text (slide-ordered, with slide markers).
		 * @param int    $file_id The submission file ID.
		 */
		$text = apply_filters( 'pressprimer_assignment_pptx_extracted_text', $text, $file_id );

		PressPrimer_Assignment_Extraction_Quality::finalize( $file, $text, self::METHOD );
	}

	// =========================================================================
	// Private extraction methods.
	// =========================================================================

	/**
	 * Parse a slide or notes-slide XML document to extract text
	 *
	 * Walks <a:p> paragraph elements (one line each — covers titles,
	 * body text, and bullets in document order) collecting their <a:t>
	 * text node descendants.
	 *
	 * @since 2.2.0
	 *
	 * @param string $xml_content Raw XML content from the slide entry.
	 * @return string Extracted plain text.
	 */
	private function parse_slide_xml( $xml_content ) {
		$doc = new DOMDocument();

		// Suppress XML parsing warnings for malformed documents.
		$prev = libxml_use_internal_errors( true );
		$doc->loadXML( $xml_content, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		$paragraphs = $doc->getElementsByTagNameNS( self::DRAWINGML_NS, 'p' );

		$lines = [];

		foreach ( $paragraphs as $paragraph ) {
			$text_parts = [];

			$text_nodes = $paragraph->getElementsByTagNameNS( self::DRAWINGML_NS, 't' );

			foreach ( $text_nodes as $text_node ) {
				$text_parts[] = $text_node->textContent;
			}

			$line = trim( implode( '', $text_parts ) );

			if ( '' !== $line ) {
				$lines[] = $line;
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Get the decompressed-size ceiling for extraction
	 *
	 * Reuses the site's max file size setting as the bound (the spec's
	 * zip-bomb guard). Applies to the summed uncompressed size of the
	 * slide/notes XML entries only — media is never read.
	 *
	 * @since 2.2.0
	 *
	 * @return int Ceiling in bytes.
	 */
	private function get_decompressed_ceiling() {
		$settings = get_option( 'pressprimer_assignment_settings', [] );

		$max_mb = isset( $settings['default_max_file_size'] )
			? absint( $settings['default_max_file_size'] )
			: 0;

		if ( $max_mb > 0 ) {
			return $max_mb * MB_IN_BYTES;
		}

		return PressPrimer_Assignment_File_Service::DEFAULT_MAX_FILE_SIZE;
	}
}
