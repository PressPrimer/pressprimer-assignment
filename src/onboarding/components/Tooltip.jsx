/**
 * Tooltip Component
 *
 * Positioned tooltip for spotlight explanations.
 * Follows the same pattern as PressPrimer Quiz Tooltip.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { Button } from 'antd';
import { LeftOutlined, RightOutlined, CloseOutlined } from '@ant-design/icons';

/**
 * Calculate optimal position for tooltip
 *
 * @param {Object} targetRect        Target element bounding rect.
 * @param {Object} tooltipRect       Tooltip element bounding rect.
 * @param {string} preferredPosition Preferred position (top, bottom, left, right).
 * @param {number} offset            Distance from target element.
 * @return {Object} Position object with top, left, and actual position.
 */
const calculatePosition = (
	targetRect,
	tooltipRect,
	preferredPosition = 'bottom',
	offset = 16
) => {
	const viewport = {
		width: window.innerWidth,
		height: window.innerHeight,
	};

	const positions = {
		top: {
			top: targetRect.top - tooltipRect.height - offset,
			left:
				targetRect.left + ( targetRect.width - tooltipRect.width ) / 2,
		},
		bottom: {
			top: targetRect.top + targetRect.height + offset,
			left:
				targetRect.left + ( targetRect.width - tooltipRect.width ) / 2,
		},
		left: {
			top:
				targetRect.top + ( targetRect.height - tooltipRect.height ) / 2,
			left: targetRect.left - tooltipRect.width - offset,
		},
		right: {
			top:
				targetRect.top + ( targetRect.height - tooltipRect.height ) / 2,
			left: targetRect.left + targetRect.width + offset,
		},
	};

	/**
	 * Check if position fits in viewport
	 *
	 * @param {Object} pos Position coordinates.
	 * @return {boolean} True if fits.
	 */
	const fitsInViewport = ( pos ) => {
		return (
			pos.top >= 10 &&
			pos.left >= 10 &&
			pos.top + tooltipRect.height <= viewport.height - 10 &&
			pos.left + tooltipRect.width <= viewport.width - 10
		);
	};

	// Try preferred position first.
	if ( fitsInViewport( positions[ preferredPosition ] ) ) {
		return {
			...positions[ preferredPosition ],
			position: preferredPosition,
		};
	}

	// Try other positions in order of preference.
	const fallbackOrder = [ 'bottom', 'top', 'right', 'left' ].filter(
		( p ) => p !== preferredPosition
	);

	for ( const pos of fallbackOrder ) {
		if ( fitsInViewport( positions[ pos ] ) ) {
			return {
				...positions[ pos ],
				position: pos,
			};
		}
	}

	// If nothing fits, use bottom and constrain to viewport.
	const constrained = { ...positions.bottom };
	constrained.top = Math.max(
		10,
		Math.min( constrained.top, viewport.height - tooltipRect.height - 10 )
	);
	constrained.left = Math.max(
		10,
		Math.min( constrained.left, viewport.width - tooltipRect.width - 10 )
	);
	constrained.position = 'bottom';

	return constrained;
};

/**
 * Tooltip Component
 *
 * @param {Object}         props                Component props.
 * @param {Object}         props.targetRect     Target element bounding rect from Spotlight.
 * @param {string}         props.title          Tooltip title.
 * @param {string|Element} props.content        Tooltip content.
 * @param {string}         props.position       Preferred position.
 * @param {number}         props.currentStep    Current step number.
 * @param {number}         props.totalSteps     Total number of steps.
 * @param {Function}       props.onPrev         Previous step handler.
 * @param {Function}       props.onNext         Next step handler.
 * @param {Function}       props.onSkip         Skip handler.
 * @param {Function}       props.onClose        Close handler.
 * @param {boolean}        props.showNavigation Whether to show navigation buttons.
 * @param {string}         props.nextLabel      Label for next button.
 * @param {string}         props.prevLabel      Label for previous button.
 */
const Tooltip = ( {
	targetRect,
	title,
	content,
	position = 'bottom',
	currentStep,
	totalSteps,
	onPrev,
	onNext,
	onSkip,
	onClose,
	showNavigation = true,
	nextLabel = 'Next',
	prevLabel = 'Back',
} ) => {
	const tooltipRef = useRef( null );
	const [ tooltipStyle, setTooltipStyle ] = useState( { opacity: 0 } );
	const [ arrowPosition, setArrowPosition ] = useState( position );

	/**
	 * Update tooltip position
	 */
	const updatePosition = useCallback( () => {
		if ( ! tooltipRef.current || ! targetRect ) {
			return;
		}

		const tooltipRect2 = tooltipRef.current.getBoundingClientRect();
		const calculated = calculatePosition(
			targetRect,
			tooltipRect2,
			position
		);

		setTooltipStyle( {
			position: 'fixed',
			top: calculated.top,
			left: calculated.left,
			opacity: 1,
			zIndex: 100001,
		} );
		setArrowPosition( calculated.position );
	}, [ targetRect, position ] );

	/**
	 * Update position when target or tooltip changes
	 */
	useEffect( () => {
		// Initial position calculation after render.
		const timer = setTimeout( updatePosition, 50 );

		// Handle resize.
		window.addEventListener( 'resize', updatePosition );

		return () => {
			clearTimeout( timer );
			window.removeEventListener( 'resize', updatePosition );
		};
	}, [ updatePosition ] );

	if ( ! targetRect ) {
		return null;
	}

	return (
		<div
			ref={ tooltipRef }
			className={ `ppa-tooltip ppa-tooltip--${ arrowPosition }` }
			style={ tooltipStyle }
		>
			{ /* Arrow */ }
			<div
				className={ `ppa-tooltip__arrow ppa-tooltip__arrow--${ arrowPosition }` }
			/>

			{ /* Close button */ }
			{ onClose && (
				<button
					type="button"
					className="ppa-tooltip__close"
					onClick={ onClose }
					aria-label="Close"
				>
					<CloseOutlined />
				</button>
			) }

			{ /* Content */ }
			<div className="ppa-tooltip__content">
				{ title && <h4 className="ppa-tooltip__title">{ title }</h4> }
				{ content && (
					<div className="ppa-tooltip__body">{ content }</div>
				) }
			</div>

			{ /* Navigation */ }
			{ showNavigation && (
				<div className="ppa-tooltip__navigation">
					<div className="ppa-tooltip__nav-left">
						{ currentStep > 1 && onPrev && (
							<Button
								type="text"
								icon={ <LeftOutlined /> }
								onClick={ onPrev }
								size="small"
							>
								{ prevLabel }
							</Button>
						) }
					</div>

					<div className="ppa-tooltip__nav-center">
						{ currentStep && totalSteps && (
							<span className="ppa-tooltip__step-indicator">
								{ currentStep } / { totalSteps }
							</span>
						) }
					</div>

					<div className="ppa-tooltip__nav-right">
						{ onSkip && currentStep < totalSteps && (
							<Button type="text" onClick={ onSkip } size="small">
								Skip
							</Button>
						) }
						{ onNext && (
							<Button
								type="primary"
								onClick={ onNext }
								size="small"
							>
								{ currentStep === totalSteps
									? 'Finish'
									: nextLabel }
								{ currentStep < totalSteps && (
									<RightOutlined />
								) }
							</Button>
						) }
					</div>
				</div>
			) }
		</div>
	);
};

export default Tooltip;
