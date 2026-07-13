/**
 * PPTX Viewer Component
 *
 * Renders PowerPoint presentations one slide at a time via PptxViewJS
 * (MIT, pinned) — a Canvas-based renderer with its own layout engine,
 * so text fitting, gradients, tables, and charts reproduce far more
 * faithfully than DOM/CSS mapping could. Rendering is still approximate
 * for exotic constructs; the original file is always downloadable, so
 * grading is never blocked by fidelity.
 *
 * Sizing contract: PptxViewJS derives its logical render size from the
 * canvas element's explicit style.width/style.height (falling back to
 * bounding rect, then intrinsic attributes — which yields a tiny 300×150
 * default if the canvas is hidden during load). This component therefore
 * always keeps the canvas in layout (the loading spinner overlays it)
 * and sets BOTH style dimensions from the slide's real aspect ratio
 * before every render. Zoom and container resizes re-render at the new
 * logical size, so slides stay crisp at any zoom level (the library
 * multiplies by devicePixelRatio internally).
 *
 * Navigation: previous/next buttons, a slide counter, zoom controls
 * matching the PDF viewer's conventions, and left/right arrow keys.
 *
 * Security note: slides are drawn to a canvas — no student-supplied
 * markup ever enters the DOM.
 *
 * @package
 * @since 2.2.0
 */

import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Spin, Button, Space, Alert, Segmented, Typography } from 'antd';
import {
	LeftOutlined,
	RightOutlined,
	DownloadOutlined,
	ZoomInOutlined,
	ZoomOutOutlined,
} from '@ant-design/icons';
import { PPTXViewer } from 'pptxviewjs';
import JSZip from 'jszip';
import { appendNonce, appendQueryParam } from '../../utils/nonce';

const { Text } = Typography;

/**
 * DrawingML namespace containing <a:t> text nodes.
 */
const DRAWINGML_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';

/**
 * Extract per-slide text (paragraph lines + speaker notes) from the
 * PPTX archive, client-side. Mirrors the server-side extraction the
 * AI pipeline uses: walk <a:p> paragraphs collecting <a:t> descendants
 * from ppt/slides/slideN.xml and ppt/notesSlides/notesSlideN.xml, in
 * slide-number order. Powers the Text view, which is exact where the
 * visual render is approximate.
 *
 * @param {ArrayBuffer} arrayBuffer The fetched PPTX file.
 * @return {Promise<Array<{lines: string[], notes: string[]}>>} Per-slide text.
 */
const extractSlideTexts = async ( arrayBuffer ) => {
	const zip = await JSZip.loadAsync( arrayBuffer );
	const parser = new window.DOMParser();

	const parseEntry = async ( entry ) => {
		const xml = await entry.async( 'string' );
		const doc = parser.parseFromString( xml, 'application/xml' );
		const paragraphs = doc.getElementsByTagNameNS( DRAWINGML_NS, 'p' );
		const lines = [];

		for ( const paragraph of paragraphs ) {
			const nodes = paragraph.getElementsByTagNameNS( DRAWINGML_NS, 't' );
			const line = Array.from( nodes )
				.map( ( node ) => node.textContent )
				.join( '' )
				.trim();
			if ( line ) {
				lines.push( line );
			}
		}

		return lines;
	};

	const slideNumbers = [];
	zip.forEach( ( path ) => {
		const match = path.match( /^ppt\/slides\/slide(\d+)\.xml$/ );
		if ( match ) {
			slideNumbers.push( parseInt( match[ 1 ], 10 ) );
		}
	} );
	slideNumbers.sort( ( a, b ) => a - b );

	const slides = [];
	for ( const number of slideNumbers ) {
		const slideEntry = zip.file( `ppt/slides/slide${ number }.xml` );
		const notesEntry = zip.file(
			`ppt/notesSlides/notesSlide${ number }.xml`
		);

		slides.push( {
			lines: slideEntry ? await parseEntry( slideEntry ) : [],
			notes: notesEntry ? await parseEntry( notesEntry ) : [],
		} );
	}

	return slides;
};

/**
 * Zoom limits, matching the PDF viewer's conventions.
 */
const MIN_ZOOM = 0.5;
const MAX_ZOOM = 3.0;
const ZOOM_STEP = 0.2;

/**
 * Default slide aspect ratio (16:9) when the deck's real size is
 * unavailable.
 */
const DEFAULT_RATIO = 9 / 16;

/**
 * Get the slide height/width ratio from a loaded viewer.
 *
 * getSlideDimensions() is not part of the public typings, so it is read
 * defensively — the library exposes it on the internal processor with
 * either EMU ({cx, cy}) or unit ({width, height}) shapes depending on
 * the code path.
 *
 * @param {Object} viewer Loaded PPTXViewer instance.
 * @return {number} height / width ratio.
 */
const getSlideRatio = ( viewer ) => {
	try {
		const dims = viewer?.processor?.getSlideDimensions?.();
		if ( dims ) {
			if ( dims.cx > 0 && dims.cy > 0 ) {
				return dims.cy / dims.cx;
			}
			if ( dims.width > 0 && dims.height > 0 ) {
				return dims.height / dims.width;
			}
		}
	} catch ( dimsError ) {
		// Fall through to the default ratio.
	}
	return DEFAULT_RATIO;
};

/**
 * PptxViewer component
 *
 * @param {Object} props     Component props.
 * @param {string} props.url Download URL for the PPTX file.
 * @return {JSX.Element} Rendered component.
 */
const PptxViewer = ( { url } ) => {
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ ready, setReady ] = useState( false );
	const [ current, setCurrent ] = useState( 0 );
	const [ total, setTotal ] = useState( 0 );
	const [ zoom, setZoom ] = useState( 1 );
	const [ containerWidth, setContainerWidth ] = useState( 0 );
	const [ viewMode, setViewMode ] = useState( 'slides' );
	const [ textSlides, setTextSlides ] = useState( [] );
	const canvasRef = useRef( null );
	const viewerRef = useRef( null );
	const stageRef = useRef( null );
	const currentRef = useRef( 0 );
	const ratioRef = useRef( DEFAULT_RATIO );

	// Track container width from mount (the stage is always in layout).
	useEffect( () => {
		const measure = () => {
			if ( stageRef.current ) {
				setContainerWidth( stageRef.current.clientWidth );
			}
		};

		measure();
		window.addEventListener( 'resize', measure );

		return () => window.removeEventListener( 'resize', measure );
	}, [] );

	/**
	 * Size the canvas per the library's contract and render a slide.
	 *
	 * @param {number} index Slide index to show.
	 */
	const sizeAndRender = useCallback(
		async ( index ) => {
			const viewer = viewerRef.current;
			const canvas = canvasRef.current;
			const stage = stageRef.current;

			if ( ! viewer || ! canvas || ! stage ) {
				return;
			}

			const clamped = Math.max(
				0,
				Math.min( viewer.getSlideCount() - 1, index )
			);

			// Explicit style dimensions are the library's primary sizing
			// input; setting both prevents any fallback to the canvas's
			// intrinsic 300×150 default.
			const width = Math.max(
				120,
				( stage.clientWidth - 32 ) * zoom || 400
			);
			const height = width * ratioRef.current;
			canvas.style.width = `${ Math.round( width ) }px`;
			canvas.style.height = `${ Math.round( height ) }px`;

			try {
				await viewer.renderSlide( clamped, canvas, {
					quality: 'high',
				} );
				currentRef.current = clamped;
				setCurrent( clamped );
			} catch ( renderError ) {
				// A single slide failing to draw is non-fatal; the
				// counter and navigation stay usable.
			}
		},
		[ zoom ]
	);

	// Load the presentation.
	useEffect( () => {
		let cancelled = false;

		const loadPptx = async () => {
			setLoading( true );
			setError( null );
			setReady( false );

			try {
				// Same nonce'd fetch as the PDF viewer — different surfaces
				// localize different globals.
				const response = await window.fetch( url, {
					credentials: 'same-origin',
					headers: {
						'X-WP-Nonce':
							window.pressprimerAssignmentGradingData?.nonce ||
							window.pressprimerAssignmentSubmissionDetailData
								?.nonce ||
							window.pressprimerAssignmentFrontendSubmission
								?.restNonce ||
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

				const viewer = new PPTXViewer( {
					canvas: canvasRef.current,
					backgroundColor: '#ffffff',
				} );

				await viewer.loadFile( arrayBuffer );

				if ( cancelled ) {
					viewer.destroy();
					return;
				}

				viewerRef.current = viewer;
				ratioRef.current = getSlideRatio( viewer );
				currentRef.current = 0;
				setTotal( viewer.getSlideCount() );
				setCurrent( 0 );
				setZoom( 1 );
				setLoading( false );
				setReady( true );

				// Extract the Text-view content in the background; the
				// slides view never waits on it. Failure just leaves the
				// Text toggle showing an empty-slide message.
				extractSlideTexts( arrayBuffer )
					.then( ( slides ) => {
						if ( ! cancelled ) {
							setTextSlides( slides );
						}
					} )
					.catch( () => {} );
			} catch ( loadError ) {
				if ( ! cancelled ) {
					setError( true );
					setLoading( false );
				}
			}
		};

		loadPptx();

		return () => {
			cancelled = true;
			if ( viewerRef.current ) {
				viewerRef.current.destroy();
				viewerRef.current = null;
			}
			setReady( false );
		};
	}, [ url ] );

	// (Re)render whenever the viewer becomes ready, the geometry inputs
	// change (zoom, container width), or the user returns from Text view.
	useEffect( () => {
		if ( ready && viewMode === 'slides' ) {
			sizeAndRender( currentRef.current );
		}
	}, [ ready, zoom, containerWidth, viewMode, sizeAndRender ] );

	/**
	 * Navigate to a slide index — renders in slides mode, or just moves
	 * the counter in Text view (both views share position).
	 */
	const goTo = useCallback(
		( index ) => {
			if ( viewMode === 'text' ) {
				const clamped = Math.max( 0, Math.min( total - 1, index ) );
				currentRef.current = clamped;
				setCurrent( clamped );
				return;
			}
			sizeAndRender( index );
		},
		[ viewMode, total, sizeAndRender ]
	);

	const goPrev = useCallback( () => {
		goTo( currentRef.current - 1 );
	}, [ goTo ] );

	const goNext = useCallback( () => {
		goTo( currentRef.current + 1 );
	}, [ goTo ] );

	/**
	 * Arrow-key navigation on the focusable viewer wrapper.
	 *
	 * @param {Object} event Keyboard event.
	 */
	const handleKeyDown = ( event ) => {
		if ( event.key === 'ArrowLeft' ) {
			event.preventDefault();
			goPrev();
		} else if ( event.key === 'ArrowRight' ) {
			event.preventDefault();
			goNext();
		}
	};

	// Download fallback: rendering failures never block grading.
	if ( error ) {
		return (
			<div style={ { padding: 24 } }>
				<Alert
					type="warning"
					showIcon
					message={ __(
						'This presentation could not be previewed.',
						'pressprimer-assignment'
					) }
					description={ __(
						'The file may use features this viewer does not support. Download it to view in PowerPoint or another application.',
						'pressprimer-assignment'
					) }
					action={
						<Button
							icon={ <DownloadOutlined /> }
							href={ appendNonce(
								appendQueryParam( url, 'download=1' )
							) }
						>
							{ __( 'Download', 'pressprimer-assignment' ) }
						</Button>
					}
				/>
			</div>
		);
	}

	return (
		/*
		 * Arrow-key navigation is scoped to the focused viewer on purpose:
		 * a document-level listener would hijack arrow keys from the
		 * grading form's other inputs. The toolbar buttons provide the
		 * fully accessible alternative for the same actions.
		 */
		/* eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions */
		<div
			className="ppa-pptx-viewer"
			role="region"
			aria-label={ __( 'Presentation viewer', 'pressprimer-assignment' ) }
			tabIndex={ 0 }
			onKeyDown={ handleKeyDown }
			style={ { outline: 'none' } }
		>
			{ /* Toolbar: counter + navigation + zoom, matching the PDF toolbar. */ }
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
					{ total > 0 &&
						sprintf(
							/* translators: 1: current slide number, 2: total slides */
							__(
								'Slide %1$d of %2$d',
								'pressprimer-assignment'
							),
							current + 1,
							total
						) }
				</span>
				<Space size="small">
					<Segmented
						size="small"
						value={ viewMode }
						onChange={ setViewMode }
						disabled={ loading }
						options={ [
							{
								value: 'slides',
								label: __( 'Slides', 'pressprimer-assignment' ),
							},
							{
								value: 'text',
								label: __( 'Text', 'pressprimer-assignment' ),
							},
						] }
					/>
					<Button
						icon={ <LeftOutlined /> }
						size="small"
						onClick={ goPrev }
						disabled={ loading || current === 0 }
						aria-label={ __(
							'Previous slide',
							'pressprimer-assignment'
						) }
					/>
					<Button
						icon={ <RightOutlined /> }
						size="small"
						onClick={ goNext }
						disabled={ loading || current >= total - 1 }
						aria-label={ __(
							'Next slide',
							'pressprimer-assignment'
						) }
					/>
					{ /* Zoom applies to the slide canvas only. In Text view
					    the controls stay in place but disabled, so the
					    toolbar never reflows when switching views. */ }
					<Button
						icon={ <ZoomOutOutlined /> }
						size="small"
						onClick={ () =>
							setZoom( ( prev ) =>
								Math.max( prev - ZOOM_STEP, MIN_ZOOM )
							)
						}
						disabled={
							loading || viewMode === 'text' || zoom <= MIN_ZOOM
						}
						aria-label={ __(
							'Zoom out',
							'pressprimer-assignment'
						) }
					/>
					<Button
						size="small"
						onClick={ () => setZoom( 1 ) }
						disabled={ loading || viewMode === 'text' }
					>
						{ Math.round( zoom * 100 ) }%
					</Button>
					<Button
						icon={ <ZoomInOutlined /> }
						size="small"
						onClick={ () =>
							setZoom( ( prev ) =>
								Math.min( prev + ZOOM_STEP, MAX_ZOOM )
							)
						}
						disabled={
							loading || viewMode === 'text' || zoom >= MAX_ZOOM
						}
						aria-label={ __( 'Zoom in', 'pressprimer-assignment' ) }
					/>
					<Button
						icon={ <DownloadOutlined /> }
						size="small"
						onClick={ () =>
							window.open(
								appendNonce(
									appendQueryParam( url, 'download=1' )
								)
							)
						}
						title={ __(
							'Download the original file',
							'pressprimer-assignment'
						) }
					/>
				</Space>
			</div>

			{ /* Slide stage — the canvas stays in layout during loading so
			    the library never measures a hidden (collapsed) element;
			    the spinner overlays it instead. */ }
			<div
				ref={ stageRef }
				style={ {
					position: 'relative',
					background: '#e8e8e8',
					padding: 16,
					overflow: 'auto',
					maxHeight: 'calc(100vh - 280px)',
					minHeight: 200,
					textAlign: 'center',
				} }
			>
				{ loading && (
					<div
						style={ {
							position: 'absolute',
							inset: 0,
							display: 'flex',
							justifyContent: 'center',
							alignItems: 'center',
							minHeight: 200,
							zIndex: 1,
						} }
					>
						<Spin
							tip={ __(
								'Loading presentation…',
								'pressprimer-assignment'
							) }
						/>
					</div>
				) }
				<canvas
					ref={ canvasRef }
					style={ {
						display:
							viewMode === 'slides' ? 'inline-block' : 'none',
						visibility: ready ? 'visible' : 'hidden',
						boxShadow: '0 1px 4px rgba(0,0,0,0.15)',
						background: '#fff',
					} }
				/>

				{ /* Text view — exact wording from the file itself, with
				    real list bullets and speaker notes. */ }
				{ viewMode === 'text' && ! loading && (
					<div
						style={ {
							background: '#fff',
							textAlign: 'left',
							maxWidth: 700,
							margin: '0 auto',
							padding: '20px 24px',
							boxShadow: '0 1px 4px rgba(0,0,0,0.15)',
							minHeight: 200,
						} }
					>
						{ textSlides[ current ] &&
						textSlides[ current ].lines.length > 0 ? (
							<ul
								style={ {
									margin: 0,
									paddingLeft: 20,
									lineHeight: 1.6,
								} }
							>
								{ textSlides[ current ].lines.map(
									( line, index ) => (
										<li key={ index }>{ line }</li>
									)
								) }
							</ul>
						) : (
							<Text type="secondary">
								{ __(
									'This slide has no text content.',
									'pressprimer-assignment'
								) }
							</Text>
						) }

						{ textSlides[ current ] &&
							textSlides[ current ].notes.length > 0 && (
								<div
									style={ {
										marginTop: 16,
										paddingTop: 12,
										borderTop: '1px solid #f0f0f0',
									} }
								>
									<Text strong>
										{ __(
											'Speaker notes',
											'pressprimer-assignment'
										) }
									</Text>
									<ul
										style={ {
											margin: '8px 0 0',
											paddingLeft: 20,
											lineHeight: 1.6,
											color: '#50575e',
										} }
									>
										{ textSlides[ current ].notes.map(
											( line, index ) => (
												<li key={ index }>{ line }</li>
											)
										) }
									</ul>
								</div>
							) }
					</div>
				) }
			</div>

			{ /* Fidelity caveat — always visible, with the escape hatches. */ }
			<div
				style={ {
					padding: '6px 12px',
					borderTop: '1px solid #f0f0f0',
					background: '#fafafa',
					fontSize: 12,
					color: '#8c8c8c',
				} }
			>
				{ __(
					'Slide rendering is approximate — fonts, layout, and effects may differ from PowerPoint. Switch to Text view for exact wording, or',
					'pressprimer-assignment'
				) }{ ' ' }
				<a
					href={ appendNonce(
						appendQueryParam( url, 'download=1' )
					) }
				>
					{ __(
						'download the original file',
						'pressprimer-assignment'
					) }
				</a>
				.
			</div>
		</div>
	);
};

export default PptxViewer;
