/**
 * EmailOptinAsk Component
 *
 * The plugin's single email ask (the free email course), shared by
 * every surface: the tour's finish stop, the dashboard card, and the
 * Phase 5.3 surfaces. The hard rules, per the 011 spec (as revised
 * in review):
 *
 * - The email field is NEVER pre-filled.
 * - Nothing is sent until the user clicks the button with a typed,
 *   valid email.
 * - The disclosure states exactly what is collected (the email
 *   address — nothing else) and links the privacy policy.
 * - No decline button: each surface provides its own quiet exit
 *   (closing the tour, dismissing the card).
 *
 * @package
 * @since 2.2.0
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Input, Button } from 'antd';
import { CheckCircleOutlined } from '@ant-design/icons';
import './EmailOptinAsk.css';

/**
 * EmailOptinAsk Component
 *
 * @param {Object}   props            Component props.
 * @param {string}   props.source     Surface tag (wizard | whats-new | dashboard-card | milestone).
 * @param {string}   props.privacyUrl Privacy policy URL.
 * @param {Function} props.onAnswered Called with 'opted_in' after an opt-in is recorded.
 */
const EmailOptinAsk = ( { source, privacyUrl, onAnswered } ) => {
	const [ email, setEmail ] = useState( '' );
	const [ submitting, setSubmitting ] = useState( false );
	const [ done, setDone ] = useState( false );
	const [ error, setError ] = useState( null );

	/**
	 * Submit the opt-in
	 */
	const handleSubmit = async () => {
		setSubmitting( true );
		setError( null );

		try {
			await apiFetch( {
				path: '/ppa/v1/email-optin',
				method: 'POST',
				data: {
					decision: 'opt_in',
					source,
					email,
				},
			} );

			setDone( true );

			if ( onAnswered ) {
				onAnswered( 'opted_in' );
			}
		} catch ( err ) {
			setError(
				err.message ||
					__( 'Something went wrong.', 'pressprimer-assignment' )
			);
		} finally {
			setSubmitting( false );
		}
	};

	// Opted in: the confirmation replaces the form. Subscription starts
	// immediately (no double opt-in, per review) — every email carries
	// an unsubscribe link.
	if ( done ) {
		return (
			<div className="ppa-email-ask ppa-email-ask--done">
				<CheckCircleOutlined className="ppa-email-ask__done-icon" />
				<p className="ppa-email-ask__done-text">
					{ __(
						"You're in — your first email is on its way!",
						'pressprimer-assignment'
					) }
				</p>
			</div>
		);
	}

	return (
		<div className="ppa-email-ask">
			<h3 className="ppa-email-ask__headline">
				{ __(
					'Get more out of your assignments',
					'pressprimer-assignment'
				) }
			</h3>

			<p className="ppa-email-ask__body">
				{ __(
					'A free 5-part email course on building a better assignment experience, followed by occasional assignment advice and product updates. Unsubscribe anytime.',
					'pressprimer-assignment'
				) }
			</p>

			<div className="ppa-email-ask__field-row">
				<Input
					type="email"
					placeholder={ __(
						'you@example.com',
						'pressprimer-assignment'
					) }
					value={ email }
					onChange={ ( e ) => setEmail( e.target.value ) }
					onPressEnter={ handleSubmit }
					aria-label={ __(
						'Email address',
						'pressprimer-assignment'
					) }
				/>
				<Button
					type="primary"
					loading={ submitting }
					onClick={ handleSubmit }
				>
					{ __( 'Sign me up!', 'pressprimer-assignment' ) }
				</Button>
			</div>

			{ error && <p className="ppa-email-ask__error">{ error }</p> }

			<p className="ppa-email-ask__disclosure">
				{ __(
					'We only collect your email address.',
					'pressprimer-assignment'
				) }{ ' ' }
				{ privacyUrl && (
					<a
						href={ privacyUrl }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Privacy policy', 'pressprimer-assignment' ) }
					</a>
				) }
			</p>
		</div>
	);
};

export default EmailOptinAsk;
