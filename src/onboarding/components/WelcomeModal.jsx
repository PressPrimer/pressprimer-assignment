/**
 * WelcomeModal Component
 *
 * First step of the onboarding tour — displays a welcome message
 * with options to start the tour, skip, or permanently dismiss.
 * Follows the same pattern as PressPrimer Quiz WelcomeModal.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect, useRef } from '@wordpress/element';
import { Button, Checkbox } from 'antd';

/**
 * WelcomeModal Component
 *
 * @param {Object}   props         Component props.
 * @param {string}   props.title   Modal title.
 * @param {string}   props.content Modal body text.
 * @param {Function} props.onStart Start tour handler.
 * @param {Function} props.onSkip  Skip tour handler (receives permanent flag).
 */
const WelcomeModal = ( { title, content, onStart, onSkip } ) => {
	const [ dontShowAgain, setDontShowAgain ] = useState( false );
	const startBtnRef = useRef( null );

	const data = window.pressprimerAssignmentOnboardingData || {};
	const logoUrl = data.pluginUrl
		? data.pluginUrl + 'assets/images/PressPrimer-Logo.svg'
		: '';

	/**
	 * Focus the start button on mount and lock body scroll
	 */
	useEffect( () => {
		if ( startBtnRef.current ) {
			startBtnRef.current.focus();
		}

		document.body.style.overflow = 'hidden';

		return () => {
			document.body.style.overflow = '';
		};
	}, [] );

	/**
	 * Handle escape key
	 */
	useEffect( () => {
		const handleKeyDown = ( e ) => {
			if ( e.key === 'Escape' ) {
				onSkip( dontShowAgain );
			}
		};

		document.addEventListener( 'keydown', handleKeyDown );
		return () => document.removeEventListener( 'keydown', handleKeyDown );
	}, [ onSkip, dontShowAgain ] );

	return (
		// eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions
		<div
			className="ppa-onboarding-overlay"
			onClick={ ( e ) => {
				if ( e.target === e.currentTarget ) {
					onSkip( dontShowAgain );
				}
			} }
			onKeyDown={ ( e ) => {
				if ( e.key === 'Escape' ) {
					onSkip( dontShowAgain );
				}
			} }
			role="dialog"
			aria-modal="true"
			aria-labelledby="ppa-welcome-title"
			tabIndex={ -1 }
		>
			<div className="ppa-onboarding-modal ppa-onboarding-modal--welcome">
				{ logoUrl && (
					<div className="ppa-onboarding-modal__logo">
						<img
							src={ logoUrl }
							alt="PressPrimer"
							className="ppa-onboarding-modal__logo-img"
						/>
					</div>
				) }

				<h2
					className="ppa-onboarding-modal__title"
					id="ppa-welcome-title"
				>
					{ title }
				</h2>

				<p className="ppa-onboarding-modal__content">{ content }</p>

				<div className="ppa-onboarding-modal__actions">
					<Button
						ref={ startBtnRef }
						type="primary"
						size="large"
						className="ppa-onboarding-modal__start-btn"
						onClick={ onStart }
					>
						Let&apos;s Go!
					</Button>

					<Button
						type="text"
						className="ppa-onboarding-modal__skip-btn"
						onClick={ () => onSkip( dontShowAgain ) }
					>
						Skip Tour
					</Button>

					<Checkbox
						className="ppa-onboarding-modal__checkbox"
						checked={ dontShowAgain }
						onChange={ ( e ) =>
							setDontShowAgain( e.target.checked )
						}
					>
						Don&apos;t show this again
					</Checkbox>
				</div>
			</div>
		</div>
	);
};

export default WelcomeModal;
