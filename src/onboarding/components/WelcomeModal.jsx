/**
 * WelcomeModal Component
 *
 * First step of the guided-build tour — pitches the five-minute build,
 * offers an optional template pick (bundled sample packs) or a blank
 * start, with options to skip or permanently dismiss.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Checkbox } from 'antd';
import { CheckOutlined } from '@ant-design/icons';

/**
 * WelcomeModal Component
 *
 * @param {Object}   props         Component props.
 * @param {string}   props.title   Modal title.
 * @param {string}   props.content Modal body text.
 * @param {Function} props.onStart Start handler; receives the chosen template key or null.
 * @param {Function} props.onSkip  Skip tour handler (receives permanent flag).
 */
const WelcomeModal = ( { title, content, onStart, onSkip } ) => {
	const [ dontShowAgain, setDontShowAgain ] = useState( false );

	const data = window.pressprimerAssignmentOnboardingData || {};
	const templates = Array.isArray( data.templates ) ? data.templates : [];

	// Default to the first template so the guided build starts with
	// real content; "Start blank" is always available.
	const [ selectedTemplate, setSelectedTemplate ] = useState(
		templates.length ? templates[ 0 ].key : null
	);

	const startBtnRef = useRef( null );

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

	/**
	 * Render one selectable template card
	 *
	 * @param {string|null} key         Template key (null = blank).
	 * @param {string}      cardTitle   Card heading.
	 * @param {string}      description Card description.
	 * @return {Element} Card element.
	 */
	const renderTemplateCard = ( key, cardTitle, description ) => {
		const isSelected = selectedTemplate === key;

		return (
			<button
				key={ key || 'blank' }
				type="button"
				className={
					'ppa-onboarding-template-card' +
					( isSelected
						? ' ppa-onboarding-template-card--selected'
						: '' )
				}
				onClick={ () => setSelectedTemplate( key ) }
				aria-pressed={ isSelected }
			>
				<span className="ppa-onboarding-template-card__check">
					<CheckOutlined />
				</span>
				<span className="ppa-onboarding-template-card__title">
					{ cardTitle }
				</span>
				<span className="ppa-onboarding-template-card__desc">
					{ description }
				</span>
			</button>
		);
	};

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

				{ templates.length > 0 && (
					<div className="ppa-onboarding-templates">
						<p className="ppa-onboarding-templates__label">
							{ __(
								'Start from a template',
								'pressprimer-assignment'
							) }
						</p>
						<div className="ppa-onboarding-templates__grid">
							{ templates.map( ( template ) =>
								renderTemplateCard(
									template.key,
									template.title,
									template.description
								)
							) }
							{ renderTemplateCard(
								null,
								__( 'Start blank', 'pressprimer-assignment' ),
								__(
									'A clean slate — you fill in everything.',
									'pressprimer-assignment'
								)
							) }
						</div>
					</div>
				) }

				<div className="ppa-onboarding-modal__actions">
					<Button
						ref={ startBtnRef }
						type="primary"
						size="large"
						className="ppa-onboarding-modal__start-btn"
						onClick={ () => onStart( selectedTemplate ) }
					>
						{ __( "Let's Go!", 'pressprimer-assignment' ) }
					</Button>

					<Button
						type="text"
						className="ppa-onboarding-modal__skip-btn"
						onClick={ () => onSkip( dontShowAgain ) }
					>
						{ __( 'Skip Tour', 'pressprimer-assignment' ) }
					</Button>

					<Checkbox
						className="ppa-onboarding-modal__checkbox"
						checked={ dontShowAgain }
						onChange={ ( e ) =>
							setDontShowAgain( e.target.checked )
						}
					>
						{ __(
							"Don't show this again",
							'pressprimer-assignment'
						) }
					</Checkbox>
				</div>
			</div>
		</div>
	);
};

export default WelcomeModal;
