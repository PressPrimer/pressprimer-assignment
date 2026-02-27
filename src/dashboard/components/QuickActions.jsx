/**
 * Quick Actions Component
 *
 * Provides shortcuts to common actions.
 *
 * @package
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { Button } from 'antd';
import {
	PlusOutlined,
	InboxOutlined,
	EditOutlined,
	BarChartOutlined,
	PlayCircleOutlined,
} from '@ant-design/icons';

/**
 * Quick Actions Component
 *
 * @param {Object}   props              Component props.
 * @param {Object}   props.urls         URL mappings for actions.
 * @param {Function} props.onLaunchTour Callback to launch onboarding tour.
 * @return {JSX.Element} Rendered component.
 */
const QuickActions = ( { urls = {}, onLaunchTour } ) => {
	const actions = [
		{
			key: 'create_assignment',
			label: __( 'Create Assignment', 'pressprimer-assignment' ),
			icon: <PlusOutlined />,
			url:
				urls.create_assignment ||
				'admin.php?page=pressprimer-assignment-assignments&action=new',
			type: 'primary',
		},
		{
			key: 'view_submissions',
			label: __( 'View Submissions', 'pressprimer-assignment' ),
			icon: <InboxOutlined />,
			url:
				urls.submissions ||
				'admin.php?page=pressprimer-assignment-submissions',
			type: 'default',
		},
		{
			key: 'open_grading',
			label: __( 'Open Grading Queue', 'pressprimer-assignment' ),
			icon: <EditOutlined />,
			url:
				urls.grading || 'admin.php?page=pressprimer-assignment-grading',
			type: 'default',
		},
		{
			key: 'view_reports',
			label: __( 'View Reports', 'pressprimer-assignment' ),
			icon: <BarChartOutlined />,
			url:
				urls.reports || 'admin.php?page=pressprimer-assignment-reports',
			type: 'default',
		},
	];

	return (
		<div className="ppa-dashboard-card">
			<h3 className="ppa-dashboard-card-title">
				{ __( 'Quick Actions', 'pressprimer-assignment' ) }
			</h3>
			<div className="ppa-quick-actions">
				{ actions.map( ( action ) => (
					<Button
						key={ action.key }
						type={ action.type }
						icon={ action.icon }
						href={ action.url }
						block
						className="ppa-quick-action-button"
					>
						{ action.label }
					</Button>
				) ) }
				<Button
					type="default"
					icon={ <PlayCircleOutlined /> }
					onClick={ onLaunchTour }
					block
					className="ppa-quick-action-button"
				>
					{ __( 'Watch Onboarding Tour', 'pressprimer-assignment' ) }
				</Button>
			</div>
		</div>
	);
};

export default QuickActions;
