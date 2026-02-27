/**
 * Assignment Performance Report Component
 *
 * Full-page report showing per-assignment performance statistics
 * in a sortable, searchable, paginated table with date range filtering.
 * Mirrors Quiz's QuizPerformanceReport pattern.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Table, Input, Card, Button, Spin, Alert } from 'antd';
import { TrophyOutlined, ArrowLeftOutlined } from '@ant-design/icons';

import DateRangePicker from './DateRangePicker';
import { getDateRange, formatGradingTime } from '../utils/dateUtils';

const { Search } = Input;

/**
 * Assignment Performance Report Component
 *
 * @return {JSX.Element} Rendered component.
 */
const AssignmentPerformanceReport = () => {
	const [ dateRange, setDateRange ] = useState( '30days' );
	const [ customDates, setCustomDates ] = useState( {
		from: null,
		to: null,
	} );
	const [ data, setData ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ pagination, setPagination ] = useState( {
		current: 1,
		pageSize: 20,
		total: 0,
	} );
	const [ search, setSearch ] = useState( '' );
	const [ sortField, setSortField ] = useState( 'submissions' );
	const [ sortOrder, setSortOrder ] = useState( 'descend' );

	// Get effective date range.
	const getEffectiveDates = useCallback( () => {
		if ( dateRange === 'custom' ) {
			return customDates;
		}
		return getDateRange( dateRange );
	}, [ dateRange, customDates ] );

	// Fetch data.
	const fetchData = useCallback( async () => {
		setLoading( true );
		setError( null );

		try {
			const dates = getEffectiveDates();
			const params = new URLSearchParams();
			params.append( 'page', pagination.current );
			params.append( 'per_page', pagination.pageSize );
			params.append( 'orderby', sortField );
			params.append( 'order', sortOrder === 'ascend' ? 'ASC' : 'DESC' );

			if ( search ) {
				params.append( 'search', search );
			}
			if ( dates.from ) {
				params.append( 'date_from', dates.from );
			}
			if ( dates.to ) {
				params.append( 'date_to', dates.to );
			}

			const response = await apiFetch( {
				path: `/ppa/v1/statistics/assignment-performance?${ params.toString() }`,
			} );

			if ( response.success ) {
				setData( response.data.items || [] );
				setPagination( ( prev ) => ( {
					...prev,
					total: response.data.total || 0,
				} ) );
			}
		} catch ( err ) {
			setError(
				err.message ||
					__(
						'Failed to load report data.',
						'pressprimer-assignment'
					)
			);
		} finally {
			setLoading( false );
		}
	}, [
		pagination.current,
		pagination.pageSize,
		sortField,
		sortOrder,
		search,
		getEffectiveDates,
	] );

	// Fetch on mount and when dependencies change.
	useEffect( () => {
		fetchData();
	}, [ fetchData ] );

	// Handle date range change.
	const handleDateRangeChange = ( preset, custom = null ) => {
		setDateRange( preset );
		if ( preset === 'custom' && custom ) {
			setCustomDates( custom );
		}
		setPagination( ( prev ) => ( { ...prev, current: 1 } ) );
	};

	// Handle table change (pagination, sorting).
	const handleTableChange = ( newPagination, filters, sorter ) => {
		setPagination( {
			...pagination,
			current: newPagination.current,
			pageSize: newPagination.pageSize,
		} );

		if ( sorter.field ) {
			setSortField( sorter.field );
			setSortOrder( sorter.order || 'descend' );
		}
	};

	// Handle search.
	const handleSearch = ( value ) => {
		setSearch( value );
		setPagination( ( prev ) => ( { ...prev, current: 1 } ) );
	};

	// Table columns.
	const columns = [
		{
			title: __( 'Assignment', 'pressprimer-assignment' ),
			dataIndex: 'title',
			key: 'title',
			sorter: true,
			sortOrder: sortField === 'title' ? sortOrder : null,
			render: ( title, record ) => (
				<a
					href={ `admin.php?page=pressprimer-assignment&action=edit&assignment=${ record.id }` }
				>
					{ title }
				</a>
			),
		},
		{
			title: __( 'Submissions', 'pressprimer-assignment' ),
			dataIndex: 'submissions',
			key: 'submissions',
			sorter: true,
			sortOrder: sortField === 'submissions' ? sortOrder : null,
			width: 120,
			align: 'center',
			render: ( submissions ) => submissions || 0,
		},
		{
			title: __( 'Avg Score', 'pressprimer-assignment' ),
			dataIndex: 'avg_score',
			key: 'avg_score',
			sorter: true,
			sortOrder: sortField === 'avg_score' ? sortOrder : null,
			width: 120,
			align: 'center',
			render: ( score ) =>
				score !== null ? `${ Math.round( score ) }%` : '-',
		},
		{
			title: __( 'Pass Rate', 'pressprimer-assignment' ),
			dataIndex: 'pass_rate',
			key: 'pass_rate',
			sorter: true,
			sortOrder: sortField === 'pass_rate' ? sortOrder : null,
			width: 120,
			align: 'center',
			render: ( rate ) =>
				rate !== null ? `${ Math.round( rate ) }%` : '-',
		},
		{
			title: __( 'Awaiting', 'pressprimer-assignment' ),
			dataIndex: 'awaiting_grading',
			key: 'awaiting_grading',
			sorter: true,
			sortOrder: sortField === 'awaiting_grading' ? sortOrder : null,
			width: 120,
			align: 'center',
			render: ( count ) => count || 0,
		},
		{
			title: __( 'Avg Grading Time', 'pressprimer-assignment' ),
			dataIndex: 'avg_grading_time',
			key: 'avg_grading_time',
			sorter: true,
			sortOrder: sortField === 'avg_grading_time' ? sortOrder : null,
			width: 160,
			align: 'center',
			render: ( time ) => formatGradingTime( time ),
		},
	];

	// Get mascot from localized data.
	const pluginUrl = window.pressprimerAssignmentReportsData?.pluginUrl || '';
	const reportsMascot =
		window.pressprimerAssignmentReportsData?.reportsMascot ||
		`${ pluginUrl }assets/images/presspilot-mascot.svg`;

	return (
		<div className="ppa-reports-container">
			{ /* Header */ }
			<div className="ppa-reports-header">
				<div className="ppa-reports-header-content">
					<Button
						type="link"
						icon={ <ArrowLeftOutlined /> }
						href="admin.php?page=pressprimer-assignment-reports"
						className="ppa-reports-back-link"
					>
						{ __( 'All Reports', 'pressprimer-assignment' ) }
					</Button>
					<h1>
						<TrophyOutlined
							style={ { marginRight: 12, color: '#faad14' } }
						/>
						{ __(
							'Assignment Performance',
							'pressprimer-assignment'
						) }
					</h1>
					<p>
						{ __(
							'See how each assignment is performing with submission counts, average scores, and pass rates.',
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

			{ /* Date Range Picker */ }
			<DateRangePicker
				value={ dateRange }
				onChange={ handleDateRangeChange }
			/>

			{ /* Data Table */ }
			<Card className="ppa-reports-card ppa-reports-table-card">
				<div className="ppa-reports-table-header">
					<Search
						placeholder={ __(
							'Search assignments…',
							'pressprimer-assignment'
						) }
						allowClear
						onSearch={ handleSearch }
						className="ppa-reports-search"
					/>
				</div>
				<Spin spinning={ loading }>
					<Table
						columns={ columns }
						dataSource={ data }
						rowKey="id"
						pagination={ {
							...pagination,
							showSizeChanger: true,
							pageSizeOptions: [ '10', '20', '50', '100' ],
							showTotal: ( total, range ) =>
								`${ range[ 0 ] }-${ range[ 1 ] } ${ __(
									'of',
									'pressprimer-assignment'
								) } ${ total }`,
						} }
						onChange={ handleTableChange }
						size="middle"
						className="ppa-reports-table"
						sortDirections={ [ 'ascend', 'descend', 'ascend' ] }
					/>
				</Spin>
			</Card>
		</div>
	);
};

export default AssignmentPerformanceReport;
