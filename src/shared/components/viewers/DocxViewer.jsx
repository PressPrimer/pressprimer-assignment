/**
 * DOCX Viewer Component
 *
 * Renders DOCX documents using Mammoth.js. Fetches the file
 * as an ArrayBuffer and converts it to HTML for display.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Spin, Button, Alert } from 'antd';
import { DownloadOutlined } from '@ant-design/icons';
import mammoth from 'mammoth';
import { appendNonce } from '../../utils/nonce';

/**
 * DocxViewer component
 *
 * @param {Object} props     Component props.
 * @param {string} props.url Download URL for the DOCX file.
 * @return {JSX.Element} Rendered component.
 */
const DocxViewer = ( { url } ) => {
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ html, setHtml ] = useState( '' );
	const [ warnings, setWarnings ] = useState( [] );

	useEffect( () => {
		let cancelled = false;

		const loadDocx = async () => {
			setLoading( true );
			setError( null );

			try {
				// Fetch the DOCX as an ArrayBuffer.
				// Include WP REST nonce for authentication.
				const response = await window.fetch( url, {
					credentials: 'same-origin',
					headers: {
						'X-WP-Nonce':
							window.ppaGradingData?.nonce ||
							window.ppaSubmissionDetailData?.nonce ||
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

				// Convert DOCX to HTML using Mammoth.
				const result = await mammoth.convertToHtml(
					{ arrayBuffer },
					{
						styleMap: [
							"p[style-name='Heading 1'] => h1:fresh",
							"p[style-name='Heading 2'] => h2:fresh",
							"p[style-name='Heading 3'] => h3:fresh",
						],
					}
				);

				if ( cancelled ) {
					return;
				}

				setHtml( result.value );
				setWarnings(
					result.messages.filter( ( m ) => m.type === 'warning' )
				);
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

		loadDocx();

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
		<div className="ppa-docx-viewer">
			{ warnings.length > 0 && (
				<Alert
					message={ __(
						'Some formatting may not be displayed correctly.',
						'pressprimer-assignment'
					) }
					type="info"
					closable
					style={ { margin: '8px 12px' } }
				/>
			) }
			<div
				className="ppa-docx-content"
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

export default DocxViewer;
