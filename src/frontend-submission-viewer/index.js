/**
 * Frontend submission viewer entry point
 *
 * Mounts a read-only DocumentPanel inside the student-facing
 * "Your Submission" card so graded/returned submissions display the
 * same inline preview the grader saw — including any annotations the
 * grader added (via the School addon's PPADocumentViewerOverrides
 * registry, when active).
 *
 * @since 2.1.0
 */

import { render } from '@wordpress/element';
import FrontendSubmissionViewer from './FrontendSubmissionViewer';

document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById(
		'ppa-frontend-submission-viewer-root'
	);
	if ( ! root ) {
		return;
	}

	const data = window.pressprimerAssignmentFrontendSubmission || {};
	render(
		<FrontendSubmissionViewer
			files={ data.files || [] }
			textContent={ data.textContent || null }
			wordCount={ data.wordCount || null }
		/>,
		root
	);
} );
