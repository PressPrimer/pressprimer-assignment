/**
 * CompletionModal Component
 *
 * Final step of the onboarding tour — displays a success message
 * with quick-action buttons to create an assignment or view settings.
 * Follows the same pattern as PressPrimer Quiz CompletionModal.
 *
 * @package
 * @since 1.0.0
 */

import { useEffect, useRef } from '@wordpress/element';
import { Button, Row, Col } from 'antd';
import {
	CheckCircleOutlined,
	PlusOutlined,
	SettingOutlined,
} from '@ant-design/icons';

/**
 * CompletionModal Component
 *
 * @param {Object}   props            Component props.
 * @param {string}   props.title      Modal title.
 * @param {string}   props.content    Modal body text.
 * @param {Function} props.onComplete Complete tour handler.
 */
const CompletionModal = ( { title, content, onComplete } ) => {
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
	 * Handle a quick action button click
	 *
	 * Completes the tour, then navigates after a short delay.
	 *
	 * @param {string} url Destination URL.
	 */
	const handleQuickAction = ( url ) => {
		onComplete();
		setTimeout( () => {
			window.location.href = url;
		}, 100 );
	};

	const assignmentsUrl = data.urls?.assignments
		? data.urls.assignments + '&action=new'
		: 'admin.php?page=pressprimer-assignment-assignments&action=new';

	const settingsUrl =
		data.urls?.settings || 'admin.php?page=pressprimer-assignment-settings';

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

				{ /* Quick Actions */ }
				<div className="ppa-onboarding-modal__quick-actions">
					<p className="ppa-onboarding-modal__quick-actions-title">
						Get started with:
					</p>
					<Row gutter={ [ 12, 12 ] }>
						<Col span={ 12 }>
							<Button
								block
								icon={ <PlusOutlined /> }
								className="ppa-onboarding-modal__action-btn"
								onClick={ () =>
									handleQuickAction( assignmentsUrl )
								}
							>
								Create Assignment
							</Button>
						</Col>
						<Col span={ 12 }>
							<Button
								block
								icon={ <SettingOutlined /> }
								className="ppa-onboarding-modal__action-btn"
								onClick={ () =>
									handleQuickAction( settingsUrl )
								}
							>
								Settings
							</Button>
						</Col>
					</Row>
				</div>

				<div className="ppa-onboarding-modal__actions">
					<Button
						ref={ completeBtnRef }
						type="primary"
						size="large"
						className="ppa-onboarding-modal__complete-btn"
						onClick={ onComplete }
					>
						Close Tour
					</Button>
				</div>
			</div>
		</div>
	);
};

export default CompletionModal;
