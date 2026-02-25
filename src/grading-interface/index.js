/**
 * Grading Interface Entry Point
 *
 * This bundle is loaded on the grading page when action=grade
 * to render the React-based grading form with document viewers.
 *
 * The grading queue list view uses a standard WP_List_Table
 * and does not require this bundle.
 *
 * @since 1.0.0
 */

import { render } from '@wordpress/element';
import { message } from 'antd';
import GradingForm from './components/GradingForm';

// Configure Ant Design message component.
message.config( {
	top: 50, // Position below WordPress admin bar.
	duration: 5,
	maxCount: 3,
} );

// Wait for DOM to be ready.
document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'ppa-grading-interface-root' );

	if ( root ) {
		const gradingData = window.ppaGradingData || {};
		const submissionId = parseInt( gradingData.submissionId, 10 );

		if ( submissionId > 0 ) {
			render( <GradingForm submissionId={ submissionId } />, root );
		}
	}
} );
