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
		$submission->status    = PressPrimer_Assignment_Submission::STATUS_GRADED;
		$submission->score     = $score;
		$submission->feedback  = $feedback;
		$submission->passed    = $passed ? 1 : 0;
		$submission->grader_id = get_current_user_id();
		$submission->graded_at = current_time( 'mysql', true );

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

		return true;
	}
}
