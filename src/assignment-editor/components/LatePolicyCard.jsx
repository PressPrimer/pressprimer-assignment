/**
 * Due Date & Late Policy Card
 *
 * Editor section for the assignment due date and late submission policy
 * (2.2, feature 009). "Apply a penalty" reveals a single rule — "If late
 * by up to [X] days, deduct [Y]%" — after which submissions stop being
 * accepted. "Accept late submissions" offers an optional cutoff of its
 * own. The site's time zone and current time are shown next to the due
 * date so users always know what clock they're working against.
 *
 * @package
 * @since 2.2.0
 */

import { __, sprintf } from '@wordpress/i18n';
import {
	Form,
	Card,
	DatePicker,
	Radio,
	InputNumber,
	Select,
	Checkbox,
	Space,
	Typography,
	Alert,
} from 'antd';
import { CalendarOutlined } from '@ant-design/icons';
import {
	ADMIN_DATETIME_FORMAT,
	ADMIN_SHOWTIME,
} from '../../shared/date-formats';

const { Title, Text } = Typography;

/**
 * Due Date & Late Policy card.
 *
 * @param {Object} props      Component props.
 * @param {Object} props.form Ant Design form instance.
 * @return {JSX.Element} Rendered card.
 */
const LatePolicyCard = ( { form } ) => {
	const dueAt = Form.useWatch( 'due_at', form );
	const latePolicy = Form.useWatch( 'late_policy', form );
	const cutoffEnabled = Form.useWatch( 'late_cutoff_enabled', form );

	const timezoneString =
		window.pressprimerAssignmentAdmin?.timezoneString || '';
	const siteNow = window.pressprimerAssignmentAdmin?.siteNow || '';

	return (
		<Card
			className="ppa-editor-card-late-policy"
			title={
				<Space>
					<Title level={ 4 } style={ { margin: 0 } }>
						{ __(
							'Due Date & Late Policy',
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
						<CalendarOutlined />
						<span>
							{ __( 'Due Date', 'pressprimer-assignment' ) }
						</span>
					</Space>
				}
				name="due_at"
				style={ { marginBottom: 4 } }
				help={ __(
					"Leave empty for no due date. Lateness is measured against each student's effective due date — group dates from add-ons take precedence.",
					'pressprimer-assignment'
				) }
			>
				<DatePicker
					showTime={ ADMIN_SHOWTIME }
					format={ ADMIN_DATETIME_FORMAT }
					style={ { width: 300 } }
					allowClear
				/>
			</Form.Item>

			{ ( timezoneString || siteNow ) && (
				<Text
					type="secondary"
					style={ {
						display: 'block',
						marginTop: 8,
						marginBottom: 16,
						fontSize: 12,
					} }
				>
					{ timezoneString &&
						sprintf(
							/* translators: %s: the site time zone, e.g. "America/Chicago" */
							__(
								'Times use the site time zone: %s.',
								'pressprimer-assignment'
							),
							timezoneString
						) }{ ' ' }
					{ siteNow &&
						sprintf(
							/* translators: %s: the current site date and time */
							__(
								'Current site time: %s.',
								'pressprimer-assignment'
							),
							siteNow
						) }
				</Text>
			) }

			<Form.Item
				label={ __( 'Late Submissions', 'pressprimer-assignment' ) }
				name="late_policy"
				style={ { marginBottom: 8 } }
			>
				<Radio.Group>
					<Space direction="vertical">
						<Radio value="accept">
							{ __(
								'Accept late submissions with no penalty',
								'pressprimer-assignment'
							) }
						</Radio>
						<Radio value="penalty">
							{ __(
								'Apply a penalty',
								'pressprimer-assignment'
							) }
						</Radio>
						<Radio value="reject">
							{ __(
								'Reject late submissions',
								'pressprimer-assignment'
							) }
						</Radio>
					</Space>
				</Radio.Group>
			</Form.Item>

			{ ! dueAt &&
				( latePolicy !== 'accept' ||
					( latePolicy === 'accept' && cutoffEnabled ) ) && (
					<Alert
						type="info"
						showIcon
						style={ { marginBottom: 8, maxWidth: 500 } }
						message={ __(
							'Set a due date to activate this policy — without one, nothing is ever late.',
							'pressprimer-assignment'
						) }
					/>
				) }

			{ latePolicy === 'accept' && (
				<Space align="center" wrap className="ppa-late-policy-row">
					<Form.Item
						name="late_cutoff_enabled"
						valuePropName="checked"
						style={ { marginBottom: 0 } }
					>
						<Checkbox>
							{ __(
								'Stop accepting submissions after',
								'pressprimer-assignment'
							) }
						</Checkbox>
					</Form.Item>
					<Form.Item
						name="late_cutoff_days"
						style={ { marginBottom: 0 } }
						rules={ [
							{
								validator() {
									if (
										! form.getFieldValue(
											'late_cutoff_enabled'
										)
									) {
										return Promise.resolve();
									}
									const days =
										form.getFieldValue(
											'late_cutoff_days'
										);
									if ( ! days || days < 1 ) {
										return Promise.reject(
											new Error(
												__(
													'Enter when submissions stop being accepted.',
													'pressprimer-assignment'
												)
											)
										);
									}
									return Promise.resolve();
								},
							},
						] }
					>
						<InputNumber
							min={ 1 }
							precision={ 0 }
							style={ { width: 90 } }
							disabled={ ! cutoffEnabled }
						/>
					</Form.Item>
					<Text type="secondary">
						{ __(
							'days past the due date',
							'pressprimer-assignment'
						) }
					</Text>
				</Space>
			) }

			{ latePolicy === 'penalty' && (
				<div className="ppa-late-policy-penalty">
					<Space align="center" wrap className="ppa-late-policy-row">
						<Text type="secondary">
							{ __(
								'If late by up to',
								'pressprimer-assignment'
							) }
						</Text>
						<Form.Item
							name="late_penalty_days"
							style={ { marginBottom: 0 } }
							rules={ [
								{
									required: true,
									message: __(
										'Enter a number of days.',
										'pressprimer-assignment'
									),
								},
							] }
						>
							<InputNumber
								min={ 1 }
								precision={ 0 }
								style={ { width: 90 } }
							/>
						</Form.Item>
						<Text type="secondary">
							{ __( 'days, deduct', 'pressprimer-assignment' ) }
						</Text>
						<Form.Item
							name="late_penalty_percent"
							style={ { marginBottom: 0 } }
							rules={ [
								{
									required: true,
									message: __(
										'Enter a penalty.',
										'pressprimer-assignment'
									),
								},
							] }
						>
							<InputNumber
								min={ 0 }
								max={ 100 }
								style={ { width: 90 } }
								addonAfter="%"
							/>
						</Form.Item>
					</Space>

					<Text
						type="secondary"
						style={ {
							display: 'block',
							marginTop: 8,
							fontSize: 12,
						} }
					>
						{ __(
							'Submissions are no longer accepted after that.',
							'pressprimer-assignment'
						) }
					</Text>

					<Form.Item
						label={ __(
							'Deduct the percentage from',
							'pressprimer-assignment'
						) }
						name="late_penalty_basis"
						help={
							/* translators: the % sign is a literal percent, not a placeholder. */
							__(
								'Example — 100-point assignment, 10% penalty, submission scored 80: "maximum points" deducts 10 points (final: 70); "earned score" deducts 8 points (final: 72).',
								'pressprimer-assignment'
							)
						}
						style={ { marginTop: 16, marginBottom: 0 } }
					>
						<Select
							style={ { width: 300 } }
							options={ [
								{
									value: 'max_points',
									label: __(
										"The assignment's maximum points",
										'pressprimer-assignment'
									),
								},
								{
									value: 'raw_score',
									label: __(
										"The student's earned score",
										'pressprimer-assignment'
									),
								},
							] }
						/>
					</Form.Item>
				</div>
			) }
		</Card>
	);
};

export default LatePolicyCard;
