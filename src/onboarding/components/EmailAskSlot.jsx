/**
 * EmailAskSlot Component
 *
 * The 011 email ask on the tour's finish stop, positioned after the
 * published assignment has delivered value. Silently skipped when
 * the user has answered anywhere, or when the intake endpoint is
 * disabled — eligibility is resolved server-side and arrives in the
 * localized data.
 *
 * @package
 * @since 2.2.0
 */

import { useState } from '@wordpress/element';
import EmailOptinAsk from '../../shared/components/EmailOptinAsk';

const EmailAskSlot = () => {
	const data = window.pressprimerAssignmentOnboardingData || {};
	const optin = data.emailOptin || {};

	const [ declined, setDeclined ] = useState( false );

	if ( ! optin.eligible || declined ) {
		return null;
	}

	return (
		<div className="ppa-onboarding-email-ask">
			<EmailOptinAsk
				source="wizard"
				accountEmail={ optin.accountEmail || '' }
				privacyUrl={ optin.privacyUrl || '' }
				onAnswered={ ( status ) => {
					if ( 'declined' === status ) {
						setDeclined( true );
					}
				} }
			/>
		</div>
	);
};

export default EmailAskSlot;
