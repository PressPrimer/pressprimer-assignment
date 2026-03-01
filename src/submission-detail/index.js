/**
 * Submission Detail Entry Point
 *
 * This bundle is loaded on the submissions page when action=view
 * to render the React-based submission detail with document viewers,
 * editable score/feedback, and admin actions.
 *
 * The submissions list view uses a standard WP_List_Table
 * and does not require this bundle.
 *
 * @since 1.0.0
 */

import { render } from '@wordpress/element';
import { message } from 'antd';
import SubmissionDetail from './components/SubmissionDetail';

// Configure Ant Design message component.
message.config( {
	top: 50, // Position below WordPress admin bar.
	duration: 5,
	maxCount: 3,
} );

// Wait for DOM to be ready.
document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'ppa-submission-detail-root' );

	if ( root ) {
		const detailData = window.pressprimerAssignmentSubmissionDetailData || {};
		const submissionId = parseInt( detailData.submissionId, 10 );

		if ( submissionId > 0 ) {
			render( <SubmissionDetail submissionId={ submissionId } />, root );
		}
	}
} );
