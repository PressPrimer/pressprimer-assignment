/**
 * SpotlightTooltip Component
 *
 * Convenience wrapper combining Spotlight and Tooltip.
 * Follows the same pattern as PressPrimer Quiz SpotlightTooltip.
 *
 * @package
 * @since 1.0.0
 */

import Spotlight from './Spotlight';
import Tooltip from './Tooltip';

/**
 * SpotlightTooltip Component
 *
 * @param {Object}   props             Component props.
 * @param {string}   props.selector    CSS selector for the target element.
 * @param {string}   props.title       Tooltip title.
 * @param {string}   props.content     Tooltip content.
 * @param {string}   props.position    Preferred tooltip position.
 * @param {number}   props.currentStep Current step number.
 * @param {number}   props.totalSteps  Total step count.
 * @param {Function} props.onPrev      Previous step handler.
 * @param {Function} props.onNext      Next step handler.
 * @param {Function} props.onSkip      Skip handler.
 * @param {Function} props.onClose     Close handler.
 */
const SpotlightTooltip = ( {
	selector,
	title,
	content,
	position,
	currentStep,
	totalSteps,
	onPrev,
	onNext,
	onSkip,
	onClose,
} ) => {
	return (
		<Spotlight selector={ selector }>
			{ ( { targetRect } ) => (
				<Tooltip
					targetRect={ targetRect }
					title={ title }
					content={ content }
					position={ position }
					currentStep={ currentStep }
					totalSteps={ totalSteps }
					onPrev={ onPrev }
					onNext={ onNext }
					onSkip={ onSkip }
					onClose={ onClose }
				/>
			) }
		</Spotlight>
	);
};

export default SpotlightTooltip;
