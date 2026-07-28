/**
 * EmailAskSlot Component
 *
 * The 011 email ask on the tour's finish stop, positioned after the
 * published assignment has delivered value. Silently skipped when
 * the user has answered anywhere, or when the intake endpoint is
 * disabled — eligibility is resolved server-side and arrives in the
 * localized data. Closing the tour is the quiet exit; there is no
 * decline button.
 *
 * @package
 * @since 2.2.0
 */

import EmailOptinAsk from '../../shared/components/EmailOptinAsk';

const EmailAskSlot = () => {
	const data = window.pressprimerAssignmentOnboardingData || {};
	const optin = data.emailOptin || {};

	if ( ! optin.eligible ) {
		return null;
	}

	return (
		<div className="ppa-onboarding-email-ask">
			<EmailOptinAsk
				source="wizard"
				privacyUrl={ optin.privacyUrl || '' }
			/>
		</div>
	);
};

export default EmailAskSlot;
