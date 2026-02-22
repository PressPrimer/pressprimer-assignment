/**
 * Assignment Form Component
 *
 * Tabbed form for creating and editing assignments.
 * Tabs: Basic Info, Scoring, Submissions, File Settings.
 *
 * @package
 * @since 1.0.0
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Form,
	Input,
	Select,
	InputNumber,
	Switch,
	Button,
	Tabs,
	Card,
	Space,
	message,
} from 'antd';
import {
	SaveOutlined,
	SendOutlined,
	ArrowLeftOutlined,
} from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';
import { assignmentsApi } from '../../api';
import RichTextEditor from '../../components/RichTextEditor';

const { TextArea } = Input;
const { Option } = Select;

/**
 * Default file type options.
 */
const FILE_TYPE_OPTIONS = [
	{ value: 'pdf', label: 'PDF' },
	{ value: 'docx', label: 'DOCX' },
	{ value: 'doc', label: 'DOC' },
	{ value: 'txt', label: 'TXT' },
	{ value: 'rtf', label: 'RTF' },
	{ value: 'jpg', label: 'JPG' },
	{ value: 'jpeg', label: 'JPEG' },
	{ value: 'png', label: 'PNG' },
	{ value: 'gif', label: 'GIF' },
];

/**
 * AssignmentForm component.
 *
 * @param {Object}      props             Component props.
 * @param {Object|null} props.initialData Initial assignment data for editing.
 * @param {boolean}     props.isEdit      Whether this is an edit form.
 * @return {JSX.Element} Assignment form.
 */
export default function AssignmentForm( {
	initialData = null,
	isEdit = false,
} ) {
	const navigate = useNavigate();
	const [ form ] = Form.useForm();
	const [ saving, setSaving ] = useState( false );

	const allowResubmission = Form.useWatch( 'allow_resubmission', form );

	/**
	 * Prepare form data for API submission.
	 *
	 * @param {Object} values Form values.
	 * @param {string} status Assignment status.
	 * @return {Object} API-ready data.
	 */
	const prepareData = ( values, status ) => {
		const data = {
			...values,
			status,
		};

		// Convert allowed_file_types array to JSON.
		if ( Array.isArray( data.allowed_file_types ) ) {
			data.allowed_file_types = JSON.stringify( data.allowed_file_types );
		}

		// Convert switch boolean to integer.
		data.allow_resubmission = data.allow_resubmission ? 1 : 0;

		return data;
	};

	/**
	 * Save the assignment.
	 *
	 * @param {string} status Status to save with (draft or published).
	 */
	const handleSave = async ( status ) => {
		try {
			const values = await form.validateFields();
			setSaving( true );

			const data = prepareData( values, status );

			if ( isEdit && initialData?.id ) {
				await assignmentsApi.update( initialData.id, data );
				message.success(
					__( 'Assignment updated.', 'pressprimer-assignment' )
				);
			} else {
				const response = await assignmentsApi.create( data );
				message.success(
					__( 'Assignment created.', 'pressprimer-assignment' )
				);
				// Navigate to edit mode after creation.
				navigate( `/assignments/${ response.id }/edit`, {
					replace: true,
				} );
			}
		} catch ( error ) {
			if ( error?.errorFields ) {
				// Form validation error - switch to the tab with the first error.
				return;
			}
			message.error(
				error.message ||
					__( 'Failed to save assignment.', 'pressprimer-assignment' )
			);
		} finally {
			setSaving( false );
		}
	};

	/**
	 * Get initial form values.
	 *
	 * @return {Object} Default or existing assignment values.
	 */
	const getInitialValues = () => {
		if ( initialData ) {
			return {
				...initialData,
				allow_resubmission: !! initialData.allow_resubmission,
				allowed_file_types: initialData.allowed_file_types
					? JSON.parse( initialData.allowed_file_types )
					: [
							'pdf',
							'docx',
							'doc',
							'txt',
							'rtf',
							'jpg',
							'jpeg',
							'png',
							'gif',
					  ],
			};
		}

		return {
			title: '',
			description: '',
			instructions: '',
			grading_guidelines: '',
			max_points: 100,
			passing_score: 60,
			allow_resubmission: false,
			max_resubmissions: 1,
			allowed_file_types: [
				'pdf',
				'docx',
				'doc',
				'txt',
				'rtf',
				'jpg',
				'jpeg',
				'png',
				'gif',
			],
			max_file_size: 10485760,
			max_files: 5,
		};
	};

	/**
	 * Tab items configuration.
	 */
	const tabItems = [
		{
			key: 'basic',
			label: __( 'Basic Info', 'pressprimer-assignment' ),
			children: (
				<Card>
					<Form.Item
						name="title"
						label={ __( 'Title', 'pressprimer-assignment' ) }
						rules={ [
							{
								required: true,
								message: __(
									'Please enter a title.',
									'pressprimer-assignment'
								),
							},
						] }
					>
						<Input
							style={ { width: 300 } }
							placeholder={ __(
								'Assignment title',
								'pressprimer-assignment'
							) }
						/>
					</Form.Item>

					<Form.Item
						name="description"
						label={ __( 'Description', 'pressprimer-assignment' ) }
						tooltip={ __(
							'Short description shown in assignment listings.',
							'pressprimer-assignment'
						) }
					>
						<TextArea
							rows={ 3 }
							style={ { maxWidth: 500 } }
							placeholder={ __(
								'Brief description of this assignment',
								'pressprimer-assignment'
							) }
						/>
					</Form.Item>

					<Form.Item
						name="instructions"
						label={ __( 'Instructions', 'pressprimer-assignment' ) }
						tooltip={ __(
							'Detailed instructions shown to students.',
							'pressprimer-assignment'
						) }
					>
						<RichTextEditor
							placeholder={ __(
								'Detailed assignment instructions\u2026',
								'pressprimer-assignment'
							) }
							rows={ 8 }
						/>
					</Form.Item>

					<Form.Item
						name="grading_guidelines"
						label={ __(
							'Grading Guidelines',
							'pressprimer-assignment'
						) }
						tooltip={ __(
							'Internal guidelines for graders. Not shown to students.',
							'pressprimer-assignment'
						) }
					>
						<RichTextEditor
							placeholder={ __(
								'Guidelines for grading this assignment\u2026',
								'pressprimer-assignment'
							) }
							rows={ 6 }
						/>
					</Form.Item>
				</Card>
			),
		},
		{
			key: 'scoring',
			label: __( 'Scoring', 'pressprimer-assignment' ),
			children: (
				<Card>
					<Form.Item
						name="max_points"
						label={ __( 'Max Points', 'pressprimer-assignment' ) }
						tooltip={ __(
							'Maximum points for this assignment.',
							'pressprimer-assignment'
						) }
					>
						<InputNumber
							min={ 0.01 }
							max={ 100000 }
							step={ 1 }
							style={ { width: 150 } }
						/>
					</Form.Item>

					<Form.Item
						name="passing_score"
						label={ __(
							'Passing Score',
							'pressprimer-assignment'
						) }
						tooltip={ __(
							'Minimum score required to pass.',
							'pressprimer-assignment'
						) }
					>
						<InputNumber
							min={ 0 }
							max={ 100000 }
							step={ 1 }
							style={ { width: 150 } }
						/>
					</Form.Item>
				</Card>
			),
		},
		{
			key: 'submissions',
			label: __( 'Submissions', 'pressprimer-assignment' ),
			children: (
				<Card>
					<Form.Item
						name="allow_resubmission"
						label={ __(
							'Allow Resubmission',
							'pressprimer-assignment'
						) }
						valuePropName="checked"
					>
						<Switch />
					</Form.Item>

					{ allowResubmission && (
						<Form.Item
							name="max_resubmissions"
							label={ __(
								'Max Resubmissions',
								'pressprimer-assignment'
							) }
							tooltip={ __(
								'Maximum number of times a student can resubmit.',
								'pressprimer-assignment'
							) }
						>
							<InputNumber
								min={ 1 }
								max={ 100 }
								style={ { width: 150 } }
							/>
						</Form.Item>
					) }
				</Card>
			),
		},
		{
			key: 'files',
			label: __( 'File Settings', 'pressprimer-assignment' ),
			children: (
				<Card>
					<Form.Item
						name="allowed_file_types"
						label={ __(
							'Allowed File Types',
							'pressprimer-assignment'
						) }
						tooltip={ __(
							'File extensions students are allowed to upload.',
							'pressprimer-assignment'
						) }
					>
						<Select
							mode="multiple"
							style={ { width: 300 } }
							placeholder={ __(
								'Select file types',
								'pressprimer-assignment'
							) }
							options={ FILE_TYPE_OPTIONS }
						/>
					</Form.Item>

					<Form.Item
						name="max_file_size"
						label={ __(
							'Max File Size',
							'pressprimer-assignment'
						) }
						tooltip={ __(
							'Maximum size per uploaded file.',
							'pressprimer-assignment'
						) }
					>
						<Select style={ { width: 300 } }>
							<Option value={ 1048576 }>1 MB</Option>
							<Option value={ 2097152 }>2 MB</Option>
							<Option value={ 5242880 }>5 MB</Option>
							<Option value={ 10485760 }>10 MB</Option>
							<Option value={ 20971520 }>20 MB</Option>
							<Option value={ 52428800 }>50 MB</Option>
							<Option value={ 104857600 }>100 MB</Option>
						</Select>
					</Form.Item>

					<Form.Item
						name="max_files"
						label={ __(
							'Max Files Per Submission',
							'pressprimer-assignment'
						) }
						tooltip={ __(
							'Maximum number of files a student can upload per submission.',
							'pressprimer-assignment'
						) }
					>
						<InputNumber
							min={ 1 }
							max={ 50 }
							style={ { width: 150 } }
						/>
					</Form.Item>
				</Card>
			),
		},
	];

	return (
		<div className="ppa-assignment-form">
			<div
				className="ppa-page-header"
				style={ {
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
					marginBottom: 16,
				} }
			>
				<Space>
					<Button
						icon={ <ArrowLeftOutlined /> }
						onClick={ () => navigate( '/assignments' ) }
					>
						{ __( 'Back', 'pressprimer-assignment' ) }
					</Button>
					<h2 style={ { margin: 0 } }>
						{ isEdit
							? __( 'Edit Assignment', 'pressprimer-assignment' )
							: __( 'New Assignment', 'pressprimer-assignment' ) }
					</h2>
				</Space>
				<Space>
					<Button
						icon={ <SaveOutlined /> }
						loading={ saving }
						onClick={ () => handleSave( 'draft' ) }
					>
						{ __( 'Save as Draft', 'pressprimer-assignment' ) }
					</Button>
					<Button
						type="primary"
						icon={ <SendOutlined /> }
						loading={ saving }
						onClick={ () => handleSave( 'published' ) }
					>
						{ __( 'Publish', 'pressprimer-assignment' ) }
					</Button>
				</Space>
			</div>

			<Form
				form={ form }
				layout="vertical"
				initialValues={ getInitialValues() }
				autoComplete="off"
			>
				<Tabs items={ tabItems } />
			</Form>
		</div>
	);
}
