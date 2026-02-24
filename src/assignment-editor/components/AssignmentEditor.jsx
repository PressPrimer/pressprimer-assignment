/**
 * Assignment Editor - Main Component
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Form,
	Button,
	message,
	Spin,
	Space,
	Typography,
	Alert,
	Divider,
	Tabs,
} from 'antd';
import {
	SaveOutlined,
	CloseOutlined,
	QuestionCircleOutlined,
} from '@ant-design/icons';

import SettingsPanel from './SettingsPanel';
import FileSettingsPanel from './FileSettingsPanel';

const { Title, Paragraph } = Typography;

/**
 * Main Assignment Editor Component
 *
 * @param {Object} props                Component props.
 * @param {Object} props.assignmentData Initial assignment data from wp_localize_script.
 */
const AssignmentEditor = ( { assignmentData = {} } ) => {
	const [ form ] = Form.useForm();
	const [ saving, setSaving ] = useState( false );
	const [ currentId, setCurrentId ] = useState( assignmentData.id || null );
	const [ activeTab, setActiveTab ] = useState( 'settings' );

	const isNew = ! currentId;

	// Initialize form with assignment data.
	useEffect( () => {
		if ( assignmentData.id ) {
			form.setFieldsValue( {
				title: assignmentData.title || '',
				description: assignmentData.description || '',
				instructions: assignmentData.instructions || '',
				grading_guidelines: assignmentData.grading_guidelines || '',
				status: assignmentData.status || 'draft',
				submission_type: assignmentData.submission_type || 'file',
				max_points: assignmentData.max_points || 100,
				passing_score: assignmentData.passing_score || 60,
				allow_resubmission: !! assignmentData.allow_resubmission,
				max_resubmissions: assignmentData.max_resubmissions || 1,
				max_file_size:
					parseInt( assignmentData.max_file_size, 10 ) || 5242880,
				max_files: assignmentData.max_files || 5,
				allowed_file_types: assignmentData.allowed_file_types || [
					'pdf',
					'docx',
					'doc',
					'txt',
					'rtf',
					'odt',
					'jpg',
					'jpeg',
					'png',
					'gif',
				],
			} );
		}
	}, [ assignmentData, form ] );

	/**
	 * Handle form submission.
	 *
	 * @param {Object} values Form values.
	 */
	const handleSubmit = async ( values ) => {
		try {
			setSaving( true );

			const assignmentId = currentId || assignmentData.id;

			// Prepare payload.
			const payload = {
				...values,
				allow_resubmission: values.allow_resubmission ? 1 : 0,
			};

			// Submit via REST API.
			const endpoint = assignmentId
				? `/ppa/v1/assignments/${ assignmentId }`
				: '/ppa/v1/assignments';

			const method = assignmentId ? 'PUT' : 'POST';

			const response = await apiFetch( {
				path: endpoint,
				method,
				data: payload,
			} );

			message.success(
				__( 'Assignment saved successfully!', 'pressprimer-assignment' )
			);

			// Update URL if this was a new assignment.
			if ( ! assignmentId && response.id ) {
				window.history.replaceState(
					{},
					'',
					`${ window.pressprimerAssignmentAdmin.adminUrl }admin.php?page=pressprimer-assignment-assignments&action=edit&assignment=${ response.id }`
				);
				setCurrentId( response.id );
			}
		} catch ( error ) {
			message.error(
				error.message ||
					__( 'Failed to save assignment.', 'pressprimer-assignment' )
			);
		} finally {
			setSaving( false );
		}
	};

	/**
	 * Handle cancel.
	 */
	const handleCancel = () => {
		if (
			// eslint-disable-next-line no-alert -- Standard WordPress confirm pattern per Quiz plugin.
			window.confirm(
				__(
					'Are you sure you want to cancel? Any unsaved changes will be lost.',
					'pressprimer-assignment'
				)
			)
		) {
			window.location.href = window.pressprimerAssignmentAdmin.listUrl;
		}
	};

	const tabItems = [
		{
			key: 'settings',
			label: __( 'Settings', 'pressprimer-assignment' ),
			children: <SettingsPanel form={ form } />,
		},
		{
			key: 'file-settings',
			label: __( 'File Settings', 'pressprimer-assignment' ),
			children: <FileSettingsPanel form={ form } />,
		},
	];

	return (
		<div className="ppa-assignment-editor-container">
			<Spin
				spinning={ saving }
				tip={ __( 'Saving assignment…', 'pressprimer-assignment' ) }
			>
				<Form
					form={ form }
					layout="vertical"
					onFinish={ handleSubmit }
					initialValues={ {
						title: '',
						description: '',
						instructions: '',
						grading_guidelines: '',
						status: 'draft',
						submission_type: 'file',
						max_points: 100,
						passing_score: 60,
						allow_resubmission: false,
						max_resubmissions: 1,
						max_file_size: 5242880,
						max_files: 5,
						allowed_file_types: [
							'pdf',
							'docx',
							'doc',
							'txt',
							'rtf',
							'odt',
							'jpg',
							'jpeg',
							'png',
							'gif',
						],
					} }
				>
					{ /* Header */ }
					<div className="ppa-editor-header">
						<Space direction="vertical" style={ { width: '100%' } }>
							<div
								style={ {
									display: 'flex',
									justifyContent: 'space-between',
									alignItems: 'center',
								} }
							>
								<Title level={ 2 } style={ { margin: 0 } }>
									{ isNew
										? __(
												'Create New Assignment',
												'pressprimer-assignment'
										  )
										: __(
												'Edit Assignment',
												'pressprimer-assignment'
										  ) }
								</Title>
								<Space>
									<Button
										icon={ <CloseOutlined /> }
										onClick={ handleCancel }
									>
										{ __(
											'Cancel',
											'pressprimer-assignment'
										) }
									</Button>
									<Button
										type="primary"
										icon={ <SaveOutlined /> }
										htmlType="submit"
										loading={ saving }
										size="large"
									>
										{ __(
											'Save Assignment',
											'pressprimer-assignment'
										) }
									</Button>
								</Space>
							</div>

							<Alert
								message={ __(
									'Assignment Editor Guide',
									'pressprimer-assignment'
								) }
								description={
									<>
										<Paragraph
											style={ { marginBottom: 8 } }
										>
											{ __(
												'Create assignments for your students. Configure settings, set grading criteria, and manage file upload requirements.',
												'pressprimer-assignment'
											) }
										</Paragraph>
										<Paragraph
											style={ { marginBottom: 0 } }
										>
											<strong>
												{ __(
													'Pro Tip:',
													'pressprimer-assignment'
												) }
											</strong>{ ' ' }
											{ __(
												'Use the Settings tab to configure grading and submission options, and the File Settings tab to control which file types and sizes students can upload.',
												'pressprimer-assignment'
											) }
										</Paragraph>
									</>
								}
								type="info"
								icon={ <QuestionCircleOutlined /> }
								showIcon
								closable
							/>
						</Space>
					</div>

					<Divider />

					{ /* Tabbed Content */ }
					<Tabs
						activeKey={ activeTab }
						onChange={ setActiveTab }
						items={ tabItems }
						size="large"
					/>

					{ /* Bottom Action Buttons */ }
					<div
						style={ {
							background: '#fff',
							padding: '20px 24px',
							borderRadius: 8,
							marginTop: 20,
							display: 'flex',
							justifyContent: 'flex-end',
							gap: 12,
							boxShadow: '0 2px 8px rgba(0, 0, 0, 0.06)',
						} }
					>
						<Button
							icon={ <CloseOutlined /> }
							onClick={ handleCancel }
							size="large"
						>
							{ __( 'Cancel', 'pressprimer-assignment' ) }
						</Button>
						<Button
							type="primary"
							icon={ <SaveOutlined /> }
							htmlType="submit"
							loading={ saving }
							size="large"
						>
							{ __(
								'Save Assignment',
								'pressprimer-assignment'
							) }
						</Button>
					</div>
				</Form>
			</Spin>
		</div>
	);
};

export default AssignmentEditor;
