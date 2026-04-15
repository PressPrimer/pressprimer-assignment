/**
 * Assignment Settings Panel Component
 *
 * @package
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	Form,
	Input,
	InputNumber,
	Select,
	Switch,
	Card,
	Checkbox,
	Divider,
	Space,
	Col,
	Row,
	Typography,
	Tooltip,
} from 'antd';
import {
	QuestionCircleOutlined,
	TrophyOutlined,
	FileTextOutlined,
	EditOutlined,
	MailOutlined,
	FormatPainterOutlined,
} from '@ant-design/icons';

const { TextArea } = Input;
const { Title } = Typography;

// Rubric editor is registered globally by the Educator addon.
const RubricEditor = window.PPAERubricEditor || null;

// Check if the Educator addon is active.
const educatorActive =
	window.pressprimerAssignmentAdmin?.addons?.educator || false;

/**
 * Settings Panel Component
 *
 * @param {Object}   props                     Component props.
 * @param {Object}   props.form                Ant Design form instance.
 * @param {Object}   props.rubricData          Current rubric criteria data.
 * @param {Function} props.onRubricDataChange  Callback when rubric data changes.
 * @param {Function} props.onRubricTotalChange Callback when rubric total points changes.
 */
const SettingsPanel = ( {
	form,
	rubricData,
	onRubricDataChange,
	onRubricTotalChange,
} ) => {
	// Watch allow_resubmission to show/hide max_resubmissions.
	const allowResubmission = Form.useWatch( 'allow_resubmission', form );

	// Watch rubric_enabled to toggle between grading guidelines and rubric editor.
	const rubricEnabled = Form.useWatch( 'rubric_enabled', form );

	return (
		<Space direction="vertical" size="large" style={ { width: '100%' } }>
			{ /* Basic Information */ }
			<Card
				title={
					<Space>
						<Title level={ 4 } style={ { margin: 0 } }>
							{ __(
								'Basic Information',
								'pressprimer-assignment'
							) }{ ' ' }
							<span style={ { color: '#ff4d4f' } }>*</span>
						</Title>
					</Space>
				}
				style={ { marginBottom: 24 } }
			>
				<Form.Item
					label={
						<Space>
							<FileTextOutlined />
							<span>
								{ __(
									'Assignment Title',
									'pressprimer-assignment'
								) }
							</span>
						</Space>
					}
					name="title"
					rules={ [
						{
							required: true,
							message: __(
								'Please enter an assignment title',
								'pressprimer-assignment'
							),
						},
					] }
				>
					<Input
						placeholder={ __(
							'e.g., "Week 5 Essay" or "Final Project Submission"',
							'pressprimer-assignment'
						) }
						style={ { width: 300 } }
						size="small"
					/>
				</Form.Item>

				<Row gutter={ 16 }>
					<Col span={ 12 }>
						<Form.Item
							label={
								<Space>
									<FileTextOutlined />
									<span>
										{ __(
											'Description',
											'pressprimer-assignment'
										) }
									</span>
									<Tooltip
										title={ __(
											'Brief description shown in assignment lists',
											'pressprimer-assignment'
										) }
									>
										<QuestionCircleOutlined
											style={ {
												fontSize: 12,
												color: '#8c8c8c',
											} }
										/>
									</Tooltip>
								</Space>
							}
							name="description"
						>
							<TextArea
								rows={ 5 }
								placeholder={ __(
									'Brief description of the assignment…',
									'pressprimer-assignment'
								) }
								size="small"
							/>
						</Form.Item>
					</Col>
					<Col span={ 12 }>
						<Form.Item
							label={
								<Space>
									<span>
										{ __(
											'Instructions',
											'pressprimer-assignment'
										) }
									</span>
									<Tooltip
										title={ __(
											'Detailed instructions shown to students on the assignment page',
											'pressprimer-assignment'
										) }
									>
										<QuestionCircleOutlined
											style={ {
												fontSize: 12,
												color: '#8c8c8c',
											} }
										/>
									</Tooltip>
								</Space>
							}
							name="instructions"
						>
							<TextArea
								rows={ 5 }
								placeholder={ __(
									'Provide detailed instructions for completing this assignment…',
									'pressprimer-assignment'
								) }
								size="small"
							/>
						</Form.Item>
					</Col>
				</Row>

				<Row gutter={ 16 }>
					<Col span={ 12 }>
						<Form.Item
							label={
								<Space>
									<span>
										{ __(
											'Status',
											'pressprimer-assignment'
										) }
									</span>
									<Tooltip
										title={ __(
											'Draft = work in progress. Published = visible to students. Archived = hidden but preserved.',
											'pressprimer-assignment'
										) }
									>
										<QuestionCircleOutlined
											style={ {
												fontSize: 12,
												color: '#8c8c8c',
											} }
										/>
									</Tooltip>
								</Space>
							}
							name="status"
						>
							<Select
								style={ { width: 200 } }
								size="small"
								options={ [
									{
										value: 'draft',
										label: __(
											'Draft',
											'pressprimer-assignment'
										),
									},
									{
										value: 'published',
										label: __(
											'Published',
											'pressprimer-assignment'
										),
									},
									{
										value: 'archived',
										label: __(
											'Archived',
											'pressprimer-assignment'
										),
									},
								] }
							/>
						</Form.Item>
					</Col>
					<Col span={ 12 }>
						<Form.Item
							label={
								<Space>
									<FormatPainterOutlined />
									<span>
										{ __(
											'Theme',
											'pressprimer-assignment'
										) }
									</span>
									<Tooltip
										title={ __(
											'Visual theme for this assignment. Overrides the global default set in Settings.',
											'pressprimer-assignment'
										) }
									>
										<QuestionCircleOutlined
											style={ {
												fontSize: 12,
												color: '#8c8c8c',
											} }
										/>
									</Tooltip>
								</Space>
							}
							name="theme"
						>
							<Select
								style={ { width: 200 } }
								size="small"
								options={ [
									{
										value: 'default',
										label: __(
											'Default',
											'pressprimer-assignment'
										),
									},
									{
										value: 'modern',
										label: __(
											'Modern',
											'pressprimer-assignment'
										),
									},
									{
										value: 'minimal',
										label: __(
											'Minimal',
											'pressprimer-assignment'
										),
									},
								] }
							/>
						</Form.Item>
					</Col>
				</Row>
			</Card>

			{ /* Grading Settings */ }
			<Card
				title={
					<Space>
						<Title level={ 4 } style={ { margin: 0 } }>
							{ __( 'Grading', 'pressprimer-assignment' ) }
						</Title>
					</Space>
				}
				style={ { marginBottom: 24 } }
			>
				<Row gutter={ 16 }>
					<Col span={ 12 }>
						<Form.Item
							label={
								<Space>
									<TrophyOutlined />
									<span>
										{ __(
											'Maximum Points',
											'pressprimer-assignment'
										) }
									</span>
									<Tooltip
										title={
											rubricEnabled
												? __(
														'Automatically set from rubric criteria totals',
														'pressprimer-assignment'
												  )
												: __(
														'The maximum number of points for this assignment',
														'pressprimer-assignment'
												  )
										}
									>
										<QuestionCircleOutlined
											style={ {
												fontSize: 12,
												color: '#8c8c8c',
											} }
										/>
									</Tooltip>
								</Space>
							}
							name="max_points"
							extra={
								rubricEnabled
									? __(
											'Synced to rubric total.',
											'pressprimer-assignment'
									  )
									: undefined
							}
						>
							<InputNumber
								min={ 0.01 }
								max={ 100000 }
								step={ 1 }
								style={ { width: 150 } }
								size="small"
								disabled={ !! rubricEnabled }
								addonAfter={ __(
									'pts',
									'pressprimer-assignment'
								) }
							/>
						</Form.Item>
					</Col>
					<Col span={ 12 }>
						<Form.Item
							label={
								<Space>
									<span>
										{ __(
											'Passing Score',
											'pressprimer-assignment'
										) }
									</span>
									<Tooltip
										title={ __(
											'Minimum points needed to pass',
											'pressprimer-assignment'
										) }
									>
										<QuestionCircleOutlined
											style={ {
												fontSize: 12,
												color: '#8c8c8c',
											} }
										/>
									</Tooltip>
								</Space>
							}
							name="passing_score"
						>
							<InputNumber
								min={ 0 }
								max={ 100000 }
								step={ 1 }
								style={ { width: 150 } }
								size="small"
								addonAfter={ __(
									'pts',
									'pressprimer-assignment'
								) }
							/>
						</Form.Item>
					</Col>
				</Row>

				{ /* Grading guidelines — hidden when rubric is enabled */ }
				{ ! rubricEnabled && (
					<Form.Item
						label={
							<Space>
								<span>
									{ __(
										'Grading Guidelines',
										'pressprimer-assignment'
									) }
								</span>
								<Tooltip
									title={ __(
										'Internal guidelines for graders (not shown to students)',
										'pressprimer-assignment'
									) }
								>
									<QuestionCircleOutlined
										style={ {
											fontSize: 12,
											color: '#8c8c8c',
										} }
									/>
								</Tooltip>
							</Space>
						}
						name="grading_guidelines"
					>
						<TextArea
							rows={ 4 }
							placeholder={ __(
								'Grading criteria and rubric notes for graders…',
								'pressprimer-assignment'
							) }
							style={ { maxWidth: 500 } }
							size="small"
						/>
					</Form.Item>
				) }

				{ /* Rubric section — only when Educator addon is active */ }
				{ educatorActive && (
					<>
						<Divider />
						<Form.Item
							name="rubric_enabled"
							valuePropName="checked"
							style={ { marginBottom: rubricEnabled ? 16 : 0 } }
						>
							<Checkbox>
								<Space>
									<span>
										{ __(
											'Use rubric for grading',
											'pressprimer-assignment'
										) }
									</span>
									<Tooltip
										title={ __(
											'Replaces the grading guidelines text box with a structured rubric. Criteria and levels will be visible to students on the assignment page.',
											'pressprimer-assignment'
										) }
									>
										<QuestionCircleOutlined
											style={ {
												fontSize: 12,
												color: '#8c8c8c',
											} }
										/>
									</Tooltip>
								</Space>
							</Checkbox>
						</Form.Item>

						{ rubricEnabled && RubricEditor && (
							<RubricEditor
								initialData={ rubricData }
								onDataChange={ onRubricDataChange }
								onTotalChange={ onRubricTotalChange }
							/>
						) }
					</>
				) }
			</Card>

			{ /* Submission Settings */ }
			<Card
				title={
					<Space>
						<Title level={ 4 } style={ { margin: 0 } }>
							{ __(
								'Submission Settings',
								'pressprimer-assignment'
							) }
						</Title>
					</Space>
				}
				style={ { marginBottom: 24 } }
			>
				<Row gutter={ 16 }>
					<Col span={ 12 }>
						<Form.Item
							label={
								<Space>
									<EditOutlined />
									<span>
										{ __(
											'Submission Type',
											'pressprimer-assignment'
										) }
									</span>
									<Tooltip
										title={ __(
											'Choose how students submit their work: file upload, text editor, or either',
											'pressprimer-assignment'
										) }
									>
										<QuestionCircleOutlined
											style={ {
												fontSize: 12,
												color: '#8c8c8c',
											} }
										/>
									</Tooltip>
								</Space>
							}
							name="submission_type"
						>
							<Select
								style={ { width: 300 } }
								size="small"
								options={ [
									{
										value: 'file',
										label: __(
											'File Upload Only',
											'pressprimer-assignment'
										),
									},
									{
										value: 'text',
										label: __(
											'Text Editor Only',
											'pressprimer-assignment'
										),
									},
									{
										value: 'either',
										label: __(
											'Either (Student Chooses)',
											'pressprimer-assignment'
										),
									},
								] }
							/>
						</Form.Item>

						<Form.Item
							label={
								<Space>
									<MailOutlined />
									<span>
										{ __(
											'Notification Email',
											'pressprimer-assignment'
										) }
									</span>
									<Tooltip
										title={ __(
											'Additional email addresses to notify when a student submits. The assignment author always receives the notification.',
											'pressprimer-assignment'
										) }
									>
										<QuestionCircleOutlined
											style={ {
												fontSize: 12,
												color: '#8c8c8c',
											} }
										/>
									</Tooltip>
								</Space>
							}
							name="notification_email"
							extra={ __(
								'Comma-separated email addresses. Leave blank to only notify the assignment author.',
								'pressprimer-assignment'
							) }
						>
							<Input
								placeholder={ __(
									'e.g., instructor@school.edu, ta@school.edu',
									'pressprimer-assignment'
								) }
								style={ { width: 300 } }
								size="small"
							/>
						</Form.Item>
					</Col>
					<Col span={ 12 }>
						<Form.Item
							label={
								<Space>
									<span>
										{ __(
											'Allow Resubmission',
											'pressprimer-assignment'
										) }
									</span>
									<Tooltip
										title={ __(
											'Allow students to resubmit after their initial submission',
											'pressprimer-assignment'
										) }
									>
										<QuestionCircleOutlined
											style={ {
												fontSize: 12,
												color: '#8c8c8c',
											} }
										/>
									</Tooltip>
								</Space>
							}
							name="allow_resubmission"
							valuePropName="checked"
							extra={
								! allowResubmission
									? __(
											'When disabled, students can only submit once.',
											'pressprimer-assignment'
									  )
									: undefined
							}
						>
							<Switch size="small" />
						</Form.Item>

						{ allowResubmission && (
							<Form.Item
								label={
									<Space>
										<span>
											{ __(
												'Maximum Resubmissions',
												'pressprimer-assignment'
											) }
										</span>
										<Tooltip
											title={ __(
												'Maximum number of times a student can resubmit',
												'pressprimer-assignment'
											) }
										>
											<QuestionCircleOutlined
												style={ {
													fontSize: 12,
													color: '#8c8c8c',
												} }
											/>
										</Tooltip>
									</Space>
								}
								name="max_resubmissions"
								extra={ __(
									'Set to 0 for unlimited resubmissions.',
									'pressprimer-assignment'
								) }
							>
								<InputNumber
									min={ 0 }
									max={ 100 }
									style={ { width: 300 } }
									size="small"
								/>
							</Form.Item>
						) }
					</Col>
				</Row>
			</Card>
		</Space>
	);
};

export default SettingsPanel;
