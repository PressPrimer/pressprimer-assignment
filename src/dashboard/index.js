/**
 * Dashboard - React Entry Point
 *
 * @package
 * @since 1.0.0
 */

import { render } from '@wordpress/element';
import Dashboard from './components/Dashboard';
import './style.css';

// Wait for DOM to be ready.
document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'ppa-dashboard-root' );

	if ( root ) {
		const dashboardData = window.pressprimerAssignmentDashboardData || {};

		render( <Dashboard initialData={ dashboardData } />, root );
	}
} );
