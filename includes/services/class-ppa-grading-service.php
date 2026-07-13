<?php
/**
 * Grading service
 *
 * Handles grading calculations, pass/fail determination,
 * and submission returning.
 *
 * @package PressPrimer_Assignment
 * @subpackage Services
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Grading service class
 *
 * Provides grading workflow methods including score validation,
 * pass/fail determination with filters, and submission status
 * management.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Grading_Service {

	/**
	 * Grade a submission
	 *
	 * Validates the score, determines pass/fail, updates the
	 * submission record, and fires appropriate action hooks.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $submission_id      Submission ID.
	 * @param float  $score              Score to assign.
	 * @param string $feedback           Grader feedback text.
	 * @param int    $grading_time_delta Active grading seconds to add (0 to skip).
	 * @return array|WP_Error Result array on success, WP_Error on failure.
	 */
	public function grade( $submission_id, $score, $feedback, $grading_time_delta = 0 ) {
		$submission_id = absint( $submission_id );

		// Validate submission exists.
		$submission = PressPrimer_Assignment_Submission::get( $submission_id );
		if ( ! $submission ) {
			return new WP_Error(
				'pressprimer_assignment_submission_not_found',
				__( 'Submission not found.', 'pressprimer-assignment' )
			);
		}

		// Get the assignment for score range validation.
		$assignment = $submission->get_assignment();
		if ( ! $assignment ) {
			return new WP_Error(
				'pressprimer_assignment_assignment_not_found',
				__( 'Assignment not found for this submission.', 'pressprimer-assignment' )
			);
		}

		// Validate score range (0 to max_points).
		$score = floatval( $score );
		if ( $score < 0 || $score > $assignment->max_points ) {
			return new WP_Error(
				'pressprimer_assignment_invalid_score',
				sprintf(
					/* translators: %s: maximum points value */
					__( 'Score must be between 0 and %s.', 'pressprimer-assignment' ),
					number_format_i18n( $assignment->max_points, 2 )
				)
			);
		}

		// Sanitize feedback.
		$feedback = wp_kses_post( $feedback );

		/**
		 * Fires before grading a submission.
		 *
		 * @since 1.0.0
		 *
		 * @param int $submission_id The submission ID.
		 */
		do_action( 'pressprimer_assignment_before_grade', $submission_id );

		// Apply the graduated late penalty (2.2, feature 009). The grader
		// enters the raw score; when the assignment's late policy is
		// 'penalty' and the submission is late, the schedule resolves a
		// deduction and the stored score becomes the penalized final. The
		// breakdown is kept in submission meta so the grading interface
		// and student view can itemize it. Pass/fail uses the final score.
		$late_penalty = null;
		if ( 'penalty' === $assignment->late_policy ) {
			$late_penalty = $this->resolve_late_penalty( $submission, $assignment, $score );
		}

		if ( null !== $late_penalty ) {
			$score = $late_penalty['final_score'];
			$submission->set_meta( 'late_penalty', $late_penalty );
		} elseif ( null !== $submission->get_meta( 'late_penalty' ) ) {
			// Regrade after the policy or schedule stopped applying —
			// clear the stale breakdown so nothing itemizes a penalty
			// that no longer exists.
			$submission->set_meta( 'late_penalty', null );
		}

		// Determine pass/fail.
		$passing_score = floatval( $assignment->passing_score );
		$passed        = $score >= $passing_score;

		/**
		 * Filter whether a submission is considered passed.
		 *
		 * @since 1.0.0
		 *
		 * @param bool  $passed        Whether submission passed.
		 * @param int   $submission_id The submission ID.
		 * @param float $score         The score.
		 * @param float $passing_score The passing threshold.
		 */
		$passed = apply_filters( 'pressprimer_assignment_passed', $passed, $submission_id, $score, $passing_score );

		// Update submission record.
		$submission->status = PressPrimer_Assignment_Submission::STATUS_GRADED;
		$submission->score  = $score;
		// Snapshot the assignment's max_points at the moment of grading
		// so historical percentages don't shift if an admin later edits
		// the assignment's max. The Statistics queries divide by this
		// column (falling back to a.max_points for pre-1.10 rows).
		$submission->max_points_at_grading = (float) $assignment->max_points;
		$submission->feedback              = $feedback;
		$submission->passed                = $passed ? 1 : 0;
		$submission->grader_id             = get_current_user_id();
		$submission->graded_at             = current_time( 'mysql', true );

		// Accumulate active grading time.
		$grading_time_delta = absint( $grading_time_delta );
		if ( $grading_time_delta > 0 ) {
			$existing_time                    = absint( $submission->grading_time_seconds );
			$submission->grading_time_seconds = $existing_time + $grading_time_delta;
		}

		$save_result = $submission->save();

		if ( is_wp_error( $save_result ) ) {
			return $save_result;
		}

		// Update assignment graded count.
		$assignment->update_graded_count();

		/**
		 * Fires after grading a submission.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $submission_id The submission ID.
		 * @param float  $score         The score.
		 * @param string $feedback      The feedback text.
		 */
		do_action( 'pressprimer_assignment_after_grade', $submission_id, $score, $feedback );

		/**
		 * Fires when a submission is graded.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $submission_id The submission ID.
		 * @param float $score         The score awarded.
		 */
		do_action( 'pressprimer_assignment_submission_graded', $submission_id, $score );

		/**
		 * Fire audit log event for grade saved.
		 *
		 * Enterprise addon listens to this and writes to the audit log.
		 * When Enterprise is not active, this hook fires harmlessly.
		 *
		 * @since 2.0.0
		 *
		 * @param string $event_type  Event identifier.
		 * @param string $object_type Object type affected.
		 * @param int    $object_id   Object ID.
		 * @param array  $data        Additional context.
		 */
		do_action(
			'pressprimer_assignment_log_event',
			'grade.saved',
			'submission',
			$submission_id,
			[
				'grader_id'     => $submission->grader_id,
				'score'         => $score,
				'passed'        => $passed,
				'assignment_id' => $submission->assignment_id,
			]
		);

		// Fire passed or failed action.
		if ( $passed ) {
			/**
			 * Fires when a student passes an assignment.
			 *
			 * @since 1.0.0
			 *
			 * @param int   $submission_id The submission ID.
			 * @param float $score         The score.
			 */
			do_action( 'pressprimer_assignment_submission_passed', $submission_id, $score );
		} else {
			/**
			 * Fires when a student fails an assignment.
			 *
			 * @since 1.0.0
			 *
			 * @param int   $submission_id The submission ID.
			 * @param float $score         The score.
			 */
			do_action( 'pressprimer_assignment_submission_failed', $submission_id, $score );
		}

		return [
			'submission_id' => $submission_id,
			'score'         => $score,
			'passed'        => $passed,
			'grader_id'     => $submission->grader_id,
			'graded_at'     => $submission->graded_at,
		];
	}

	/**
	 * Resolve the late penalty for a submission from the assignment's schedule
	 *
	 * Measures lateness against the student's effective due date — the
	 * assignment default superseded by addon-supplied dates (Educator's
	 * per-group dates today, per-student overrides in Educator 2.2) via
	 * the pressprimer_assignment_due_date_for_user filter. One calculation
	 * path, no addon-specific branches.
	 *
	 * Tier matching: comparisons happen in minutes. The first tier whose
	 * threshold covers the lateness applies (a null threshold covers any
	 * lateness — the single-tier flat case). Lateness beyond the last tier
	 * takes the last tier's penalty; the schedule's cutoff is enforced at
	 * submission time, not here — a submission that exists is graded.
	 *
	 * The penalty is a percentage of the earned raw score ("deduct 10%"
	 * takes 10% of what the student scored, not of max points).
	 *
	 * @since 2.2.0
	 *
	 * @param PressPrimer_Assignment_Submission $submission The submission.
	 * @param PressPrimer_Assignment_Assignment $assignment Its assignment.
	 * @param float                             $raw_score  Raw score before penalty.
	 * @return array|null Breakdown array (late_minutes, tier_index,
	 *                    penalty_percent, raw_score, deduction, final_score)
	 *                    or null when no penalty applies.
	 */
	public function resolve_late_penalty( $submission, $assignment, $raw_score ) {
		if ( empty( $submission->submitted_at ) ) {
			return null;
		}

		$schedule = $assignment->get_late_penalty_schedule();
		if ( null === $schedule ) {
			return null;
		}

		// Effective due date for this student (addon filters applied).
		$due_at = $assignment->get_due_date_for_user( (int) $submission->user_id );
		if ( empty( $due_at ) ) {
			// No due date for this student — nothing is ever late.
			return null;
		}

		// Both datetimes are stored as UTC MySQL strings.
		$due_timestamp       = strtotime( $due_at . ' UTC' );
		$submitted_timestamp = strtotime( $submission->submitted_at . ' UTC' );

		if ( false === $due_timestamp || false === $submitted_timestamp ) {
			return null;
		}

		$late_seconds = $submitted_timestamp - $due_timestamp;
		if ( $late_seconds <= 0 ) {
			// On time.
			return null;
		}

		// Whole minutes, rounded up: 30 seconds late is late.
		$late_minutes = (int) ceil( $late_seconds / 60 );

		// First tier whose threshold covers the lateness applies; beyond
		// the last tier, the last tier applies.
		$tiers      = $schedule['tiers'];
		$tier_index = count( $tiers ) - 1;
		foreach ( $tiers as $index => $tier ) {
			if ( null === $tier['late_by_hours'] || $late_minutes <= $tier['late_by_hours'] * 60 ) {
				$tier_index = $index;
				break;
			}
		}

		$penalty_percent = (float) $tiers[ $tier_index ]['penalty_percent'];
		$raw_score       = (float) $raw_score;
		$penalty         = round( $raw_score * $penalty_percent / 100, 2 );

		/**
		 * Filters the late penalty resolved from a graduated schedule.
		 *
		 * @since 2.2.0
		 *
		 * @param float $penalty       Penalty amount in points (deducted from the raw score).
		 * @param int   $submission_id The submission ID.
		 * @param float $raw_score     Raw score before penalty.
		 * @param array $schedule      The decoded schedule array (tiers + cutoff_hours).
		 * @param int   $late_minutes  Lateness in minutes past the effective due date.
		 */
		$penalty = apply_filters(
			'pressprimer_assignment_late_penalty_calculated',
			$penalty,
			(int) $submission->id,
			$raw_score,
			$schedule,
			$late_minutes
		);

		// Clamp: a filtered penalty can neither add points nor push the
		// final score below zero.
		$penalty = max( 0.0, min( (float) $penalty, $raw_score ) );

		return [
			'late_minutes'    => $late_minutes,
			'tier_index'      => $tier_index,
			'penalty_percent' => $penalty_percent,
			'raw_score'       => $raw_score,
			'deduction'       => $penalty,
			'final_score'     => round( $raw_score - $penalty, 2 ),
		];
	}

	/**
	 * Return a graded submission to the student
	 *
	 * Updates the submission status to 'returned' and fires
	 * the appropriate action hook.
	 *
	 * @since 1.0.0
	 *
	 * @param int $submission_id Submission ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function return_submission( $submission_id ) {
		$submission_id = absint( $submission_id );

		$submission = PressPrimer_Assignment_Submission::get( $submission_id );
		if ( ! $submission ) {
			return new WP_Error(
				'pressprimer_assignment_submission_not_found',
				__( 'Submission not found.', 'pressprimer-assignment' )
			);
		}

		// Verify submission is in a graded state.
		if ( PressPrimer_Assignment_Submission::STATUS_GRADED !== $submission->status ) {
			return new WP_Error(
				'pressprimer_assignment_not_graded',
				__( 'Submission must be graded before it can be returned.', 'pressprimer-assignment' )
			);
		}

		// Update status to returned.
		$submission->status      = PressPrimer_Assignment_Submission::STATUS_RETURNED;
		$submission->returned_at = current_time( 'mysql', true );

		$result = $submission->save();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/**
		 * Fires when a submission is returned to the student.
		 *
		 * @since 1.0.0
		 *
		 * @param int $submission_id The submission ID.
		 */
		do_action( 'pressprimer_assignment_submission_returned', $submission_id );

		/**
		 * Fire audit log event for grade returned.
		 *
		 * @since 2.0.0
		 */
		do_action(
			'pressprimer_assignment_log_event',
			'grade.returned',
			'submission',
			$submission_id,
			[
				'assignment_id' => $submission->assignment_id,
				'user_id'       => $submission->user_id,
			]
		);

		return true;
	}
}
