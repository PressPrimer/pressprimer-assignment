/**
 * Settings Panel - React Entry Point
 *
 * @package
 * @since 1.0.0
 */

import { render } from '@wordpress/element';
import { message } from 'antd';
import SettingsPage from './components/SettingsPage';
import './style.css';

// Configure Ant Design message component.
message.config( {
	top: 50,
	duration: 5,
	maxCount: 3,
} );

// Wait for DOM to be ready.
document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'ppa-settings-root' );

	if ( root ) {
		const settingsData = window.ppaSettingsData || {};

		render( <SettingsPage settingsData={ settingsData } />, root );
	}
} );
