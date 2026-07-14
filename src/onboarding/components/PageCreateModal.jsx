/**
 * PageCreateModal Component
 *
 * The "Put it on a page" tour stop: one click creates a published
 * page containing the assignment block, then presents the money
 * moment — "View your assignment" plus a copyable URL. Idempotent
 * server-side; re-running reuses the earlier page.
 *
 * @package
 * @since 2.2.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Input, Alert } from 'antd';
import {
	FileAddOutlined,
	CheckCircleOutlined,
	LeftOutlined,
	CopyOutlined,
	ExportOutlined,
	CloseOutlined,
} from '@ant-design/icons';

/**
 * PageCreateModal Component
 *
 * @param {Object}      props              Component props.
 * @param {string}      props.title        Step title.
 * @param {string}      props.content      Step body text.
 * @param {number|null} props.assignmentId The assignment saved during the tour.
 * @param {Function}    props.onNext       Advance to the next step.
 * @param {Function}    props.onPrev       Back to the previous step.
 * @param {Function}    props.onClose      Close the tour for this session.
 */
const PageCreateModal = ( {
	title,
	content,
	assignmentId,
	onNext,
	onPrev,
	onClose,
} ) => {
	const [ creating, setCreating ] = useState( false );
	const [ pageUrl, setPageUrl ] = useState( null );
	const [ reused, setReused ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ copied, setCopied ] = useState( false );

	const data = window.pressprimerAssignmentOnboardingData || {};
	const lms = data.lms || {};

	/**
	 * Lock body scroll while the modal is open
	 */
	useEffect( () => {
		document.body.style.overflow = 'hidden';

		return () => {
			document.body.style.overflow = '';
		};
	}, [] );

	/**
	 * Handle escape key — close the tour for the session, like the
	 * spotlight stops' close button
	 */
	useEffect( () => {
		const handleKeyDown = ( e ) => {
			if ( e.key === 'Escape' && onClose ) {
				onClose();
			}
		};

		document.addEventListener( 'keydown', handleKeyDown );
		return () => document.removeEventListener( 'keydown', handleKeyDown );
	}, [ onClose ] );

	/**
	 * Create (or reuse) the assignment page via admin-ajax
	 */
	const handleCreate = async () => {
		setCreating( true );
		setError( null );

		try {
			const formData = new FormData();
			formData.append(
				'action',
				'pressprimer_assignment_setup_create_page'
			);
			formData.append( 'nonce', data.nonce || '' );
			formData.append( 'assignment_id', String( assignmentId ) );

			const response = await fetch( data.ajaxUrl || '', {
				method: 'POST',
				credentials: 'same-origin',
				body: formData,
			} );
			const json = await response.json();

			if ( json?.success && json.data?.page_url ) {
				setPageUrl( json.data.page_url );
				setReused( ! json.data.created );
			} else {
				setError(
					json?.data?.message ||
						__(
							'Could not create the page.',
							'pressprimer-assignment'
						)
				);
			}
		} catch ( e ) {
			setError(
				__( 'Could not create the page.', 'pressprimer-assignment' )
			);
		} finally {
			setCreating( false );
		}
	};

	/**
	 * Copy the page URL to the clipboard
	 */
	const handleCopy = async () => {
		try {
			await window.navigator.clipboard.writeText( pageUrl );
			setCopied( true );
			setTimeout( () => setCopied( false ), 2000 );
		} catch ( e ) {
			// Clipboard unavailable — the URL stays selectable in the input.
		}
	};

	/**
	 * Render the one-line LMS pointer (runtime detection, nothing stored)
	 */
	const renderLmsPointer = () => {
		if ( ! lms.learndash && ! lms.tutorlms ) {
			return null;
		}

		const text = lms.learndash
			? __(
					"LearnDash is active on this site — you can also attach this assignment to a lesson from the lesson's settings.",
					'pressprimer-assignment'
			  )
			: __(
					"TutorLMS is active on this site — you can also attach this assignment to a lesson from the lesson's settings.",
					'pressprimer-assignment'
			  );

		return <p className="ppa-onboarding-modal__lms-pointer">{ text }</p>;
	};

	return (
		<div
			className="ppa-onboarding-overlay"
			role="dialog"
			aria-modal="true"
			aria-labelledby="ppa-page-title"
			tabIndex={ -1 }
		>
			<div className="ppa-onboarding-modal ppa-onboarding-modal--page">
				{ onClose && (
					<button
						type="button"
						className="ppa-onboarding-modal__close"
						onClick={ onClose }
						aria-label={ __( 'Close', 'pressprimer-assignment' ) }
					>
						<CloseOutlined />
					</button>
				) }

				<div
					className={
						'ppa-onboarding-modal__icon' +
						( pageUrl
							? ' ppa-onboarding-modal__icon--success'
							: '' )
					}
				>
					{ pageUrl ? <CheckCircleOutlined /> : <FileAddOutlined /> }
				</div>

				<h2 className="ppa-onboarding-modal__title" id="ppa-page-title">
					{ pageUrl
						? __(
								'Your assignment is live!',
								'pressprimer-assignment'
						  )
						: title }
				</h2>

				{ ! pageUrl && (
					<p className="ppa-onboarding-modal__content">
						{ assignmentId
							? content
							: __(
									'Save your assignment first — then come back to this step to put it on a page.',
									'pressprimer-assignment'
							  ) }
					</p>
				) }

				{ pageUrl && (
					<p className="ppa-onboarding-modal__content">
						{ reused
							? __(
									'This assignment already had a page from an earlier run — here it is.',
									'pressprimer-assignment'
							  )
							: __(
									'A page with your assignment on it was just published. Share the link with your students.',
									'pressprimer-assignment'
							  ) }
					</p>
				) }

				{ error && (
					<Alert
						className="ppa-onboarding-modal__error"
						message={ error }
						type="error"
						showIcon
					/>
				) }

				{ ! pageUrl && (
					<div className="ppa-onboarding-modal__actions">
						<Button
							type="primary"
							size="large"
							icon={ <FileAddOutlined /> }
							loading={ creating }
							disabled={ ! assignmentId }
							onClick={ handleCreate }
						>
							{ creating
								? __(
										'Creating page…',
										'pressprimer-assignment'
								  )
								: __(
										'Create the page',
										'pressprimer-assignment'
								  ) }
						</Button>
					</div>
				) }

				{ pageUrl && (
					<>
						<div className="ppa-onboarding-modal__actions">
							<Button
								type="primary"
								size="large"
								icon={ <ExportOutlined /> }
								href={ pageUrl }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ __(
									'View your assignment',
									'pressprimer-assignment'
								) }
							</Button>
						</div>

						<div className="ppa-onboarding-page-url">
							<Input
								readOnly
								value={ pageUrl }
								onFocus={ ( e ) => e.target.select() }
								aria-label={ __(
									'Assignment page URL',
									'pressprimer-assignment'
								) }
							/>
							<Button
								icon={ <CopyOutlined /> }
								onClick={ handleCopy }
							>
								{ copied
									? __( 'Copied!', 'pressprimer-assignment' )
									: __( 'Copy', 'pressprimer-assignment' ) }
							</Button>
						</div>
					</>
				) }

				{ renderLmsPointer() }

				<div className="ppa-onboarding-modal__nav">
					<Button
						type="text"
						icon={ <LeftOutlined /> }
						className="ppa-onboarding-modal__skip-btn"
						onClick={ onPrev }
					>
						{ __( 'Back', 'pressprimer-assignment' ) }
					</Button>
					<Button
						type={ pageUrl ? 'primary' : 'text' }
						className={
							pageUrl ? '' : 'ppa-onboarding-modal__skip-btn'
						}
						onClick={ onNext }
					>
						{ pageUrl
							? __( 'Next', 'pressprimer-assignment' )
							: __( 'Skip for now', 'pressprimer-assignment' ) }
					</Button>
				</div>
			</div>
		</div>
	);
};

export default PageCreateModal;
