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
	window.ppaGradingData?.nonce || window.ppaSubmissionDetailData?.nonce || '';

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
