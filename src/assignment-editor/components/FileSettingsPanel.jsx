/**
 * File Settings Panel Component
 *
 * @package
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	Form,
	InputNumber,
	Select,
	Card,
	Space,
	Typography,
	Tooltip,
	Checkbox,
	Row,
	Col,
	Button,
} from 'antd';
import {
	QuestionCircleOutlined,
	FileOutlined,
	CloudUploadOutlined,
} from '@ant-design/icons';

const { Title } = Typography;

/**
 * Default allowed file types with labels.
 */
const FILE_TYPE_OPTIONS = [
	{ value: 'pdf', label: 'PDF (.pdf)' },
	{ value: 'docx', label: 'Word (.docx)' },
	{ value: 'txt', label: 'Text (.txt)' },
	{ value: 'rtf', label: 'Rich Text (.rtf)' },
	{ value: 'odt', label: 'OpenDocument (.odt)' },
	{ value: 'jpg', label: 'JPEG (.jpg)' },
	{ value: 'jpeg', label: 'JPEG (.jpeg)' },
	{ value: 'png', label: 'PNG (.png)' },
	{ value: 'gif', label: 'GIF (.gif)' },
];

/**
 * Common file size presets in bytes.
 */
const FILE_SIZE_OPTIONS = [
	{
		value: 1048576,
		label: __( '1 MB', 'pressprimer-assignment' ),
	},
	{
		value: 2097152,
		label: __( '2 MB', 'pressprimer-assignment' ),
	},
	{
		value: 5242880,
		label: __( '5 MB', 'pressprimer-assignment' ),
	},
	{
		value: 10485760,
		label: __( '10 MB', 'pressprimer-assignment' ),
	},
	{
		value: 20971520,
		label: __( '20 MB', 'pressprimer-assignment' ),
	},
	{
		value: 52428800,
		label: __( '50 MB', 'pressprimer-assignment' ),
	},
	{
		value: 104857600,
		label: __( '100 MB', 'pressprimer-assignment' ),
	},
];

/**
 * File Settings Panel Component
 *
 * @param {Object} props      Component props.
 * @param {Object} props.form Ant Design form instance.
 */
// eslint-disable-next-line no-unused-vars
const FileSettingsPanel = ( { form } ) => {
	return (
		<Space direction="vertical" size="large" style={ { width: '100%' } }>
			{ /* File Type Settings */ }
			<Card
				title={
					<Space>
						<Title level={ 4 } style={ { margin: 0 } }>
							{ __(
								'Allowed File Types',
								'pressprimer-assignment'
							) }
						</Title>
						<Tooltip
							title={ __(
								'Select which file types students can upload',
								'pressprimer-assignment'
							) }
						>
							<QuestionCircleOutlined
								style={ { color: '#8c8c8c' } }
							/>
						</Tooltip>
					</Space>
				}
				style={ { marginBottom: 24 } }
			>
				<Form.Item
					label={
						<Space>
							<FileOutlined />
							<span>
								{ __(
									'Accepted File Types',
									'pressprimer-assignment'
								) }
							</span>
						</Space>
					}
					name="allowed_file_types"
				>
					<Checkbox.Group style={ { width: '100%' } }>
						<Row gutter={ [ 8, 4 ] }>
							{ FILE_TYPE_OPTIONS.map( ( opt ) => (
								<Col span={ 8 } key={ opt.value }>
									<Checkbox value={ opt.value }>
										{ opt.label }
									</Checkbox>
								</Col>
							) ) }
						</Row>
					</Checkbox.Group>
				</Form.Item>
				<Space style={ { marginBottom: 8 } } size="small">
					<Button
						size="small"
						onClick={ () => {
							const allValues = FILE_TYPE_OPTIONS.map(
								( opt ) => opt.value
							);
							form.setFieldValue(
								'allowed_file_types',
								allValues
							);
						} }
					>
						{ __( 'Select All', 'pressprimer-assignment' ) }
					</Button>
					<Button
						size="small"
						onClick={ () => {
							form.setFieldValue( 'allowed_file_types', [] );
						} }
					>
						{ __( 'Deselect All', 'pressprimer-assignment' ) }
					</Button>
				</Space>
			</Card>

			{ /* Upload Limits */ }
			<Card
				title={
					<Space>
						<Title level={ 4 } style={ { margin: 0 } }>
							{ __( 'Upload Limits', 'pressprimer-assignment' ) }
						</Title>
						<Tooltip
							title={ __(
								'Control file size and quantity limits per submission',
								'pressprimer-assignment'
							) }
						>
							<QuestionCircleOutlined
								style={ { color: '#8c8c8c' } }
							/>
						</Tooltip>
					</Space>
				}
				style={ { marginBottom: 24 } }
			>
				<Form.Item
					label={
						<Space>
							<CloudUploadOutlined />
							<span>
								{ __(
									'Maximum File Size',
									'pressprimer-assignment'
								) }
							</span>
							<Tooltip
								title={ __(
									'Maximum size per individual file',
									'pressprimer-assignment'
								) }
							>
								<QuestionCircleOutlined
									style={ { fontSize: 12, color: '#8c8c8c' } }
								/>
							</Tooltip>
						</Space>
					}
					name="max_file_size"
				>
					<Select
						options={ FILE_SIZE_OPTIONS }
						style={ { width: 150 } }
						size="small"
					/>
				</Form.Item>

				<Form.Item
					label={
						<Space>
							<span>
								{ __(
									'Maximum Files per Submission',
									'pressprimer-assignment'
								) }
							</span>
							<Tooltip
								title={ __(
									'How many files a student can upload in a single submission',
									'pressprimer-assignment'
								) }
							>
								<QuestionCircleOutlined
									style={ { fontSize: 12, color: '#8c8c8c' } }
								/>
							</Tooltip>
						</Space>
					}
					name="max_files"
				>
					<InputNumber
						min={ 1 }
						max={ 50 }
						style={ { width: 150 } }
						size="small"
					/>
				</Form.Item>
			</Card>
		</Space>
	);
};

export default FileSettingsPanel;
