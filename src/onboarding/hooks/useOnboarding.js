/**
 * useOnboarding Hook
 *
 * Manages onboarding tour state, step navigation, and AJAX persistence.
 * Follows the same pattern as PressPrimer Quiz useOnboarding.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useCallback } from '@wordpress/element';
import {
	getStep,
	getStepUrl,
	getTotalSteps,
	isOnCorrectPage,
	STEP_TYPE,
} from '../tourSteps';
import { clearSavedAssignment } from '../setupSession';

/**
 * Get onboarding data from PHP
 */
const getData = () => window.pressprimerAssignmentOnboardingData || {};

/**
 * Send an AJAX request to update onboarding progress
 *
 * Returns a promise so callers can wait for persistence
 * before triggering page navigation.
 *
 * @param {string} actionType The action type (start, next, prev, skip, complete, reset).
 * @param {Object} extra      Additional form data fields.
 * @return {Promise} Fetch promise.
 */
const sendProgress = ( actionType, extra = {} ) => {
	const data = getData();
	const formData = new FormData();
	formData.append( 'action', 'pressprimer_assignment_onboarding_progress' );
	formData.append( 'nonce', data.nonce || '' );
	formData.append( 'action_type', actionType );

	Object.entries( extra ).forEach( ( [ key, value ] ) => {
		formData.append( key, value );
	} );

	return fetch( data.ajaxUrl || '', {
		method: 'POST',
		credentials: 'same-origin',
		body: formData,
	} ).catch( () => {
		// Silently fail — progress is best-effort.
	} );
};

/**
 * useOnboarding hook
 *
 * @return {Object} Onboarding state and actions.
 */
const useOnboarding = () => {
	const data = getData();
	const initialState = data.state || {};

	const [ isLoading, setIsLoading ] = useState( false );
	const [ isActive, setIsActive ] = useState( initialState.should_show );
	const [ currentStep, setCurrentStep ] = useState(
		initialState.current_step || 1
	);
	const totalSteps = getTotalSteps();

	/**
	 * Start the guided build (transition from welcome modal to step 2)
	 *
	 * Navigates to the real assignment editor. The pick travels as a
	 * nonce'd URL parameter ('blank' when no template was chosen) that
	 * the editor screen resolves server-side into a form prefill — for
	 * blank starts that still defaults Status to Published so the
	 * publish stop is a single Save click.
	 *
	 * @param {string|null} templateKey Chosen template key, or null for blank.
	 */
	const startTour = useCallback( ( templateKey = null ) => {
		// Hide the tour UI until the editor page takes over — rendering
		// step 2 on the current page would flash the floating fallback.
		setIsLoading( true );

		// A fresh run must never target the previous run's assignment.
		clearSavedAssignment();

		// Persist the landing step BEFORE navigating: 'start' alone
		// stores step 1, which would re-show the welcome modal on the
		// editor page.
		sendProgress( 'start', { step: 2 } ).then( () => {
			const onboardingData = getData();

			let url = getStepUrl( 2 );

			if ( url && onboardingData.templateNonce ) {
				url +=
					'&ppa-template=' +
					encodeURIComponent( templateKey || 'blank' ) +
					'&_wpnonce=' +
					encodeURIComponent( onboardingData.templateNonce );
			}

			if ( url ) {
				window.location.href = url;
			}
		} );
	}, [] );

	/**
	 * Go to the next step
	 */
	const nextStep = useCallback( () => {
		const next = currentStep + 1;

		if ( next > totalSteps ) {
			// Complete the tour.
			setCurrentStep( totalSteps );
			sendProgress( 'complete' );
			return;
		}

		setCurrentStep( next );

		// Check if we need to navigate to a different page.
		const step = getStep( next );
		const needsNavigation =
			step &&
			step.type === STEP_TYPE.SPOTLIGHT &&
			! isOnCorrectPage( next );

		if ( needsNavigation ) {
			setIsLoading( true );
		}

		// Wait for AJAX before navigating to avoid race condition.
		sendProgress( 'next', { step: next } ).then( () => {
			if ( needsNavigation ) {
				const stepUrl = getStepUrl( next );
				if ( stepUrl ) {
					window.location.href = stepUrl;
				}
			}
		} );
	}, [ currentStep, totalSteps ] );

	/**
	 * Go to the previous step
	 */
	const prevStep = useCallback( () => {
		const prev = Math.max( 1, currentStep - 1 );
		setCurrentStep( prev );

		// Check if we need to navigate to a different page.
		const step = getStep( prev );
		const needsNavigation =
			step &&
			step.type === STEP_TYPE.SPOTLIGHT &&
			! isOnCorrectPage( prev );

		if ( needsNavigation ) {
			setIsLoading( true );
		}

		// Wait for AJAX before navigating to avoid race condition.
		sendProgress( 'prev', { step: prev } ).then( () => {
			if ( needsNavigation ) {
				const stepUrl = getStepUrl( prev );
				if ( stepUrl ) {
					window.location.href = stepUrl;
				}
			}
		} );
	}, [ currentStep ] );

	/**
	 * Skip the tour
	 *
	 * @param {boolean} permanent Whether to permanently skip.
	 */
	const skipTour = useCallback( ( permanent = false ) => {
		setIsActive( false );
		sendProgress( 'skip', { permanent: permanent ? 'true' : 'false' } );
	}, [] );

	/**
	 * Complete the tour
	 */
	const completeTour = useCallback( () => {
		setIsActive( false );
		sendProgress( 'complete' );
	}, [] );

	/**
	 * Close the tour (temporary, for current session)
	 */
	const closeTour = useCallback( () => {
		setIsActive( false );
		sendProgress( 'skip', { permanent: 'false' } );
	}, [] );

	/**
	 * Relaunch the tour (used from dashboard)
	 */
	const relaunchTour = useCallback( () => {
		setCurrentStep( 1 );
		setIsActive( true );
		sendProgress( 'reset' );
	}, [] );

	return {
		isLoading,
		isActive,
		currentStep,
		totalSteps,
		startTour,
		nextStep,
		prevStep,
		skipTour,
		completeTour,
		closeTour,
		relaunchTour,
	};
};

export default useOnboarding;
