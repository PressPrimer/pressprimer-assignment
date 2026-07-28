/**
 * ProgressDots Component
 *
 * The tour's step indicator, shared by the spotlight tooltip and the
 * modal stops so the "where am I in this" context never disappears.
 *
 * @package
 * @since 2.2.0
 */

import { __, sprintf } from '@wordpress/i18n';

/**
 * ProgressDots Component
 *
 * @param {Object} props             Component props.
 * @param {number} props.currentStep Current 1-based step.
 * @param {number} props.totalSteps  Total step count.
 */
const ProgressDots = ( { currentStep, totalSteps } ) => {
	if ( ! currentStep || ! totalSteps ) {
		return null;
	}

	return (
		<span
			className="ppa-progress-dots"
			aria-label={ sprintf(
				/* translators: 1: current step number, 2: total step count */
				__( 'Step %1$d of %2$d', 'pressprimer-assignment' ),
				currentStep,
				totalSteps
			) }
		>
			{ Array.from( { length: totalSteps }, ( _, i ) => (
				<span
					key={ i }
					className={
						'ppa-progress-dot' +
						( i + 1 === currentStep
							? ' ppa-progress-dot--active'
							: '' ) +
						( i + 1 < currentStep ? ' ppa-progress-dot--done' : '' )
					}
				/>
			) ) }
		</span>
	);
};

export default ProgressDots;
