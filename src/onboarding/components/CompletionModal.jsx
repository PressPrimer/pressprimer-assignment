/**
 * CompletionModal Component
 *
 * The tour's finish stop: the completion message and the 011 email-ask
 * mount point (renders nothing until Phase 5).
 *
 * @package
 * @since 1.0.0
 */

import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from 'antd';
import { CheckCircleOutlined, LeftOutlined } from '@ant-design/icons';
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
