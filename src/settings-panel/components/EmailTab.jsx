/**
 * Email Tab Component
 *
 * Email notification settings with customizable templates,
 * matching PressPrimer Quiz's email settings pattern.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Form,
	Input,
	Switch,
	Typography,
	Button,
	message,
	Space,
	Select,
} from 'antd';
import {
	SendOutlined,
	PictureOutlined,
	DeleteOutlined,
} from '@ant-design/icons';

const { TextArea } = Input;
const { Title, Paragraph, Text } = Typography;

/**
 * Copy text to clipboard.
 *
 * @param {string} text Text to copy.
 * @return {Promise} Resolves when copied.
 */
const copyToClipboard = ( text ) => {
	if ( window.navigator.clipboard ) {
		return window.navigator.clipboard.writeText( text );
	}
	// Fallback for older browsers.
	const el = document.createElement( 'textarea' );
	el.value = text;
	document.body.appendChild( el );
	el.select();
	document.execCommand( 'copy' );
	document.body.removeChild( el );
	return Promise.resolve();
};

/**
 * Token item component - click to copy a token placeholder.
 *
 * @param {Object} props             Component props.
 * @param {string} props.token       Token string (e.g., "{first_name}").
 * @param {string} props.description Description of the token.
 */
const TokenItem = ( { token, description } ) => {
	const handleCopy = async () => {
		try {
			await copyToClipboard( token );
			message.success( __( 'Copied!', 'pressprimer-assignment' ) );
		} catch ( err ) {
			message.error( __( 'Failed to copy', 'pressprimer-assignment' ) );
		}
	};

	return (
		<Paragraph style={ { marginBottom: 4 } }>
			<Text
				code
				onClick={ handleCopy }
				style={ { cursor: 'pointer' } }
				title={ __( 'Click to copy', 'pressprimer-assignment' ) }
			>
				{ token }
			</Text>{ ' ' }
			- { description }
		</Paragraph>
	);
};

/**
 * Default email templates.
 * These match the PHP defaults in PressPrimer_Assignment_Email_Service.
 * Not wrapped in __() because multiline templates with \n are not
 * suitable for i18n collapsible-whitespace rules; PHP handles translation.
 */
const DEFAULTS = {
	submission_subject: 'Submission Received: {assignment_title}',
	submission_body: [
		'Hi {first_name},',
		'',
		'Your submission for "{assignment_title}" has been received.',
		'',
		'Submitted: {date}',
		'',
		"Your instructor will review your submission and provide feedback. You'll receive an email when your grade is ready.",
		'',
		'{view_url}',
	].join( '\n' ),
	grade_subject: 'Your Grade is Ready: {assignment_title}',
	grade_body: [
		'{score_summary}',
		'',
		'Hi {first_name},',
		'',
		'Your assignment "{assignment_title}" has been graded.',
		'',
		'- Score: {score} / {max_points}',
		'- Status: {passed}',
		'',
		'Your instructor has provided feedback. Click the button below to view your complete results.',
		'',
		'{view_url}',
	].join( '\n' ),
	admin_subject:
		'New Submission: {student_name} submitted {assignment_title}',
	admin_body: [
		'{student_name} has submitted "{assignment_title}".',
		'',
		'- Student: {student_name} ({student_email})',
		'- Assignment: {assignment_title}',
		'- Submitted: {date}',
		'',
		'{grade_url}',
	].join( '\n' ),
};

/**
 * Email Tab - Email notification settings with template customization.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.settings      Current settings.
 * @param {Function} props.updateSetting Function to update a setting.
 * @param {Object}   props.settingsData  Full settings data from PHP.
 */
const EmailTab = ( { settings, updateSetting, settingsData } ) => {
	const [ testEmail, setTestEmail ] = useState(
		settingsData.defaults?.adminEmail || ''
	);
	const [ testType, setTestType ] = useState( 'submission' );
	const [ sendingTest, setSendingTest ] = useState( false );

	/**
	 * Open media library to select logo.
	 */
	const handleSelectLogo = useCallback( () => {
		const frame = wp.media( {
			title: __( 'Select Email Logo', 'pressprimer-assignment' ),
			button: {
				text: __( 'Use this image', 'pressprimer-assignment' ),
			},
			multiple: false,
			library: { type: 'image' },
		} );

		frame.on( 'select', () => {
			const attachment = frame
				.state()
				.get( 'selection' )
				.first()
				.toJSON();
			updateSetting( 'email_logo_url', attachment.url );
			updateSetting( 'email_logo_id', attachment.id );
		} );

		frame.open();
	}, [ updateSetting ] );

	/**
	 * Remove selected logo.
	 */
	const handleRemoveLogo = useCallback( () => {
		updateSetting( 'email_logo_url', '' );
		updateSetting( 'email_logo_id', '' );
	}, [ updateSetting ] );

	/**
	 * Send test email.
	 */
	const handleSendTestEmail = async () => {
		if ( ! testEmail || ! testEmail.includes( '@' ) ) {
			message.error(
				__(
					'Please enter a valid email address',
					'pressprimer-assignment'
				)
			);
			return;
		}

		setSendingTest( true );
		try {
			const response = await apiFetch( {
				path: '/ppa/v1/email/test',
				method: 'POST',
				data: { email: testEmail, type: testType },
			} );

			if ( response.success ) {
				message.success(
					__(
						'Test email sent successfully!',
						'pressprimer-assignment'
					)
				);
			} else {
				message.error(
					response.message ||
						__(
							'Failed to send test email',
							'pressprimer-assignment'
						)
				);
			}
		} catch ( err ) {
			message.error(
				err.message ||
					__( 'Failed to send test email', 'pressprimer-assignment' )
			);
		} finally {
			setSendingTest( false );
		}
	};

	return (
		<div>
			{ /* Email Settings Section */ }
			<div className="ppa-settings-section">
				<Title level={ 4 } className="ppa-settings-section-title">
					{ __( 'Email Settings', 'pressprimer-assignment' ) }
				</Title>
				<Paragraph className="ppa-settings-section-description">
					{ __(
						'Configure email notifications sent by the plugin.',
						'pressprimer-assignment'
					) }
				</Paragraph>

				<div className="ppa-settings-field">
					<Form.Item
						label={ __( 'Email Logo', 'pressprimer-assignment' ) }
						help={ __(
							'Logo displayed at the top of emails. Max width 400px, max height 150px.',
							'pressprimer-assignment'
						) }
					>
						{ settings.email_logo_url ? (
							<div
								style={ {
									display: 'flex',
									alignItems: 'flex-start',
									gap: 16,
								} }
							>
								<div
									style={ {
										border: '1px solid #d9d9d9',
										borderRadius: 8,
										padding: 12,
										background: '#fafafa',
										maxWidth: 200,
									} }
								>
									<img
										src={ settings.email_logo_url }
										alt={ __(
											'Email logo',
											'pressprimer-assignment'
										) }
										style={ {
											maxWidth: '100%',
											maxHeight: 100,
											display: 'block',
										} }
									/>
								</div>
								<Button
									icon={ <DeleteOutlined /> }
									onClick={ handleRemoveLogo }
									danger
								>
									{ __( 'Remove', 'pressprimer-assignment' ) }
								</Button>
							</div>
						) : (
							<Button
								icon={ <PictureOutlined /> }
								onClick={ handleSelectLogo }
							>
								{ __(
									'Select Logo',
									'pressprimer-assignment'
								) }
							</Button>
						) }
					</Form.Item>
				</div>

				<div className="ppa-settings-field">
					<Form.Item
						label={ __( 'From Name', 'pressprimer-assignment' ) }
						help={ __(
							'Name shown in the "From" field of emails sent by the plugin.',
							'pressprimer-assignment'
						) }
					>
						<Input
							value={
								settings.email_from_name ||
								settingsData.defaults?.siteName ||
								''
							}
							onChange={ ( e ) =>
								updateSetting(
									'email_from_name',
									e.target.value
								)
							}
							placeholder={
								settingsData.defaults?.siteName || ''
							}
							style={ { maxWidth: 400 } }
						/>
					</Form.Item>
				</div>

				<div className="ppa-settings-field">
					<Form.Item
						label={ __(
							'From Email Address',
							'pressprimer-assignment'
						) }
						help={ __(
							'Email address shown in the "From" field of emails sent by the plugin.',
							'pressprimer-assignment'
						) }
					>
						<Input
							type="email"
							value={
								settings.email_from_email ||
								settingsData.defaults?.adminEmail ||
								''
							}
							onChange={ ( e ) =>
								updateSetting(
									'email_from_email',
									e.target.value
								)
							}
							placeholder={
								settingsData.defaults?.adminEmail || ''
							}
							style={ { maxWidth: 400 } }
						/>
					</Form.Item>
				</div>
			</div>

			{ /* Notification Toggles Section */ }
			<div className="ppa-settings-section">
				<Title level={ 4 } className="ppa-settings-section-title">
					{ __( 'Notification Toggles', 'pressprimer-assignment' ) }
				</Title>
				<Paragraph className="ppa-settings-section-description">
					{ __(
						'Choose which email notifications are sent automatically.',
						'pressprimer-assignment'
					) }
				</Paragraph>

				<div className="ppa-settings-field">
					<Form.Item
						label={ __(
							'Submission Confirmation',
							'pressprimer-assignment'
						) }
					>
						<Switch
							checked={
								settings.student_submission_confirmation !==
								false
							}
							onChange={ ( checked ) =>
								updateSetting(
									'student_submission_confirmation',
									checked
								)
							}
						/>
						<Text type="secondary" style={ { marginLeft: 12 } }>
							{ __(
								'Email students when their submission is received',
								'pressprimer-assignment'
							) }
						</Text>
					</Form.Item>
				</div>

				<div className="ppa-settings-field">
					<Form.Item
						label={ __(
							'Grade Notification',
							'pressprimer-assignment'
						) }
					>
						<Switch
							checked={
								settings.student_grade_notification !== false
							}
							onChange={ ( checked ) =>
								updateSetting(
									'student_grade_notification',
									checked
								)
							}
						/>
						<Text type="secondary" style={ { marginLeft: 12 } }>
							{ __(
								'Email students when their assignment is graded and returned',
								'pressprimer-assignment'
							) }
						</Text>
					</Form.Item>
				</div>

				<div className="ppa-settings-field">
					<Form.Item
						label={ __(
							'New Submission Notification',
							'pressprimer-assignment'
						) }
					>
						<Switch
							checked={ settings.admin_new_submission !== false }
							onChange={ ( checked ) =>
								updateSetting( 'admin_new_submission', checked )
							}
						/>
						<Text type="secondary" style={ { marginLeft: 12 } }>
							{ __(
								'Email the assignment owner (and any additional addresses set on the assignment) when a student submits',
								'pressprimer-assignment'
							) }
						</Text>
					</Form.Item>
				</div>
			</div>

			{ /* Submission Confirmation Template */ }
			<div className="ppa-settings-section">
				<Title level={ 4 } className="ppa-settings-section-title">
					{ __(
						'Submission Confirmation Email',
						'pressprimer-assignment'
					) }
				</Title>
				<Paragraph className="ppa-settings-section-description">
					{ __(
						'Customize the email sent to students when their submission is received.',
						'pressprimer-assignment'
					) }
				</Paragraph>

				<div className="ppa-settings-field">
					<Form.Item
						label={ __( 'Subject Line', 'pressprimer-assignment' ) }
					>
						<Input
							value={
								settings.email_submission_subject ??
								DEFAULTS.submission_subject
							}
							onChange={ ( e ) =>
								updateSetting(
									'email_submission_subject',
									e.target.value
								)
							}
							placeholder={ DEFAULTS.submission_subject }
							style={ { maxWidth: 500 } }
						/>
					</Form.Item>
				</div>

				<div className="ppa-settings-field">
					<Form.Item
						label={ __( 'Email Body', 'pressprimer-assignment' ) }
					>
						<TextArea
							value={
								settings.email_submission_body ??
								DEFAULTS.submission_body
							}
							onChange={ ( e ) =>
								updateSetting(
									'email_submission_body',
									e.target.value
								)
							}
							placeholder={ DEFAULTS.submission_body }
							rows={ 10 }
							style={ {
								maxWidth: 500,
								fontFamily: 'monospace',
								fontSize: 13,
							} }
						/>
					</Form.Item>
				</div>

				<div className="ppa-token-list">
					<Text strong>
						{ __( 'Available Tokens:', 'pressprimer-assignment' ) }
					</Text>
					<Text type="secondary" style={ { marginLeft: 8 } }>
						{ __( '(click to copy)', 'pressprimer-assignment' ) }
					</Text>
					<div style={ { marginTop: 8 } }>
						<TokenItem
							token="{first_name}"
							description={ __(
								"Student's first name",
								'pressprimer-assignment'
							) }
						/>
						<TokenItem
							token="{student_name}"
							description={ __(
								"Student's full display name",
								'pressprimer-assignment'
							) }
						/>
						<TokenItem
							token="{assignment_title}"
							description={ __(
								'Title of the assignment',
								'pressprimer-assignment'
							) }
						/>
						<TokenItem
							token="{date}"
							description={ __(
								'Submission date and time',
								'pressprimer-assignment'
							) }
						/>
						<TokenItem
							token="{site_name}"
							description={ __(
								'Your site name',
								'pressprimer-assignment'
							) }
						/>
						<TokenItem
							token="{view_url}"
							description={ __(
								'Button linking to submission status page',
								'pressprimer-assignment'
							) }
						/>
					</div>
				</div>
			</div>

			{ /* Grade Notification Template */ }
			<div className="ppa-settings-section">
				<Title level={ 4 } className="ppa-settings-section-title">
					{ __(
						'Grade Notification Email',
						'pressprimer-assignment'
					) }
				</Title>
				<Paragraph className="ppa-settings-section-description">
					{ __(
						'Customize the email sent to students when their assignment is graded.',
						'pressprimer-assignment'
					) }
				</Paragraph>

				<div className="ppa-settings-field">
					<Form.Item
						label={ __( 'Subject Line', 'pressprimer-assignment' ) }
					>
						<Input
							value={
								settings.email_grade_subject ??
								DEFAULTS.grade_subject
							}
							onChange={ ( e ) =>
								updateSetting(
									'email_grade_subject',
									e.target.value
								)
							}
							placeholder={ DEFAULTS.grade_subject }
							style={ { maxWidth: 500 } }
						/>
					</Form.Item>
				</div>

				<div className="ppa-settings-field">
					<Form.Item
						label={ __( 'Email Body', 'pressprimer-assignment' ) }
					>
						<TextArea
							value={
								settings.email_grade_body ?? DEFAULTS.grade_body
							}
							onChange={ ( e ) =>
								updateSetting(
									'email_grade_body',
									e.target.value
								)
							}
							placeholder={ DEFAULTS.grade_body }
							rows={ 12 }
							style={ {
								maxWidth: 500,
								fontFamily: 'monospace',
								fontSize: 13,
							} }
						/>
					</Form.Item>
				</div>

				<div className="ppa-token-list">
					<Text strong>
						{ __( 'Available Tokens:', 'pressprimer-assignment' ) }
					</Text>
					<Text type="secondary" style={ { marginLeft: 8 } }>
						{ __( '(click to copy)', 'pressprimer-assignment' ) }
					</Text>
					<div style={ { marginTop: 8 } }>
						<TokenItem
							token="{first_name}"
							description={ __(
								"Student's first name",
								'pressprimer-assignment'
							) }
						/>
						<TokenItem
							token="{student_name}"
							description={ __(
								"Student's full display name",
								'pressprimer-assignment'
							) }
						/>
						<TokenItem
							token="{assignment_title}"
							description={ __(
								'Title of the assignment',
								'pressprimer-assignment'
							) }
						/>
						<TokenItem
							token="{score}"
							description={ __(
								'Score achieved',
								'pressprimer-assignment'
							) }
						/>
						<TokenItem
							token="{max_points}"
							description={ __(
								'Maximum points possible',
								'pressprimer-assignment'
							) }
						/>
						<TokenItem
							token="{passed}"
							description={ __(
								'Pass/fail status text',
								'pressprimer-assignment'
							) }
						/>
						<TokenItem
							token="{score_summary}"
							description={ __(
								'Visual score summary box with pass/fail',
								'pressprimer-assignment'
							) }
						/>
						<TokenItem
							token="{date}"
							description={ __(
								'Submission date and time',
								'pressprimer-assignment'
							) }
						/>
						<TokenItem
							token="{site_name}"
							description={ __(
								'Your site name',
								'pressprimer-assignment'
							) }
						/>
						<TokenItem
							token="{view_url}"
							description={ __(
								'Button linking to grade and feedback page',
								'pressprimer-assignment'
							) }
						/>
					</div>
				</div>
			</div>

			{ /* New Submission Notification Template */ }
			<div className="ppa-settings-section">
				<Title level={ 4 } className="ppa-settings-section-title">
					{ __(
						'New Submission Notification Email',
						'pressprimer-assignment'
					) }
				</Title>
				<Paragraph className="ppa-settings-section-description">
					{ __(
						'Customize the email sent to the assignment owner (and any additional notification addresses configured on the assignment) when a student submits.',
						'pressprimer-assignment'
					) }
				</Paragraph>

				<div className="ppa-settings-field">
					<Form.Item
						label={ __( 'Subject Line', 'pressprimer-assignment' ) }
					>
						<Input
							value={
								settings.email_admin_subject ??
								DEFAULTS.admin_subject
							}
							onChange={ ( e ) =>
								updateSetting(
									'email_admin_subject',
									e.target.value
								)
							}
							placeholder={ DEFAULTS.admin_subject }
							style={ { maxWidth: 500 } }
						/>
					</Form.Item>
				</div>

				<div className="ppa-settings-field">
					<Form.Item
						label={ __( 'Email Body', 'pressprimer-assignment' ) }
					>
						<TextArea
							value={
								settings.email_admin_body ?? DEFAULTS.admin_body
							}
							onChange={ ( e ) =>
								updateSetting(
									'email_admin_body',
									e.target.value
								)
							}
							placeholder={ DEFAULTS.admin_body }
							rows={ 10 }
							style={ {
								maxWidth: 500,
								fontFamily: 'monospace',
								fontSize: 13,
							} }
						/>
					</Form.Item>
				</div>

				<div className="ppa-token-list">
					<Text strong>
						{ __( 'Available Tokens:', 'pressprimer-assignment' ) }
					</Text>
					<Text type="secondary" style={ { marginLeft: 8 } }>
						{ __( '(click to copy)', 'pressprimer-assignment' ) }
					</Text>
					<div style={ { marginTop: 8 } }>
						<TokenItem
							token="{student_name}"
							description={ __(
								"Student's full display name",
								'pressprimer-assignment'
							) }
						/>
						<TokenItem
							token="{student_email}"
							description={ __(
								"Student's email address",
								'pressprimer-assignment'
							) }
						/>
						<TokenItem
							token="{assignment_title}"
							description={ __(
								'Title of the assignment',
								'pressprimer-assignment'
							) }
						/>
						<TokenItem
							token="{date}"
							description={ __(
								'Submission date and time',
								'pressprimer-assignment'
							) }
						/>
						<TokenItem
							token="{site_name}"
							description={ __(
								'Your site name',
								'pressprimer-assignment'
							) }
						/>
						<TokenItem
							token="{grade_url}"
							description={ __(
								'Button linking to the grading page',
								'pressprimer-assignment'
							) }
						/>
					</div>
				</div>
			</div>

			{ /* Test Email Section */ }
			<div className="ppa-settings-section">
				<Title level={ 4 } className="ppa-settings-section-title">
					{ __( 'Test Email', 'pressprimer-assignment' ) }
				</Title>
				<Paragraph className="ppa-settings-section-description">
					{ __(
						'Send a test email to verify your email configuration and preview your templates.',
						'pressprimer-assignment'
					) }
				</Paragraph>

				<div className="ppa-settings-field">
					<Form.Item
						label={ __( 'Email Type', 'pressprimer-assignment' ) }
					>
						<Select
							value={ testType }
							onChange={ ( value ) => setTestType( value ) }
							style={ { width: 300 } }
							options={ [
								{
									value: 'submission',
									label: __(
										'Submission Confirmation',
										'pressprimer-assignment'
									),
								},
								{
									value: 'grade',
									label: __(
										'Grade Notification',
										'pressprimer-assignment'
									),
								},
								{
									value: 'admin',
									label: __(
										'New Submission Notification',
										'pressprimer-assignment'
									),
								},
							] }
						/>
					</Form.Item>
				</div>

				<div className="ppa-settings-field">
					<Form.Item
						label={ __( 'Send To', 'pressprimer-assignment' ) }
					>
						<Space.Compact style={ { maxWidth: 400 } }>
							<Input
								type="email"
								value={ testEmail }
								onChange={ ( e ) =>
									setTestEmail( e.target.value )
								}
								placeholder={ __(
									'Enter email address',
									'pressprimer-assignment'
								) }
								style={ { width: 280 } }
							/>
							<Button
								type="primary"
								icon={ <SendOutlined /> }
								onClick={ handleSendTestEmail }
								loading={ sendingTest }
							>
								{ __( 'Send Test', 'pressprimer-assignment' ) }
							</Button>
						</Space.Compact>
						<div style={ { marginTop: 8 } }>
							<Text type="secondary">
								{ __(
									'Save your settings first, then send a test email to preview your templates with sample data.',
									'pressprimer-assignment'
								) }
							</Text>
						</div>
					</Form.Item>
				</div>
			</div>
		</div>
	);
};

export default EmailTab;
