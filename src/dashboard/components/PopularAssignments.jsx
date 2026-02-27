/**
 * Popular Assignments Component
 *
 * Shows top assignments by submission count.
 *
 * @package
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { Empty, Skeleton } from 'antd';
import { TrophyOutlined, FireOutlined } from '@ant-design/icons';

/**
 * Popular Assignments Component
 *
 * @param {Object}  props             Component props.
 * @param {Array}   props.assignments Popular assignments data.
 * @param {boolean} props.loading     Loading state.
 * @return {JSX.Element} Rendered component.
 */
const PopularAssignments = ( { assignments = [], loading } ) => {
	// Medal colors for top 3.
	const getMedalColor = ( index ) => {
		switch ( index ) {
			case 0:
				return '#faad14'; // Gold.
			case 1:
				return '#8c8c8c'; // Silver.
			case 2:
				return '#d48806'; // Bronze.
			default:
				return '#d9d9d9';
		}
	};

	const renderContent = () => {
		if ( loading ) {
			return (
				<div className="ppa-popular-assignments-loading">
					{ [ 1, 2, 3 ].map( ( i ) => (
						<Skeleton.Input
							key={ i }
							active
							size="small"
							block
							style={ { marginBottom: 12 } }
						/>
					) ) }
				</div>
			);
		}

		if ( assignments.length === 0 ) {
			return (
				<Empty
					image={ Empty.PRESENTED_IMAGE_SIMPLE }
					description={ __(
						'No submissions yet',
						'pressprimer-assignment'
					) }
				/>
			);
		}

		return (
			<div className="ppa-popular-assignments-list">
				{ assignments.map( ( assignment, index ) => (
					<div
						key={ assignment.id }
						className="ppa-popular-assignment-item"
					>
						<div className="ppa-popular-assignment-rank">
							{ index < 3 ? (
								<TrophyOutlined
									style={ {
										color: getMedalColor( index ),
										fontSize: 18,
									} }
								/>
							) : (
								<span className="ppa-popular-assignment-rank-number">
									{ index + 1 }
								</span>
							) }
						</div>
						<div className="ppa-popular-assignment-info">
							<a
								href={ `admin.php?page=pressprimer-assignment-assignments&action=edit&assignment=${ assignment.id }` }
								className="ppa-popular-assignment-title"
							>
								{ assignment.title }
							</a>
							<span className="ppa-popular-assignment-submissions">
								{ assignment.submission_count }{ ' ' }
								{ assignment.submission_count === 1
									? __(
											'submission',
											'pressprimer-assignment'
									  )
									: __(
											'submissions',
											'pressprimer-assignment'
									  ) }
							</span>
						</div>
					</div>
				) ) }
			</div>
		);
	};

	return (
		<div className="ppa-dashboard-card">
			<h3 className="ppa-dashboard-card-title">
				<FireOutlined style={ { marginRight: 8, color: '#fa541c' } } />
				{ __( 'Popular Assignments', 'pressprimer-assignment' ) }
				<span className="ppa-dashboard-card-subtitle">
					{ __( 'Last 30 days', 'pressprimer-assignment' ) }
				</span>
			</h3>

			{ renderContent() }
		</div>
	);
};

export default PopularAssignments;
