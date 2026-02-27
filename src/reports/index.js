/**
 * Reports App Entry Point
 *
 * Routes to the appropriate report component based on the URL
 * query parameter. Mirrors Quiz's reports routing pattern.
 *
 * @package
 * @since 1.0.0
 */

import { render } from '@wordpress/element';
import Reports from './components/Reports';
import AssignmentPerformanceReport from './components/AssignmentPerformanceReport';
import './style.css';

document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'ppa-reports-root' );

	if ( root ) {
		const urlParams = new URLSearchParams( window.location.search );
		const reportType = urlParams.get( 'report' );

		let Component;

		switch ( reportType ) {
			case 'assignment-performance':
				Component = AssignmentPerformanceReport;
				break;
			default:
				Component = Reports;
		}

		render( <Component />, root );
	}
} );
