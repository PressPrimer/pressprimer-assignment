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

import { __, sprintf } from '@wordpress/i18n';
import { Empty } from 'antd';
import { FileTextOutlined } from '@ant-design/icons';

/**
 * TextContentViewer component
 *
 * @param {Object}      props           Component props.
 * @param {string|null} props.content   HTML content of the text submission.
 * @param {number|null} props.wordCount Word count of the submission.
 * @return {JSX.Element} Rendered component.
 */
const TextContentViewer = ( { content = null, wordCount = null } ) => {
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
				{ wordCount !== null && (
					<span>
						{ sprintf(
							/* translators: %s: number of words */
							__( '%s words', 'pressprimer-assignment' ),
							Number( wordCount ).toLocaleString()
						) }
					</span>
				) }
			</div>

			{ /* Content area */ }
			<div
				className="ppa-text-content-body"
				style={ {
					padding: '24px 32px',
					maxHeight: 'calc(100vh - 280px)',
					overflow: 'auto',
					lineHeight: 1.8,
					fontSize: 15,
				} }
				dangerouslySetInnerHTML={ { __html: content } }
			/>
		</div>
	);
};

export default TextContentViewer;
