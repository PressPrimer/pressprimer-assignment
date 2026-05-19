/**
 * Nonce utility for REST API URLs.
 *
 * Appends the WP REST nonce as a query parameter so that
 * direct browser navigation (href links) can authenticate
 * against the REST API without the X-WP-Nonce header.
 *
 * @package
 * @since 1.0.0
 */

/**
 * Get the REST API nonce from available global data.
 *
 * @return {string} The nonce string, or empty string if unavailable.
 */
export const getNonce = () =>
	window.pressprimerAssignmentGradingData?.nonce ||
	window.pressprimerAssignmentSubmissionDetailData?.nonce ||
	'';

/**
 * Append the WP REST nonce to a URL as a query parameter.
 *
 * @param {string} url The REST API URL.
 * @return {string} URL with _wpnonce parameter.
 */
export const appendNonce = ( url ) => {
	const nonce = getNonce();
	if ( ! nonce || ! url ) {
		return url;
	}
	const separator = url.includes( '?' ) ? '&' : '?';
	return url + separator + '_wpnonce=' + encodeURIComponent( nonce );
};

/**
 * Append a query parameter to a URL using the right separator.
 *
 * The admin file-content URL is REST-formed and has no query string,
 * but the legacy frontend file URL already carries action/id/nonce as
 * query params. A hardcoded "?download=1" works on the first but
 * breaks the second by introducing a double "?", which lets the
 * download=1 value bleed into the previous param. This helper picks
 * "&" vs "?" based on whether the URL already has a query string.
 *
 * @param {string} url   The URL to extend.
 * @param {string} param The key=value pair to append.
 * @return {string} URL with the param appended.
 */
export const appendQueryParam = ( url, param ) => {
	if ( ! url || ! param ) {
		return url;
	}
	const separator = url.includes( '?' ) ? '&' : '?';
	return url + separator + param;
};
