/**
 * Submission Detail Component
 *
 * Displays a single submission's full details in a single-column layout
 * with document preview, read-only score/feedback, and admin actions.
 * Used on the Submissions admin page when action=view.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Card,
	Descriptions,
	Tag,
	Button,
	Space,
	Spin,
	Alert,
	Divider,
	Typography,
	Tooltip,
	Modal,
	message,
} from 'antd';
import {
	LeftOutlined,
	RightOutlined,
	CheckCircleOutlined,
	CloseCircleOutlined,
	EditOutlined,
	DeleteOutlined,
	ExclamationCircleOutlined,
} from '@ant-design/icons';
import DocumentPanel from '../../shared/components/viewers/DocumentPanel';

const { Title, Text, Paragraph } = Typography;

/**
 * Status label mapping.
 */
const STATUS_CONFIG = {
	draft: { label: __( 'Draft', 'pressprimer-assignment' ), color: 'default' },
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
};

/**
 * Navigate to a submission detail URL.
 *
 * @param {number} id Submission ID.
 */
const navigateToSubmission = ( id ) => {
	const url = new URL( window.location.href );
	url.searchParams.set( 'submission', String( id ) );
	window.location.href = url.toString();
};

/**
 * Navigate back to the submissions list.
 */
const navigateToList = () => {
	const adminUrl =
		window.pressprimerAssignmentSubmissionDetailData?.adminUrl || '';
	window.location.href =
		adminUrl + 'admin.php?page=pressprimer-assignment-submissions';
};

/**
 * SubmissionDetail component
 *
 * @param {Object} props              Component props.
 * @param {number} props.submissionId Submission ID to display.
 * @return {JSX.Element} Rendered component.
 */
const SubmissionDetail = ( { submissionId } ) => {
	const [ submission, setSubmission ] = useState( null );
	const [ assignment, setAssignment ] = useState( null );
	const [ files, setFiles ] = useState( [] );
	const [ siblings, setSiblings ] = useState( { prev: null, next: null } );
	const [ loading, setLoading ] = useState( true );

	/**
	 * Load submission data from REST API.
	 */
	const loadSubmission = useCallback( async () => {
		setLoading( true );
		try {
			const data = await apiFetch( {
				path: `/ppa/v1/submissions/${ submissionId }`,
			} );

			setSubmission( data.submission );
			setAssignment( data.assignment );
			setFiles( data.files );
			setSiblings( data.siblings );
		} catch ( loadError ) {
			message.error(
				loadError.message ||
					__( 'Failed to load submission.', 'pressprimer-assignment' )
			);
		} finally {
			setLoading( false );
		}
	}, [ submissionId ] );

	useEffect( () => {
		loadSubmission();
	}, [ loadSubmission ] );

	/**
	 * Handle delete submission.
	 */
	const handleDelete = useCallback( () => {
		Modal.confirm( {
			title: __( 'Delete this submission?', 'pressprimer-assignment' ),
			icon: <ExclamationCircleOutlined />,
			content: __(
				'This action cannot be undone. All submitted files will also be deleted.',
				'pressprimer-assignment'
			),
			okText: __( 'Delete', 'pressprimer-assignment' ),
			okType: 'danger',
			cancelText: __( 'Cancel', 'pressprimer-assignment' ),
			onOk() {
				const detailData =
					window.pressprimerAssignmentSubmissionDetailData || {};
				const adminUrl = detailData.adminUrl || '';
				const deleteNonce = detailData.deleteNonce || '';

				window.location.href =
					adminUrl +
					'admin.php?page=pressprimer-assignment-submissions&action=delete&submission=' +
					submissionId +
					'&_wpnonce=' +
					deleteNonce;
			},
		} );
	}, [ submissionId ] );

	if ( loading ) {
		return (
			<div
				style={ {
					display: 'flex',
					justifyContent: 'center',
					alignItems: 'center',
					minHeight: 400,
				} }
			>
				<Spin size="large" />
			</div>
		);
	}

	if ( ! submission ) {
		return (
			<Alert
				message={ __(
					'Submission not found.',
					'pressprimer-assignment'
				) }
				type="error"
				showIcon
			/>
		);
	}

	const statusConfig = STATUS_CONFIG[ submission.status ] || {
		label: submission.status,
		color: 'default',
	};
	const canDelete =
		submission.status === 'draft' ||
		submission.status === 'submitted' ||
		submission.status === 'grading';
	const score = submission.score;
	const feedback = submission.feedback || '';
	const passing =
		score !== null && assignment && score >= assignment.passing_score;
	const percentage =
		score !== null && assignment && assignment.max_points > 0
			? Math.round( ( score / assignment.max_points ) * 100 )
			: null;

	const detailData = window.pressprimerAssignmentSubmissionDetailData || {};
	const adminUrl = detailData.adminUrl || '';
	const gradingUrl =
		adminUrl +
		'admin.php?page=pressprimer-assignment-grading&action=grade&submission=' +
		submissionId;

	return (
		<div className="ppa-submission-detail">
			{ /* Header */ }
			<div
				style={ {
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
					marginBottom: 16,
				} }
			>
				<Button onClick={ navigateToList }>
					{ __(
						'\u2190 Back to Submissions',
						'pressprimer-assignment'
					) }
				</Button>

				<div
					style={ {
						display: 'flex',
						alignItems: 'center',
						gap: 12,
					} }
				>
					<Title level={ 4 } style={ { margin: 0 } }>
						{ __( 'Submission Details', 'pressprimer-assignment' ) }
					</Title>
					<Tag color={ statusConfig.color }>
						{ statusConfig.label }
					</Tag>
				</div>

				<Space>
					<Button
						disabled={ ! siblings.prev }
						onClick={ () => navigateToSubmission( siblings.prev ) }
						icon={ <LeftOutlined /> }
						size="small"
					>
						{ __( 'Prev', 'pressprimer-assignment' ) }
					</Button>
					<Button
						disabled={ ! siblings.next }
						onClick={ () => navigateToSubmission( siblings.next ) }
						size="small"
					>
						{ __( 'Next', 'pressprimer-assignment' ) }{ ' ' }
						<RightOutlined />
					</Button>
				</Space>
			</div>

			{ /* Submission Info */ }
			<Card
				title={ __(
					'Submission Information',
					'pressprimer-assignment'
				) }
				style={ { marginBottom: 16 } }
			>
				<Descriptions column={ 2 } size="small" bordered>
					<Descriptions.Item
						label={ __( 'ID', 'pressprimer-assignment' ) }
					>
						{ submission.id }
					</Descriptions.Item>
					<Descriptions.Item
						label={ __( 'Assignment', 'pressprimer-assignment' ) }
					>
						{ assignment ? (
							<a
								href={
									adminUrl +
									'admin.php?page=pressprimer-assignment-assignments&action=edit&assignment=' +
									assignment.id
								}
							>
								{ assignment.title }
							</a>
						) : (
							'\u2014'
						) }
					</Descriptions.Item>
					<Descriptions.Item
						label={ __( 'Student', 'pressprimer-assignment' ) }
					>
						{ submission.student_name }
						{ submission.student_email && (
							<Text type="secondary" style={ { marginLeft: 8 } }>
								({ submission.student_email })
							</Text>
						) }
					</Descriptions.Item>
					<Descriptions.Item
						label={ __( 'Status', 'pressprimer-assignment' ) }
					>
						<Tag color={ statusConfig.color }>
							{ statusConfig.label }
						</Tag>
					</Descriptions.Item>
					<Descriptions.Item
						label={ __( 'Submitted', 'pressprimer-assignment' ) }
					>
						{ submission.formatted_date || '\u2014' }
					</Descriptions.Item>
					{ submission.submission_number > 1 && (
						<Descriptions.Item
							label={ __(
								'Submission #',
								'pressprimer-assignment'
							) }
						>
							<Tag color="blue">
								{ sprintf(
									/* translators: %d: submission number */
									__( '#%d', 'pressprimer-assignment' ),
									submission.submission_number
								) }
							</Tag>
						</Descriptions.Item>
					) }
					{ submission.graded_at && (
						<Descriptions.Item
							label={ __( 'Graded', 'pressprimer-assignment' ) }
						>
							{ submission.formatted_graded_at ||
								submission.graded_at }
						</Descriptions.Item>
					) }
					{ submission.returned_at && (
						<Descriptions.Item
							label={ __( 'Returned', 'pressprimer-assignment' ) }
						>
							{ submission.formatted_returned_at ||
								submission.returned_at }
						</Descriptions.Item>
					) }
				</Descriptions>
			</Card>

			{ /* Student Notes */ }
			{ submission.student_notes && (
				<Card
					title={ __( 'Student Notes', 'pressprimer-assignment' ) }
					style={ { marginBottom: 16 } }
				>
					<Paragraph
						style={ {
							margin: 0,
							whiteSpace: 'pre-wrap',
						} }
					>
						{ submission.student_notes }
					</Paragraph>
				</Card>
			) }

			{ /* Document Viewer */ }
			{ ( files.length > 0 || submission.text_content ) && (
				<Card
					title={
						submission.text_content
							? __(
									'Submitted Content',
									'pressprimer-assignment'
							  )
							: __( 'Submitted Files', 'pressprimer-assignment' )
					}
					style={ { marginBottom: 16 } }
					bodyStyle={ { padding: 0 } }
				>
					<DocumentPanel
						files={ files }
						textContent={ submission.text_content }
						wordCount={ submission.word_count }
					/>
				</Card>
			) }

			{ /* Score & Feedback */ }
			{ assignment && ( score !== null || feedback ) && (
				<Card
					title={ __( 'Score & Feedback', 'pressprimer-assignment' ) }
					style={ { marginBottom: 16 } }
				>
					{ /* Score */ }
					{ score !== null && (
						<div style={ { marginBottom: feedback ? 0 : 0 } }>
							<Text
								strong
								style={ {
									display: 'block',
									marginBottom: 8,
								} }
							>
								{ __( 'Score', 'pressprimer-assignment' ) }
							</Text>
							<Space align="center">
								<Text style={ { fontSize: 18 } }>
									{ score } / { assignment.max_points }
								</Text>
								{ percentage !== null && (
									<Text type="secondary">
										({ percentage }%)
									</Text>
								) }
							</Space>

							{ /* Pass/Fail indicator */ }
							<div style={ { marginTop: 8 } }>
								{ passing ? (
									<Tag
										icon={ <CheckCircleOutlined /> }
										color="success"
									>
										{ __(
											'Passing',
											'pressprimer-assignment'
										) }
									</Tag>
								) : (
									<Tag
										icon={ <CloseCircleOutlined /> }
										color="error"
									>
										{ sprintf(
											/* translators: %s: required passing score in points */
											__(
												'Not Passing (requires %s pts)',
												'pressprimer-assignment'
											),
											String( assignment.passing_score )
										) }
									</Tag>
								) }
							</div>
						</div>
					) }

					{ score !== null && feedback && <Divider /> }

					{ /* Feedback */ }
					{ feedback && (
						<div>
							<Text
								strong
								style={ {
									display: 'block',
									marginBottom: 8,
								} }
							>
								{ __( 'Feedback', 'pressprimer-assignment' ) }
							</Text>
							<Paragraph
								style={ {
									margin: 0,
									whiteSpace: 'pre-wrap',
								} }
							>
								{ feedback }
							</Paragraph>
						</div>
					) }
				</Card>
			) }

			{ /* Actions */ }
			<Card title={ __( 'Actions', 'pressprimer-assignment' ) }>
				<Space>
					<Tooltip
						title={ __(
							'Open in the full grading interface with keyboard shortcuts and workflow tools',
							'pressprimer-assignment'
						) }
					>
						<Button
							type="primary"
							icon={ <EditOutlined /> }
							href={ gradingUrl }
						>
							{ __(
								'Open in Grading Interface',
								'pressprimer-assignment'
							) }
						</Button>
					</Tooltip>

					{ canDelete && (
						<Button
							danger
							icon={ <DeleteOutlined /> }
							onClick={ handleDelete }
						>
							{ __(
								'Delete Submission',
								'pressprimer-assignment'
							) }
						</Button>
					) }
				</Space>
			</Card>
		</div>
	);
};

export default SubmissionDetail;
