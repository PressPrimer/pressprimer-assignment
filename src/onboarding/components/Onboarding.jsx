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
import PageCreateModal from './PageCreateModal';
import SpotlightTooltip from './SpotlightTooltip';

/**
 * Session storage key for the assignment saved during the tour
 */
const SAVED_ID_KEY = 'ppaSetupAssignmentId';

/**
 * Read the tour's saved assignment ID (survives editor reloads)
 *
 * @return {number|null} Assignment ID or null.
 */
const readSavedAssignmentId = () => {
	try {
		const stored = window.sessionStorage.getItem( SAVED_ID_KEY );
		const parsed = parseInt( stored, 10 );
		return parsed > 0 ? parsed : null;
	} catch ( e ) {
		return null;
	}
};

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

	// undefined = still resolving (render nothing yet),
	// null      = given up (render the floating fallback),
	// string    = found.
	const [ resolvedSelector, setResolvedSelector ] = useState( undefined );

	// The assignment saved during the tour — needed by the page step.
	const [ savedAssignmentId, setSavedAssignmentId ] = useState(
		readSavedAssignmentId
	);

	const step = getStep( currentStep );

	/**
	 * Listen for editor saves (bridged from PPAEditorAfterSave)
	 *
	 * Remembers the saved assignment for the page step, and advances
	 * the publish stop automatically when the user publishes for real.
	 */
	useEffect( () => {
		const onSaved = ( event ) => {
			const { id, status } = event.detail || {};

			if ( id ) {
				setSavedAssignmentId( id );
				try {
					window.sessionStorage.setItem( SAVED_ID_KEY, String( id ) );
				} catch ( e ) {
					// Session storage unavailable — in-memory state still works.
				}
			}

			if (
				isActive &&
				step?.id === 'publish' &&
				status === 'published'
			) {
				nextStep();
			}
		};

		window.addEventListener( 'ppa:assignment-saved', onSaved );
		return () =>
			window.removeEventListener( 'ppa:assignment-saved', onSaved );
	}, [ isActive, step, nextStep ] );

	/**
	 * Resolve selector for spotlight steps
	 *
	 * Polls until the target exists: the React admin screens (and
	 * TinyMCE) mount asynchronously, so a single early check races the
	 * page and falls back to highlighting the whole container.
	 */
	useEffect( () => {
		if ( ! step || step.type !== STEP_TYPE.SPOTLIGHT ) {
			setResolvedSelector( undefined );
			return;
		}

		// Let the step prepare its target first (e.g. switching to the
		// editor tab its target lives on).
		if ( typeof step.onEnter === 'function' ) {
			step.onEnter();
		}

		setResolvedSelector( undefined );

		const startedAt = Date.now();
		let poll = null;

		const tryResolve = () => {
			const found = findValidSelector( step.selector, null );

			if ( found ) {
				setResolvedSelector( found );
				if ( poll ) {
					clearInterval( poll );
					poll = null;
				}
				return;
			}

			// Primary target never appeared — settle for the fallback
			// container, or the floating tooltip if even that is gone.
			if ( Date.now() - startedAt > 4000 ) {
				setResolvedSelector(
					step.fallbackSelector &&
						document.querySelector( step.fallbackSelector )
						? step.fallbackSelector
						: null
				);
				if ( poll ) {
					clearInterval( poll );
					poll = null;
				}
			}
		};

		poll = setInterval( tryResolve, 200 );
		tryResolve();

		return () => {
			if ( poll ) {
				clearInterval( poll );
			}
		};
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

	// Page creation stop.
	if ( step.id === 'page' ) {
		return (
			<PageCreateModal
				title={ step.title }
				content={ step.content }
				assignmentId={ savedAssignmentId }
				onNext={ nextStep }
				onPrev={ prevStep }
				onClose={ closeTour }
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
				onPrev={ prevStep }
			/>
		);
	}

	// Spotlight steps.
	if ( step.type === STEP_TYPE.SPOTLIGHT ) {
		// Still waiting for the target to mount — render nothing rather
		// than flashing the floating fallback.
		if ( resolvedSelector === undefined ) {
			return null;
		}

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
