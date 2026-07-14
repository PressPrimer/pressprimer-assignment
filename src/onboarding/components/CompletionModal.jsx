/**
 * CompletionModal Component
 *
 * The tour's finish stop: a next-steps checklist (grading queue,
 * settings, docs) and the 011 email-ask mount point (renders nothing
 * until Phase 5).
 *
 * @package
 * @since 1.0.0
 */

import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from 'antd';
import {
	CheckCircleOutlined,
	LeftOutlined,
	InboxOutlined,
	SettingOutlined,
	ReadOutlined,
} from '@ant-design/icons';
import EmailAskSlot from './EmailAskSlot';
import ProgressDots from './ProgressDots';

/**
 * CompletionModal Component
 *
 * @param {Object}   props             Component props.
 * @param {string}   props.title       Modal title.
 * @param {string}   props.content     Modal body text.
 * @param {number}   props.currentStep Current 1-based tour step.
 * @param {number}   props.totalSteps  Total tour step count.
 * @param {Function} props.onComplete  Complete tour handler.
 * @param {Function} props.onPrev      Back to the previous step.
 */
const CompletionModal = ( {
	title,
	content,
	currentStep,
	totalSteps,
	onComplete,
	onPrev,
} ) => {
	const data = window.pressprimerAssignmentOnboardingData || {};
	const completeBtnRef = useRef( null );

	/**
	 * Focus the complete button on mount and lock body scroll
	 */
	useEffect( () => {
		if ( completeBtnRef.current ) {
			completeBtnRef.current.focus();
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
				onComplete();
			}
		};

		document.addEventListener( 'keydown', handleKeyDown );
		return () => document.removeEventListener( 'keydown', handleKeyDown );
	}, [ onComplete ] );

	/**
	 * Complete the tour, then navigate to an admin destination
	 *
	 * @param {string} url Destination URL.
	 */
	const handleNextStep = ( url ) => {
		onComplete();
		setTimeout( () => {
			window.location.href = url;
		}, 100 );
	};

	const gradingUrl =
		data.urls?.grading || 'admin.php?page=pressprimer-assignment-grading';
	const settingsUrl =
		data.urls?.settings || 'admin.php?page=pressprimer-assignment-settings';
	const docsUrl =
		data.docsUrl ||
		'https://pressprimer.com/knowledge-base/pressprimer-assignment/';

	const nextSteps = [
		{
			key: 'grading',
			icon: <InboxOutlined />,
			text: __(
				'Student submissions land in the Grading queue — grade them side by side.',
				'pressprimer-assignment'
			),
			linkText: __( 'Open Grading', 'pressprimer-assignment' ),
			onClick: () => handleNextStep( gradingUrl ),
		},
		{
			key: 'settings',
			icon: <SettingOutlined />,
			text: __(
				'Set defaults, appearance, and email notifications.',
				'pressprimer-assignment'
			),
			linkText: __( 'Open Settings', 'pressprimer-assignment' ),
			onClick: () => handleNextStep( settingsUrl ),
		},
		{
			key: 'docs',
			icon: <ReadOutlined />,
			text: __(
				'The Knowledge Base covers everything else.',
				'pressprimer-assignment'
			),
			linkText: __( 'Browse the docs', 'pressprimer-assignment' ),
			href: docsUrl,
		},
	];

	return (
		// eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions
		<div
			className="ppa-onboarding-overlay"
			onClick={ ( e ) => {
				if ( e.target === e.currentTarget ) {
					onComplete();
				}
			} }
			onKeyDown={ ( e ) => {
				if ( e.key === 'Escape' ) {
					onComplete();
				}
			} }
			role="dialog"
			aria-modal="true"
			aria-labelledby="ppa-complete-title"
			tabIndex={ -1 }
		>
			<div className="ppa-onboarding-modal ppa-onboarding-modal--complete">
				<div className="ppa-onboarding-modal__icon ppa-onboarding-modal__icon--success">
					<CheckCircleOutlined />
				</div>

				<h2
					className="ppa-onboarding-modal__title"
					id="ppa-complete-title"
				>
					{ title }
				</h2>

				<p className="ppa-onboarding-modal__content">{ content }</p>

				{ /* Next steps */ }
				<div className="ppa-onboarding-next">
					<p className="ppa-onboarding-next__label">
						{ __( 'Where to next', 'pressprimer-assignment' ) }
					</p>
					{ nextSteps.map( ( item ) => (
						<div
							className="ppa-onboarding-next__row"
							key={ item.key }
						>
							<span className="ppa-onboarding-next__icon">
								{ item.icon }
							</span>
							<span className="ppa-onboarding-next__text">
								{ item.text }
							</span>
							{ item.href ? (
								<a
									className="ppa-onboarding-next__link"
									href={ item.href }
									target="_blank"
									rel="noopener noreferrer"
								>
									{ item.linkText }
								</a>
							) : (
								<button
									type="button"
									className="ppa-onboarding-next__link"
									onClick={ item.onClick }
								>
									{ item.linkText }
								</button>
							) }
						</div>
					) ) }
				</div>

				{ /* 011 email opt-in mount point (Phase 5). */ }
				<EmailAskSlot />

				<div className="ppa-onboarding-modal__nav">
					<div className="ppa-onboarding-modal__nav-left">
						{ onPrev && (
							<Button
								type="text"
								icon={ <LeftOutlined /> }
								className="ppa-onboarding-modal__skip-btn"
								onClick={ onPrev }
							>
								{ __( 'Back', 'pressprimer-assignment' ) }
							</Button>
						) }
					</div>
					<div className="ppa-onboarding-modal__nav-center">
						<ProgressDots
							currentStep={ currentStep }
							totalSteps={ totalSteps }
						/>
					</div>
					<div className="ppa-onboarding-modal__nav-right">
						<Button
							ref={ completeBtnRef }
							type="primary"
							className="ppa-onboarding-modal__complete-btn"
							onClick={ onComplete }
						>
							{ __( 'Close Tour', 'pressprimer-assignment' ) }
						</Button>
					</div>
				</div>
			</div>
		</div>
	);
};

export default CompletionModal;
