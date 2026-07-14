/**
 * Tour session state
 *
 * The assignment saved during the guided build, remembered across
 * editor reloads within the tab. Cleared whenever a tour (re)starts
 * so a relaunched tour never targets the previous run's assignment.
 *
 * @package
 * @since 2.2.0
 */

const SAVED_ID_KEY = 'ppaSetupAssignmentId';
const SAVED_STATUS_KEY = 'ppaSetupAssignmentStatus';

/**
 * Read the saved assignment from session storage
 *
 * @return {{id: number|null, status: string|null}} Saved assignment info.
 */
export const readSavedAssignment = () => {
	try {
		const id = parseInt(
			window.sessionStorage.getItem( SAVED_ID_KEY ),
			10
		);
		const status = window.sessionStorage.getItem( SAVED_STATUS_KEY );

		return {
			id: id > 0 ? id : null,
			status: status || null,
		};
	} catch ( e ) {
		return { id: null, status: null };
	}
};

/**
 * Remember the assignment saved during the tour
 *
 * @param {number} id     Assignment ID.
 * @param {string} status Saved status ('published' or 'draft').
 */
export const writeSavedAssignment = ( id, status ) => {
	try {
		window.sessionStorage.setItem( SAVED_ID_KEY, String( id ) );
		if ( status ) {
			window.sessionStorage.setItem( SAVED_STATUS_KEY, status );
		}
	} catch ( e ) {
		// Session storage unavailable — in-memory state still works.
	}
};

/**
 * Forget the saved assignment (called when a tour starts fresh)
 */
export const clearSavedAssignment = () => {
	try {
		window.sessionStorage.removeItem( SAVED_ID_KEY );
		window.sessionStorage.removeItem( SAVED_STATUS_KEY );
	} catch ( e ) {
		// Nothing to clear.
	}
};
