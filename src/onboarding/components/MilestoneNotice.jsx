/**
 * MilestoneNotice Component
 *
 * The 10-submission milestone surface (011): a celebratory,
 * permanently-dismissible panel pairing the moment with the email
 * ask. Follows the Quiz review-notice engine's trigger/dismissal
 * conventions (threshold counter, plugin-screens only, permanent
 * dismissal) — eligibility, the admin gate, and the review-prompt
 * priority rule are all resolved server-side.
 *
 * @package
 * @since 2.2.0
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { CloseOutlined } from '@ant-design/icons';
import EmailOptinAsk from '../../shared/components/EmailOptinAsk';

const MilestoneNotice = () => {
	const [ hidden, setHidden ] = useState( false );

	const data = window.pressprimerAssignmentOnboardingData || {};
	const optin = data.emailOptin || {};

	if ( hidden ) {
		return null;
	}

	/**
	 * Dismiss the milestone prompt — permanent, separate from answering
	 */
	const handleDismiss = () => {
		setHidden( true );

		apiFetch( {
			path: '/ppa/v1/email-optin',
			method: 'POST',
			data: {
				decision: 'dismiss',
				source: 'milestone',
			},
		} ).catch( () => {
			// Best-effort: the prompt is already hidden for this view.
		} );
	};

	return (
		<div className="ppa-milestone-notice">
			<button
				type="button"
				className="ppa-milestone-notice__dismiss"
				onClick={ handleDismiss }
				aria-label={ __( 'Dismiss', 'pressprimer-assignment' ) }
			>
				<CloseOutlined />
			</button>

			<h3 className="ppa-milestone-notice__title">
				{ __(
					'🎉 Your site just passed 10 submissions!',
					'pressprimer-assignment'
				) }
			</h3>

			<EmailOptinAsk
				source="milestone"
				privacyUrl={ optin.privacyUrl || '' }
			/>
		</div>
	);
};

export default MilestoneNotice;
