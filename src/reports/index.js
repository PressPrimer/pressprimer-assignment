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
				// If no report type or unknown type, check if it's an addon report.
				// Addon reports render their own content in ppa-addon-report-root.
				if ( reportType ) {
					root.innerHTML =
						'<div id="ppa-addon-report-root"></div>';
					document.dispatchEvent(
						new CustomEvent( 'ppa-addon-report-ready', {
							detail: { reportType },
						} )
					);
					return;
				}
				Component = Reports;
		}

		render( <Component />, root );
	}
} );
