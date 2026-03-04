/**
 * RTF Viewer Component
 *
 * Renders RTF documents using rtf.js. Fetches the file as an
 * ArrayBuffer and converts it to HTML for display.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Spin, Button, Alert } from 'antd';
import { DownloadOutlined } from '@ant-design/icons';
import { RTFJS } from 'rtf.js';
import { appendNonce } from '../../utils/nonce';

// Disable verbose rtf.js debug logging.
RTFJS.loggingEnabled( false );

/**
 * Convert an array of DOM elements to an HTML string.
 *
 * @param {HTMLElement[]} elements DOM elements from rtf.js render.
 * @return {string} Combined outer HTML.
 */
const elementsToHtml = ( elements ) => {
	const container = document.createElement( 'div' );
	elements.forEach( ( el ) => container.appendChild( el ) );
	return container.innerHTML;
};

/**
 * RtfViewer component
 *
 * @param {Object} props     Component props.
 * @param {string} props.url Download URL for the RTF file.
 * @return {JSX.Element} Rendered component.
 */
const RtfViewer = ( { url } ) => {
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ html, setHtml ] = useState( '' );

	useEffect( () => {
		let cancelled = false;

		const loadRtf = async () => {
			setLoading( true );
			setError( null );

			try {
				const response = await window.fetch( url, {
					credentials: 'same-origin',
					headers: {
						'X-WP-Nonce':
							window.pressprimerAssignmentGradingData?.nonce ||
							window.pressprimerAssignmentSubmissionDetailData
								?.nonce ||
							'',
					},
				} );

				if ( ! response.ok ) {
					throw new Error(
						`HTTP ${ response.status }: ${ response.statusText }`
					);
				}

				const arrayBuffer = await response.arrayBuffer();

				if ( cancelled ) {
					return;
				}

				// Parse and render the RTF document.
				const doc = new RTFJS.Document( arrayBuffer, {} );
				const elements = await doc.render();

				if ( cancelled ) {
					return;
				}

				// Convert DOM elements to HTML string for React rendering.
				setHtml( elementsToHtml( elements ) );
				setLoading( false );
			} catch ( loadError ) {
				if ( ! cancelled ) {
					setError(
						loadError.message ||
							__(
								'Failed to load document.',
								'pressprimer-assignment'
							)
					);
					setLoading( false );
				}
			}
		};

		loadRtf();

		return () => {
			cancelled = true;
		};
	}, [ url ] );

	if ( loading ) {
		return (
			<div
				style={ {
					display: 'flex',
					justifyContent: 'center',
					alignItems: 'center',
					minHeight: 300,
				} }
			>
				<Spin
					tip={ __( 'Loading document…', 'pressprimer-assignment' ) }
				/>
			</div>
		);
	}

	if ( error ) {
		return (
			<div style={ { padding: 40, textAlign: 'center' } }>
				<Alert
					message={ __(
						'Could not preview document',
						'pressprimer-assignment'
					) }
					description={ error }
					type="warning"
					showIcon
					style={ { marginBottom: 16 } }
				/>
				<Button
					type="primary"
					icon={ <DownloadOutlined /> }
					href={ appendNonce( url ) }
				>
					{ __( 'Download File', 'pressprimer-assignment' ) }
				</Button>
			</div>
		);
	}

	return (
		<div className="ppa-rtf-viewer">
			<div
				className="ppa-rtf-content"
				style={ {
					padding: '24px 32px',
					maxHeight: 'calc(100vh - 280px)',
					overflow: 'auto',
					lineHeight: 1.6,
					fontSize: 14,
				} }
				dangerouslySetInnerHTML={ { __html: html } }
			/>
		</div>
	);
};

export default RtfViewer;
