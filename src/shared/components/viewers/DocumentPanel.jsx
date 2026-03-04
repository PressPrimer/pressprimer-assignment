/**
 * Document Panel Component
 *
 * Tab container that renders the appropriate viewer for each
 * submission file based on its extension. Supports PDF, DOCX,
 * images, plain text, and text submissions.
 *
 * @package
 * @since 1.0.0
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Tabs, Button, Empty } from 'antd';
import {
	DownloadOutlined,
	FilePdfOutlined,
	FileWordOutlined,
	FileImageOutlined,
	FileTextOutlined,
	EditOutlined,
} from '@ant-design/icons';
import PdfViewer from './PdfViewer';
import DocxViewer from './DocxViewer';
import RtfViewer from './RtfViewer';
import OdtViewer from './OdtViewer';
import ImageViewer from './ImageViewer';
import TextViewer from './TextViewer';
import TextContentViewer from './TextContentViewer';
import { appendNonce } from '../../utils/nonce';

/**
 * File extension to icon mapping.
 */
const FILE_ICONS = {
	pdf: <FilePdfOutlined />,
	docx: <FileWordOutlined />,
	txt: <FileTextOutlined />,
	rtf: <FileWordOutlined />,
	odt: <FileWordOutlined />,
	jpg: <FileImageOutlined />,
	jpeg: <FileImageOutlined />,
	png: <FileImageOutlined />,
	gif: <FileImageOutlined />,
};

/**
 * DocumentPanel component
 *
 * @param {Object}      props             Component props.
 * @param {Array}       props.files       Array of file objects from REST API.
 * @param {string|null} props.textContent Text submission content (if any).
 * @param {number|null} props.wordCount   Word count for text submissions.
 * @return {JSX.Element} Rendered component.
 */
const DocumentPanel = ( {
	files = [],
	textContent = null,
	wordCount = null,
} ) => {
	// Build tab items: text content tab first (if present), then file tabs.
	const tabItems = [];

	if ( textContent ) {
		tabItems.push( {
			key: 'text-content',
			label: (
				<span>
					<EditOutlined style={ { marginRight: 4 } } />
					{ __( 'Text Submission', 'pressprimer-assignment' ) }
				</span>
			),
		} );
	}

	files.forEach( ( file ) => {
		const ext = file.file_extension?.toLowerCase() || '';
		tabItems.push( {
			key: String( file.id ),
			label: (
				<span>
					{ FILE_ICONS[ ext ] || <FileTextOutlined /> }
					<span style={ { marginLeft: 4 } }>
						{ file.original_filename }
					</span>
				</span>
			),
		} );
	} );

	// Default to first tab.
	const defaultKey = tabItems.length > 0 ? tabItems[ 0 ].key : null;
	const [ activeKey, setActiveKey ] = useState( defaultKey );

	/**
	 * Get the appropriate viewer for a file.
	 *
	 * @param {Object} file File object.
	 * @return {JSX.Element} Viewer component.
	 */
	const getViewer = ( file ) => {
		const ext = file.file_extension?.toLowerCase() || '';

		if ( ext === 'pdf' ) {
			return <PdfViewer url={ file.download_url } />;
		}

		if ( ext === 'docx' ) {
			return <DocxViewer url={ file.download_url } />;
		}

		if ( [ 'jpg', 'jpeg', 'png', 'gif' ].includes( ext ) ) {
			return (
				<ImageViewer
					url={ file.download_url }
					alt={ file.original_filename }
				/>
			);
		}

		if ( ext === 'rtf' ) {
			return <RtfViewer url={ file.download_url } />;
		}

		if ( ext === 'odt' ) {
			return <OdtViewer url={ file.download_url } />;
		}

		if ( ext === 'txt' ) {
			return <TextViewer url={ file.download_url } />;
		}

		// Unsupported file type - show download button.
		return (
			<div
				style={ {
					padding: 40,
					textAlign: 'center',
				} }
			>
				<p>
					{ __(
						'Preview not available for this file type.',
						'pressprimer-assignment'
					) }
				</p>
				<Button
					type="primary"
					icon={ <DownloadOutlined /> }
					href={ appendNonce( file.download_url ) }
				>
					{ __( 'Download File', 'pressprimer-assignment' ) }
				</Button>
			</div>
		);
	};

	// Get current file object for the download button.
	const currentFile =
		activeKey && activeKey !== 'text-content'
			? files.find( ( f ) => String( f.id ) === activeKey )
			: null;

	// No files and no text content.
	if ( tabItems.length === 0 ) {
		return (
			<div className="ppa-document-panel">
				<Empty
					description={ __(
						'No files submitted',
						'pressprimer-assignment'
					) }
					image={ Empty.PRESENTED_IMAGE_SIMPLE }
				/>
			</div>
		);
	}

	return (
		<div className="ppa-document-panel">
			{ /* File tabs (only show if more than one item) */ }
			{ tabItems.length > 1 && (
				<Tabs
					activeKey={ activeKey }
					onChange={ setActiveKey }
					items={ tabItems }
					size="small"
					style={ { marginBottom: 0 } }
					tabBarStyle={ { paddingLeft: 12, paddingRight: 12 } }
				/>
			) }

			{ /* File header with download */ }
			{ currentFile && (
				<div
					className="ppa-document-header"
					style={ {
						display: 'flex',
						justifyContent: 'space-between',
						alignItems: 'center',
						padding: '8px 12px',
						borderBottom: '1px solid #f0f0f0',
						background: '#fafafa',
					} }
				>
					<span style={ { fontWeight: 500, fontSize: 13 } }>
						{ currentFile.original_filename }
						<span
							style={ {
								color: '#999',
								marginLeft: 8,
								fontWeight: 400,
							} }
						>
							{ currentFile.formatted_size || '' }
						</span>
					</span>
					<Button
						icon={ <DownloadOutlined /> }
						href={ appendNonce( currentFile.download_url ) }
						size="small"
					>
						{ __( 'Download', 'pressprimer-assignment' ) }
					</Button>
				</div>
			) }

			{ /* Document viewer area */ }
			<div
				className="ppa-document-viewer"
				style={ {
					minHeight: 400,
					background: '#fff',
					overflow: 'auto',
				} }
			>
				{ activeKey === 'text-content' && (
					<TextContentViewer
						content={ textContent }
						wordCount={ wordCount }
					/>
				) }
				{ activeKey !== 'text-content' &&
					currentFile &&
					getViewer( currentFile ) }
			</div>
		</div>
	);
};

export default DocumentPanel;
