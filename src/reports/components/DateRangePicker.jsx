/**
 * Date Range Picker Component
 *
 * Allows selecting a date range for reports filtering.
 * Mirrors Quiz's DateRangePicker with ppa- prefix.
 *
 * @package
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { Radio, DatePicker, Space } from 'antd';
import { CalendarOutlined } from '@ant-design/icons';
import { ADMIN_DATE_FORMAT } from '../../shared/date-formats';

const { RangePicker } = DatePicker;

/**
 * Date Range Picker Component
 *
 * @param {Object}   props          Component props.
 * @param {string}   props.value    Current preset value.
 * @param {Function} props.onChange Change handler.
 * @return {JSX.Element} Rendered component.
 */
const DateRangePicker = ( { value, onChange } ) => {
	// Fully controlled: the parent's value drives both the highlighted
	// preset and the custom picker's visibility, so external changes
	// (Reset Filters, URL restores) can never desynchronise from a
	// half-finished custom selection.
	const showCustom = value === 'custom';

	const presets = [
		{
			value: '7days',
			label: __( 'Last 7 days', 'pressprimer-assignment' ),
		},
		{
			value: '30days',
			label: __( 'Last 30 days', 'pressprimer-assignment' ),
		},
		{
			value: '90days',
			label: __( 'Last 90 days', 'pressprimer-assignment' ),
		},
		{
			value: 'all',
			label: __( 'All time', 'pressprimer-assignment' ),
		},
		{
			value: 'custom',
			label: __( 'Custom', 'pressprimer-assignment' ),
		},
	];

	const handlePresetChange = ( e ) => {
		// Custom commits immediately (with no dates yet) so the radio
		// highlights it right away; parents treat a date-less custom
		// range as unfiltered until both dates are picked.
		onChange( e.target.value );
	};

	const handleCustomChange = ( dates ) => {
		if ( dates && dates[ 0 ] && dates[ 1 ] ) {
			onChange( 'custom', {
				from: dates[ 0 ].format( 'YYYY-MM-DD' ),
				to: dates[ 1 ].format( 'YYYY-MM-DD' ),
			} );
		}
	};

	return (
		<div className="ppa-date-range-picker">
			<div className="ppa-date-range-label">
				<CalendarOutlined style={ { marginRight: 8 } } />
				{ __( 'Date Range:', 'pressprimer-assignment' ) }
			</div>
			<Space size="middle" wrap>
				<Radio.Group
					value={ value }
					onChange={ handlePresetChange }
					optionType="button"
					buttonStyle="solid"
				>
					{ presets.map( ( preset ) => (
						<Radio.Button
							key={ preset.value }
							value={ preset.value }
						>
							{ preset.label }
						</Radio.Button>
					) ) }
				</Radio.Group>

				{ showCustom && (
					<RangePicker
						onChange={ handleCustomChange }
						format={ ADMIN_DATE_FORMAT }
						allowClear={ false }
					/>
				) }
			</Space>
		</div>
	);
};

export default DateRangePicker;
