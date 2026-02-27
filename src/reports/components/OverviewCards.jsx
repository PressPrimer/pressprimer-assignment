/**
 * Overview Cards Component
 *
 * Displays all-time summary statistics in card format for the reports page.
 * Mirrors the Quiz OverviewCards pattern.
 *
 * @package
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	LineChartOutlined,
	PercentageOutlined,
	CheckCircleOutlined,
	ClockCircleOutlined,
} from '@ant-design/icons';

/**
 * Format hours to human readable time.
 *
 * @param {number} hours Total hours.
 * @return {string} Formatted time.
 */
const formatGradingTime = ( hours ) => {
	if ( ! hours || hours === 0 ) {
		return '-';
	}

	if ( hours < 1 ) {
		const mins = Math.round( hours * 60 );
		return `${ mins }m`;
	}

	if ( hours < 24 ) {
		return `${ hours }h`;
	}

	const days = Math.floor( hours / 24 );
	const remainingHours = Math.round( hours % 24 );

	if ( remainingHours === 0 ) {
		return `${ days }d`;
	}

	return `${ days }d ${ remainingHours }h`;
};

/**
 * Overview Cards Component
 *
 * @param {Object}  props         Component props.
 * @param {Object}  props.stats   Statistics data.
 * @param {boolean} props.loading Loading state.
 * @return {JSX.Element} Rendered component.
 */
const OverviewCards = ( { stats, loading } ) => {
	const iconColor = '#1890ff';
	const iconBgColor = '#e6f7ff';

	const cards = [
		{
			key: 'total_submissions',
			label: __( 'Total Submissions', 'pressprimer-assignment' ),
			value: stats?.total_submissions ?? '-',
			icon: <LineChartOutlined />,
		},
		{
			key: 'avg_score',
			label: __( 'Average Score', 'pressprimer-assignment' ),
			value:
				stats?.avg_score !== undefined ? `${ stats.avg_score }%` : '-',
			icon: <PercentageOutlined />,
		},
		{
			key: 'pass_rate',
			label: __( 'Pass Rate', 'pressprimer-assignment' ),
			value:
				stats?.pass_rate !== undefined ? `${ stats.pass_rate }%` : '-',
			icon: <CheckCircleOutlined />,
		},
		{
			key: 'avg_grading_time',
			label: __( 'Avg. Grading Time', 'pressprimer-assignment' ),
			value: formatGradingTime( stats?.avg_grading_time_hours ),
			icon: <ClockCircleOutlined />,
		},
	];

	return (
		<div className="ppa-overview-cards">
			{ cards.map( ( card ) => (
				<div key={ card.key } className="ppa-overview-card">
					<div
						className="ppa-overview-card-icon"
						style={ {
							color: iconColor,
							backgroundColor: iconBgColor,
						} }
					>
						{ card.icon }
					</div>
					<div className="ppa-overview-card-content">
						<div className="ppa-overview-card-value">
							{ loading ? '-' : card.value }
						</div>
						<div className="ppa-overview-card-label">
							{ card.label }
						</div>
					</div>
				</div>
			) ) }
		</div>
	);
};

export default OverviewCards;
