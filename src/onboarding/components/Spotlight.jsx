/**
 * Spotlight Component
 *
 * Renders an SVG mask overlay with a transparent cutout around the
 * target element. Uses a render prop to pass the target bounding
 * rect to child components (e.g., Tooltip).
 *
 * Follows the same pattern as PressPrimer Quiz Spotlight.
 *
 * @package
 * @since 1.0.0
 */

import {
	useState,
	useEffect,
	useCallback,
	createPortal,
} from '@wordpress/element';

/**
 * Spotlight Component
 *
 * @param {Object}   props          Component props.
 * @param {string}   props.selector CSS selector for the target element.
 * @param {number}   props.padding  Extra padding around the cutout.
 * @param {Function} props.children Render prop receiving { targetRect }.
 */
const Spotlight = ( { selector, padding = 8, children } ) => {
	const [ targetRect, setTargetRect ] = useState( null );

	/**
	 * Calculate the target element position
	 */
	const updatePosition = useCallback( () => {
		if ( ! selector ) {
			return;
		}

		const element = document.querySelector( selector );
		if ( ! element ) {
			setTargetRect( null );
			return;
		}

		const rect = element.getBoundingClientRect();
		setTargetRect( {
			top: rect.top,
			left: rect.left,
			width: rect.width,
			height: rect.height,
			bottom: rect.bottom,
			right: rect.right,
		} );
	}, [ selector ] );

	/**
	 * Set up position tracking
	 */
	useEffect( () => {
		// Scroll to top, then measure after a short delay.
		window.scrollTo( { top: 0, behavior: 'smooth' } );

		const timer = setTimeout( updatePosition, 300 );

		// Watch for resize and scroll.
		window.addEventListener( 'resize', updatePosition );
		window.addEventListener( 'scroll', updatePosition );

		// ResizeObserver for dynamic element changes.
		let observer;
		if ( selector ) {
			const element = document.querySelector( selector );
			if ( element && typeof window.ResizeObserver !== 'undefined' ) {
				observer = new window.ResizeObserver( updatePosition );
				observer.observe( element );
			}
		}

		return () => {
			clearTimeout( timer );
			window.removeEventListener( 'resize', updatePosition );
			window.removeEventListener( 'scroll', updatePosition );
			if ( observer ) {
				observer.disconnect();
			}
		};
	}, [ selector, updatePosition ] );

	if ( ! targetRect ) {
		// No target found — just render children without spotlight.
		return typeof children === 'function'
			? children( { targetRect: null } )
			: null;
	}

	const cutout = {
		x: targetRect.left - padding,
		y: targetRect.top - padding,
		width: targetRect.width + padding * 2,
		height: targetRect.height + padding * 2,
		rx: 8,
	};

	const overlay = createPortal(
		<>
			{ /* SVG mask overlay */ }
			<svg
				className="ppa-spotlight__overlay"
				style={ {
					position: 'fixed',
					inset: 0,
					width: '100%',
					height: '100%',
					zIndex: 99998,
					pointerEvents: 'none',
				} }
			>
				<defs>
					<mask id="ppa-spotlight-mask">
						<rect
							x="0"
							y="0"
							width="100%"
							height="100%"
							fill="white"
						/>
						<rect
							x={ cutout.x }
							y={ cutout.y }
							width={ cutout.width }
							height={ cutout.height }
							rx={ cutout.rx }
							fill="black"
						/>
					</mask>
				</defs>
				<rect
					x="0"
					y="0"
					width="100%"
					height="100%"
					fill="rgba(0, 0, 0, 0.5)"
					mask="url(#ppa-spotlight-mask)"
					style={ { pointerEvents: 'all' } }
				/>
			</svg>

			{ /* Highlight border around target */ }
			<div
				className="ppa-spotlight__highlight ppa-spotlight__highlight--pulse"
				style={ {
					position: 'fixed',
					top: cutout.y,
					left: cutout.x,
					width: cutout.width,
					height: cutout.height,
					borderRadius: cutout.rx,
					zIndex: 99999,
					pointerEvents: 'none',
				} }
			/>
		</>,
		document.body
	);

	return (
		<>
			{ overlay }
			{ typeof children === 'function'
				? children( { targetRect } )
				: null }
		</>
	);
};

export default Spotlight;
