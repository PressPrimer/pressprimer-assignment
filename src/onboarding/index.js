/**
 * Onboarding Entry Point
 *
 * Bootstraps the onboarding React app when the PHP backend has
 * determined the current user should see the onboarding wizard.
 * Follows the same pattern as PressPrimer Quiz onboarding.
 *
 * @package
 * @since 1.0.0
 */

import { render, unmountComponentAtNode } from '@wordpress/element';
import Onboarding from './components/Onboarding';
import './style.css';

/**
 * Initialize the onboarding overlay
 */
const initOnboarding = () => {
	const data = window.pressprimerAssignmentOnboardingData;

	if ( ! data || ! data.state || ! data.state.should_show ) {
		return;
	}

	// Create a root container if it doesn't exist.
	let root = document.getElementById( 'ppa-onboarding-root' );
	if ( ! root ) {
		root = document.createElement( 'div' );
		root.id = 'ppa-onboarding-root';
		document.body.appendChild( root );
	}

	render( <Onboarding />, root );
};

/**
 * Bridge the assignment editor's after-save hook into a DOM event.
 *
 * The editor and the tour are separate React bundles; the editor
 * already exposes window.PPAEditorAfterSave for addons, so the tour
 * listens the same way. The publish step auto-advances when it hears
 * a published save.
 */
window.PPAEditorAfterSave = window.PPAEditorAfterSave || [];
window.PPAEditorAfterSave.push( ( { id, values } ) => {
	window.dispatchEvent(
		new CustomEvent( 'ppa:assignment-saved', {
			detail: { id, status: values?.status },
		} )
	);
} );

/**
 * Expose a global function to relaunch the onboarding tour.
 * Called from the dashboard "Relaunch Tour" button.
 */
window.ppaLaunchOnboarding = () => {
	const data = window.pressprimerAssignmentOnboardingData;

	if ( ! data ) {
		return;
	}

	// Reset via AJAX.
	const formData = new FormData();
	formData.append( 'action', 'pressprimer_assignment_onboarding_progress' );
	formData.append( 'nonce', data.nonce || '' );
	formData.append( 'action_type', 'reset' );

	fetch( data.ajaxUrl || '', {
		method: 'POST',
		credentials: 'same-origin',
		body: formData,
	} ).then( () => {
		// Update local state.
		data.state.should_show = true;
		data.state.current_step = 1;
		data.state.completed = false;
		data.state.started = false;

		// Unmount and re-render.
		const root = document.getElementById( 'ppa-onboarding-root' );
		if ( root ) {
			unmountComponentAtNode( root );
		}

		initOnboarding();
	} );
};

// Boot when DOM is ready.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initOnboarding );
} else {
	initOnboarding();
}
