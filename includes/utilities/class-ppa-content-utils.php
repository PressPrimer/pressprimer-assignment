<?php
/**
 * Content rendering utilities
 *
 * Small helpers for cleaning up rich text content before it is
 * rendered to the page.
 *
 * @package PressPrimer_Assignment
 * @subpackage Utilities
 * @since 2.1.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content utilities.
 *
 * Static utility class. All members are static — do not instantiate.
 *
 * @since 2.1.0
 */
class PressPrimer_Assignment_Content_Utils {

	/**
	 * Strip trailing empty paragraphs from rich text content.
	 *
	 * TinyMCE inserts a trailing `<p></p>` (variants: `<p>&nbsp;</p>`,
	 * `<p><br></p>`) when the user hits Enter at the end of their content.
	 * That renders as a visible blank line on the frontend, which is rarely
	 * what the author intended. This trims those trailing empties off the
	 * tail of the string while leaving meaningful empty paragraphs in the
	 * middle of the document untouched.
	 *
	 * Safe on plain-text input (no `<p>` tags = no match = returns input
	 * unchanged) and on already-clean HTML.
	 *
	 * @since 2.1.0
	 *
	 * @param string $html Rich text HTML (or plain text).
	 * @return string The input with trailing empty paragraphs removed.
	 */
	public static function strip_trailing_empty_paragraphs( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}

		// Trailing tail patterns we strip:
		// - empty <p> tag (with whitespace, &nbsp;, or <br> as the only content)
		// - lone <br> outside any paragraph
		// Loop because content like "<p></p><p>&nbsp;</p>" stacks two empties
		// and a single regex pass would only catch the outermost.
		//
		// "Empty content" alternation includes:
		// - \s (PCRE whitespace: space, tab, CR, LF, FF)
		// - \xc2\xa0 (UTF-8 byte sequence for U+00A0, non-breaking space —
		// TinyMCE serializes the &nbsp; entity as the literal character)
		// - &nbsp;, &#160;, &#xa0; (entity forms, in case any consumer
		// re-encodes)
		// - <br> / <br /> (line break with or without slash)
		$pattern = '~(?:<p[^>]*>(?:\s|\xc2\xa0|&nbsp;|&\#(?:160|x[aA]0);|<br\s*/?>)*</p>|<br\s*/?>)\s*$~i';

		$html  = rtrim( $html );
		$guard = 10; // hard ceiling on iterations.
		while ( $guard-- > 0 && preg_match( $pattern, $html ) ) {
			$next = preg_replace( $pattern, '', $html );
			if ( null === $next || $next === $html ) {
				break;
			}
			$html = rtrim( $next );
		}

		return $html;
	}
}
