<?php
/**
 * Extraction quality scoring and text sanitisation
 *
 * Evaluates extracted text quality on a 0-3 scale and provides a
 * shared sanitisation pipeline for all extraction services. The
 * quality heuristics generalise the per-line filtering logic from
 * the PDF service into a document-level scorer that works across
 * formats.
 *
 * @package PressPrimer_Assignment
 * @subpackage Utilities
 * @since 2.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extraction quality class
 *
 * Static utility class. All members are static — do not instantiate.
 *
 * @since 2.0.0
 */
class PressPrimer_Assignment_Extraction_Quality {

	/**
	 * Quality score: extraction failed (empty or threw)
	 *
	 * @since 2.0.0
	 * @var int
	 */
	const QUALITY_FAILED = 0;

	/**
	 * Quality score: poor (heuristics suggest garbage)
	 *
	 * @since 2.0.0
	 * @var int
	 */
	const QUALITY_POOR = 1;

	/**
	 * Quality score: acceptable (borderline)
	 *
	 * @since 2.0.0
	 * @var int
	 */
	const QUALITY_ACCEPTABLE = 2;

	/**
	 * Quality score: good (passes all checks)
	 *
	 * @since 2.0.0
	 * @var int
	 */
	const QUALITY_GOOD = 3;

	/**
	 * Evaluate extraction quality
	 *
	 * Scores the extracted text on a 0-3 scale using multiple
	 * heuristics. Returns an associative array with the score,
	 * computed metrics, and a human-readable reason.
	 *
	 * @since 2.0.0
	 *
	 * @param string $text       Extracted text.
	 * @param int    $file_size  Original file size in bytes.
	 * @param string $extension  File extension (pdf, docx, odt, rtf, txt).
	 * @return array{quality: int, length: int, word_count: int, reason: string}
	 */
	public static function evaluate( $text, $file_size, $extension ) {
		$text = is_string( $text ) ? $text : '';

		$length     = mb_strlen( $text );
		$word_count = $length > 0 ? str_word_count( $text ) : 0;

		// Failed: empty extraction.
		if ( 0 === $length || '' === trim( $text ) ) {
			return array(
				'quality'    => self::QUALITY_FAILED,
				'length'     => $length,
				'word_count' => $word_count,
				'reason'     => 'empty_extraction',
			);
		}

		$thresholds = self::get_thresholds( $extension );

		// Check minimum word count.
		if ( $word_count < $thresholds['min_words_poor'] ) {
			return array(
				'quality'    => self::QUALITY_POOR,
				'length'     => $length,
				'word_count' => $word_count,
				'reason'     => 'too_few_words',
			);
		}

		// Check replacement-character ratio.
		$replacement_count = preg_match_all( '/[\x{FFFD}\?]|[^\P{C}\n\t\r]/u', $text );
		if ( false === $replacement_count ) {
			$replacement_count = 0;
		}
		$replacement_ratio = $length > 0 ? $replacement_count / $length : 0;
		if ( $replacement_ratio > $thresholds['max_replacement_ratio_poor'] ) {
			return array(
				'quality'    => self::QUALITY_POOR,
				'length'     => $length,
				'word_count' => $word_count,
				'reason'     => 'high_replacement_chars',
			);
		}

		// Check length-to-file-size ratio (suspiciously low = poor).
		if ( $file_size > 0 ) {
			$length_ratio = $length / $file_size;
			if ( $length_ratio < $thresholds['min_length_ratio_poor'] ) {
				return array(
					'quality'    => self::QUALITY_POOR,
					'length'     => $length,
					'word_count' => $word_count,
					'reason'     => 'low_length_to_size_ratio',
				);
			}
		}

		// Check average word length (no spaces = concatenated garbage).
		if ( $word_count > 0 ) {
			$avg_word_length = $length / $word_count;
			if ( $avg_word_length > $thresholds['max_avg_word_length_poor'] ) {
				return array(
					'quality'    => self::QUALITY_POOR,
					'length'     => $length,
					'word_count' => $word_count,
					'reason'     => 'high_avg_word_length',
				);
			}
		}

		// Check vowel ratio in words (real text has vowels).
		$vowel_quality = self::check_vowel_quality( $text );
		if ( $vowel_quality < $thresholds['min_vowel_ratio_poor'] ) {
			return array(
				'quality'    => self::QUALITY_POOR,
				'length'     => $length,
				'word_count' => $word_count,
				'reason'     => 'low_vowel_ratio',
			);
		}

		// Borderline checks for QUALITY_ACCEPTABLE.
		$is_borderline = false;

		if ( $word_count < $thresholds['min_words_acceptable'] ) {
			$is_borderline = true;
		}

		if ( $replacement_ratio > $thresholds['max_replacement_ratio_acceptable'] ) {
			$is_borderline = true;
		}

		if ( $vowel_quality < $thresholds['min_vowel_ratio_acceptable'] ) {
			$is_borderline = true;
		}

		if ( $is_borderline ) {
			return array(
				'quality'    => self::QUALITY_ACCEPTABLE,
				'length'     => $length,
				'word_count' => $word_count,
				'reason'     => 'borderline',
			);
		}

		return array(
			'quality'    => self::QUALITY_GOOD,
			'length'     => $length,
			'word_count' => $word_count,
			'reason'     => 'passed',
		);
	}

	/**
	 * Sanitise extracted text
	 *
	 * Applies a normalisation pipeline to extracted text before
	 * storage. All extraction services should route their output
	 * through this method.
	 *
	 * @since 2.0.0
	 *
	 * @param string $text Raw extracted text.
	 * @return string Sanitised text.
	 */
	public static function sanitize( $text ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return '';
		}

		// 1. UTF-8 conversion.
		$encoding = mb_detect_encoding( $text, array( 'UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII' ), true );
		if ( false !== $encoding && 'UTF-8' !== $encoding ) {
			$converted = mb_convert_encoding( $text, 'UTF-8', $encoding );
			if ( false !== $converted ) {
				$text = $converted;
			}
		}

		// 2. Unicode NFC normalisation.
		if ( class_exists( 'Normalizer' ) ) {
			$normalised = Normalizer::normalize( $text, Normalizer::FORM_C );
			if ( false !== $normalised ) {
				$text = $normalised;
			}
		}

		// 3. Control-character strip (preserve \n, \t, \r).
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );

		// 4. Whitespace variant normalisation.
		$text = str_replace(
			array( "\xC2\xA0", "\xE2\x80\x8B", "\xE2\x80\xAF" ),
			' ',
			$text
		);

		// 5. Line-ending normalisation.
		$text = str_replace( "\r\n", "\n", $text );
		$text = str_replace( "\r", "\n", $text );

		// 6. Collapse runs of 3+ blank lines down to 2.
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );

		return trim( $text );
	}

	/**
	 * Finalise extraction results on a file record
	 *
	 * Applies the post-extraction filter, stores results on the file
	 * model, and fires the completion action. All extraction services
	 * should call this instead of manually saving fields.
	 *
	 * @since 2.0.0
	 *
	 * @param PressPrimer_Assignment_Submission_File $file   File model instance.
	 * @param string                                 $text   Extracted and sanitised text (empty string on failure).
	 * @param string                                 $method Extraction method identifier.
	 * @param string|null                            $error  Error message on failure, null on success.
	 */
	public static function finalize( $file, $text, $method, $error = null ) {
		$extension = strtolower( $file->file_extension );

		// Apply post-extraction filter (allows addons like OCR to modify text).
		if ( '' !== $text ) {
			/**
			 * Filter extracted text before storage
			 *
			 * Allows addons to post-process or replace extracted text.
			 * For example, an OCR addon could fill in text for image-only
			 * PDFs that the standard extractor could not read.
			 *
			 * @since 2.0.0
			 *
			 * @param string $text    Extracted text (already sanitised).
			 * @param int    $file_id Submission file record ID.
			 * @param string $method  Extraction method identifier.
			 */
			$text = apply_filters( 'pressprimer_assignment_extracted_text', $text, $file->id, $method );
		}

		// Score quality.
		$quality_result = self::evaluate( $text, $file->file_size, $extension );

		// Store results.
		$file->extracted_text        = '' !== $text ? $text : null;
		$file->text_extractable      = '' !== $text ? 1 : 0;
		$file->extraction_method     = $method;
		$file->extraction_quality    = $quality_result['quality'];
		$file->extracted_at          = current_time( 'mysql', true );
		$file->extracted_text_length = $quality_result['length'];
		$file->extracted_word_count  = $quality_result['word_count'];
		$file->extraction_error      = $error;
		$file->save();

		/**
		 * Fires when text extraction completes for a submission file
		 *
		 * Fires on both success and failure. Check the $quality parameter
		 * to determine the outcome.
		 *
		 * @since 2.0.0
		 *
		 * @param int    $file_id Submission file record ID.
		 * @param int    $quality Quality score (0-3).
		 * @param string $method  Extraction method identifier.
		 */
		do_action( 'pressprimer_assignment_text_extracted', $file->id, $quality_result['quality'], $method );
	}

	/**
	 * Get quality thresholds for a file extension
	 *
	 * Returns the threshold array used by evaluate(). Thresholds
	 * are filterable via pressprimer_assignment_extraction_quality_thresholds.
	 *
	 * @since 2.0.0
	 *
	 * @param string $extension File extension.
	 * @return array Threshold values.
	 */
	private static function get_thresholds( $extension ) {
		$defaults = array(
			'min_words_poor'                   => 5,
			'min_words_acceptable'             => 20,
			'max_replacement_ratio_poor'       => 0.15,
			'max_replacement_ratio_acceptable' => 0.05,
			'min_length_ratio_poor'            => 0.001,
			'max_avg_word_length_poor'         => 30,
			'min_vowel_ratio_poor'             => 0.15,
			'min_vowel_ratio_acceptable'       => 0.25,
		);

		// PDFs tend to have noisier extraction — relax thresholds slightly.
		if ( 'pdf' === $extension ) {
			$defaults['max_replacement_ratio_poor']       = 0.20;
			$defaults['max_replacement_ratio_acceptable'] = 0.08;
			$defaults['min_vowel_ratio_poor']             = 0.12;
		}

		// Plain text is always clean — tighten thresholds.
		if ( 'txt' === $extension ) {
			$defaults['max_replacement_ratio_poor']       = 0.05;
			$defaults['max_replacement_ratio_acceptable'] = 0.02;
		}

		/**
		 * Filter extraction quality thresholds
		 *
		 * Allows addons or site operators to tune scoring per format.
		 *
		 * @since 2.0.0
		 *
		 * @param array  $thresholds Threshold values.
		 * @param string $extension  File extension (pdf, docx, odt, rtf, txt).
		 */
		return apply_filters( 'pressprimer_assignment_extraction_quality_thresholds', $defaults, $extension );
	}

	/**
	 * Check vowel quality in text
	 *
	 * Measures the proportion of words that contain at least one
	 * vowel. Real prose has vowels in nearly every word; extraction
	 * garbage (font encoding artefacts, binary data) typically does not.
	 *
	 * Adapted from PressPrimer_Assignment_PDF_Service::filter_garbage_text().
	 *
	 * @since 2.0.0
	 *
	 * @param string $text Text to check.
	 * @return float Ratio of words containing vowels (0.0 to 1.0).
	 */
	private static function check_vowel_quality( $text ) {
		// Extract words (sequences of 2+ alphabetic characters).
		preg_match_all( '/\b[a-zA-Z]{2,}\b/', $text, $matches );

		if ( empty( $matches[0] ) ) {
			return 0.0;
		}

		$total_words = count( $matches[0] );
		$vowel_words = 0;

		foreach ( $matches[0] as $word ) {
			if ( preg_match( '/[aeiouyAEIOUY]/', $word ) ) {
				++$vowel_words;
			}
		}

		return $total_words > 0 ? $vowel_words / $total_words : 0.0;
	}
}
