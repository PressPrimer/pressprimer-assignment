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
									style={ { fontSize: 12, color: '#8c8c8c' } }
								/>
							</Tooltip>
						</Space>
					}
					name="description"
				>
					<TextArea
						rows={ 3 }
						placeholder={ __(
							'Brief description of the assignment…',
							'pressprimer-assignment'
						) }
						style={ { maxWidth: 500 } }
						size="small"
					/>
				</Form.Item>

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
									style={ { fontSize: 12, color: '#8c8c8c' } }
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
						style={ { maxWidth: 500 } }
						size="small"
					/>
				</Form.Item>

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
									style={ { fontSize: 12, color: '#8c8c8c' } }
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
			</Card>
		</Space>
	);
};

export default SettingsPanel;
