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
import CategoriesPanel from './CategoriesPanel';

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
	const [ selectedCategories, setSelectedCategories ] = useState(
		assignmentData.categories || []
	);
	const [ rubricData, setRubricData ] = useState( null );

	// Defaults from plugin settings (provided for new assignments).
	const defaults = assignmentData.defaults || {};

	const isNew = ! currentId;

	// Initialize form with assignment data.
	useEffect( () => {
		if ( assignmentData.id ) {
			// wp_localize_script converts all values to strings,
			// so parse numeric/boolean fields explicitly.
			const allowResub =
				parseInt( assignmentData.allow_resubmission, 10 ) === 1;
			const maxResub = parseInt( assignmentData.max_resubmissions, 10 );

			const rubricEnabled =
				parseInt( assignmentData.rubric_enabled, 10 ) === 1;

			const aiAutoGrade =
				parseInt( assignmentData.ai_auto_grade, 10 ) === 1;

			const fieldValues = {
				title: assignmentData.title || '',
				description: assignmentData.description || '',
				instructions: assignmentData.instructions || '',
				grading_guidelines: assignmentData.grading_guidelines || '',
				status: assignmentData.status || 'draft',
				theme: assignmentData.theme || 'default',
				submission_type: assignmentData.submission_type || 'file',
				max_points: parseFloat( assignmentData.max_points ) || 100,
				passing_score: parseFloat( assignmentData.passing_score ) || 60,
				allow_resubmission: allowResub,
				max_resubmissions: isNaN( maxResub ) ? 1 : maxResub,
				notification_email: assignmentData.notification_email || '',
				max_file_size:
					parseInt( assignmentData.max_file_size, 10 ) || 5242880,
				max_files: parseInt( assignmentData.max_files, 10 ) || 5,
				allowed_file_types: assignmentData.allowed_file_types || [
					'pdf',
					'docx',
					'txt',
					'rtf',
					'odt',
					'jpg',
					'jpeg',
					'png',
					'gif',
				],
				rubric_enabled: rubricEnabled,
				ai_auto_grade: aiAutoGrade,
			};

			form.setFieldsValue( fieldValues );

			// Initialize rubric data from existing rubric structure.
			if ( assignmentData.rubric ) {
				setRubricData( assignmentData.rubric );
			}
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

			// Prepare payload — exclude rubric_enabled (managed by Educator endpoints).
			const { rubric_enabled: rubricEnabledValue, ...rest } = values;
			const payload = {
				...rest,
				allow_resubmission: values.allow_resubmission ? 1 : 0,
				max_resubmissions: values.allow_resubmission
					? values.max_resubmissions
					: 0,
				ai_auto_grade: values.ai_auto_grade ? 1 : 0,
				categories: selectedCategories,
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

			// Resolve the saved assignment ID.
			const savedId = assignmentId || response.id;

			// Save or delete rubric via Educator endpoints (if addon is active).
			if (
				savedId &&
				window.pressprimerAssignmentAdmin?.addons?.educator
			) {
				if ( rubricEnabledValue && rubricData ) {
					await apiFetch( {
						path: `/ppae/v1/assignments/${ savedId }/rubric`,
						method: 'POST',
						data: { criteria: rubricData },
					} );
				} else if ( ! rubricEnabledValue ) {
					// Delete rubric (sets rubric_enabled = 0).
					await apiFetch( {
						path: `/ppae/v1/assignments/${ savedId }/rubric`,
						method: 'DELETE',
					} ).catch( () => {
						// Ignore 404 — no rubric to delete.
					} );
				}
			}

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
	 * Handle validation failure — switch to the tab containing the first error.
	 *
	 * @param {Object} errorInfo             Ant Design validation error info.
	 * @param {Array}  errorInfo.errorFields Array of fields that failed validation.
	 */
	const handleFinishFailed = ( { errorFields } ) => {
		if ( ! errorFields || errorFields.length === 0 ) {
			return;
		}

		// Fields that live on the settings tab.
		const settingsFields = [
			'title',
			'description',
			'instructions',
			'grading_guidelines',
			'status',
			'theme',
			'submission_type',
			'max_points',
			'passing_score',
			'allow_resubmission',
			'max_resubmissions',
			'notification_email',
			'rubric_enabled',
			'ai_auto_grade',
		];

		const firstFieldName = errorFields[ 0 ].name[ 0 ];

		if ( settingsFields.includes( firstFieldName ) ) {
			setActiveTab( 'settings' );
		} else {
			setActiveTab( 'file-settings' );
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
			children: (
				<SettingsPanel
					form={ form }
					rubricData={ rubricData }
					onRubricDataChange={ setRubricData }
					onRubricTotalChange={ ( total ) => {
						// Sync assignment max_points to rubric total when rubric is enabled.
						if ( total > 0 ) {
							form.setFieldValue( 'max_points', total );
						}
					} }
				/>
			),
		},
		{
			key: 'file-settings',
			label: __( 'File Settings', 'pressprimer-assignment' ),
			children: <FileSettingsPanel form={ form } />,
		},
		{
			key: 'categories',
			label: __( 'Categories', 'pressprimer-assignment' ),
			children: (
				<CategoriesPanel
					categories={ selectedCategories }
					onCategoriesChange={ setSelectedCategories }
					availableCategories={
						assignmentData.availableCategories || []
					}
					availableTags={ assignmentData.availableTags || [] }
				/>
			),
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
					onFinishFailed={ handleFinishFailed }
					initialValues={ {
						title: '',
						description: '',
						instructions: '',
						grading_guidelines: '',
						status: 'draft',
						theme: 'default',
						submission_type: 'file',
						max_points: 100,
						passing_score: defaults.passing_score || 60,
						allow_resubmission: false,
						max_resubmissions: 1,
						notification_email: '',
						max_file_size: defaults.max_file_size || 5242880,
						max_files: defaults.max_files || 5,
						allowed_file_types: [
							'pdf',
							'docx',
							'txt',
							'rtf',
							'odt',
							'jpg',
							'jpeg',
							'png',
							'gif',
						],
						rubric_enabled: false,
						ai_auto_grade: false,
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
