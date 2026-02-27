<?php
/**
 * Uncanny Automator Helpers
 *
 * Provides shared functionality for Uncanny Automator triggers.
 *
 * @package PressPrimer_Assignment
 * @subpackage Integrations\UncannyAutomator
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class for PressPrimer Assignment Automator integration
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Automator_Helpers {

	/**
	 * Get all published assignments for dropdown options
	 *
	 * @since 1.0.0
	 *
	 * @return array Assignment options array with 'text' and 'value' keys.
	 */
	public function get_assignment_options() {
		$options = array();

		if ( ! class_exists( 'PressPrimer_Assignment_Assignment' ) ) {
			return $options;
		}

		$assignments = \PressPrimer_Assignment_Assignment::find(
			array(
				'where'    => array( 'status' => 'published' ),
				'order_by' => 'title',
				'order'    => 'ASC',
				'limit'    => 999,
			)
		);

		foreach ( $assignments as $assignment ) {
			$options[] = array(
				'text'  => $assignment->title,
				'value' => (string) $assignment->id,
			);
		}

		return $options;
	}

	/**
	 * Get token data from submission and assignment objects
	 *
	 * @since 1.0.0
	 *
	 * @param object $submission PressPrimer_Assignment_Submission object.
	 * @param object $assignment PressPrimer_Assignment_Assignment object.
	 * @return array Token data keyed by token ID.
	 */
	public function get_token_data_from_objects( $submission, $assignment ) {
		$data = array(
			'ASSIGNMENT_ID'    => '',
			'ASSIGNMENT_TITLE' => '',
			'ASSIGNMENT_URL'   => '',
			'SUBMISSION_ID'    => '',
			'STUDENT_NAME'     => '',
			'STUDENT_EMAIL'    => '',
			'SCORE'            => '',
			'SCORE_PERCENT'    => '',
			'POINTS_EARNED'    => '',
			'POINTS_POSSIBLE'  => '',
			'PASSING_SCORE'    => '',
			'PASSED'           => '',
			'SUBMISSION_TYPE'  => '',
			'FILE_COUNT'       => '',
			'STUDENT_NOTES'    => '',
			'FEEDBACK'         => '',
			'STATUS'           => '',
		);

		// Get assignment data.
		if ( $assignment ) {
			$data['ASSIGNMENT_ID']    = $assignment->id ?? '';
			$data['ASSIGNMENT_TITLE'] = $assignment->title ?? '';
			$data['POINTS_POSSIBLE']  = $assignment->max_points ?? 0;
			$data['PASSING_SCORE']    = $assignment->passing_score ?? 0;
		}

		// Get submission data.
		if ( $submission ) {
			$data['SUBMISSION_ID']  = $submission->id ?? '';
			$data['STATUS']         = $submission->status ?? '';
			$data['FILE_COUNT']     = $submission->file_count ?? 0;
			$data['STUDENT_NOTES']  = $submission->student_notes ?? '';
			$data['FEEDBACK']       = $submission->feedback ?? '';
			$data['ASSIGNMENT_URL'] = $submission->get_meta( 'assignment_page_url', '' );

			// Submission type.
			if ( ! empty( $submission->text_content ) ) {
				$data['SUBMISSION_TYPE'] = __( 'Text', 'pressprimer-assignment' );
			} else {
				$data['SUBMISSION_TYPE'] = __( 'File', 'pressprimer-assignment' );
			}

			// Score data.
			$score      = $submission->score;
			$max_points = $assignment->max_points ?? 0;

			if ( null !== $score ) {
				$data['POINTS_EARNED'] = $score;
				$data['SCORE']         = $max_points > 0 ? round( ( $score / $max_points ) * 100 ) : 0;
				$data['SCORE_PERCENT'] = $data['SCORE'] . '%';
			}

			// Pass/fail.
			$data['PASSED'] = ! empty( $submission->passed )
				? __( 'Yes', 'pressprimer-assignment' )
				: __( 'No', 'pressprimer-assignment' );

			// Student info.
			$user = get_userdata( (int) ( $submission->user_id ?? 0 ) );
			if ( $user ) {
				$data['STUDENT_NAME']  = $user->display_name;
				$data['STUDENT_EMAIL'] = $user->user_email;
			}
		}

		return $data;
	}

	/**
	 * Load submission and assignment objects from a submission ID
	 *
	 * Used by grading triggers that only receive the submission ID
	 * from the action hook.
	 *
	 * @since 1.0.0
	 *
	 * @param int $submission_id Submission ID.
	 * @return array{0: object|null, 1: object|null} Array of [submission, assignment].
	 */
	public function load_objects_from_submission_id( $submission_id ) {
		$submission = null;
		$assignment = null;

		if ( class_exists( 'PressPrimer_Assignment_Submission' ) ) {
			$submission = \PressPrimer_Assignment_Submission::find_by_id( absint( $submission_id ) );
		}

		if ( $submission && class_exists( 'PressPrimer_Assignment_Assignment' ) ) {
			$assignment = \PressPrimer_Assignment_Assignment::find_by_id( absint( $submission->assignment_id ) );
		}

		return array( $submission, $assignment );
	}

	/**
	 * Get token definitions for the Submitted trigger
	 *
	 * Excludes grading-related tokens (score, points earned, passed,
	 * feedback) that are not available at submission time.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tokens Existing tokens.
	 * @return array Modified tokens.
	 */
	public function get_submission_token_definitions( $tokens ) {
		$grading_tokens = array( 'SCORE', 'SCORE_PERCENT', 'POINTS_EARNED', 'PASSED', 'FEEDBACK' );

		return $this->get_token_definitions( $tokens, $grading_tokens );
	}

	/**
	 * Get shared token definitions for all triggers
	 *
	 * @since 1.0.0
	 *
	 * @param array $tokens  Existing tokens.
	 * @param array $exclude Optional token IDs to exclude.
	 * @return array Modified tokens.
	 */
	public function get_token_definitions( $tokens, $exclude = array() ) {
		$all_tokens = array(
			array(
				'tokenId'   => 'ASSIGNMENT_ID',
				'tokenName' => esc_attr__( 'Assignment ID', 'pressprimer-assignment' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'ASSIGNMENT_TITLE',
				'tokenName' => esc_attr__( 'Assignment title', 'pressprimer-assignment' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ASSIGNMENT_URL',
				'tokenName' => esc_attr__( 'Assignment URL', 'pressprimer-assignment' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'SUBMISSION_ID',
				'tokenName' => esc_attr__( 'Submission ID', 'pressprimer-assignment' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'STUDENT_NAME',
				'tokenName' => esc_attr__( 'Student name', 'pressprimer-assignment' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'STUDENT_EMAIL',
				'tokenName' => esc_attr__( 'Student email', 'pressprimer-assignment' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'SCORE',
				'tokenName' => esc_attr__( 'Score (percentage)', 'pressprimer-assignment' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'SCORE_PERCENT',
				'tokenName' => esc_attr__( 'Score (with % sign)', 'pressprimer-assignment' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POINTS_EARNED',
				'tokenName' => esc_attr__( 'Points earned', 'pressprimer-assignment' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'POINTS_POSSIBLE',
				'tokenName' => esc_attr__( 'Points possible', 'pressprimer-assignment' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'PASSING_SCORE',
				'tokenName' => esc_attr__( 'Passing score', 'pressprimer-assignment' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'PASSED',
				'tokenName' => esc_attr__( 'Passed (Yes/No)', 'pressprimer-assignment' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SUBMISSION_TYPE',
				'tokenName' => esc_attr__( 'Submission type (File/Text)', 'pressprimer-assignment' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FILE_COUNT',
				'tokenName' => esc_attr__( 'Number of files', 'pressprimer-assignment' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'STUDENT_NOTES',
				'tokenName' => esc_attr__( 'Student notes', 'pressprimer-assignment' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FEEDBACK',
				'tokenName' => esc_attr__( 'Grader feedback', 'pressprimer-assignment' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'STATUS',
				'tokenName' => esc_attr__( 'Submission status', 'pressprimer-assignment' ),
				'tokenType' => 'text',
			),
		);

		foreach ( $all_tokens as $token ) {
			if ( ! empty( $exclude ) && in_array( $token['tokenId'], $exclude, true ) ) {
				continue;
			}
			$tokens[] = $token;
		}

		return $tokens;
	}
}
