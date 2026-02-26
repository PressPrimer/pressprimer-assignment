/**
 * Text Viewer Component
 *
 * Displays plain text files (TXT, RTF) by fetching the file
 * content and rendering it in a pre-formatted block.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Spin, Button, Alert } from 'antd';
import { DownloadOutlined } from '@ant-design/icons';
import { appendNonce } from '../../utils/nonce';

/**
 * TextViewer component
 *
 * @param {Object} props     Component props.
 * @param {string} props.url Download URL for the text file.
 * @return {JSX.Element} Rendered component.
 */
const TextViewer = ( { url } ) => {
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ content, setContent ] = useState( '' );

	useEffect( () => {
		let cancelled = false;

		const loadText = async () => {
			setLoading( true );
			setError( null );

			try {
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

				const text = await response.text();

				if ( cancelled ) {
					return;
				}

				setContent( text );
				setLoading( false );
			} catch ( loadError ) {
				if ( ! cancelled ) {
					setError(
						loadError.message ||
							__(
								'Failed to load file.',
								'pressprimer-assignment'
							)
					);
					setLoading( false );
				}
			}
		};

		loadText();

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
				<Spin tip={ __( 'Loading file…', 'pressprimer-assignment' ) } />
			</div>
		);
	}

	if ( error ) {
		return (
			<div style={ { padding: 40, textAlign: 'center' } }>
				<Alert
					message={ __(
						'Could not preview file',
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

	// Count lines for display.
	const lineCount = content.split( '\n' ).length;

	return (
		<div className="ppa-text-viewer">
			{ /* Info bar */ }
			<div
				style={ {
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
					padding: '6px 12px',
					borderBottom: '1px solid #f0f0f0',
					background: '#fafafa',
					fontSize: 13,
					color: '#666',
				} }
			>
				<span>
					{ sprintf(
						/* translators: 1: number of lines, 2: number of characters */
						__(
							'%1$d lines, %2$d characters',
							'pressprimer-assignment'
						),
						lineCount,
						content.length
					) }
				</span>
			</div>

			{ /* Content */ }
			<pre
				style={ {
					margin: 0,
					padding: '16px 24px',
					maxHeight: 'calc(100vh - 280px)',
					overflow: 'auto',
					whiteSpace: 'pre-wrap',
					wordBreak: 'break-word',
					fontFamily:
						'SFMono-Regular, Consolas, "Liberation Mono", Menlo, monospace',
					fontSize: 13,
					lineHeight: 1.6,
					background: '#fff',
				} }
			>
				{ content }
			</pre>
		</div>
	);
};

export default TextViewer;
