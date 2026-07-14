/**
 * Due Date & Late Policy Card
 *
 * Editor section for the assignment due date and late submission policy
 * (2.2, feature 009). Choosing "Apply a penalty" reveals the graduated
 * tier repeater ("If late by up to [N] [hours/days], deduct [X]%"), the
 * deduction basis, and an optional cutoff row. Client-side validation
 * mirrors the server rules: 1–5 tiers, strictly increasing thresholds,
 * penalties 0–100 non-decreasing, cutoff later than the last tier.
 *
 * @package
 * @since 2.2.0
 */

import { __ } from '@wordpress/i18n';
import {
	Form,
	Card,
	DatePicker,
	Radio,
	InputNumber,
	Select,
	Checkbox,
	Button,
	Space,
	Typography,
	Alert,
} from 'antd';
import {
	CalendarOutlined,
	PlusOutlined,
	DeleteOutlined,
	ArrowUpOutlined,
	ArrowDownOutlined,
} from '@ant-design/icons';

const { Title, Text } = Typography;

const UNIT_OPTIONS = [
	{ value: 'hours', label: __( 'hours', 'pressprimer-assignment' ) },
	{ value: 'days', label: __( 'days', 'pressprimer-assignment' ) },
];

/**
 * Convert a row's amount + unit to hours.
 *
 * @param {number|null} amount Numeric amount.
 * @param {string}      unit   'hours' or 'days'.
 * @return {number|null} Hours, or null when amount is empty.
 */
export const rowToHours = ( amount, unit ) => {
	if ( amount === null || amount === undefined || amount === '' ) {
		return null;
	}
	return unit === 'days' ? amount * 24 : amount;
};

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
	const tiers = Form.useWatch( 'late_penalty_tiers', form ) || [];

	/**
	 * Validator: threshold must be larger than the previous row's.
	 *
	 * @param {number} index Row index.
	 * @return {Object} Ant Design rule object.
	 */
	const thresholdRule = ( index ) => ( {
		validator() {
			const rows = form.getFieldValue( 'late_penalty_tiers' ) || [];
			const row = rows[ index ];
			const hours = row ? rowToHours( row.amount, row.unit ) : null;

			if ( hours === null ) {
				return Promise.reject(
					new Error(
						__(
							'Enter a time threshold.',
							'pressprimer-assignment'
						)
					)
				);
			}

			if ( index > 0 ) {
				const prev = rows[ index - 1 ];
				const prevHours = prev
					? rowToHours( prev.amount, prev.unit )
					: null;

				if ( prevHours !== null && hours <= prevHours ) {
					return Promise.reject(
						new Error(
							__(
								'Must be a larger time threshold than the tier above.',
								'pressprimer-assignment'
							)
						)
					);
				}
			}

			return Promise.resolve();
		},
	} );

	/**
	 * Validator: penalty must not be smaller than the previous row's.
	 *
	 * @param {number} index Row index.
	 * @return {Object} Ant Design rule object.
	 */
	const penaltyRule = ( index ) => ( {
		validator() {
			const rows = form.getFieldValue( 'late_penalty_tiers' ) || [];
			const row = rows[ index ];

			if ( ! row || row.penalty === null || row.penalty === undefined ) {
				return Promise.reject(
					new Error(
						__( 'Enter a penalty.', 'pressprimer-assignment' )
					)
				);
			}

			if ( index > 0 ) {
				const prev = rows[ index - 1 ];
				if (
					prev &&
					prev.penalty !== null &&
					prev.penalty !== undefined &&
					row.penalty < prev.penalty
				) {
					return Promise.reject(
						new Error(
							__(
								'Cannot be a smaller penalty than the tier above.',
								'pressprimer-assignment'
							)
						)
					);
				}
			}

			return Promise.resolve();
		},
	} );

	// Cutoff must land after the last tier's threshold.
	const cutoffRule = {
		validator() {
			if ( ! form.getFieldValue( 'late_cutoff_enabled' ) ) {
				return Promise.resolve();
			}

			const amount = form.getFieldValue( 'late_cutoff_amount' );
			const unit = form.getFieldValue( 'late_cutoff_unit' );
			const hours = rowToHours( amount, unit );

			if ( hours === null || hours <= 0 ) {
				return Promise.reject(
					new Error(
						__(
							'Enter when submissions stop being accepted.',
							'pressprimer-assignment'
						)
					)
				);
			}

			const rows = form.getFieldValue( 'late_penalty_tiers' ) || [];
			const last = rows[ rows.length - 1 ];
			const lastHours = last
				? rowToHours( last.amount, last.unit )
				: null;

			if ( lastHours !== null && hours <= lastHours ) {
				return Promise.reject(
					new Error(
						__(
							"The cutoff must be later than the last tier's threshold.",
							'pressprimer-assignment'
						)
					)
				);
			}

			return Promise.resolve();
		},
	};

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
				help={ __(
					"Leave empty for no due date. Lateness is measured against each student's effective due date — group dates from add-ons take precedence.",
					'pressprimer-assignment'
				) }
			>
				<DatePicker
					showTime={ { format: 'HH:mm' } }
					format="YYYY-MM-DD HH:mm"
					style={ { width: 300 } }
					allowClear
				/>
			</Form.Item>

			<Form.Item
				label={ __( 'Late Submissions', 'pressprimer-assignment' ) }
				name="late_policy"
				style={ { marginBottom: latePolicy === 'penalty' ? 8 : 0 } }
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

			{ ! dueAt && latePolicy !== 'accept' && (
				<Alert
					type="info"
					showIcon
					style={ { marginTop: 8, maxWidth: 500 } }
					message={ __(
						'Set a due date to activate this policy — without one, nothing is ever late.',
						'pressprimer-assignment'
					) }
				/>
			) }

			{ latePolicy === 'penalty' && (
				<div className="ppa-late-tier-repeater">
					<Form.List name="late_penalty_tiers">
						{ ( fields, { add, remove, move } ) => (
							<>
								{ fields.map( ( field, index ) => (
									<Space
										key={ field.key }
										align="baseline"
										wrap
										style={ { marginBottom: 4 } }
									>
										<Text type="secondary">
											{ __(
												'If late by up to',
												'pressprimer-assignment'
											) }
										</Text>
										<Form.Item
											name={ [ field.name, 'amount' ] }
											rules={ [ thresholdRule( index ) ] }
											style={ { marginBottom: 8 } }
										>
											<InputNumber
												min={ 1 }
												style={ { width: 90 } }
											/>
										</Form.Item>
										<Form.Item
											name={ [ field.name, 'unit' ] }
											style={ { marginBottom: 8 } }
										>
											<Select
												options={ UNIT_OPTIONS }
												style={ { width: 90 } }
											/>
										</Form.Item>
										<Text type="secondary">
											{ __(
												', deduct',
												'pressprimer-assignment'
											) }
										</Text>
										<Form.Item
											name={ [ field.name, 'penalty' ] }
											rules={ [ penaltyRule( index ) ] }
											style={ { marginBottom: 8 } }
										>
											<InputNumber
												min={ 0 }
												max={ 100 }
												style={ { width: 90 } }
												addonAfter="%"
											/>
										</Form.Item>
										<Button
											type="text"
											size="small"
											icon={ <ArrowUpOutlined /> }
											disabled={ index === 0 }
											onClick={ () =>
												move( index, index - 1 )
											}
											aria-label={ __(
												'Move tier up',
												'pressprimer-assignment'
											) }
										/>
										<Button
											type="text"
											size="small"
											icon={ <ArrowDownOutlined /> }
											disabled={
												index === fields.length - 1
											}
											onClick={ () =>
												move( index, index + 1 )
											}
											aria-label={ __(
												'Move tier down',
												'pressprimer-assignment'
											) }
										/>
										<Button
											type="text"
											size="small"
											danger
											icon={ <DeleteOutlined /> }
											disabled={ fields.length === 1 }
											onClick={ () => remove( index ) }
											aria-label={ __(
												'Remove tier',
												'pressprimer-assignment'
											) }
										/>
									</Space>
								) ) }

								{ fields.length < 5 && (
									<Form.Item style={ { marginBottom: 8 } }>
										<Button
											type="dashed"
											icon={ <PlusOutlined /> }
											onClick={ () =>
												add( {
													amount: null,
													unit: 'days',
													penalty: null,
												} )
											}
										>
											{ __(
												'Add tier',
												'pressprimer-assignment'
											) }
										</Button>
									</Form.Item>
								) }
							</>
						) }
					</Form.List>

					<Space align="baseline" wrap>
						<Form.Item
							name="late_cutoff_enabled"
							valuePropName="checked"
							style={ { marginBottom: 8 } }
						>
							<Checkbox>
								{ __(
									'Stop accepting submissions after',
									'pressprimer-assignment'
								) }
							</Checkbox>
						</Form.Item>
						<Form.Item
							name="late_cutoff_amount"
							rules={ [ cutoffRule ] }
							style={ { marginBottom: 8 } }
						>
							<InputNumber
								min={ 1 }
								style={ { width: 90 } }
								disabled={ ! cutoffEnabled }
							/>
						</Form.Item>
						<Form.Item
							name="late_cutoff_unit"
							style={ { marginBottom: 8 } }
						>
							<Select
								options={ UNIT_OPTIONS }
								style={ { width: 90 } }
								disabled={ ! cutoffEnabled }
							/>
						</Form.Item>
					</Space>

					<Form.Item
						label={ __(
							'Deduct percentages from',
							'pressprimer-assignment'
						) }
						name="late_penalty_basis"
						help={ __(
							'"Maximum points" costs the same points however the work scores; "earned score" scales with it.',
							'pressprimer-assignment'
						) }
						style={ { marginTop: 8, marginBottom: 0 } }
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

					{ tiers.length > 0 && (
						<Text
							type="secondary"
							style={ {
								display: 'block',
								marginTop: 8,
								fontSize: 12,
							} }
						>
							{ __(
								"Lateness beyond the last tier keeps the last tier's penalty unless a cutoff is set.",
								'pressprimer-assignment'
							) }
						</Text>
					) }
				</div>
			) }
		</Card>
	);
};

export default LatePolicyCard;
