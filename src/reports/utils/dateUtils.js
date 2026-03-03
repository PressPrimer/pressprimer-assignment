/**
 * Date Utilities for Reports
 *
 * @package
 * @since 1.0.0
 */

/**
 * Get date range based on preset.
 *
 * @param {string} preset Preset name (7days, 30days, 90days, all).
 * @return {Object} Date range with from and to dates (YYYY-MM-DD format).
 */
export const getDateRange = ( preset ) => {
	// For "all", return null dates (no filtering).
	if ( preset === 'all' ) {
		return { from: null, to: null };
	}

	// Get today's date at end of day for the "to" date.
	const today = new Date();
	// Add one day to include all of today's records.
	const tomorrow = new Date( today );
	tomorrow.setDate( tomorrow.getDate() + 1 );
	const to = tomorrow.toISOString().split( 'T' )[ 0 ];

	// Calculate "from" date based on preset.
	const fromDate = new Date( today );

	switch ( preset ) {
		case '7days':
			fromDate.setDate( fromDate.getDate() - 6 ); // -6 to include today as day 7.
			break;
		case '30days':
			fromDate.setDate( fromDate.getDate() - 29 ); // -29 to include today as day 30.
			break;
		case '90days':
			fromDate.setDate( fromDate.getDate() - 89 ); // -89 to include today as day 90.
			break;
		default:
			return { from: null, to: null };
	}

	const from = fromDate.toISOString().split( 'T' )[ 0 ];

	return { from, to };
};

/**
 * Format grading turnaround time from seconds to human-readable string.
 *
 * @param {number} seconds Total seconds.
 * @return {string} Formatted duration (e.g., "2h 30m", "1d 5h").
 */
export const formatGradingTime = ( seconds ) => {
	if ( ! seconds || seconds === 0 ) {
		return '-';
	}

	const minutes = Math.floor( seconds / 60 );
	const hours = Math.floor( minutes / 60 );
	const days = Math.floor( hours / 24 );

	if ( days > 0 ) {
		const remainingHours = hours % 24;
		if ( remainingHours === 0 ) {
			return `${ days }d`;
		}
		return `${ days }d ${ remainingHours }h`;
	}

	if ( hours > 0 ) {
		const remainingMins = minutes % 60;
		if ( remainingMins === 0 ) {
			return `${ hours }h`;
		}
		return `${ hours }h ${ remainingMins }m`;
	}

	if ( minutes === 0 ) {
		return '< 1m';
	}

	return `${ minutes }m`;
};
