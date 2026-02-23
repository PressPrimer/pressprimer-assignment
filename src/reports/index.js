/**
 * Reports Entry Point
 *
 * @package
 * @since 1.0.0
 */

import { render } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Reports Component
 *
 * Placeholder reports page.
 */
const Reports = () => {
	return (
		<div className="wrap">
			<h1>{ __( 'Reports', 'pressprimer-assignment' ) }</h1>
			<p>{ __( 'Reports coming soon.', 'pressprimer-assignment' ) }</p>
		</div>
	);
};

// Wait for DOM to be ready.
document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'ppa-reports-root' );

	if ( root ) {
		render( <Reports />, root );
	}
} );
