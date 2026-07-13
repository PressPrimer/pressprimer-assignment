/**
 * PPTX Viewer Component
 *
 * Renders PowerPoint presentations one slide at a time using the
 * pptxtojson parser (MIT) and an in-house renderer: absolutely
 * positioned text, shapes, and images scaled to the container width.
 * Rendering is deliberately approximate — animations, transitions,
 * SmartArt, charts, and embedded media are not reproduced; the original
 * file is always downloadable, so grading is never blocked by fidelity.
 *
 * Navigation: previous/next buttons, a slide counter, and left/right
 * arrow keys.
 *
 * Security note: element content arrives as parser-generated HTML built
 * from a student-supplied file. It is sanitized through an allowlist
 * (tags + style-only attributes, style values with url()/expression()
 * stripped) before rendering, and images render from data: URIs only.
 *
 * @package
 * @since 2.2.0
 */

import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Spin, Button, Space, Alert } from 'antd';
import {
	LeftOutlined,
	RightOutlined,
	DownloadOutlined,
} from '@ant-design/icons';
import { parse } from 'pptxtojson';
import { appendNonce, appendQueryParam } from '../../utils/nonce';

/**
 * Tags the sanitizer allows through (uppercase, per DOM nodeName).
 */
const ALLOWED_TAGS = [
	'P',
	'DIV',
	'SPAN',
	'BR',
	'STRONG',
	'B',
	'EM',
	'I',
	'U',
	'S',
	'SUB',
	'SUP',
	'UL',
	'OL',
	'LI',
];

/**
 * Sanitize parser-generated HTML from an untrusted PPTX file.
 *
 * Rebuilds the fragment keeping only allowlisted tags, with `style` as
 * the only surviving attribute — and only when its value is free of
 * url(), expression(), and protocol tricks. Everything else is reduced
 * to its text content.
 *
 * @param {string} html Parser-generated HTML string.
 * @return {string} Sanitized HTML string.
 */
const sanitizeContent = ( html ) => {
	if ( ! html || typeof html !== 'string' ) {
		return '';
	}

	const doc = new window.DOMParser().parseFromString(
		`<div>${ html }</div>`,
		'text/html'
	);

	const sanitizeNode = ( node ) => {
		if ( node.nodeType === window.Node.TEXT_NODE ) {
			return document.createTextNode( node.textContent );
		}

		if ( node.nodeType !== window.Node.ELEMENT_NODE ) {
			return null;
		}

		const isAllowed = ALLOWED_TAGS.includes( node.nodeName );

		// Disallowed elements contribute their children only (their own
		// tag and attributes are dropped).
		const target = isAllowed
			? document.createElement( node.nodeName.toLowerCase() )
			: document.createDocumentFragment();

		if ( isAllowed ) {
			const style = node.getAttribute( 'style' );
			if (
				style &&
				! /url\s*\(|expression\s*\(|javascript:|@import/i.test( style )
			) {
				target.setAttribute( 'style', style );
			}
		}

		node.childNodes.forEach( ( child ) => {
			const clean = sanitizeNode( child );
			if ( clean ) {
				target.appendChild( clean );
			}
		} );

		return target;
	};

	const root = sanitizeNode( doc.body.firstChild );
	const wrapper = document.createElement( 'div' );
	if ( root ) {
		wrapper.appendChild( root );
	}

	return wrapper.innerHTML;
};

/**
 * Vertical alignment map: pptxtojson vAlign → flexbox justify-content.
 */
const V_ALIGN_MAP = {
	mid: 'center',
	down: 'flex-end',
};

/**
 * Get a CSS background color from a pptxtojson fill object.
 *
 * Only solid color fills are reproduced; gradient/pattern/image fills
 * fall back to transparent (approximate fidelity by design).
 *
 * @param {Object|undefined} fill Fill descriptor.
 * @return {string} CSS color or 'transparent'.
 */
const fillToColor = ( fill ) => {
	if ( fill && fill.type === 'color' && typeof fill.value === 'string' ) {
		return fill.value;
	}
	return 'transparent';
};

/**
 * Render one parsed slide element.
 *
 * @param {Object} element Parsed element.
 * @param {number} index   Element index (render order = z-order).
 * @return {JSX.Element|null} Rendered element or null for unsupported types.
 */
const renderElement = ( element, index ) => {
	const base = {
		position: 'absolute',
		left: element.left,
		top: element.top,
		width: element.width,
		height: element.height,
		transform: element.rotate
			? `rotate(${ element.rotate }deg)`
			: undefined,
	};

	if ( element.type === 'image' ) {
		// Only inline data URIs from the parser are trusted as sources.
		if (
			typeof element.src !== 'string' ||
			! element.src.startsWith( 'data:image/' )
		) {
			return null;
		}

		return (
			<img
				key={ index }
				src={ element.src }
				alt=""
				style={ {
					...base,
					objectFit: 'contain',
				} }
			/>
		);
	}

	if ( element.type === 'shape' || element.type === 'text' ) {
		const isEllipse = element.shapType === 'ellipse';

		return (
			<div
				key={ index }
				style={ {
					...base,
					backgroundColor: fillToColor( element.fill ),
					border: element.borderWidth
						? `${ element.borderWidth }px ${
								element.borderType || 'solid'
						  } ${ element.borderColor || 'transparent' }`
						: undefined,
					borderRadius: isEllipse ? '50%' : element.borderRadius,
					display: 'flex',
					flexDirection: 'column',
					justifyContent:
						V_ALIGN_MAP[ element.vAlign ] || 'flex-start',
					overflow: 'hidden',
				} }
				// eslint-disable-next-line react/no-danger
				dangerouslySetInnerHTML={ {
					__html: sanitizeContent( element.content ),
				} }
			/>
		);
	}

	// Charts, tables, video, audio, SmartArt: not reproduced (documented
	// approximate fidelity — the original file is always downloadable).
	return null;
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
	const [ presentation, setPresentation ] = useState( null );
	const [ current, setCurrent ] = useState( 0 );
	const [ containerWidth, setContainerWidth ] = useState( 0 );
	const stageRef = useRef( null );

	// Load and parse the presentation.
	useEffect( () => {
		let cancelled = false;

		const loadPptx = async () => {
			setLoading( true );
			setError( null );

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

				const json = await parse( arrayBuffer, {
					imageMode: 'base64',
					videoMode: 'none',
					audioMode: 'none',
				} );

				if ( cancelled ) {
					return;
				}

				if ( ! json || ! json.slides || ! json.slides.length ) {
					throw new Error(
						__(
							'No slides found in this presentation.',
							'pressprimer-assignment'
						)
					);
				}

				setPresentation( json );
				setCurrent( 0 );
				setLoading( false );
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
		};
	}, [ url ] );

	// Track container width for slide scaling (initial + resize).
	useEffect( () => {
		const measure = () => {
			if ( stageRef.current ) {
				setContainerWidth( stageRef.current.clientWidth );
			}
		};

		measure();
		window.addEventListener( 'resize', measure );

		return () => window.removeEventListener( 'resize', measure );
	}, [ loading ] );

	const total = presentation ? presentation.slides.length : 0;

	const goPrev = useCallback( () => {
		setCurrent( ( prev ) => Math.max( 0, prev - 1 ) );
	}, [] );

	const goNext = useCallback( () => {
		setCurrent( ( prev ) => Math.min( total - 1, prev + 1 ) );
	}, [ total ] );

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
					tip={ __(
						'Loading presentation…',
						'pressprimer-assignment'
					) }
				/>
			</div>
		);
	}

	const slide = presentation.slides[ current ];
	const slideWidth = presentation.size?.width || 960;
	const slideHeight = presentation.size?.height || 540;
	const scale =
		containerWidth > 0 ? Math.min( 1.5, containerWidth / slideWidth ) : 1;

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
			{ /* Toolbar: counter + navigation, matching the PDF toolbar. */ }
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
					{ sprintf(
						/* translators: 1: current slide number, 2: total slides */
						__( 'Slide %1$d of %2$d', 'pressprimer-assignment' ),
						current + 1,
						total
					) }
				</span>
				<Space size="small">
					<Button
						icon={ <LeftOutlined /> }
						size="small"
						onClick={ goPrev }
						disabled={ current === 0 }
						aria-label={ __(
							'Previous slide',
							'pressprimer-assignment'
						) }
					/>
					<Button
						icon={ <RightOutlined /> }
						size="small"
						onClick={ goNext }
						disabled={ current >= total - 1 }
						aria-label={ __(
							'Next slide',
							'pressprimer-assignment'
						) }
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

			{ /* Slide stage */ }
			<div
				ref={ stageRef }
				style={ {
					background: '#e8e8e8',
					padding: 16,
					overflow: 'auto',
					maxHeight: 'calc(100vh - 280px)',
				} }
			>
				<div
					style={ {
						width: slideWidth * scale,
						height: slideHeight * scale,
						margin: '0 auto',
						boxShadow: '0 1px 4px rgba(0,0,0,0.15)',
						overflow: 'hidden',
					} }
				>
					<div
						dir="ltr"
						style={ {
							width: slideWidth,
							height: slideHeight,
							transform: `scale(${ scale })`,
							transformOrigin: 'top left',
							position: 'relative',
							backgroundColor:
								fillToColor( slide.fill ) === 'transparent'
									? '#ffffff'
									: fillToColor( slide.fill ),
						} }
					>
						{ ( slide.layoutElements || [] ).map(
							( element, index ) =>
								renderElement( element, `layout-${ index }` )
						) }
						{ ( slide.elements || [] ).map( ( element, index ) =>
							renderElement( element, index )
						) }
					</div>
				</div>
			</div>
		</div>
	);
};

export default PptxViewer;
