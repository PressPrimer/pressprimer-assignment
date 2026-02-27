/**
 * Stats Cards Component
 *
 * Displays key metrics in card format.
 *
 * @package
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	FileTextOutlined,
	SendOutlined,
	InboxOutlined,
	CheckCircleOutlined,
	PercentageOutlined,
	SwapOutlined,
} from '@ant-design/icons';

/**
 * Stats Cards Component
 *
 * @param {Object}  props         Component props.
 * @param {Object}  props.stats   Statistics data.
 * @param {boolean} props.loading Loading state.
 * @return {JSX.Element} Rendered component.
 */
const StatsCards = ( { stats, loading } ) => {
	// Consistent blue styling for all card icons (matches Quiz).
	const iconColor = '#1890ff';
	const iconBgColor = '#e6f7ff';

	const cards = [
		{
			key: 'assignments',
			label: __( 'Published Assignments', 'pressprimer-assignment' ),
			value: stats?.total_assignments ?? '-',
			icon: <FileTextOutlined />,
		},
		{
			key: 'submissions',
			label: __( 'Submissions (7 days)', 'pressprimer-assignment' ),
			value: stats?.recent_submissions ?? '-',
			icon: <SendOutlined />,
		},
		{
			key: 'awaiting',
			label: __( 'Awaiting Grading', 'pressprimer-assignment' ),
			value: stats?.awaiting_grading ?? '-',
			icon: <InboxOutlined />,
		},
		{
			key: 'graded',
			label: __( 'Graded (7 days)', 'pressprimer-assignment' ),
			value: stats?.recent_graded ?? '-',
			icon: <CheckCircleOutlined />,
		},
		{
			key: 'avg_score',
			label: __( 'Average Score (7 days)', 'pressprimer-assignment' ),
			value:
				stats?.recent_avg_score !== undefined
					? `${ stats.recent_avg_score }%`
					: '-',
			icon: <PercentageOutlined />,
		},
		{
			key: 'return_rate',
			label: __( 'Return Rate (7 days)', 'pressprimer-assignment' ),
			value:
				stats?.recent_return_rate !== undefined
					? `${ stats.recent_return_rate }%`
					: '-',
			icon: <SwapOutlined />,
		},
	];

	return (
		<div className="ppa-stats-cards">
			{ cards.map( ( card ) => (
				<div key={ card.key } className="ppa-stats-card">
					<div
						className="ppa-stats-card-icon"
						style={ {
							color: iconColor,
							backgroundColor: iconBgColor,
						} }
					>
						{ card.icon }
					</div>
					<div className="ppa-stats-card-content">
						<div className="ppa-stats-card-value">
							{ loading ? '-' : card.value }
						</div>
						<div className="ppa-stats-card-label">
							{ card.label }
						</div>
					</div>
				</div>
			) ) }
		</div>
	);
};

export default StatsCards;
