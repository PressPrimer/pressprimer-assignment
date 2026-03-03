/**
 * Text Content Viewer Component
 *
 * Displays text submissions (as opposed to file submissions).
 * Renders the HTML content submitted through the text editor
 * with word count and metadata.
 *
 * @package
 * @since 1.0.0
 */

import { useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Empty, Button } from 'antd';
import { FileTextOutlined, DownloadOutlined } from '@ant-design/icons';

/**
 * TextContentViewer component
 *
 * @param {Object}      props           Component props.
 * @param {string|null} props.content   HTML content of the text submission.
 * @param {number|null} props.wordCount Word count of the submission.
 * @return {JSX.Element} Rendered component.
 */
const TextContentViewer = ( { content = null, wordCount = null } ) => {
	/**
	 * Download the text content as a .txt file.
	 *
	 * Strips HTML tags and converts entities to produce plain text.
	 */
	const handleDownload = useCallback( () => {
		if ( ! content ) {
			return;
		}

		// Convert HTML to plain text, preserving paragraph breaks.
		// Replace block-level closing tags with newlines before
		// extracting textContent so paragraph spacing is retained.
		let html = content;
		html = html.replace( /<\/p>\s*<p[^>]*>/gi, '\n\n' );
		html = html.replace( /<br\s*\/?>/gi, '\n' );
		html = html.replace( /<\/(?:p|div|h[1-6]|li|blockquote)>/gi, '\n' );
		html = html.replace( /<\/(?:ul|ol|table|tr)>/gi, '\n' );

		const tempDiv = document.createElement( 'div' );
		tempDiv.innerHTML = html;
		const plainText = (
			tempDiv.textContent ||
			tempDiv.innerText ||
			''
		).trim();

		const blob = new Blob( [ plainText ], { type: 'text/plain' } );
		const url = URL.createObjectURL( blob );
		const link = document.createElement( 'a' );
		link.href = url;
		link.download = 'submission.txt';
		document.body.appendChild( link );
		link.click();
		document.body.removeChild( link );
		URL.revokeObjectURL( url );
	}, [ content ] );

	if ( ! content ) {
		return (
			<Empty
				description={ __(
					'No text content submitted',
					'pressprimer-assignment'
				) }
				image={ Empty.PRESENTED_IMAGE_SIMPLE }
				style={ { padding: 40 } }
			/>
		);
	}

	return (
		<div className="ppa-text-content-viewer">
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
					<FileTextOutlined style={ { marginRight: 4 } } />
					{ __( 'Text Submission', 'pressprimer-assignment' ) }
				</span>
				<span>
					{ wordCount !== null &&
						sprintf(
							/* translators: %s: number of words */
							__( '%s words', 'pressprimer-assignment' ),
							Number( wordCount ).toLocaleString()
						) }
					<Button
						icon={ <DownloadOutlined /> }
						size="small"
						onClick={ handleDownload }
						style={ { marginLeft: 8 } }
					>
						{ __( 'Download TXT', 'pressprimer-assignment' ) }
					</Button>
				</span>
			</div>

			{ /* Content area */ }
			<div
				className="ppa-text-content-body"
				style={ {
					padding: '24px 32px',
					maxHeight: 'calc(100vh - 280px)',
					overflow: 'auto',
					lineHeight: 1.8,
					fontSize: 14,
				} }
				dangerouslySetInnerHTML={ { __html: content } }
			/>
		</div>
	);
};

export default TextContentViewer;
