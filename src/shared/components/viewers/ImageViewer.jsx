/**
 * Image Viewer Component
 *
 * Displays image files (JPG, PNG, GIF) with zoom controls.
 * Fetches the image via the download URL as a blob and
 * creates an object URL for rendering.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Spin, Button, Space } from 'antd';
import {
	ZoomInOutlined,
	ZoomOutOutlined,
	FullscreenOutlined,
} from '@ant-design/icons';

/**
 * Zoom limits and step.
 */
const DEFAULT_SCALE = 1.0;
const MIN_SCALE = 0.25;
const MAX_SCALE = 4.0;
const ZOOM_STEP = 0.25;

/**
 * ImageViewer component
 *
 * @param {Object} props     Component props.
 * @param {string} props.url Download URL for the image file.
 * @param {string} props.alt Alt text for the image.
 * @return {JSX.Element} Rendered component.
 */
const ImageViewer = ( { url, alt = '' } ) => {
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ objectUrl, setObjectUrl ] = useState( null );
	const [ scale, setScale ] = useState( DEFAULT_SCALE );
	const [ naturalSize, setNaturalSize ] = useState( {
		width: 0,
		height: 0,
	} );
	const imgRef = useRef( null );

	useEffect( () => {
		let cancelled = false;
		let blobUrl = null;

		const loadImage = async () => {
			setLoading( true );
			setError( null );

			try {
				// Fetch image as blob via REST API.
				// Include the WP REST nonce for authentication.
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

				const blob = await response.blob();

				if ( cancelled ) {
					return;
				}

				blobUrl = URL.createObjectURL( blob );
				setObjectUrl( blobUrl );
			} catch ( loadError ) {
				if ( ! cancelled ) {
					setError(
						loadError.message ||
							__(
								'Failed to load image.',
								'pressprimer-assignment'
							)
					);
					setLoading( false );
				}
			}
		};

		loadImage();

		return () => {
			cancelled = true;
			if ( blobUrl ) {
				URL.revokeObjectURL( blobUrl );
			}
		};
	}, [ url ] );

	const handleImageLoad = () => {
		if ( imgRef.current ) {
			setNaturalSize( {
				width: imgRef.current.naturalWidth,
				height: imgRef.current.naturalHeight,
			} );
		}
		setLoading( false );
	};

	const handleImageError = () => {
		setError( __( 'Failed to display image.', 'pressprimer-assignment' ) );
		setLoading( false );
	};

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
			</div>
		);
	}

	return (
		<div className="ppa-image-viewer">
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
					{ naturalSize.width > 0 &&
						`${ naturalSize.width } \u00d7 ${ naturalSize.height }` }
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
						onClick={ () => window.open( url ) }
						title={ __(
							'Open in new tab',
							'pressprimer-assignment'
						) }
					/>
				</Space>
			</div>

			{ /* Image container */ }
			<div
				style={ {
					overflow: 'auto',
					maxHeight: 'calc(100vh - 280px)',
					padding: 16,
					textAlign: 'center',
					background: '#f5f5f5',
				} }
			>
				{ loading && (
					<div
						style={ {
							display: 'flex',
							justifyContent: 'center',
							alignItems: 'center',
							minHeight: 200,
						} }
					>
						<Spin />
					</div>
				) }
				{ objectUrl && (
					<img
						ref={ imgRef }
						src={ objectUrl }
						alt={ alt }
						onLoad={ handleImageLoad }
						onError={ handleImageError }
						style={ {
							maxWidth: '100%',
							transform: `scale(${ scale })`,
							transformOrigin: 'top center',
							transition: 'transform 0.2s ease',
							display: loading ? 'none' : 'inline-block',
							boxShadow: '0 1px 4px rgba(0,0,0,0.15)',
						} }
					/>
				) }
			</div>
		</div>
	);
};

export default ImageViewer;
