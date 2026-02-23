/**
 * Dashboard Entry Point
 *
 * @package
 * @since 1.0.0
 */

import { render } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Dashboard Component
 *
 * Placeholder dashboard page.
 */
const Dashboard = () => {
	const data = window.pressprimerAssignmentDashboardData || {};
	const pluginName = data.pluginName || 'PPA Assignments';

	return (
		<div className="wrap">
			<h1>{ pluginName }</h1>
			<p>{ __( 'Dashboard coming soon.', 'pressprimer-assignment' ) }</p>
		</div>
	);
};

// Wait for DOM to be ready.
document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'ppa-dashboard-root' );

	if ( root ) {
		render( <Dashboard />, root );
	}
} );
