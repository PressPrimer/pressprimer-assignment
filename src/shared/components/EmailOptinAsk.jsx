/**
 * EmailOptinAsk Component
 *
 * The plugin's single email ask (the free 5-part email course),
 * shared verbatim by every surface: the tour's finish stop, the
 * dashboard card, and (Phase 5.3) the What's New panel and milestone
 * prompt. The hard rules, per the 011 spec:
 *
 * - The email field is NEVER pre-filled; "Use my account email"
 *   fills it only on click.
 * - Nothing is sent until the user clicks the affirmative button
 *   with a typed, valid email.
 * - "No thanks" is a real, adjacent action with the same permanence
 *   as opting in.
 * - The disclosure states exactly what is collected (the email
 *   address — nothing else) and links the privacy policy.
 *
 * @package
 * @since 2.2.0
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Input, Button } from 'antd';
import { CheckCircleOutlined, MailOutlined } from '@ant-design/icons';
import './EmailOptinAsk.css';

/**
 * EmailOptinAsk Component
 *
 * @param {Object}   props              Component props.
 * @param {string}   props.source       Surface tag (wizard | whats-new | dashboard-card | milestone).
 * @param {string}   props.accountEmail The user's account email (used only on explicit click).
 * @param {string}   props.privacyUrl   Privacy policy URL.
 * @param {Function} props.onAnswered   Called with 'opted_in' or 'declined' after an answer is recorded.
 */
const EmailOptinAsk = ( { source, accountEmail, privacyUrl, onAnswered } ) => {
	const [ email, setEmail ] = useState( '' );
	const [ submitting, setSubmitting ] = useState( null );
	const [ status, setStatus ] = useState( null );
	const [ error, setError ] = useState( null );

	/**
	 * Send an answer to the opt-in endpoint
	 *
	 * @param {string} decision 'opt_in' or 'decline'.
	 */
	const sendAnswer = async ( decision ) => {
		setSubmitting( decision );
		setError( null );

		try {
			const response = await apiFetch( {
				path: '/ppa/v1/email-optin',
				method: 'POST',
				data: {
					decision,
					source,
					...( 'opt_in' === decision ? { email } : {} ),
				},
			} );

			const answered = response?.status || 'declined';
			setStatus( answered );

			if ( onAnswered ) {
				onAnswered( answered );
			}
		} catch ( err ) {
			setError(
				err.message ||
					__( 'Something went wrong.', 'pressprimer-assignment' )
			);
		} finally {
			setSubmitting( null );
		}
	};

	// Opted in: the confirmation replaces the form (double opt-in).
	if ( 'opted_in' === status ) {
		return (
			<div className="ppa-email-ask ppa-email-ask--done">
				<CheckCircleOutlined className="ppa-email-ask__done-icon" />
				<p className="ppa-email-ask__done-text">
					{ __(
						"You're almost in — check your inbox and click the confirmation link to start the course.",
						'pressprimer-assignment'
					) }
				</p>
			</div>
		);
	}

	// Declined: the parent hides the surface; render nothing.
	if ( 'declined' === status ) {
		return null;
	}

	return (
		<div className="ppa-email-ask">
			<h4 className="ppa-email-ask__headline">
				{ __(
					'Want to get more out of your assignments?',
					'pressprimer-assignment'
				) }
			</h4>

			<p className="ppa-email-ask__body">
				{ __(
					'Get our free 5-part email course on giving feedback that improves student work, plus occasional product updates. One or two emails a month after the course. Unsubscribe anytime.',
					'pressprimer-assignment'
				) }
			</p>

			<div className="ppa-email-ask__field-row">
				<Input
					type="email"
					prefix={ <MailOutlined /> }
					placeholder={ __(
						'you@example.com',
						'pressprimer-assignment'
					) }
					value={ email }
					onChange={ ( e ) => setEmail( e.target.value ) }
					onPressEnter={ () => sendAnswer( 'opt_in' ) }
					aria-label={ __(
						'Email address',
						'pressprimer-assignment'
					) }
				/>
				{ accountEmail && (
					<button
						type="button"
						className="ppa-email-ask__use-account"
						onClick={ () => setEmail( accountEmail ) }
					>
						{ __(
							'Use my account email',
							'pressprimer-assignment'
						) }
					</button>
				) }
			</div>

			{ error && <p className="ppa-email-ask__error">{ error }</p> }

			<div className="ppa-email-ask__actions">
				<Button
					type="primary"
					loading={ 'opt_in' === submitting }
					disabled={ 'decline' === submitting }
					onClick={ () => sendAnswer( 'opt_in' ) }
				>
					{ __( 'Send me the course', 'pressprimer-assignment' ) }
				</Button>
				<button
					type="button"
					className="ppa-email-ask__decline"
					disabled={ null !== submitting }
					onClick={ () => sendAnswer( 'decline' ) }
				>
					{ __( 'No thanks', 'pressprimer-assignment' ) } &rarr;
				</button>
			</div>

			<p className="ppa-email-ask__disclosure">
				{ __(
					'We collect your email address — nothing else.',
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
