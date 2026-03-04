/* global DOMParser, Node */
/**
 * ODT Viewer Component
 *
 * Renders OpenDocument Text (.odt) files by extracting content.xml
 * from the ZIP archive using JSZip and parsing the ODF XML into HTML.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Spin, Button, Alert } from 'antd';
import { DownloadOutlined } from '@ant-design/icons';
import JSZip from 'jszip';
import { appendNonce } from '../../utils/nonce';

// ODF XML namespace URIs.
const NS_STYLE = 'urn:oasis:names:tc:opendocument:xmlns:style:1.0';
const NS_OFFICE = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';

/**
 * Escape HTML special characters.
 *
 * @param {string} text Raw text.
 * @return {string} Escaped text.
 */
const escapeHtml = ( text ) => {
	return text
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' );
};

/**
 * Escape an HTML attribute value.
 *
 * @param {string} text Raw attribute value.
 * @return {string} Escaped attribute value.
 */
const escapeAttr = ( text ) => {
	return text
		.replace( /&/g, '&amp;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#39;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );
};

/**
 * Parse ODF XML content into HTML string.
 *
 * Handles common ODF elements: paragraphs, headings, spans with
 * bold/italic/underline styling, lists, and line breaks.
 *
 * @param {string} xmlString The content.xml text from the ODT archive.
 * @return {string} HTML representation of the document.
 */
const parseOdfToHtml = ( xmlString ) => {
	const parser = new DOMParser();
	const doc = parser.parseFromString( xmlString, 'application/xml' );

	// Build a map of style names to their formatting properties.
	const styles = {};
	const styleNodes = doc.getElementsByTagNameNS( NS_STYLE, 'style' );
	for ( let i = 0; i < styleNodes.length; i++ ) {
		const styleNode = styleNodes[ i ];
		const styleName = styleNode.getAttribute( 'style:name' );
		const textProps = styleNode.getElementsByTagNameNS(
			NS_STYLE,
			'text-properties'
		)[ 0 ];

		if ( styleName && textProps ) {
			const fontWeight =
				textProps.getAttribute( 'fo:font-weight' ) === 'bold';
			const fontStyle =
				textProps.getAttribute( 'fo:font-style' ) === 'italic';
			const underline =
				textProps.getAttribute( 'style:text-underline-style' ) ===
				'solid';

			styles[ styleName ] = {
				bold: fontWeight,
				italic: fontStyle,
				underline,
			};
		}
	}

	/**
	 * Process inline content of an element (text nodes, spans, line breaks).
	 *
	 * @param {Element} element The ODF element to process.
	 * @return {string} HTML inline content.
	 */
	const processInline = ( element ) => {
		let html = '';

		for ( let i = 0; i < element.childNodes.length; i++ ) {
			const node = element.childNodes[ i ];

			if ( node.nodeType === Node.TEXT_NODE ) {
				html += escapeHtml( node.textContent );
				continue;
			}

			if ( node.nodeType !== Node.ELEMENT_NODE ) {
				continue;
			}

			const localName = node.localName;

			if ( localName === 'span' ) {
				const sName = node.getAttribute( 'text:style-name' );
				const style = sName ? styles[ sName ] : null;
				let content = processInline( node );

				if ( style?.bold ) {
					content = '<strong>' + content + '</strong>';
				}
				if ( style?.italic ) {
					content = '<em>' + content + '</em>';
				}
				if ( style?.underline ) {
					content = '<u>' + content + '</u>';
				}

				html += content;
			} else if ( localName === 'line-break' ) {
				html += '<br>';
			} else if ( localName === 'tab' ) {
				html += '&emsp;';
			} else if ( localName === 's' ) {
				// Repeated spaces.
				const count = parseInt(
					node.getAttribute( 'text:c' ) || '1',
					10
				);
				html += '&nbsp;'.repeat( count );
			} else if ( localName === 'a' ) {
				const href = node.getAttribute( 'xlink:href' ) || '#';
				html +=
					'<a href="' +
					escapeAttr( href ) +
					'" target="_blank" rel="noopener noreferrer">' +
					processInline( node ) +
					'</a>';
			} else {
				// Unknown inline element — extract text.
				html += processInline( node );
			}
		}

		return html;
	};

	/**
	 * Process ODF list elements.
	 *
	 * @param {Element} listNode The text:list element.
	 * @return {string} HTML list.
	 */
	const processList = ( listNode ) => {
		let items = '';

		const listItems = listNode.childNodes;
		for ( let i = 0; i < listItems.length; i++ ) {
			const item = listItems[ i ];
			if (
				item.nodeType !== Node.ELEMENT_NODE ||
				item.localName !== 'list-item'
			) {
				continue;
			}

			let itemContent = '';
			for ( let j = 0; j < item.childNodes.length; j++ ) {
				const child = item.childNodes[ j ];
				if ( child.nodeType !== Node.ELEMENT_NODE ) {
					continue;
				}
				if ( child.localName === 'p' ) {
					itemContent += processInline( child );
				} else if ( child.localName === 'list' ) {
					itemContent += processList( child );
				}
			}
			items += '<li>' + itemContent + '</li>';
		}

		return '<ul>' + items + '</ul>';
	};

	/**
	 * Process ODF table elements.
	 *
	 * @param {Element} tableNode The table:table element.
	 * @return {string} HTML table.
	 */
	const processTable = ( tableNode ) => {
		let rows = '';

		for ( let i = 0; i < tableNode.childNodes.length; i++ ) {
			const rowNode = tableNode.childNodes[ i ];
			if (
				rowNode.nodeType !== Node.ELEMENT_NODE ||
				rowNode.localName !== 'table-row'
			) {
				continue;
			}

			let cells = '';
			for ( let j = 0; j < rowNode.childNodes.length; j++ ) {
				const cellNode = rowNode.childNodes[ j ];
				if (
					cellNode.nodeType !== Node.ELEMENT_NODE ||
					cellNode.localName !== 'table-cell'
				) {
					continue;
				}

				let cellContent = '';
				for ( let k = 0; k < cellNode.childNodes.length; k++ ) {
					const child = cellNode.childNodes[ k ];
					if (
						child.nodeType === Node.ELEMENT_NODE &&
						child.localName === 'p'
					) {
						cellContent += processInline( child );
					}
				}
				cells += '<td>' + ( cellContent || '&nbsp;' ) + '</td>';
			}
			rows += '<tr>' + cells + '</tr>';
		}

		return '<table>' + rows + '</table>';
	};

	/**
	 * Process block-level elements from the document body.
	 *
	 * @param {Element} bodyElement The office:body > office:text element.
	 * @return {string} HTML block content.
	 */
	const processBody = ( bodyElement ) => {
		let html = '';

		for ( let i = 0; i < bodyElement.childNodes.length; i++ ) {
			const node = bodyElement.childNodes[ i ];

			if ( node.nodeType !== Node.ELEMENT_NODE ) {
				continue;
			}

			const localName = node.localName;

			if ( localName === 'p' ) {
				const content = processInline( node );
				html += '<p>' + ( content || '&nbsp;' ) + '</p>';
			} else if ( localName === 'h' ) {
				const level = parseInt(
					node.getAttribute( 'text:outline-level' ) || '1',
					10
				);
				const tag = 'h' + Math.min( Math.max( level, 1 ), 6 );
				html +=
					'<' + tag + '>' + processInline( node ) + '</' + tag + '>';
			} else if ( localName === 'list' ) {
				html += processList( node );
			} else if ( localName === 'table' ) {
				html += processTable( node );
			}
		}

		return html;
	};

	// Find the document body: office:document-content > office:body > office:text.
	const bodyElements = doc.getElementsByTagNameNS( NS_OFFICE, 'text' );

	if ( bodyElements.length === 0 ) {
		const noContent = __(
			'No content found in document.',
			'pressprimer-assignment'
		);
		return '<p>' + noContent + '</p>';
	}

	return processBody( bodyElements[ 0 ] );
};

/**
 * OdtViewer component
 *
 * @param {Object} props     Component props.
 * @param {string} props.url Download URL for the ODT file.
 * @return {JSX.Element} Rendered component.
 */
const OdtViewer = ( { url } ) => {
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ html, setHtml ] = useState( '' );

	useEffect( () => {
		let cancelled = false;

		const loadOdt = async () => {
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

				// Unzip the ODT and extract content.xml.
				const zip = await JSZip.loadAsync( arrayBuffer );
				const contentXml = zip.file( 'content.xml' );

				if ( ! contentXml ) {
					throw new Error(
						__(
							'Invalid ODT file: content.xml not found.',
							'pressprimer-assignment'
						)
					);
				}

				const xmlString = await contentXml.async( 'string' );

				if ( cancelled ) {
					return;
				}

				// Parse ODF XML into HTML.
				const renderedHtml = parseOdfToHtml( xmlString );
				setHtml( renderedHtml );
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

		loadOdt();

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
		<div className="ppa-odt-viewer">
			<div
				className="ppa-odt-content"
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

export default OdtViewer;
