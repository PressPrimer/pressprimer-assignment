/**
 * Reports - Main Index Component
 *
 * Shows overview stats, activity chart, and available reports grid.
 * Mirrors the Quiz Reports index page pattern.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Spin, Alert, Card, Row, Col, Select, Empty } from 'antd';
import { TrophyOutlined, RocketOutlined } from '@ant-design/icons';
import {
	LineChart,
	Line,
	XAxis,
	YAxis,
	CartesianGrid,
	Tooltip,
	Legend,
	ResponsiveContainer,
} from 'recharts';

import OverviewCards from './OverviewCards';

/**
 * Format date for chart display.
 *
 * @param {string} dateStr Date string in YYYY-MM-DD format.
 * @return {string} Formatted date.
 */
const formatDateLabel = ( dateStr ) => {
	const date = new Date( dateStr + 'T00:00:00' );
	return date.toLocaleDateString( undefined, {
		month: 'short',
		day: 'numeric',
	} );
};

/**
 * Custom tooltip component for the chart.
 *
 * @param {Object}  props         Tooltip props from Recharts.
 * @param {boolean} props.active  Whether tooltip is active.
 * @param {Array}   props.payload Tooltip data points.
 * @param {string}  props.label   X-axis label value.
 * @return {JSX.Element|null} Tooltip element or null.
 */
const CustomTooltip = ( { active, payload, label } ) => {
	if ( ! active || ! payload || ! payload.length ) {
		return null;
	}

	const date = new Date( label + 'T00:00:00' );
	const formattedDate = date.toLocaleDateString( undefined, {
		weekday: 'short',
		month: 'short',
		day: 'numeric',
		year: 'numeric',
	} );

	return (
		<div className="ppa-chart-tooltip">
			<p className="ppa-chart-tooltip-date">{ formattedDate }</p>
			{ payload.map( ( entry, index ) => (
				<p key={ index } style={ { color: entry.color } }>
					{ entry.name }: { entry.value !== null ? entry.value : '-' }
					{ entry.dataKey === 'avg_score' && entry.value !== null
						? '%'
						: '' }
				</p>
			) ) }
		</div>
	);
};

/**
 * Reports Component
 *
 * @return {JSX.Element} Rendered component.
 */
const Reports = () => {
	const [ overviewStats, setOverviewStats ] = useState( null );
	const [ chartData, setChartData ] = useState( [] );
	const [ chartDays, setChartDays ] = useState( 90 );
	const [ loading, setLoading ] = useState( true );
	const [ chartLoading, setChartLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	// Fetch overview stats.
	useEffect( () => {
		const fetchOverview = async () => {
			try {
				setLoading( true );
				const response = await apiFetch( {
					path: '/ppa/v1/statistics/overview',
				} );

				if ( response.success ) {
					setOverviewStats( response.data );
				}
			} catch ( err ) {
				setError(
					err.message ||
						__(
							'Failed to load overview statistics.',
							'pressprimer-assignment'
						)
				);
			} finally {
				setLoading( false );
			}
		};

		fetchOverview();
	}, [] );

	// Fetch chart data.
	const fetchChart = useCallback( async () => {
		setChartLoading( true );
		try {
			const response = await apiFetch( {
				path: `/ppa/v1/statistics/activity-chart?days=${ chartDays }`,
			} );

			if ( response.success && response.data?.data ) {
				setChartData( response.data.data );
			}
		} catch {
			// Chart will show empty state.
		} finally {
			setChartLoading( false );
		}
	}, [ chartDays ] );

	useEffect( () => {
		fetchChart();
	}, [ fetchChart ] );

	// Calculate tick interval based on data length.
	const getTickInterval = () => {
		if ( chartData.length <= 30 ) {
			return 0;
		}
		if ( chartData.length <= 90 ) {
			return 6;
		}
		if ( chartData.length <= 180 ) {
			return 13;
		}
		if ( chartData.length <= 365 ) {
			return 29;
		}
		return 59;
	};

	const hasChartData = chartData.some( ( d ) => d.submissions > 0 );

	// Chart range options.
	const rangeOptions = [
		{
			value: 30,
			label: __( 'Last 30 days', 'pressprimer-assignment' ),
		},
		{
			value: 90,
			label: __( 'Last 90 days', 'pressprimer-assignment' ),
		},
		{
			value: 180,
			label: __( 'Last 6 months', 'pressprimer-assignment' ),
		},
		{
			value: 365,
			label: __( 'Last year', 'pressprimer-assignment' ),
		},
		{
			value: 730,
			label: __( 'Last 2 years', 'pressprimer-assignment' ),
		},
	];

	// Report cards for the grid.
	const reports = [
		{
			key: 'assignment-performance',
			title: __( 'Assignment Performance', 'pressprimer-assignment' ),
			description: __(
				'See how each assignment is performing with submission counts, average scores, and pass rates.',
				'pressprimer-assignment'
			),
			icon: <TrophyOutlined />,
			color: '#faad14',
			available: true,
			comingSoon: false,
			href: 'admin.php?page=pressprimer-assignment-reports&report=assignment-performance',
		},
		{
			key: 'coming-soon',
			title: __( 'More Reports Coming Soon!', 'pressprimer-assignment' ),
			description: __(
				"We're working on additional reports to help you better understand your assignment results.",
				'pressprimer-assignment'
			),
			icon: <RocketOutlined />,
			color: '#8c8c8c',
			available: false,
			comingSoon: true,
		},
	];

	// Get mascot from localized data.
	const pluginUrl = window.pressprimerAssignmentReportsData?.pluginUrl || '';
	const reportsMascot =
		window.pressprimerAssignmentReportsData?.reportsMascot ||
		`${ pluginUrl }assets/images/reports-mascot.png`;

	return (
		<div className="ppa-reports-container">
			{ /* Header */ }
			<div className="ppa-reports-header">
				<div className="ppa-reports-header-content">
					<h1>{ __( 'Reports', 'pressprimer-assignment' ) }</h1>
					<p>
						{ __(
							'View assignment performance and student results.',
							'pressprimer-assignment'
						) }
					</p>
				</div>
				<img
					src={ reportsMascot }
					alt=""
					className="ppa-reports-header-mascot"
				/>
			</div>

			{ /* Error Alert */ }
			{ error && (
				<Alert
					message={ __( 'Error', 'pressprimer-assignment' ) }
					description={ error }
					type="error"
					showIcon
					closable
					onClose={ () => setError( null ) }
					style={ { marginBottom: 24 } }
				/>
			) }

			<Spin
				spinning={ loading }
				tip={ __( 'Loading…', 'pressprimer-assignment' ) }
			>
				<div className="ppa-reports-content">
					{ /* Overview Cards */ }
					<OverviewCards
						stats={ overviewStats }
						loading={ loading }
					/>

					{ /* Activity Chart */ }
					<div className="ppa-reports-chart-card">
						<div className="ppa-reports-chart-header">
							<h3 className="ppa-reports-chart-title">
								{ __(
									'Submission Activity',
									'pressprimer-assignment'
								) }
							</h3>
							<Select
								value={ chartDays }
								onChange={ setChartDays }
								options={ rangeOptions }
								size="small"
								className="ppa-reports-chart-select"
							/>
						</div>

						<Spin spinning={ chartLoading }>
							<div className="ppa-reports-chart-container">
								{ hasChartData ? (
									<ResponsiveContainer
										width="100%"
										height={ 280 }
									>
										<LineChart
											data={ chartData }
											margin={ {
												top: 10,
												right: 30,
												left: 0,
												bottom: 0,
											} }
										>
											<CartesianGrid
												strokeDasharray="3 3"
												stroke="#f0f0f0"
											/>
											<XAxis
												dataKey="date"
												tickFormatter={
													formatDateLabel
												}
												interval={ getTickInterval() }
												tick={ {
													fontSize: 11,
													fill: '#8c8c8c',
												} }
												axisLine={ {
													stroke: '#d9d9d9',
												} }
												tickLine={ {
													stroke: '#d9d9d9',
												} }
											/>
											<YAxis
												yAxisId="left"
												tick={ {
													fontSize: 11,
													fill: '#8c8c8c',
												} }
												axisLine={ {
													stroke: '#d9d9d9',
												} }
												tickLine={ {
													stroke: '#d9d9d9',
												} }
												allowDecimals={ false }
											/>
											<YAxis
												yAxisId="right"
												orientation="right"
												domain={ [ 0, 100 ] }
												tick={ {
													fontSize: 11,
													fill: '#8c8c8c',
												} }
												axisLine={ {
													stroke: '#d9d9d9',
												} }
												tickLine={ {
													stroke: '#d9d9d9',
												} }
												tickFormatter={ ( value ) =>
													`${ value }%`
												}
											/>
											<Tooltip
												content={ <CustomTooltip /> }
											/>
											<Legend
												wrapperStyle={ {
													paddingTop: 10,
													fontSize: 12,
												} }
											/>
											<Line
												yAxisId="left"
												type="monotone"
												dataKey="submissions"
												name={ __(
													'Submissions',
													'pressprimer-assignment'
												) }
												stroke="#1890ff"
												strokeWidth={ 2 }
												dot={ false }
												activeDot={ {
													r: 4,
												} }
											/>
											<Line
												yAxisId="right"
												type="monotone"
												dataKey="avg_score"
												name={ __(
													'Avg Score',
													'pressprimer-assignment'
												) }
												stroke="#52c41a"
												strokeWidth={ 2 }
												dot={ false }
												activeDot={ {
													r: 4,
												} }
												connectNulls
											/>
										</LineChart>
									</ResponsiveContainer>
								) : (
									<Empty
										image={ Empty.PRESENTED_IMAGE_SIMPLE }
										description={ __(
											'No assignment activity in this period',
											'pressprimer-assignment'
										) }
										style={ {
											padding: '40px 0',
										} }
									/>
								) }
							</div>
						</Spin>
					</div>

					{ /* Available Reports Grid */ }
					<div className="ppa-reports-section">
						<h2 className="ppa-reports-section-title">
							{ __(
								'Available Reports',
								'pressprimer-assignment'
							) }
						</h2>
						<Row gutter={ [ 16, 16 ] }>
							{ reports.map( ( report ) => (
								<Col
									key={ report.key }
									xs={ 24 }
									sm={ 12 }
									lg={ 8 }
								>
									<Card
										className={ `ppa-report-card ${
											report.comingSoon
												? 'ppa-report-card--coming-soon'
												: ''
										}` }
										hoverable={ report.available }
										onClick={ () => {
											if (
												report.available &&
												report.href
											) {
												window.location.href =
													report.href;
											}
										} }
									>
										<div
											className="ppa-report-card-icon"
											style={ {
												backgroundColor: report.color,
											} }
										>
											{ report.icon }
										</div>
										<div className="ppa-report-card-content">
											<h3 className="ppa-report-card-title">
												{ report.title }
											</h3>
											<p className="ppa-report-card-description">
												{ report.description }
											</p>
										</div>
									</Card>
								</Col>
							) ) }
						</Row>
					</div>
				</div>
			</Spin>
		</div>
	);
};

export default Reports;
