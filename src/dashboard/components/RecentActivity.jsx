/**
 * Recent Activity Component
 *
 * Shows recent submissions.
 *
 * @package
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { Table, Tag, Empty, Button } from 'antd';
import {
	ClockCircleOutlined,
	UserOutlined,
	ArrowRightOutlined,
	FileOutlined,
	FileTextOutlined,
} from '@ant-design/icons';

/**
 * Format date relative to now.
 *
 * @param {string} dateStr Date string (MySQL format, UTC).
 * @return {string} Formatted date.
 */
const formatDate = ( dateStr ) => {
	if ( ! dateStr ) {
		return '-';
	}

	// MySQL datetime format: "2024-12-02 10:30:00"
	// Append 'Z' to indicate UTC if not already present.
	let normalizedDate = dateStr;
	if (
		! dateStr.endsWith( 'Z' ) &&
		! dateStr.includes( '+' ) &&
		! dateStr.includes( 'T' )
	) {
		normalizedDate = dateStr.replace( ' ', 'T' ) + 'Z';
	}

	const date = new Date( normalizedDate );

	// Check for invalid date.
	if ( isNaN( date.getTime() ) ) {
		return '-';
	}

	const diffMins = Math.floor( ( new Date() - date ) / 60000 );
	if ( diffMins < 1 ) {
		return __( 'Just now', 'pressprimer-assignment' );
	}
	if ( diffMins < 60 ) {
		return `${ diffMins }m ${ __( 'ago', 'pressprimer-assignment' ) }`;
	}

	const diffHours = Math.floor( diffMins / 60 );
	if ( diffHours < 24 ) {
		return `${ diffHours }h ${ __( 'ago', 'pressprimer-assignment' ) }`;
	}

	const diffDays = Math.floor( diffHours / 24 );
	if ( diffDays < 7 ) {
		return `${ diffDays }d ${ __( 'ago', 'pressprimer-assignment' ) }`;
	}

	return date.toLocaleDateString();
};

/**
 * Status tag configuration.
 */
const STATUS_CONFIG = {
	submitted: {
		label: __( 'Submitted', 'pressprimer-assignment' ),
		color: 'blue',
	},
	grading: {
		label: __( 'Grading', 'pressprimer-assignment' ),
		color: 'orange',
	},
	graded: {
		label: __( 'Graded', 'pressprimer-assignment' ),
		color: 'purple',
	},
	returned: {
		label: __( 'Returned', 'pressprimer-assignment' ),
		color: 'green',
	},
	draft: {
		label: __( 'Draft', 'pressprimer-assignment' ),
		color: 'default',
	},
};

/**
 * Recent Activity Component
 *
 * @param {Object}  props             Component props.
 * @param {Array}   props.submissions Recent submissions data.
 * @param {boolean} props.loading     Loading state.
 * @return {JSX.Element} Rendered component.
 */
const RecentActivity = ( { submissions = [], loading } ) => {
	const columns = [
		{
			title: __( 'Student', 'pressprimer-assignment' ),
			dataIndex: 'student_name',
			key: 'student',
			render: ( name ) => (
				<div className="ppa-activity-student">
					<UserOutlined className="ppa-activity-student-icon" />
					<span>
						{ name || __( 'Unknown', 'pressprimer-assignment' ) }
					</span>
				</div>
			),
		},
		{
			title: __( 'Assignment', 'pressprimer-assignment' ),
			dataIndex: 'assignment_title',
			key: 'assignment',
			render: ( title, record ) => (
				<a
					href={ `admin.php?page=pressprimer-assignment-assignments&action=edit&assignment=${ record.assignment_id }` }
				>
					{ title }
				</a>
			),
		},
		{
			title: __( 'Score', 'pressprimer-assignment' ),
			dataIndex: 'score_percent',
			key: 'score',
			width: 100,
			render: ( score ) => (
				<span className="ppa-activity-score">
					{ score !== null ? `${ Math.round( score ) }%` : '-' }
				</span>
			),
		},
		{
			title: __( 'Status', 'pressprimer-assignment' ),
			dataIndex: 'status',
			key: 'status',
			width: 110,
			render: ( status ) => {
				const config = STATUS_CONFIG[ status ] || {
					label: status,
					color: 'default',
				};
				return <Tag color={ config.color }>{ config.label }</Tag>;
			},
		},
		{
			title: __( 'Type', 'pressprimer-assignment' ),
			dataIndex: 'submission_type',
			key: 'type',
			width: 80,
			render: ( type ) =>
				type === 'text' ? (
					<span>
						<FileTextOutlined style={ { marginRight: 4 } } />
						{ __( 'Text', 'pressprimer-assignment' ) }
					</span>
				) : (
					<span>
						<FileOutlined style={ { marginRight: 4 } } />
						{ __( 'File', 'pressprimer-assignment' ) }
					</span>
				),
		},
		{
			title: __( 'When', 'pressprimer-assignment' ),
			dataIndex: 'submitted_at',
			key: 'date',
			width: 120,
			render: ( date ) => (
				<span className="ppa-activity-date">
					{ formatDate( date ) }
				</span>
			),
		},
	];

	return (
		<div className="ppa-dashboard-card ppa-dashboard-card--large">
			<div className="ppa-dashboard-card-header">
				<h3 className="ppa-dashboard-card-title">
					<ClockCircleOutlined style={ { marginRight: 8 } } />
					{ __( 'Recent Activity', 'pressprimer-assignment' ) }
				</h3>
				<Button
					type="link"
					href="admin.php?page=pressprimer-assignment-submissions"
					icon={ <ArrowRightOutlined /> }
					className="ppa-dashboard-card-action"
				>
					{ __( 'View All', 'pressprimer-assignment' ) }
				</Button>
			</div>

			{ ! loading && submissions.length === 0 ? (
				<Empty
					image={ Empty.PRESENTED_IMAGE_SIMPLE }
					description={ __(
						'No recent activity',
						'pressprimer-assignment'
					) }
				/>
			) : (
				<Table
					columns={ columns }
					dataSource={ submissions }
					rowKey="id"
					loading={ loading }
					pagination={ false }
					size="middle"
					className="ppa-activity-table"
				/>
			) }
		</div>
	);
};

export default RecentActivity;
