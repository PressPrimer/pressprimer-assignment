/**
 * Assignment Editor - Main Component
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import dayjs from 'dayjs';
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
	CopyOutlined,
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
// Default prefilled penalty tier — the simple case looks like a flat rate.
const DEFAULT_TIER = { amount: 1, unit: 'days', penalty: 10 };

/**
 * Convert stored hours into a repeater row's amount + unit.
 *
 * Whole multiples of 24 present as days; anything else as hours. A null
 * threshold (the "any lateness" flat case) presents as an empty amount.
 *
 * @param {number|string|null} hours Stored hour count.
 * @return {Object} { amount, unit }.
 */
const hoursToAmountUnit = ( hours ) => {
	const numeric = parseFloat( hours );
	if ( isNaN( numeric ) || numeric <= 0 ) {
		return { amount: null, unit: 'days' };
	}
	if ( numeric >= 24 && numeric % 24 === 0 ) {
		return { amount: numeric / 24, unit: 'days' };
	}
	return { amount: numeric, unit: 'hours' };
};

const AssignmentEditor = ( { assignmentData = {} } ) => {
	const [ form ] = Form.useForm();
	const [ saving, setSaving ] = useState( false );
	const [ duplicating, setDuplicating ] = useState( false );
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

			// Late policy (2.2): map the stored schedule into repeater rows.
			const schedule = assignmentData.late_penalty_schedule || null;
			const cutoffRow =
				schedule && schedule.cutoff_hours
					? hoursToAmountUnit( schedule.cutoff_hours )
					: { amount: 7, unit: 'days' };

			fieldValues.due_at = assignmentData.due_at
				? dayjs( assignmentData.due_at )
				: null;
			fieldValues.late_policy = assignmentData.late_policy || 'accept';
			fieldValues.late_penalty_tiers =
				schedule &&
				Array.isArray( schedule.tiers ) &&
				schedule.tiers.length
					? schedule.tiers.map( ( tier ) => ( {
							...hoursToAmountUnit( tier.late_by_hours ),
							penalty: parseFloat( tier.penalty_percent ),
					  } ) )
					: [ { ...DEFAULT_TIER } ];
			fieldValues.late_penalty_basis =
				schedule && schedule.basis === 'raw_score'
					? 'raw_score'
					: 'max_points';
			fieldValues.late_cutoff_enabled = !! (
				schedule && schedule.cutoff_hours
			);
			fieldValues.late_cutoff_amount = cutoffRow.amount;
			fieldValues.late_cutoff_unit = cutoffRow.unit;

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

			// Prepare payload — exclude rubric_enabled (managed by Educator
			// endpoints) and the late-policy helper fields (collapsed into
			// one structured schedule object below).
			const {
				rubric_enabled: rubricEnabledValue,
				late_penalty_tiers: latePenaltyTiers,
				late_penalty_basis: latePenaltyBasis,
				late_cutoff_enabled: lateCutoffEnabled,
				late_cutoff_amount: lateCutoffAmount,
				late_cutoff_unit: lateCutoffUnit,
				...rest
			} = values;

			const toHours = ( amount, unit ) =>
				unit === 'days' ? amount * 24 : amount;

			const payload = {
				...rest,
				due_at: values.due_at
					? values.due_at.format( 'YYYY-MM-DD HH:mm:ss' )
					: null,
				late_policy: values.late_policy || 'accept',
				allow_resubmission: values.allow_resubmission ? 1 : 0,
				max_resubmissions: values.allow_resubmission
					? values.max_resubmissions
					: 0,
				ai_auto_grade: values.ai_auto_grade ? 1 : 0,
				categories: selectedCategories,
			};

			// The schedule is sent only under the penalty policy; other
			// policies leave any stored schedule untouched so switching
			// back to "Apply a penalty" restores the previous tiers.
			if ( values.late_policy === 'penalty' ) {
				payload.late_penalty_schedule =
					Array.isArray( latePenaltyTiers ) && latePenaltyTiers.length
						? {
								tiers: latePenaltyTiers.map( ( tier ) => ( {
									late_by_hours:
										tier.amount === null ||
										tier.amount === undefined
											? null
											: toHours( tier.amount, tier.unit ),
									penalty_percent: tier.penalty,
								} ) ),
								cutoff_hours:
									lateCutoffEnabled && lateCutoffAmount
										? toHours(
												lateCutoffAmount,
												lateCutoffUnit
										  )
										: null,
								basis: latePenaltyBasis || 'max_points',
						  }
						: null;
			}

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

			// Update state and URL immediately after creation so that
			// subsequent saves use PUT (update) instead of POST (create),
			// even if the rubric save below fails.
			if ( ! assignmentId && response.id ) {
				setCurrentId( response.id );
				window.history.replaceState(
					{},
					'',
					`${ window.pressprimerAssignmentAdmin.adminUrl }admin.php?page=pressprimer-assignment-assignments&action=edit&assignment=${ response.id }`
				);
			}

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

			// Addon hook: callbacks registered on window.PPAEditorAfterSave
			// run after the main save resolves, so addons can persist
			// their own per-assignment settings (e.g., annotation toggles)
			// using the saved assignment ID. A callback throwing only
			// surfaces an inline error — the main assignment save has
			// already succeeded by this point.
			if ( savedId && Array.isArray( window.PPAEditorAfterSave ) ) {
				for ( const callback of window.PPAEditorAfterSave ) {
					if ( 'function' !== typeof callback ) {
						continue;
					}
					try {
						await callback( { id: savedId, values } );
					} catch ( addonError ) {
						message.error(
							addonError?.message ||
								__(
									'An addon failed to save its settings.',
									'pressprimer-assignment'
								)
						);
					}
				}
			}

			message.success(
				__( 'Assignment saved successfully!', 'pressprimer-assignment' )
			);
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

		// Show a viewport-pinned toast so the user sees feedback even
		// when they clicked Save from the bottom of a long form. The
		// inline field errors remain visible after the tab switch, so
		// the user can scroll up to see exactly what needs fixing.
		message.error(
			__(
				'Could not save: please correct the highlighted fields and try again.',
				'pressprimer-assignment'
			)
		);

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
	 * Handle Duplicate toolbar action.
	 *
	 * Calls the REST duplicate endpoint and navigates the user to the new
	 * draft's edit screen. Disabled when the assignment is unsaved (no ID).
	 */
	const handleDuplicate = async () => {
		if ( ! currentId ) {
			return;
		}

		try {
			setDuplicating( true );

			const response = await apiFetch( {
				path: `/ppa/v1/assignments/${ currentId }/duplicate`,
				method: 'POST',
			} );

			if ( response && response.edit_url ) {
				window.location.href = response.edit_url;
				return;
			}

			message.error(
				__(
					'Duplicate succeeded but the response was malformed.',
					'pressprimer-assignment'
				)
			);
			setDuplicating( false );
		} catch ( error ) {
			message.error(
				error.message ||
					__(
						'Failed to duplicate assignment.',
						'pressprimer-assignment'
					)
			);
			setDuplicating( false );
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
						due_at: null,
						late_policy: 'accept',
						late_penalty_tiers: [ { ...DEFAULT_TIER } ],
						late_penalty_basis: 'max_points',
						late_cutoff_enabled: false,
						late_cutoff_amount: 7,
						late_cutoff_unit: 'days',
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
										icon={ <CopyOutlined /> }
										onClick={ handleDuplicate }
										disabled={ isNew || saving }
										loading={ duplicating }
									>
										{ __(
											'Duplicate',
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
							icon={ <CopyOutlined /> }
							onClick={ handleDuplicate }
							disabled={ isNew || saving }
							loading={ duplicating }
							size="large"
						>
							{ __( 'Duplicate', 'pressprimer-assignment' ) }
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
