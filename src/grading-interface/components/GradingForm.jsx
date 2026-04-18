/**
 * Grading Form Component
 *
 * The main grading interface that displays a split-panel layout
 * with the document viewer on the left and the grading form on the right.
 * Handles score input, feedback, save/return actions, keyboard shortcuts,
 * and auto-save.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Row,
	Col,
	Card,
	InputNumber,
	Button,
	Space,
	Spin,
	message,
	Tag,
	Tooltip,
	Typography,
	Divider,
	Input,
	Alert,
} from 'antd';
import {
	LeftOutlined,
	RightOutlined,
	SaveOutlined,
	CheckCircleOutlined,
	CloseCircleOutlined,
	SendOutlined,
} from '@ant-design/icons';
import DocumentPanel from '../../shared/components/viewers/DocumentPanel';

const { Title, Text, Paragraph } = Typography;
const { TextArea } = Input;

// RubricPanel is registered globally by the Educator addon's rubric-builder bundle.
const RubricPanel = window.PPAERubricPanel || null;

// Educator addon localizes rubric data on the grading page.
const educatorGrading = window.pressprimerAssignmentEducatorGrading || null;

// AIGradingPanel is registered globally by the School addon's ai-grading bundle.
const AIGradingPanel = window.PPASAIGradingPanel || null;

// School addon localizes provider configuration on the grading page.
const schoolGrading = window.pressprimerAssignmentSchoolGrading || null;

/**
 * Navigate to a grading URL for a given submission ID.
 *
 * @param {number} id Submission ID.
 */
const navigateToSubmission = ( id ) => {
	const url = new URL( window.location.href );
	url.searchParams.set( 'submission', String( id ) );
	window.location.href = url.toString();
};

/**
 * Navigate back to the grading queue list.
 */
const navigateToList = () => {
	const adminUrl = window.pressprimerAssignmentGradingData?.adminUrl || '';
	window.location.href =
		adminUrl + 'admin.php?page=pressprimer-assignment-grading';
};

/**
 * GradingForm component
 *
 * @param {Object} props              Component props.
 * @param {number} props.submissionId Submission ID to load and grade.
 * @return {JSX.Element} Rendered component.
 */
const GradingForm = ( { submissionId } ) => {
	const [ submission, setSubmission ] = useState( null );
	const [ assignment, setAssignment ] = useState( null );
	const [ files, setFiles ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ hasChanges, setHasChanges ] = useState( false );

	// Form state.
	const [ score, setScore ] = useState( null );
	const [ scoreWarning, setScoreWarning ] = useState( '' );
	const [ feedback, setFeedback ] = useState( '' );
	const [ currentStatus, setCurrentStatus ] = useState( '' );

	// Rubric grading state (Educator addon).
	const [ rubricScores, setRubricScores ] = useState( [] );

	// Key counter to force RubricPanel remount when AI scores are applied.
	const [ rubricKey, setRubricKey ] = useState( 0 );

	// Navigation state.
	const [ siblings, setSiblings ] = useState( { prev: null, next: null } );

	// Ref for auto-save timer.
	const autoSaveTimerRef = useRef( null );

	// Ref to track if a save is in progress (for keyboard shortcut debouncing).
	const savingRef = useRef( false );

	// Ref for feedback textarea to detect focus.
	const feedbackRef = useRef( null );

	// Active grading time tracking.
	const gradingTimeRef = useRef( {
		startedAt: null, // Timestamp when active tracking began.
		accumulated: 0, // Seconds accumulated since last save.
		isActive: true, // Whether the tab is visible/active.
	} );

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
			setScore( data.submission.score );
			setFeedback( data.submission.feedback || '' );
			setCurrentStatus( data.submission.status );
			setSiblings( data.siblings );
			setHasChanges( false );

			// Auto-set status to 'grading' if currently 'submitted'.
			if ( data.submission.status === 'submitted' ) {
				await apiFetch( {
					path: `/ppa/v1/submissions/${ submissionId }`,
					method: 'PUT',
					data: { status: 'grading' },
				} );
				setCurrentStatus( 'grading' );
			}
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
	 * Get elapsed active grading seconds since last reset and reset counter.
	 *
	 * @return {number} Elapsed seconds.
	 */
	const getAndResetGradingTime = useCallback( () => {
		const timer = gradingTimeRef.current;
		let elapsed = timer.accumulated;

		// Add time from current active session.
		if ( timer.isActive && timer.startedAt ) {
			elapsed += Math.round( ( Date.now() - timer.startedAt ) / 1000 );
		}

		// Reset for next interval.
		timer.accumulated = 0;
		timer.startedAt = timer.isActive ? Date.now() : null;

		return elapsed;
	}, [] );

	// Track active grading time via page visibility.
	useEffect( () => {
		const timer = gradingTimeRef.current;
		timer.startedAt = Date.now();
		timer.isActive = true;
		timer.accumulated = 0;

		const handleVisibility = () => {
			if ( document.hidden ) {
				// Tab hidden: accumulate elapsed time and pause.
				if ( timer.isActive && timer.startedAt ) {
					timer.accumulated += Math.round(
						( Date.now() - timer.startedAt ) / 1000
					);
				}
				timer.isActive = false;
				timer.startedAt = null;
			} else {
				// Tab visible: resume tracking.
				timer.isActive = true;
				timer.startedAt = Date.now();
			}
		};

		document.addEventListener( 'visibilitychange', handleVisibility );
		return () => {
			document.removeEventListener(
				'visibilitychange',
				handleVisibility
			);
		};
	}, [ submissionId ] );

	/**
	 * Save grade via REST API.
	 *
	 * @param {boolean} showMessage Whether to show a success message.
	 * @return {boolean} Whether the save succeeded.
	 */
	const saveGrade = useCallback(
		async ( showMessage = true ) => {
			if ( savingRef.current ) {
				return false;
			}

			// Prevent saving with an out-of-range score.
			if (
				score !== null &&
				assignment &&
				( score < 0 || score > assignment.max_points )
			) {
				message.warning(
					sprintf(
						/* translators: %d: maximum points */
						__(
							'Please enter a score between 0 and %d.',
							'pressprimer-assignment'
						),
						assignment.max_points
					)
				);
				return false;
			}

			savingRef.current = true;
			setSaving( true );

			const gradingTimeDelta = getAndResetGradingTime();

			try {
				// Save rubric scores first if Educator addon provides them.
				if ( rubricScores.length > 0 && educatorGrading?.rubric ) {
					await apiFetch( {
						path: `/ppae/v1/submissions/${ submissionId }/rubric-scores`,
						method: 'PUT',
						data: { scores: rubricScores },
					} );
				}

				await apiFetch( {
					path: `/ppa/v1/submissions/${ submissionId }`,
					method: 'PUT',
					data: {
						score,
						feedback,
						status: score !== null ? 'graded' : undefined,
						grading_time_seconds: gradingTimeDelta || undefined,
					},
				} );

				setHasChanges( false );
				if ( score !== null ) {
					setCurrentStatus( 'graded' );
				}
				if ( showMessage ) {
					message.success( __( 'Saved.', 'pressprimer-assignment' ) );
				}
				return true;
			} catch ( saveError ) {
				message.error(
					saveError.message ||
						__( 'Failed to save.', 'pressprimer-assignment' )
				);
				return false;
			} finally {
				setSaving( false );
				savingRef.current = false;
			}
		},
		[
			submissionId,
			score,
			feedback,
			assignment,
			getAndResetGradingTime,
			rubricScores,
		]
	);

	/**
	 * Save grade and navigate to next submission.
	 */
	const saveAndNext = useCallback( async () => {
		const saved = await saveGrade( false );
		if ( saved && siblings.next ) {
			navigateToSubmission( siblings.next );
		} else if ( saved ) {
			message.info(
				__( 'No more submissions.', 'pressprimer-assignment' )
			);
		}
	}, [ saveGrade, siblings.next ] );

	/**
	 * Save grade and return submission to student.
	 */
	const returnToStudent = useCallback( async () => {
		if ( score === null ) {
			message.warning(
				__(
					'Please assign a score before returning.',
					'pressprimer-assignment'
				)
			);
			return;
		}

		const gradingTimeDelta = getAndResetGradingTime();

		setSaving( true );
		try {
			// Save rubric scores first if Educator addon provides them.
			if ( rubricScores.length > 0 && educatorGrading?.rubric ) {
				await apiFetch( {
					path: `/ppae/v1/submissions/${ submissionId }/rubric-scores`,
					method: 'PUT',
					data: { scores: rubricScores },
				} );
			}

			await apiFetch( {
				path: `/ppa/v1/submissions/${ submissionId }`,
				method: 'PUT',
				data: {
					score,
					feedback,
					status: 'returned',
					grading_time_seconds: gradingTimeDelta || undefined,
				},
			} );

			message.success(
				__( 'Returned to student.', 'pressprimer-assignment' )
			);
			setCurrentStatus( 'returned' );
			setHasChanges( false );
		} catch ( returnError ) {
			message.error(
				returnError.message ||
					__(
						'Failed to return submission.',
						'pressprimer-assignment'
					)
			);
		} finally {
			setSaving( false );
		}
	}, [
		submissionId,
		score,
		feedback,
		getAndResetGradingTime,
		rubricScores,
	] );

	// Auto-save every 30 seconds when there are unsaved changes.
	useEffect( () => {
		if ( ! hasChanges || currentStatus === 'returned' ) {
			return;
		}

		autoSaveTimerRef.current = setTimeout( () => {
			saveGrade( false );
		}, 30000 );

		return () => {
			if ( autoSaveTimerRef.current ) {
				clearTimeout( autoSaveTimerRef.current );
			}
		};
	}, [ hasChanges, currentStatus, saveGrade ] );

	// Keyboard shortcuts using native event listener (matches Quiz pattern).
	useEffect( () => {
		const handleKeyDown = ( e ) => {
			// Don't trigger shortcuts when typing in form fields.
			const tag = e.target.tagName;
			const isInput =
				tag === 'INPUT' ||
				tag === 'TEXTAREA' ||
				tag === 'SELECT' ||
				e.target.isContentEditable;

			const isReadOnly = currentStatus === 'returned';

			// Ctrl/Cmd + S: Save.
			if ( ( e.ctrlKey || e.metaKey ) && e.key === 's' ) {
				e.preventDefault();
				if ( ! isReadOnly ) {
					saveGrade();
				}
				return;
			}

			// Ctrl/Cmd + Enter: Save and next.
			if ( ( e.ctrlKey || e.metaKey ) && e.key === 'Enter' ) {
				e.preventDefault();
				if ( ! isReadOnly ) {
					saveAndNext();
				}
				return;
			}

			// Escape: Back to list.
			if ( e.key === 'Escape' ) {
				navigateToList();
				return;
			}

			// Don't process remaining shortcuts if user is in a form field.
			if ( isInput ) {
				return;
			}

			// J: Next submission.
			if ( e.key === 'j' || e.key === 'J' ) {
				if ( siblings.next ) {
					navigateToSubmission( siblings.next );
				}
				return;
			}

			// K: Previous submission.
			if ( e.key === 'k' || e.key === 'K' ) {
				if ( siblings.prev ) {
					navigateToSubmission( siblings.prev );
				}
				return;
			}

			// 0-9: Quick score (only when not read-only and not in input).
			if ( ! isReadOnly && assignment && /^[0-9]$/.test( e.key ) ) {
				const digit = parseInt( e.key, 10 );
				const quickScore =
					digit === 0 ? assignment.max_points : digit * 10;

				if ( quickScore <= assignment.max_points ) {
					setScore( quickScore );
					setHasChanges( true );
				}
			}
		};

		document.addEventListener( 'keydown', handleKeyDown );
		return () => {
			document.removeEventListener( 'keydown', handleKeyDown );
		};
	}, [ currentStatus, siblings, assignment, saveGrade, saveAndNext ] );

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

	if ( ! submission || ! assignment ) {
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

	const isReadOnly = currentStatus === 'returned';
	const scoreIsValid =
		score !== null && score >= 0 && score <= assignment.max_points;
	const passing = scoreIsValid && score >= assignment.passing_score;
	const percentage =
		scoreIsValid && assignment.max_points > 0
			? Math.round( ( score / assignment.max_points ) * 100 )
			: null;

	// Generate quick score values (multiples of 10 up to max_points).
	const quickScoreValues = [];
	for ( let v = 10; v <= assignment.max_points; v += 10 ) {
		quickScoreValues.push( v );
	}
	// Add max_points if it's not already a multiple of 10.
	if (
		assignment.max_points % 10 !== 0 &&
		! quickScoreValues.includes( assignment.max_points )
	) {
		quickScoreValues.push( assignment.max_points );
	}

	return (
		<div className="ppa-grading-interface">
			{ /* Header */ }
			<div className="ppa-grading-header">
				<div className="ppa-grading-header-left">
					<Button onClick={ navigateToList }>
						{ __( '← Back to Queue', 'pressprimer-assignment' ) }
					</Button>
				</div>

				<div className="ppa-grading-header-center">
					<Title
						level={ 4 }
						style={ {
							margin: 0,
							textAlign: 'center',
						} }
					>
						{ assignment.title }
					</Title>
				</div>

				<div className="ppa-grading-header-right">
					{ hasChanges && (
						<Text type="secondary" style={ { fontSize: 12 } }>
							{ __(
								'Unsaved changes',
								'pressprimer-assignment'
							) }
						</Text>
					) }
					<Space>
						<Button
							disabled={ ! siblings.prev }
							onClick={ () =>
								navigateToSubmission( siblings.prev )
							}
							icon={ <LeftOutlined /> }
							size="small"
						>
							{ __( 'Prev', 'pressprimer-assignment' ) }
						</Button>
						<Button
							disabled={ ! siblings.next }
							onClick={ () =>
								navigateToSubmission( siblings.next )
							}
							size="small"
						>
							{ __( 'Next', 'pressprimer-assignment' ) }{ ' ' }
							<RightOutlined />
						</Button>
					</Space>
				</div>
			</div>

			{ /* Main Content - Split Panel */ }
			<Row gutter={ 16 } className="ppa-grading-main">
				{ /* Document Panel (left) */ }
				<Col span={ 14 }>
					<Card
						className="ppa-grading-document-card"
						bodyStyle={ { padding: 0 } }
					>
						<DocumentPanel
							files={ files }
							textContent={ submission.text_content }
							wordCount={ submission.word_count }
							onFileUpdate={ ( fileId, result ) => {
								setFiles( ( prev ) =>
									prev.map( ( f ) =>
										f.id === fileId
											? {
													...f,
													extraction_method:
														result.method,
													extraction_quality:
														result.quality,
													extraction_error:
														result.error,
											  }
											: f
									)
								);
							} }
						/>
					</Card>
				</Col>

				{ /* Grading Panel (right) */ }
				<Col span={ 10 }>
					<Card className="ppa-grading-form-card">
						{ /* Student Info */ }
						<div className="ppa-student-info">
							<Title level={ 5 } style={ { marginBottom: 4 } }>
								{ submission.student_name }
							</Title>
							<Text type="secondary">
								{ submission.student_email }
							</Text>
							<div
								className="ppa-submission-meta"
								style={ { marginTop: 8 } }
							>
								<Text type="secondary">
									{ __(
										'Submitted:',
										'pressprimer-assignment'
									) }{ ' ' }
									{ submission.formatted_date }
								</Text>
								{ submission.submission_number > 1 && (
									<Tag
										style={ { marginLeft: 8 } }
										color="blue"
									>
										{ sprintf(
											/* translators: %d: submission number */
											__(
												'Resubmission #%d',
												'pressprimer-assignment'
											),
											submission.submission_number
										) }
									</Tag>
								) }
							</div>
						</div>

						{ /* Student Notes */ }
						{ submission.student_notes && (
							<>
								<Divider />
								<div className="ppa-student-notes">
									<Text strong>
										{ __(
											'Student Notes:',
											'pressprimer-assignment'
										) }
									</Text>
									<Paragraph
										style={ {
											marginTop: 4,
											marginBottom: 0,
											padding: '8px 12px',
											background: '#f6f7f7',
											borderRadius: 4,
											whiteSpace: 'pre-wrap',
										} }
									>
										{ submission.student_notes }
									</Paragraph>
								</div>
							</>
						) }

						{ /* Assignment Instructions */ }
						<Divider />
						<div className="ppa-reference-section">
							<Text
								strong
								style={ { display: 'block', marginBottom: 8 } }
							>
								{ __(
									'Assignment Instructions',
									'pressprimer-assignment'
								) }
							</Text>
							{ assignment.instructions ? (
								<div
									style={ {
										padding: '12px 16px',
										background: '#f6f7f7',
										borderRadius: 4,
										lineHeight: 1.6,
									} }
									dangerouslySetInnerHTML={ {
										__html: assignment.instructions,
									} }
								/>
							) : (
								<Text
									type="secondary"
									italic
									style={ { fontSize: 13 } }
								>
									{ __(
										'No instructions provided.',
										'pressprimer-assignment'
									) }
								</Text>
							) }
						</div>

						{ /* Grading Guidelines (hidden when a rubric is active) */ }
						{ ! ( RubricPanel && educatorGrading?.rubric ) && (
							<>
								<Divider />
								<div className="ppa-reference-section">
									<Text
										strong
										style={ {
											display: 'block',
											marginBottom: 8,
										} }
									>
										{ __(
											'Grading Guidelines',
											'pressprimer-assignment'
										) }
									</Text>
									{ assignment.grading_guidelines ? (
										<div
											style={ {
												padding: '12px 16px',
												background: '#f6f7f7',
												borderRadius: 4,
												lineHeight: 1.6,
											} }
											dangerouslySetInnerHTML={ {
												__html: assignment.grading_guidelines,
											} }
										/>
									) : (
										<Text
											type="secondary"
											italic
											style={ { fontSize: 13 } }
										>
											{ __(
												'No grading guidelines provided.',
												'pressprimer-assignment'
											) }
										</Text>
									) }
								</div>
							</>
						) }

						{ /* AI Grading Panel (School addon) */ }
						{ AIGradingPanel && schoolGrading && ! isReadOnly && (
							<>
								<Divider />
								<AIGradingPanel
									submissionId={ submissionId }
									hasRubric={
										!! (
											RubricPanel &&
											educatorGrading?.rubric
										)
									}
									providerConfigured={
										!! schoolGrading.providerConfigured
									}
									aiAutoGrade={
										!! schoolGrading.aiAutoGrade
									}
									preloadedSuggestions={
										schoolGrading?.aiSuggestions || null
									}
									onApplySuggestions={ ( {
										criteria,
										overallFeedback,
										suggestedScore,
									} ) => {
										// Apply overall feedback.
										if ( overallFeedback ) {
											setFeedback( overallFeedback );
											setHasChanges( true );
										}

										// Apply per-criterion scores to
										// the rubric panel (if present).
										if (
											criteria &&
											criteria.length > 0 &&
											educatorGrading?.rubric
										) {
											const newScores = criteria.map(
												( c ) => {
													const pts =
														c.suggested_points !==
														undefined
															? c.suggested_points
															: null;
													return {
														criterion_id:
															c.criterion_id,
														level_id:
															c.suggested_level_id ||
															null,
														points: pts,
														feedback:
															c.feedback || '',
													};
												}
											);

											setRubricScores( ( prev ) => {
												// Merge AI suggestions with
												// any existing manual scores.
												const merged = [ ...prev ];
												newScores.forEach( ( ns ) => {
													const idx =
														merged.findIndex(
															( m ) =>
																m.criterion_id ===
																ns.criterion_id
														);
													if ( idx >= 0 ) {
														merged[ idx ] = ns;
													} else {
														merged.push( ns );
													}
												} );
												return merged;
											} );

											// Force RubricPanel remount so
											// it re-initializes with the
											// AI-applied scores.
											setRubricKey( ( k ) => k + 1 );
											setHasChanges( true );

											// Sum points for score field.
											const totalFromAI =
												newScores.reduce(
													( sum, s ) =>
														sum +
														( s.points ||
															0 ),
													0
												);
											if ( totalFromAI > 0 ) {
												setScore( totalFromAI );
											}
										} else if (
											suggestedScore !== null &&
											suggestedScore !== undefined
										) {
											// Non-rubric mode: apply
											// the suggested overall score.
											setScore( suggestedScore );
											setHasChanges( true );
										}
									} }
								/>
							</>
						) }

						{ /* Rubric Panel (Educator addon) */ }
						{ RubricPanel && educatorGrading?.rubric && (
							<>
								<Divider />
								<RubricPanel
									key={ rubricKey }
									rubricData={ educatorGrading.rubric }
									existingScores={
										rubricScores.length > 0
											? rubricScores
											: educatorGrading.existing_scores || []
									}
									onTotalChange={ ( total ) => {
										setScore( total );
										setHasChanges( true );
									} }
									onScoresChange={ ( scores ) => {
										setRubricScores( scores );
										setHasChanges( true );
									} }
									disabled={ isReadOnly }
								/>
							</>
						) }

						<Divider />

						{ /* Score Section */ }
						<div className="ppa-score-section">
							<label
								htmlFor="ppa-score-input"
								style={ {
									display: 'block',
									fontWeight: 600,
									marginBottom: 8,
								} }
							>
								{ __( 'Score', 'pressprimer-assignment' ) }
							</label>
							<Space align="center">
								<InputNumber
									id="ppa-score-input"
									value={ score }
									onChange={ ( val ) => {
										setScore( val );
										setHasChanges( true );
										if ( val === null || val === '' ) {
											setScoreWarning( '' );
										} else if (
											val < 0 ||
											val > assignment.max_points
										) {
											setScoreWarning(
												sprintf(
													/* translators: %d: maximum points */
													__(
														'Score must be between 0 and %d.',
														'pressprimer-assignment'
													),
													assignment.max_points
												)
											);
										} else {
											setScoreWarning( '' );
										}
									} }
									status={
										scoreWarning ? 'warning' : undefined
									}
									style={ { width: 120 } }
									size="large"
									disabled={ isReadOnly }
								/>
								<Text>
									/ { assignment.max_points }{ ' ' }
									{ __( 'points', 'pressprimer-assignment' ) }
								</Text>
								{ percentage !== null && (
									<Text type="secondary">
										({ percentage }%)
									</Text>
								) }
							</Space>
							{ scoreWarning && (
								<div style={ { marginTop: 4 } }>
									<Text
										type="warning"
										style={ {
											color: '#faad14',
											fontSize: 13,
										} }
									>
										{ scoreWarning }
									</Text>
								</div>
							) }

							{ /* Quick Score Buttons */ }
							{ ! isReadOnly && (
								<div
									className="ppa-quick-scores"
									style={ { marginTop: 8 } }
								>
									{ quickScoreValues.map( ( val ) => {
										let shortcutKey = '';
										if (
											val === assignment.max_points &&
											val === 100
										) {
											shortcutKey = '0';
										} else if ( val <= 90 ) {
											shortcutKey = String( val / 10 );
										}
										return (
											<Tooltip
												key={ val }
												title={
													shortcutKey
														? sprintf(
																/* translators: %s: keyboard key */
																__(
																	'Press %s',
																	'pressprimer-assignment'
																),
																shortcutKey
														  )
														: undefined
												}
											>
												<Button
													size="small"
													type={
														score === val
															? 'primary'
															: 'default'
													}
													onClick={ () => {
														setScore( val );
														setHasChanges( true );
													} }
													style={ {
														marginRight: 4,
														marginBottom: 4,
													} }
												>
													{ val }
												</Button>
											</Tooltip>
										);
									} ) }
								</div>
							) }

							{ /* Pass/Fail Preview */ }
							{ scoreIsValid && (
								<div
									className="ppa-pass-preview"
									style={ { marginTop: 12 } }
								>
									{ passing ? (
										<Tag
											icon={ <CheckCircleOutlined /> }
											color="success"
										>
											{ sprintf(
												/* translators: %1$d: score percentage */
												__(
													'Passing (%1$d%%)',
													'pressprimer-assignment'
												),
												percentage
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
												String(
													assignment.passing_score
												)
											) }
										</Tag>
									) }
								</div>
							) }
						</div>

						<Divider />

						{ /* Feedback */ }
						<div className="ppa-feedback-section">
							<label
								htmlFor="ppa-feedback-input"
								style={ {
									display: 'block',
									fontWeight: 600,
									marginBottom: 8,
								} }
							>
								{ __( 'Feedback', 'pressprimer-assignment' ) }
							</label>
							<TextArea
								id="ppa-feedback-input"
								ref={ feedbackRef }
								value={ feedback }
								onChange={ ( e ) => {
									setFeedback( e.target.value );
									setHasChanges( true );
								} }
								placeholder={ __(
									'Enter feedback for the student…',
									'pressprimer-assignment'
								) }
								disabled={ isReadOnly }
								autoSize={ { minRows: 4, maxRows: 12 } }
							/>
						</div>

						<Divider />

						{ /* Bottom Actions */ }
						{ ! isReadOnly ? (
							<div className="ppa-grading-submit">
								<Space>
									<Button
										type="primary"
										icon={ <SaveOutlined /> }
										loading={ saving }
										onClick={ () => saveGrade() }
									>
										{ __(
											'Save',
											'pressprimer-assignment'
										) }
									</Button>
									<Button
										type="primary"
										icon={ <SendOutlined /> }
										onClick={ returnToStudent }
										disabled={ ! scoreIsValid }
										className="ppa-btn-return"
									>
										{ __(
											'Save & Return to Student',
											'pressprimer-assignment'
										) }
									</Button>
								</Space>
							</div>
						) : (
							<div className="ppa-returned-notice">
								<Tag
									color="green"
									icon={ <CheckCircleOutlined /> }
								>
									{ __(
										'This submission has been returned to the student.',
										'pressprimer-assignment'
									) }
								</Tag>
							</div>
						) }

						{ /* Keyboard Shortcuts Help */ }
						<div
							className="ppa-shortcuts-help"
							style={ {
								marginTop: 16,
								fontSize: 12,
								color: '#787c82',
							} }
						>
							<Text type="secondary" style={ { fontSize: 12 } }>
								{ __( 'Shortcuts:', 'pressprimer-assignment' ) }{ ' ' }
								J/K{ ' ' }
								{ __( 'navigate', 'pressprimer-assignment' ) },{ ' ' }
								1-9{ ' ' }
								{ __(
									'quick score',
									'pressprimer-assignment'
								) }
								, Ctrl+S{ ' ' }
								{ __( 'save', 'pressprimer-assignment' ) },{ ' ' }
								Ctrl+Enter{ ' ' }
								{ __(
									'save & next',
									'pressprimer-assignment'
								) }
								, Esc{ ' ' }
								{ __( 'close', 'pressprimer-assignment' ) }
							</Text>
						</div>
					</Card>
				</Col>
			</Row>
		</div>
	);
};

export default GradingForm;
