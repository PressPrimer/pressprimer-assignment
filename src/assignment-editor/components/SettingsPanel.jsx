/**
 * Assignment Settings Panel Component
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Form,
	Input,
	InputNumber,
	Select,
	Switch,
	Card,
	Space,
	Col,
	Row,
	Typography,
	Tooltip,
	Radio,
} from 'antd';
import {
	QuestionCircleOutlined,
	TrophyOutlined,
	FileTextOutlined,
	EditOutlined,
	MailOutlined,
	FormatPainterOutlined,
	LinkOutlined,
} from '@ant-design/icons';

const { TextArea } = Input;
const { Title, Text } = Typography;

/**
 * Settings Panel Component
 *
 * @param {Object} props      Component props.
 * @param {Object} props.form Ant Design form instance.
 */
const SettingsPanel = ( { form } ) => {
	// Watch allow_resubmission to show/hide max_resubmissions.
	const allowResubmission = Form.useWatch( 'allow_resubmission', form );

	// LMS integration state.
	const adminData = window.pressprimerAssignmentAdmin || {};
	const lifterlmsActive = adminData.integrations?.lifterlms_active || false;
	const learnpressActive = adminData.integrations?.learnpress_active || false;
	const [ lifterlmsObjects, setLifterlmsObjects ] = useState( [] );
	const [ lifterlmsLoading, setLifterlmsLoading ] = useState( false );
	const [ learnpressObjects, setLearnpressObjects ] = useState( [] );
	const [ learnpressLoading, setLearnpressLoading ] = useState( false );

	/**
	 * Fetch LifterLMS lessons and courses for the selector.
	 *
	 * @param {string} search Optional search term.
	 */
	const fetchLifterlmsObjects = useCallback( async ( search = '' ) => {
		try {
			setLifterlmsLoading( true );
			const params = search
				? `?search=${ encodeURIComponent( search ) }`
				: '';
			const response = await apiFetch( {
				path: `/ppa/v1/lifterlms/objects${ params }`,
				method: 'GET',
			} );

			if ( response.success && response.objects ) {
				setLifterlmsObjects(
					response.objects.map( ( obj ) => ( {
						value: obj.id,
						label: obj.label,
					} ) )
				);
			}
		} catch {
			// Silently fail — selector will be empty.
		} finally {
			setLifterlmsLoading( false );
		}
	}, [] );

	/**
	 * Fetch LearnPress lessons and courses for the selector.
	 *
	 * @param {string} search Optional search term.
	 */
	const fetchLearnpressObjects = useCallback( async ( search = '' ) => {
		try {
			setLearnpressLoading( true );
			const params = search
				? `?search=${ encodeURIComponent( search ) }`
				: '';
			const response = await apiFetch( {
				path: `/ppa/v1/learnpress/objects${ params }`,
				method: 'GET',
			} );

			if ( response.success && response.objects ) {
				setLearnpressObjects(
					response.objects.map( ( obj ) => ( {
						value: obj.id,
						label: obj.label,
					} ) )
				);
			}
		} catch {
			// Silently fail — selector will be empty.
		} finally {
			setLearnpressLoading( false );
		}
	}, [] );

	// Load initial LifterLMS objects when integration is active.
	useEffect( () => {
		if ( lifterlmsActive ) {
			fetchLifterlmsObjects();
		}
	}, [ lifterlmsActive, fetchLifterlmsObjects ] );

	// Load initial LearnPress objects when integration is active.
	useEffect( () => {
		if ( learnpressActive ) {
			fetchLearnpressObjects();
		}
	}, [ learnpressActive, fetchLearnpressObjects ] );

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
										title={ __(
											'The maximum number of points for this assignment',
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
							name="max_points"
						>
							<InputNumber
								min={ 0.01 }
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
									style={ { fontSize: 12, color: '#8c8c8c' } }
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
							>
								<InputNumber
									min={ 1 }
									max={ 100 }
									style={ { width: 150 } }
									size="small"
								/>
							</Form.Item>
						) }
						<Text
							type="secondary"
							style={ { fontSize: 12, display: 'block' } }
						>
							{ __(
								'When disabled, students can only submit once.',
								'pressprimer-assignment'
							) }
						</Text>
					</Col>
				</Row>
			</Card>

			{ /* LifterLMS Integration */ }
			{ lifterlmsActive && (
				<Card
					title={
						<Space>
							<Title level={ 4 } style={ { margin: 0 } }>
								{ __(
									'LifterLMS Integration',
									'pressprimer-assignment'
								) }
							</Title>
						</Space>
					}
					style={ { marginBottom: 24 } }
				>
					<Form.Item
						label={
							<Space>
								<LinkOutlined />
								<span>
									{ __(
										'LifterLMS Lesson or Course',
										'pressprimer-assignment'
									) }
								</span>
								<Tooltip
									title={ __(
										'Link this assignment to a LifterLMS lesson or course. When a student passes, the linked content will be marked complete.',
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
						name="ppa_lifterlms_object_id"
					>
						<Select
							showSearch
							allowClear
							placeholder={ __(
								'Select a lesson or course…',
								'pressprimer-assignment'
							) }
							style={ { width: 300 } }
							size="small"
							options={ lifterlmsObjects }
							loading={ lifterlmsLoading }
							filterOption={ false }
							onSearch={ fetchLifterlmsObjects }
							notFoundContent={
								lifterlmsLoading
									? __( 'Loading…', 'pressprimer-assignment' )
									: __(
											'No lessons or courses found',
											'pressprimer-assignment'
									  )
							}
						/>
					</Form.Item>

					<Form.Item
						label={
							<Space>
								<span>
									{ __(
										'Completion Type',
										'pressprimer-assignment'
									) }
								</span>
								<Tooltip
									title={ __(
										'Choose whether passing this assignment marks a LifterLMS lesson or an entire course as complete.',
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
						name="ppa_lifterlms_completion_type"
					>
						<Radio.Group>
							<Radio value="lesson">
								{ __(
									'Lesson complete',
									'pressprimer-assignment'
								) }
							</Radio>
							<Radio value="course">
								{ __(
									'Course complete',
									'pressprimer-assignment'
								) }
							</Radio>
						</Radio.Group>
					</Form.Item>

					<Text
						type="secondary"
						style={ { fontSize: 12, display: 'block' } }
					>
						{ __(
							'Leave the lesson/course field empty if this assignment should not trigger LifterLMS completion.',
							'pressprimer-assignment'
						) }
					</Text>
				</Card>
			) }

			{ /* LearnPress Integration */ }
			{ learnpressActive && (
				<Card
					title={
						<Space>
							<Title level={ 4 } style={ { margin: 0 } }>
								{ __(
									'LearnPress Integration',
									'pressprimer-assignment'
								) }
							</Title>
						</Space>
					}
					style={ { marginBottom: 24 } }
				>
					<Form.Item
						label={
							<Space>
								<LinkOutlined />
								<span>
									{ __(
										'LearnPress Lesson or Course',
										'pressprimer-assignment'
									) }
								</span>
								<Tooltip
									title={ __(
										'Link this assignment to a LearnPress lesson or course. When a student passes, the linked content will be marked complete.',
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
						name="ppa_learnpress_object_id"
					>
						<Select
							showSearch
							allowClear
							placeholder={ __(
								'Select a lesson or course…',
								'pressprimer-assignment'
							) }
							style={ { width: 300 } }
							size="small"
							options={ learnpressObjects }
							loading={ learnpressLoading }
							filterOption={ false }
							onSearch={ fetchLearnpressObjects }
							notFoundContent={
								learnpressLoading
									? __( 'Loading…', 'pressprimer-assignment' )
									: __(
											'No lessons or courses found',
											'pressprimer-assignment'
									  )
							}
						/>
					</Form.Item>

					<Form.Item
						label={
							<Space>
								<span>
									{ __(
										'Completion Type',
										'pressprimer-assignment'
									) }
								</span>
								<Tooltip
									title={ __(
										'Choose whether passing this assignment marks a LearnPress lesson or an entire course as complete.',
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
						name="ppa_learnpress_completion_type"
					>
						<Radio.Group>
							<Radio value="lesson">
								{ __(
									'Lesson complete',
									'pressprimer-assignment'
								) }
							</Radio>
							<Radio value="course">
								{ __(
									'Course complete',
									'pressprimer-assignment'
								) }
							</Radio>
						</Radio.Group>
					</Form.Item>

					<Text
						type="secondary"
						style={ { fontSize: 12, display: 'block' } }
					>
						{ __(
							'Leave the lesson/course field empty if this assignment should not trigger LearnPress completion.',
							'pressprimer-assignment'
						) }
					</Text>
				</Card>
			) }
		</Space>
	);
};

export default SettingsPanel;
