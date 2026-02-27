/**
 * Onboarding Component
 *
 * Main orchestrator that renders the correct component based on the
 * current step type (modal or spotlight). Handles auto-navigation
 * to the correct page for spotlight steps.
 *
 * Follows the same pattern as PressPrimer Quiz Onboarding.
 *
 * @package
 * @since 1.0.0
 */

import { useEffect, useState } from '@wordpress/element';
import useOnboarding from '../hooks/useOnboarding';
import { getStep, STEP_TYPE } from '../tourSteps';
import WelcomeModal from './WelcomeModal';
import CompletionModal from './CompletionModal';
import SpotlightTooltip from './SpotlightTooltip';

/**
 * Find a valid CSS selector from a comma-separated list + fallback
 *
 * @param {string} selector         Primary selector (comma-separated).
 * @param {string} fallbackSelector Fallback selector.
 * @return {string|null} First matching selector or null.
 */
const findValidSelector = ( selector, fallbackSelector ) => {
	if ( ! selector ) {
		return null;
	}

	// Try each comma-separated selector.
	const selectors = selector.split( ',' ).map( ( s ) => s.trim() );
	for ( const sel of selectors ) {
		if ( document.querySelector( sel ) ) {
			return sel;
		}
	}

	// Try fallback.
	if ( fallbackSelector && document.querySelector( fallbackSelector ) ) {
		return fallbackSelector;
	}

	return null;
};

/**
 * Onboarding Component
 */
const Onboarding = () => {
	const {
		isActive,
		isLoading,
		currentStep,
		totalSteps,
		startTour,
		nextStep,
		prevStep,
		skipTour,
		completeTour,
		closeTour,
	} = useOnboarding();

	const [ resolvedSelector, setResolvedSelector ] = useState( null );

	const step = getStep( currentStep );

	/**
	 * Resolve selector for spotlight steps
	 */
	useEffect( () => {
		if ( ! step || step.type !== STEP_TYPE.SPOTLIGHT ) {
			setResolvedSelector( null );
			return;
		}

		// Wait for DOM to be ready.
		const timer = setTimeout( () => {
			const found = findValidSelector(
				step.selector,
				step.fallbackSelector
			);
			setResolvedSelector( found );
		}, 100 );

		return () => clearTimeout( timer );
	}, [ step ] );

	if ( ! isActive || ! step || isLoading ) {
		return null;
	}

	// Welcome modal (step 1).
	if ( step.id === 'welcome' ) {
		return (
			<WelcomeModal
				title={ step.title }
				content={ step.content }
				onStart={ startTour }
				onSkip={ skipTour }
			/>
		);
	}

	// Completion modal (last step).
	if ( step.id === 'complete' ) {
		return (
			<CompletionModal
				title={ step.title }
				content={ step.content }
				onComplete={ completeTour }
			/>
		);
	}

	// Spotlight steps.
	if ( step.type === STEP_TYPE.SPOTLIGHT ) {
		// If no valid selector found, show a floating tooltip.
		if ( ! resolvedSelector ) {
			return (
				<div className="ppa-onboarding-floating">
					<div className="ppa-onboarding-floating__content">
						<button
							type="button"
							className="ppa-onboarding-floating__close"
							onClick={ closeTour }
							aria-label="Close"
						>
							&times;
						</button>
						<h4 className="ppa-onboarding-floating__title">
							{ step.title }
						</h4>
						<p className="ppa-onboarding-floating__text">
							{ step.content }
						</p>
						<div className="ppa-onboarding-floating__nav">
							{ currentStep > 1 && (
								<button
									type="button"
									className="ppa-onboarding-floating__btn ppa-onboarding-floating__btn--back"
									onClick={ prevStep }
								>
									Back
								</button>
							) }
							<span className="ppa-onboarding-floating__step">
								{ currentStep } / { totalSteps }
							</span>
							<button
								type="button"
								className="ppa-onboarding-floating__btn ppa-onboarding-floating__btn--next"
								onClick={ nextStep }
							>
								{ currentStep === totalSteps
									? 'Finish'
									: 'Next' }
							</button>
						</div>
					</div>
				</div>
			);
		}

		return (
			<SpotlightTooltip
				selector={ resolvedSelector }
				title={ step.title }
				content={ step.content }
				position={ step.position }
				currentStep={ currentStep }
				totalSteps={ totalSteps }
				onPrev={ currentStep > 1 ? prevStep : null }
				onNext={ nextStep }
				onSkip={ closeTour }
				onClose={ closeTour }
			/>
		);
	}

	return null;
};

export default Onboarding;
