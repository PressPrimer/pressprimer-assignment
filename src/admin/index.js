/**
 * Admin - React Entry Point
 *
 * Mounts the main admin application into the page container.
 *
 * @package
 * @since 1.0.0
 */

import { render } from '@wordpress/element';
import { message } from 'antd';
import App from './App';

// Configure Ant Design message component.
message.config( {
	top: 50,
	duration: 5,
	maxCount: 3,
} );

// Wait for DOM to be ready.
document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'ppa-admin-root' );

	if ( root ) {
		render( <App />, root );
	}
} );
