/**
 * Assignment Editor Entry Point
 *
 * @package
 * @since 1.0.0
 */

import { render } from '@wordpress/element';
import { message } from 'antd';
import AssignmentEditor from './components/AssignmentEditor';
import './style.css';

// Configure Ant Design message component.
message.config( {
	top: 50,
	duration: 10,
	maxCount: 3,
} );

// Wait for DOM to be ready.
document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'ppa-assignment-editor-root' );

	if ( root ) {
		// Get assignment data from localized script.
		const assignmentData = window.pressprimerAssignmentEditorData || {};

		// Render the editor.
		render( <AssignmentEditor assignmentData={ assignmentData } />, root );
	}
} );
