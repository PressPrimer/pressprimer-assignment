/**
 * PDF Viewer Component
 *
 * Renders PDF documents using PDF.js. Fetches the PDF from the
 * server via the download URL and renders each page onto canvas
 * elements for scrollable viewing.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Spin, Button, Space } from 'antd';
import {
	ZoomInOutlined,
	ZoomOutOutlined,
	FullscreenOutlined,
} from '@ant-design/icons';
import * as pdfjsLib from 'pdfjs-dist';
import { appendNonce } from '../../utils/nonce';

// Configure PDF.js worker.
// The worker file is copied to the build directory by webpack CopyPlugin.
// buildUrl is passed from PHP via wp_localize_script.
pdfjsLib.GlobalWorkerOptions.workerSrc =
	( window.pressprimerAssignmentGradingData?.buildUrl ||
		window.pressprimerAssignmentSubmissionDetailData?.buildUrl ||
		'' ) + 'pdf.worker.min.js'; // eslint-disable-line no-undef

/**
 * Default scale and zoom limits.
 */
const DEFAULT_SCALE = 1.2;
const MIN_SCALE = 0.5;
const MAX_SCALE = 3.0;
const ZOOM_STEP = 0.2;

/**
 * PdfViewer component
 *
 * @param {Object} props     Component props.
 * @param {string} props.url Download URL for the PDF file.
 * @return {JSX.Element} Rendered component.
 */
const PdfViewer = ( { url } ) => {
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ numPages, setNumPages ] = useState( 0 );
	const [ scale, setScale ] = useState( DEFAULT_SCALE );
	const containerRef = useRef( null );
	const pdfDocRef = useRef( null );
	const renderTasksRef = useRef( [] );

	/**
	 * Render all pages of the PDF at the current scale.
	 */
	const renderPages = useCallback( async ( pdfDoc, currentScale ) => {
		const container = containerRef.current;
		if ( ! container || ! pdfDoc ) {
			return;
		}

		// Cancel any existing render tasks.
		renderTasksRef.current.forEach( ( task ) => {
			if ( task && typeof task.cancel === 'function' ) {
				task.cancel();
			}
		} );
		renderTasksRef.current = [];

		// Clear existing canvases.
		container.innerHTML = '';

		const totalPages = pdfDoc.numPages;

		for ( let pageNum = 1; pageNum <= totalPages; pageNum++ ) {
			try {
				const page = await pdfDoc.getPage( pageNum );
				const viewport = page.getViewport( {
					scale: currentScale,
				} );

				// Create canvas for this page.
				const canvas = document.createElement( 'canvas' );
				const context = canvas.getContext( '2d' );
				canvas.height = viewport.height;
				canvas.width = viewport.width;
				canvas.style.display = 'block';
				canvas.style.margin = '0 auto 16px auto';
				canvas.style.boxShadow = '0 1px 4px rgba(0,0,0,0.15)';

				container.appendChild( canvas );

				const renderTask = page.render( {
					canvasContext: context,
					viewport,
				} );

				renderTasksRef.current.push( renderTask );
				await renderTask.promise;
			} catch ( renderError ) {
				// Ignore cancelled render tasks.
				if ( renderError?.name !== 'RenderingCancelledException' ) {
					// eslint-disable-next-line no-console
					console.error(
						`Failed to render page ${ pageNum }:`,
						renderError
					);
				}
			}
		}
	}, [] );

	/**
	 * Load the PDF document.
	 */
	useEffect( () => {
		let cancelled = false;

		const loadPdf = async () => {
			setLoading( true );
			setError( null );

			try {
				// Fetch the PDF as an ArrayBuffer via REST API.
				// Include the WP REST nonce for authentication.
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

				const loadingTask = pdfjsLib.getDocument( {
					data: arrayBuffer,
				} );
				const pdfDoc = await loadingTask.promise;

				if ( cancelled ) {
					pdfDoc.destroy();
					return;
				}

				pdfDocRef.current = pdfDoc;
				setNumPages( pdfDoc.numPages );
				setLoading( false );

				await renderPages( pdfDoc, scale );
			} catch ( loadError ) {
				if ( ! cancelled ) {
					setError(
						loadError.message ||
							__(
								'Failed to load PDF.',
								'pressprimer-assignment'
							)
					);
					setLoading( false );
				}
			}
		};

		loadPdf();

		return () => {
			cancelled = true;
			// Cancel pending render tasks.
			renderTasksRef.current.forEach( ( task ) => {
				if ( task && typeof task.cancel === 'function' ) {
					task.cancel();
				}
			} );
			// Destroy the PDF document.
			if ( pdfDocRef.current ) {
				pdfDocRef.current.destroy();
				pdfDocRef.current = null;
			}
		};
	}, [ url ] ); // eslint-disable-line react-hooks/exhaustive-deps

	/**
	 * Re-render when scale changes.
	 */
	useEffect( () => {
		if ( pdfDocRef.current && ! loading ) {
			renderPages( pdfDocRef.current, scale );
		}
	}, [ scale, loading, renderPages ] );

	const handleZoomIn = () => {
		setScale( ( prev ) => Math.min( prev + ZOOM_STEP, MAX_SCALE ) );
	};

	const handleZoomOut = () => {
		setScale( ( prev ) => Math.max( prev - ZOOM_STEP, MIN_SCALE ) );
	};

	const handleResetZoom = () => {
		setScale( DEFAULT_SCALE );
	};

	if ( error ) {
		return (
			<div style={ { padding: 40, textAlign: 'center', color: '#999' } }>
				<p>{ error }</p>
				<Button
					type="link"
					onClick={ () => window.open( appendNonce( url ) ) }
				>
					{ __(
						'Try opening in a new tab',
						'pressprimer-assignment'
					) }
				</Button>
			</div>
		);
	}

	return (
		<div className="ppa-pdf-viewer">
			{ /* Toolbar */ }
			<div
				style={ {
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
					padding: '6px 12px',
					borderBottom: '1px solid #f0f0f0',
					background: '#fafafa',
					fontSize: 13,
				} }
			>
				<span style={ { color: '#666' } }>
					{ numPages > 0 &&
						sprintf(
							/* translators: %d: number of pages */
							__( '%d pages', 'pressprimer-assignment' ),
							numPages
						) }
				</span>
				<Space size="small">
					<Button
						icon={ <ZoomOutOutlined /> }
						size="small"
						onClick={ handleZoomOut }
						disabled={ scale <= MIN_SCALE }
					/>
					<Button size="small" onClick={ handleResetZoom }>
						{ Math.round( scale * 100 ) }%
					</Button>
					<Button
						icon={ <ZoomInOutlined /> }
						size="small"
						onClick={ handleZoomIn }
						disabled={ scale >= MAX_SCALE }
					/>
					<Button
						icon={ <FullscreenOutlined /> }
						size="small"
						onClick={ () => window.open( appendNonce( url ) ) }
						title={ __(
							'Open in new tab',
							'pressprimer-assignment'
						) }
					/>
				</Space>
			</div>

			{ /* Pages container */ }
			<div
				style={ {
					overflow: 'auto',
					maxHeight: 'calc(100vh - 280px)',
					padding: 16,
					background: '#e8e8e8',
					position: 'relative',
				} }
			>
				{ loading && (
					<div
						style={ {
							display: 'flex',
							justifyContent: 'center',
							alignItems: 'center',
							minHeight: 300,
						} }
					>
						<Spin
							tip={ __(
								'Loading PDF…',
								'pressprimer-assignment'
							) }
						/>
					</div>
				) }
				<div ref={ containerRef } />
			</div>
		</div>
	);
};

export default PdfViewer;
