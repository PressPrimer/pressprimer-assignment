/**
 * Integrations Tab Component
 *
 * LMS integration status and settings for PressPrimer Assignment.
 * Only shows LearnDash and Tutor LMS (the integrations with code).
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Form,
	Input,
	Button,
	Typography,
	Alert,
	Descriptions,
	Tag,
	Spin,
	Collapse,
} from 'antd';
import { CheckCircleOutlined, SettingOutlined } from '@ant-design/icons';

const { Title, Paragraph, Text } = Typography;

/**
 * Integrations Tab - LMS integrations
 *
 * @param {Object}   props               Component props
 * @param {Object}   props.settings      Current settings
 * @param {Function} props.updateSetting Function to update a setting
 * @param {Object}   props.settingsData  Full settings data including LMS status
 */
// eslint-disable-next-line no-unused-vars -- Props passed by SettingsPage to all tabs.
const IntegrationsTab = ( { settings, updateSetting, settingsData } ) => {
	// LMS Integration states - use pre-loaded data from PHP.
	const lmsStatus = settingsData.lmsStatus || {};

	const [ learndashStatus, setLearndashStatus ] = useState(
		lmsStatus.learndash?.active
			? { active: true, version: lmsStatus.learndash.version }
			: { active: false }
	);
	const [ loadingLearndash, setLoadingLearndash ] = useState(
		lmsStatus.learndash?.active || false
	);
	const [ learndashSettings, setLearndashSettings ] = useState( {
		restriction_message: '',
	} );
	const [ savingLearndash, setSavingLearndash ] = useState( false );

	const [ tutorlmsStatus, setTutorlmsStatus ] = useState(
		lmsStatus.tutorlms?.active
			? { active: true, version: lmsStatus.tutorlms.version }
			: { active: false }
	);
	const [ loadingTutorlms, setLoadingTutorlms ] = useState(
		lmsStatus.tutorlms?.active || false
	);

	const [ lifterlmsStatus, setLifterlmsStatus ] = useState(
		lmsStatus.lifterlms?.active
			? { active: true, version: lmsStatus.lifterlms.version }
			: { active: false }
	);
	const [ loadingLifterlms, setLoadingLifterlms ] = useState(
		lmsStatus.lifterlms?.active || false
	);

	const [ learnpressStatus, setLearnpressStatus ] = useState(
		lmsStatus.learnpress?.active
			? { active: true, version: lmsStatus.learnpress.version }
			: { active: false }
	);
	const [ loadingLearnpress, setLoadingLearnpress ] = useState(
		lmsStatus.learnpress?.active || false
	);

	// Fetch LearnDash extended status only if LearnDash is active.
	useEffect( () => {
		if ( ! lmsStatus.learndash?.active ) {
			setLoadingLearndash( false );
			return;
		}

		const fetchLearndashStatus = async () => {
			try {
				const response = await apiFetch( {
					path: '/ppa/v1/learndash/status',
					method: 'GET',
				} );

				if ( response.success ) {
					setLearndashStatus( response.status );
					if ( response.settings ) {
						setLearndashSettings( response.settings );
					}
				}
			} catch ( error ) {
				// Keep the basic status from PHP.
			} finally {
				setLoadingLearndash( false );
			}
		};

		fetchLearndashStatus();
	}, [ lmsStatus.learndash?.active ] );

	// Fetch Tutor LMS extended status only if Tutor LMS is active.
	useEffect( () => {
		if ( ! lmsStatus.tutorlms?.active ) {
			setLoadingTutorlms( false );
			return;
		}

		const fetchTutorlmsStatus = async () => {
			try {
				const response = await apiFetch( {
					path: '/ppa/v1/tutorlms/status',
					method: 'GET',
				} );

				if ( response.success ) {
					setTutorlmsStatus( response.status );
				}
			} catch ( error ) {
				// Keep the basic status from PHP.
			} finally {
				setLoadingTutorlms( false );
			}
		};

		fetchTutorlmsStatus();
	}, [ lmsStatus.tutorlms?.active ] );

	// Fetch LifterLMS extended status only if LifterLMS is active.
	useEffect( () => {
		if ( ! lmsStatus.lifterlms?.active ) {
			setLoadingLifterlms( false );
			return;
		}

		const fetchLifterlmsStatus = async () => {
			try {
				const response = await apiFetch( {
					path: '/ppa/v1/lifterlms/status',
					method: 'GET',
				} );

				if ( response.success ) {
					setLifterlmsStatus( response.status );
				}
			} catch ( error ) {
				// Keep the basic status from PHP.
			} finally {
				setLoadingLifterlms( false );
			}
		};

		fetchLifterlmsStatus();
	}, [ lmsStatus.lifterlms?.active ] );

	// Fetch LearnPress extended status only if LearnPress is active.
	useEffect( () => {
		if ( ! lmsStatus.learnpress?.active ) {
			setLoadingLearnpress( false );
			return;
		}

		const fetchLearnpressStatus = async () => {
			try {
				const response = await apiFetch( {
					path: '/ppa/v1/learnpress/status',
					method: 'GET',
				} );

				if ( response.success ) {
					setLearnpressStatus( response.status );
				}
			} catch ( error ) {
				// Keep the basic status from PHP.
			} finally {
				setLoadingLearnpress( false );
			}
		};

		fetchLearnpressStatus();
	}, [ lmsStatus.learnpress?.active ] );

	/**
	 * Save LearnDash settings
	 */
	const handleSaveLearndashSettings = async () => {
		try {
			setSavingLearndash( true );
			await apiFetch( {
				path: '/ppa/v1/learndash/settings',
				method: 'POST',
				data: learndashSettings,
			} );
		} catch ( error ) {
			// Silently fail - settings may not save but user can retry.
		} finally {
			setSavingLearndash( false );
		}
	};

	/**
	 * Render LMS integration content based on loading and status
	 * @param {boolean}     loading                Whether status is loading.
	 * @param {Object|null} status                 LMS status object.
	 * @param {string}      notDetectedMessage     Not-detected alert title.
	 * @param {string}      notDetectedDescription Not-detected alert description.
	 * @param {Object|null} extraContent           Extra content to render when active.
	 */
	const renderLmsContent = (
		loading,
		status,
		notDetectedMessage,
		notDetectedDescription,
		extraContent = null
	) => {
		if ( loading ) {
			return (
				<div style={ { padding: '16px', textAlign: 'center' } }>
					<Spin size="small" />
				</div>
			);
		}

		if ( status?.active ) {
			return (
				<>
					<Descriptions
						column={ 1 }
						size="small"
						style={ { marginTop: 12 } }
					>
						<Descriptions.Item
							label={ __( 'Status', 'pressprimer-assignment' ) }
						>
							<Tag
								color="success"
								icon={ <CheckCircleOutlined /> }
							>
								{ __( 'Active', 'pressprimer-assignment' ) }
							</Tag>
						</Descriptions.Item>
						<Descriptions.Item
							label={ __( 'Version', 'pressprimer-assignment' ) }
						>
							{ status.version }
						</Descriptions.Item>
						<Descriptions.Item
							label={ __(
								'Integration',
								'pressprimer-assignment'
							) }
						>
							<Tag color="blue">
								{ __( 'Working', 'pressprimer-assignment' ) }
							</Tag>
						</Descriptions.Item>
						{ status.attached_assignments > 0 && (
							<Descriptions.Item
								label={ __(
									'Attached Assignments',
									'pressprimer-assignment'
								) }
							>
								{ status.attached_assignments }
							</Descriptions.Item>
						) }
					</Descriptions>
					{ extraContent }
				</>
			);
		}

		return (
			<Alert
				message={ notDetectedMessage }
				description={ notDetectedDescription }
				type="info"
				showIcon
				style={ { marginTop: 12 } }
			/>
		);
	};

	return (
		<div>
			{ /* LMS Integrations Section */ }
			<div className="ppa-settings-section">
				<Title level={ 4 } className="ppa-settings-section-title">
					{ __( 'LMS Integrations', 'pressprimer-assignment' ) }
				</Title>
				<Paragraph className="ppa-settings-section-description">
					{ __(
						'Connect with popular Learning Management Systems.',
						'pressprimer-assignment'
					) }
				</Paragraph>

				{ /* LearnDash */ }
				<div className="ppa-lms-integration">
					<div className="ppa-lms-integration-header">
						<Text strong>LearnDash</Text>
					</div>

					{ renderLmsContent(
						loadingLearndash,
						learndashStatus,
						__(
							'LearnDash Not Detected',
							'pressprimer-assignment'
						),
						__(
							'Install and activate LearnDash to enable this integration. Once active, you can attach PressPrimer assignments to lessons and topics.',
							'pressprimer-assignment'
						),
						<Collapse
							style={ { marginTop: 16 } }
							items={ [
								{
									key: 'settings',
									label: (
										<span>
											<SettingOutlined
												style={ {
													marginRight: 8,
												} }
											/>
											{ __(
												'LearnDash Settings',
												'pressprimer-assignment'
											) }
										</span>
									),
									children: (
										<div className="ppa-learndash-settings">
											<Form.Item
												label={ __(
													'Message shown when the assignment is locked until all topics are complete',
													'pressprimer-assignment'
												) }
												style={ {
													marginBottom: 16,
												} }
											>
												<Input.TextArea
													rows={ 2 }
													value={
														learndashSettings.restriction_message
													}
													onChange={ ( e ) =>
														setLearndashSettings( {
															...learndashSettings,
															restriction_message:
																e.target.value,
														} )
													}
													placeholder={ __(
														'Complete all topics in this lesson to unlock the assignment.',
														'pressprimer-assignment'
													) }
												/>
												<Paragraph
													type="secondary"
													style={ {
														marginTop: 8,
														marginBottom: 0,
													} }
												>
													{ __(
														'This message appears on lesson pages when the assignment is restricted until all topics are completed. Leave blank to use the default message.',
														'pressprimer-assignment'
													) }
												</Paragraph>
											</Form.Item>
											<Button
												type="primary"
												onClick={
													handleSaveLearndashSettings
												}
												loading={ savingLearndash }
											>
												{ __(
													'Save Settings',
													'pressprimer-assignment'
												) }
											</Button>
										</div>
									),
								},
							] }
						/>
					) }
				</div>

				{ /* Tutor LMS */ }
				<div
					className="ppa-lms-integration"
					style={ { marginTop: 24 } }
				>
					<div className="ppa-lms-integration-header">
						<Text strong>Tutor LMS</Text>
					</div>

					{ renderLmsContent(
						loadingTutorlms,
						tutorlmsStatus,
						__(
							'Tutor LMS Not Detected',
							'pressprimer-assignment'
						),
						__(
							'Install and activate Tutor LMS to enable this integration. Once active, you can attach PressPrimer assignments to lessons.',
							'pressprimer-assignment'
						)
					) }
				</div>

				{ /* LifterLMS */ }
				<div
					className="ppa-lms-integration"
					style={ { marginTop: 24 } }
				>
					<div className="ppa-lms-integration-header">
						<Text strong>LifterLMS</Text>
					</div>

					{ renderLmsContent(
						loadingLifterlms,
						lifterlmsStatus,
						__(
							'LifterLMS Not Detected',
							'pressprimer-assignment'
						),
						__(
							'Install and activate LifterLMS to enable this integration. Once active, you can attach PressPrimer assignments to lessons and courses.',
							'pressprimer-assignment'
						)
					) }
				</div>

				{ /* LearnPress */ }
				<div
					className="ppa-lms-integration"
					style={ { marginTop: 24 } }
				>
					<div className="ppa-lms-integration-header">
						<Text strong>LearnPress</Text>
					</div>

					{ renderLmsContent(
						loadingLearnpress,
						learnpressStatus,
						__(
							'LearnPress Not Detected',
							'pressprimer-assignment'
						),
						__(
							'Install and activate LearnPress to enable this integration. Once active, you can attach PressPrimer assignments to lessons and courses.',
							'pressprimer-assignment'
						)
					) }
				</div>
			</div>
		</div>
	);
};

export default IntegrationsTab;
