/**
 * Assignments List Page
 *
 * Displays assignments in an Ant Design Table with filtering,
 * bulk actions, and navigation to create/edit forms.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Table,
	Button,
	Tag,
	Space,
	Popconfirm,
	message,
	Input,
	Select,
} from 'antd';
import {
	PlusOutlined,
	EditOutlined,
	DeleteOutlined,
	SearchOutlined,
} from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';
import { assignmentsApi } from '../../api';

const { Option } = Select;

/**
 * Status tag color mapping.
 */
const STATUS_COLORS = {
	draft: 'default',
	published: 'green',
	archived: 'orange',
};

/**
 * Status label mapping.
 */
const STATUS_LABELS = {
	draft: __( 'Draft', 'pressprimer-assignment' ),
	published: __( 'Published', 'pressprimer-assignment' ),
	archived: __( 'Archived', 'pressprimer-assignment' ),
};

/**
 * Assignments list page component.
 *
 * @return {JSX.Element} Assignments list view.
 */
export default function Assignments() {
	const navigate = useNavigate();

	const [ assignments, setAssignments ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const [ currentPage, setCurrentPage ] = useState( 1 );
	const [ pageSize, setPageSize ] = useState( 20 );
	const [ total, setTotal ] = useState( 0 );
	const [ filters, setFilters ] = useState( {
		search: '',
		status: '',
	} );
	const [ selectedRowKeys, setSelectedRowKeys ] = useState( [] );
	const [ deleting, setDeleting ] = useState( false );

	/**
	 * Fetch assignments from API.
	 */
	const fetchAssignments = useCallback( async () => {
		setLoading( true );
		try {
			const params = {
				page: currentPage,
				per_page: pageSize,
			};

			if ( filters.search ) {
				params.search = filters.search;
			}
			if ( filters.status ) {
				params.status = filters.status;
			}

			const response = await assignmentsApi.list( params );

			setAssignments( response.items || [] );
			setTotal( response.total || 0 );
		} catch ( error ) {
			message.error(
				error.message ||
					__(
						'Failed to load assignments.',
						'pressprimer-assignment'
					)
			);
		} finally {
			setLoading( false );
		}
	}, [ currentPage, pageSize, filters ] );

	useEffect( () => {
		fetchAssignments();
	}, [ fetchAssignments ] );

	/**
	 * Handle table pagination change.
	 *
	 * @param {Object} pag Pagination object from Ant Design Table.
	 */
	const handleTableChange = ( pag ) => {
		setCurrentPage( pag.current );
		setPageSize( pag.pageSize );
	};

	/**
	 * Handle single assignment delete.
	 *
	 * @param {number} id Assignment ID.
	 */
	const handleDelete = async ( id ) => {
		try {
			await assignmentsApi.delete( id );
			message.success(
				__( 'Assignment deleted.', 'pressprimer-assignment' )
			);
			fetchAssignments();
		} catch ( error ) {
			message.error(
				error.message ||
					__(
						'Failed to delete assignment.',
						'pressprimer-assignment'
					)
			);
		}
	};

	/**
	 * Handle bulk delete of selected assignments.
	 */
	const handleBulkDelete = async () => {
		setDeleting( true );
		try {
			await Promise.all(
				selectedRowKeys.map( ( id ) => assignmentsApi.delete( id ) )
			);
			message.success(
				__( 'Selected assignments deleted.', 'pressprimer-assignment' )
			);
			setSelectedRowKeys( [] );
			fetchAssignments();
		} catch ( error ) {
			message.error(
				error.message ||
					__(
						'Failed to delete some assignments.',
						'pressprimer-assignment'
					)
			);
		} finally {
			setDeleting( false );
		}
	};

	/**
	 * Handle search input change with debounce reset.
	 *
	 * @param {Object} e Input change event.
	 */
	const handleSearchChange = ( e ) => {
		setFilters( ( prev ) => ( { ...prev, search: e.target.value } ) );
		setCurrentPage( 1 );
	};

	/**
	 * Handle status filter change.
	 *
	 * @param {string} value Status value.
	 */
	const handleStatusChange = ( value ) => {
		setFilters( ( prev ) => ( { ...prev, status: value } ) );
		setCurrentPage( 1 );
	};

	/**
	 * Table column definitions.
	 */
	const columns = [
		{
			title: __( 'Title', 'pressprimer-assignment' ),
			dataIndex: 'title',
			key: 'title',
			render: ( text, record ) => (
				<Button
					type="link"
					onClick={ () =>
						navigate( `/assignments/${ record.id }/edit` )
					}
					style={ { padding: 0 } }
				>
					{ text || __( '(Untitled)', 'pressprimer-assignment' ) }
				</Button>
			),
		},
		{
			title: __( 'Status', 'pressprimer-assignment' ),
			dataIndex: 'status',
			key: 'status',
			width: 120,
			render: ( status ) => (
				<Tag color={ STATUS_COLORS[ status ] || 'default' }>
					{ STATUS_LABELS[ status ] || status }
				</Tag>
			),
		},
		{
			title: __( 'Submissions', 'pressprimer-assignment' ),
			dataIndex: 'submission_count',
			key: 'submission_count',
			width: 120,
			align: 'center',
			render: ( count ) => count || 0,
		},
		{
			title: __( 'Actions', 'pressprimer-assignment' ),
			key: 'actions',
			width: 150,
			render: ( _, record ) => (
				<Space size="small">
					<Button
						type="text"
						size="small"
						icon={ <EditOutlined /> }
						onClick={ () =>
							navigate( `/assignments/${ record.id }/edit` )
						}
					>
						{ __( 'Edit', 'pressprimer-assignment' ) }
					</Button>
					<Popconfirm
						title={ __(
							'Delete this assignment?',
							'pressprimer-assignment'
						) }
						description={ __(
							'This action cannot be undone.',
							'pressprimer-assignment'
						) }
						onConfirm={ () => handleDelete( record.id ) }
						okText={ __( 'Delete', 'pressprimer-assignment' ) }
						cancelText={ __( 'Cancel', 'pressprimer-assignment' ) }
					>
						<Button
							type="text"
							size="small"
							danger
							icon={ <DeleteOutlined /> }
						/>
					</Popconfirm>
				</Space>
			),
		},
	];

	/**
	 * Row selection configuration for bulk actions.
	 */
	const rowSelection = {
		selectedRowKeys,
		onChange: ( keys ) => setSelectedRowKeys( keys ),
	};

	return (
		<div className="ppa-assignments-list">
			<div
				className="ppa-page-header"
				style={ {
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
					marginBottom: 16,
				} }
			>
				<h2>{ __( 'Assignments', 'pressprimer-assignment' ) }</h2>
				<Button
					type="primary"
					icon={ <PlusOutlined /> }
					onClick={ () => navigate( '/assignments/new' ) }
				>
					{ __( 'Create New', 'pressprimer-assignment' ) }
				</Button>
			</div>

			<div
				className="ppa-table-filters"
				style={ {
					display: 'flex',
					gap: 12,
					marginBottom: 16,
				} }
			>
				<Input
					placeholder={ __(
						'Search assignments\u2026',
						'pressprimer-assignment'
					) }
					prefix={ <SearchOutlined /> }
					value={ filters.search }
					onChange={ handleSearchChange }
					style={ { width: 300 } }
					allowClear
				/>
				<Select
					placeholder={ __(
						'All statuses',
						'pressprimer-assignment'
					) }
					value={ filters.status || undefined }
					onChange={ handleStatusChange }
					allowClear
					style={ { width: 160 } }
				>
					<Option value="draft">
						{ __( 'Draft', 'pressprimer-assignment' ) }
					</Option>
					<Option value="published">
						{ __( 'Published', 'pressprimer-assignment' ) }
					</Option>
					<Option value="archived">
						{ __( 'Archived', 'pressprimer-assignment' ) }
					</Option>
				</Select>

				{ selectedRowKeys.length > 0 && (
					<Popconfirm
						title={ __(
							'Delete selected assignments?',
							'pressprimer-assignment'
						) }
						description={ __(
							'This will delete all selected assignments.',
							'pressprimer-assignment'
						) }
						onConfirm={ handleBulkDelete }
						okText={ __( 'Delete', 'pressprimer-assignment' ) }
						cancelText={ __( 'Cancel', 'pressprimer-assignment' ) }
					>
						<Button danger loading={ deleting }>
							{ __(
								'Delete Selected',
								'pressprimer-assignment'
							) }{ ' ' }
							({ selectedRowKeys.length })
						</Button>
					</Popconfirm>
				) }
			</div>

			<Table
				rowKey="id"
				columns={ columns }
				dataSource={ assignments }
				loading={ loading }
				rowSelection={ rowSelection }
				pagination={ {
					current: currentPage,
					pageSize,
					total,
					showSizeChanger: true,
					showTotal: ( count ) =>
						sprintf(
							/* translators: %d: number of assignments */
							__( '%d assignment(s)', 'pressprimer-assignment' ),
							count
						),
				} }
				onChange={ handleTableChange }
				locale={ {
					emptyText: __(
						'No assignments found.',
						'pressprimer-assignment'
					),
				} }
			/>
		</div>
	);
}
