<?php
/**
 * Text submission handler
 *
 * Handles AJAX requests for text draft saving, heartbeat auto-save,
 * and text-based assignment submission from the frontend.
 *
 * @package PressPrimer_Assignment
 * @subpackage Frontend
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Text handler class
 *
 * Registers AJAX actions for the text submission workflow:
 * save draft, auto-save via heartbeat, and submit text assignment.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Text_Handler {

	/**
	 * Initialize the handler
	 *
	 * Registers AJAX actions and heartbeat filter for logged-in users.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'wp_ajax_pressprimer_assignment_save_text_draft', [ $this, 'handle_save_draft' ] );
		add_action( 'wp_ajax_pressprimer_assignment_submit_text_assignment', [ $this, 'handle_submit' ] );
		add_filter( 'heartbeat_received', [ $this, 'handle_heartbeat' ], 10, 2 );
	}

	// =========================================================================
	// AJAX: Save Text Draft.
	// =========================================================================

	/**
	 * Handle AJAX draft save
	 *
	 * Validates nonce, checks ownership, and persists draft text content.
	 *
	 * AJAX action: pressprimer_assignment_save_text_draft
	 *
	 * @since 1.0.0
	 */
	public function handle_save_draft() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'pressprimer_assignment_save_text_draft', 'nonce', false ) ) {
			wp_send_json_error(
				[
					'code'    => 'invalid_nonce',
					'message' => __( 'Security check failed. Please refresh the page and try again.', 'pressprimer-assignment' ),
				],
				403
			);
		}

		// Require login.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				[
					'code'    => 'not_logged_in',
					'message' => __( 'You must be logged in.', 'pressprimer-assignment' ),
				],
				401
			);
		}

		$user_id       = get_current_user_id();
		$assignment_id = isset( $_POST['assignment_id'] ) ? absint( wp_unslash( $_POST['assignment_id'] ) ) : 0;
		$submission_id = isset( $_POST['submission_id'] ) ? absint( wp_unslash( $_POST['submission_id'] ) ) : 0;
		$text_content  = isset( $_POST['text_content'] ) ? wp_kses_post( wp_unslash( $_POST['text_content'] ) ) : '';
		$word_count    = isset( $_POST['word_count'] ) ? absint( wp_unslash( $_POST['word_count'] ) ) : 0;

		// Validate assignment.
		$assignment = PressPrimer_Assignment_Assignment::get( $assignment_id );

		if ( ! $assignment || ! $assignment->accepts_text_submission() ) {
			wp_send_json_error(
				[
					'code'    => 'invalid_assignment',
					'message' => __( 'Invalid assignment.', 'pressprimer-assignment' ),
				],
				400
			);
		}

		// Get or create draft submission.
		$submission = $this->get_or_create_draft( $user_id, $assignment_id, $submission_id );

		if ( is_wp_error( $submission ) ) {
			wp_send_json_error(
				[
					'code'    => $submission->get_error_code(),
					'message' => $submission->get_error_message(),
				],
				400
			);
		}

		// Verify ownership.
		if ( (int) $submission->user_id !== $user_id ) {
			wp_send_json_error(
				[
					'code'    => 'not_owner',
					'message' => __( 'You do not own this submission.', 'pressprimer-assignment' ),
				],
				403
			);
		}

		// Verify it's still a draft.
		if ( PressPrimer_Assignment_Submission::STATUS_DRAFT !== $submission->status ) {
			wp_send_json_error(
				[
					'code'    => 'not_draft',
					'message' => __( 'This submission has already been submitted.', 'pressprimer-assignment' ),
				],
				400
			);
		}

		// Update draft content.
		$submission->text_content = $text_content;
		$submission->word_count   = $word_count;

		$result = $submission->save();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				[
					'code'    => 'save_failed',
					'message' => __( 'Failed to save draft. Please try again.', 'pressprimer-assignment' ),
				],
				500
			);
		}

		wp_send_json_success(
			[
				'submission_id' => $submission->id,
				'saved_at'      => current_time( 'mysql' ),
			]
		);
	}

	// =========================================================================
	// Heartbeat: Auto-Save.
	// =========================================================================

	/**
	 * Handle heartbeat auto-save
	 *
	 * Processes text draft auto-save data piggybacked on the
	 * WordPress heartbeat. Silently fails without error responses
	 * to avoid disrupting the heartbeat cycle.
	 *
	 * @since 1.0.0
	 *
	 * @param array $response Heartbeat response data.
	 * @param array $data     Heartbeat request data.
	 * @return array Modified response data.
	 */
	public function handle_heartbeat( $response, $data ) {
		if ( empty( $data['pressprimer_assignment_text_autosave'] ) ) {
			return $response;
		}

		$autosave = $data['pressprimer_assignment_text_autosave'];

		// Require login.
		if ( ! is_user_logged_in() ) {
			$response['pressprimer_assignment_text_autosave_response'] = [ 'success' => false ];
			return $response;
		}

		$user_id       = get_current_user_id();
		$assignment_id = isset( $autosave['assignment_id'] ) ? absint( $autosave['assignment_id'] ) : 0;
		$submission_id = isset( $autosave['submission_id'] ) ? absint( $autosave['submission_id'] ) : 0;
		$text_content  = isset( $autosave['text_content'] ) ? wp_kses_post( wp_unslash( $autosave['text_content'] ) ) : '';
		$word_count    = isset( $autosave['word_count'] ) ? absint( $autosave['word_count'] ) : 0;

		// Validate assignment.
		$assignment = PressPrimer_Assignment_Assignment::get( $assignment_id );

		if ( ! $assignment || ! $assignment->accepts_text_submission() ) {
			$response['pressprimer_assignment_text_autosave_response'] = [ 'success' => false ];
			return $response;
		}

		// Get or create draft.
		$submission = $this->get_or_create_draft( $user_id, $assignment_id, $submission_id );

		if ( is_wp_error( $submission ) ) {
			$response['pressprimer_assignment_text_autosave_response'] = [ 'success' => false ];
			return $response;
		}

		// Verify ownership and draft status.
		if ( (int) $submission->user_id !== $user_id || PressPrimer_Assignment_Submission::STATUS_DRAFT !== $submission->status ) {
			$response['pressprimer_assignment_text_autosave_response'] = [ 'success' => false ];
			return $response;
		}

		// Update content.
		$submission->text_content = $text_content;
		$submission->word_count   = $word_count;
		$submission->save();

		$response['pressprimer_assignment_text_autosave_response'] = [
			'success'       => true,
			'submission_id' => $submission->id,
		];

		return $response;
	}

	// =========================================================================
	// AJAX: Submit Text Assignment.
	// =========================================================================

	/**
	 * Handle text assignment submission
	 *
	 * Finalizes a text draft submission: validates content exists,
	 * updates status to 'submitted', and fires action hooks.
	 *
	 * AJAX action: pressprimer_assignment_submit_text_assignment
	 *
	 * @since 1.0.0
	 */
	public function handle_submit() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'pressprimer_assignment_frontend_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				[
					'code'    => 'invalid_nonce',
					'message' => __( 'Security check failed. Please refresh the page and try again.', 'pressprimer-assignment' ),
				],
				403
			);
		}

		// Require login.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				[
					'code'    => 'not_logged_in',
					'message' => __( 'You must be logged in to submit.', 'pressprimer-assignment' ),
				],
				401
			);
		}

		// Validate assignment.
		$assignment_id = isset( $_POST['assignment_id'] ) ? absint( wp_unslash( $_POST['assignment_id'] ) ) : 0;
		$assignment    = PressPrimer_Assignment_Assignment::get( $assignment_id );

		// Allow admins to submit for draft assignments (preview/testing).
		$is_admin_preview = $assignment && ! $assignment->accepts_submissions() && current_user_can( 'manage_options' );

		if ( ! $assignment || ( ! $assignment->accepts_submissions() && ! $is_admin_preview ) ) {
			wp_send_json_error(
				[
					'code'    => 'invalid_assignment',
					'message' => __( 'This assignment is not accepting submissions.', 'pressprimer-assignment' ),
				],
				400
			);
		}

		// Verify assignment accepts text.
		if ( ! $assignment->accepts_text_submission() ) {
			wp_send_json_error(
				[
					'code'    => 'text_not_allowed',
					'message' => __( 'This assignment does not accept text submissions.', 'pressprimer-assignment' ),
				],
				400
			);
		}

		$user_id       = get_current_user_id();
		$submission_id = isset( $_POST['submission_id'] ) ? absint( wp_unslash( $_POST['submission_id'] ) ) : 0;
		$text_content  = isset( $_POST['text_content'] ) ? wp_kses_post( wp_unslash( $_POST['text_content'] ) ) : '';
		$word_count    = isset( $_POST['word_count'] ) ? absint( wp_unslash( $_POST['word_count'] ) ) : 0;

		// Check if user can submit (skip for admin preview of draft assignments).
		if ( ! $is_admin_preview ) {
			$can_submit = $this->can_user_submit( $user_id, $assignment );

			if ( is_wp_error( $can_submit ) ) {
				wp_send_json_error(
					[
						'code'    => $can_submit->get_error_code(),
						'message' => $can_submit->get_error_message(),
					],
					403
				);
			}
		}

		// Get or create draft submission.
		$submission = $this->get_or_create_draft( $user_id, $assignment_id, $submission_id );

		if ( is_wp_error( $submission ) ) {
			wp_send_json_error(
				[
					'code'    => $submission->get_error_code(),
					'message' => $submission->get_error_message(),
				],
				400
			);
		}

		// Verify ownership.
		if ( (int) $submission->user_id !== $user_id ) {
			wp_send_json_error(
				[
					'code'    => 'not_owner',
					'message' => __( 'You do not own this submission.', 'pressprimer-assignment' ),
				],
				403
			);
		}

		// Validate text content exists.
		$content_text = wp_strip_all_tags( $text_content );

		if ( '' === trim( $content_text ) ) {
			wp_send_json_error(
				[
					'code'    => 'empty_content',
					'message' => __( 'Please write something before submitting.', 'pressprimer-assignment' ),
				],
				400
			);
		}

		// Update submission with final content.
		$submission->text_content = $text_content;
		$submission->word_count   = $word_count;
		$submission->status       = PressPrimer_Assignment_Submission::STATUS_SUBMITTED;
		$submission->submitted_at = current_time( 'mysql', true );

		$result = $submission->save();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				[
					'code'    => 'save_failed',
					'message' => __( 'Failed to save submission. Please try again.', 'pressprimer-assignment' ),
				],
				500
			);
		}

		// Update assignment submission count.
		$assignment->update_submission_count();

		/**
		 * Fires after a text submission is finalized.
		 *
		 * @since 1.0.0
		 *
		 * @param PressPrimer_Assignment_Submission $submission The submission instance.
		 * @param PressPrimer_Assignment_Assignment $assignment The assignment instance.
		 */
		do_action( 'pressprimer_assignment_submission_submitted', $submission, $assignment );

		wp_send_json_success(
			[
				'message'       => __( 'Your assignment has been submitted successfully.', 'pressprimer-assignment' ),
				'submission_id' => $submission->id,
			]
		);
	}

	// =========================================================================
	// Helper Methods.
	// =========================================================================

	/**
	 * Get or create a draft submission for the user
	 *
	 * Retrieves an existing draft submission by ID or finds the latest
	 * draft for the user/assignment pair. Creates a new draft if none exists.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id       User ID.
	 * @param int $assignment_id Assignment ID.
	 * @param int $submission_id Optional. Existing submission ID to look up first.
	 * @return PressPrimer_Assignment_Submission|WP_Error Submission instance or WP_Error.
	 */
	private function get_or_create_draft( $user_id, $assignment_id, $submission_id = 0 ) {
		$user_id       = absint( $user_id );
		$assignment_id = absint( $assignment_id );
		$submission_id = absint( $submission_id );

		// If submission_id provided, try to retrieve it.
		if ( $submission_id > 0 ) {
			$submission = PressPrimer_Assignment_Submission::get( $submission_id );

			if ( $submission && PressPrimer_Assignment_Submission::STATUS_DRAFT === $submission->status ) {
				return $submission;
			}
		}

		// Look for existing draft.
		$drafts = PressPrimer_Assignment_Submission::find(
			[
				'where'    => [
					'user_id'       => $user_id,
					'assignment_id' => $assignment_id,
					'status'        => PressPrimer_Assignment_Submission::STATUS_DRAFT,
				],
				'order_by' => 'created_at',
				'order'    => 'DESC',
				'limit'    => 1,
			]
		);

		if ( ! empty( $drafts ) ) {
			return $drafts[0];
		}

		// Determine submission number (for resubmissions).
		$submission_number = $this->get_next_submission_number( $user_id, $assignment_id );

		// Create new draft.
		$new_id = PressPrimer_Assignment_Submission::create(
			[
				'assignment_id'     => $assignment_id,
				'user_id'           => $user_id,
				'status'            => PressPrimer_Assignment_Submission::STATUS_DRAFT,
				'submission_number' => $submission_number,
			]
		);

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		return PressPrimer_Assignment_Submission::get( $new_id );
	}

	/**
	 * Check if a user can submit to an assignment
	 *
	 * Verifies the assignment accepts submissions and checks
	 * resubmission limits if the user has already submitted.
	 *
	 * @since 1.0.0
	 *
	 * @param int                               $user_id    User ID.
	 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
	 * @return true|WP_Error True if allowed, WP_Error otherwise.
	 */
	private function can_user_submit( $user_id, $assignment ) {
		$user_id = absint( $user_id );

		// Count existing non-draft submissions.
		$submitted_count = $this->count_user_submissions( $user_id, $assignment->id );

		// First submission is always allowed.
		if ( 0 === $submitted_count ) {
			/**
			 * Filters whether a user can submit to an assignment.
			 *
			 * @since 1.0.0
			 *
			 * @param true|WP_Error                      $can_submit True if allowed.
			 * @param int                                $user_id    User ID.
			 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
			 */
			return apply_filters( 'pressprimer_assignment_can_submit', true, $user_id, $assignment );
		}

		// This is a resubmission — check if allowed.
		$resubmit_check = $this->check_resubmission_allowed( $user_id, $assignment );

		if ( is_wp_error( $resubmit_check ) ) {
			/** This filter is documented above. */
			return apply_filters( 'pressprimer_assignment_can_submit', $resubmit_check, $user_id, $assignment );
		}

		/** This filter is documented above. */
		return apply_filters( 'pressprimer_assignment_can_submit', true, $user_id, $assignment );
	}

	/**
	 * Check if resubmission is allowed for a user
	 *
	 * Verifies the assignment allows resubmission and the user
	 * has not exceeded the maximum number of resubmissions.
	 * Resubmission requires the latest submission to be returned.
	 *
	 * @since 1.0.0
	 *
	 * @param int                               $user_id    User ID.
	 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
	 * @return true|WP_Error True if allowed, WP_Error otherwise.
	 */
	private function check_resubmission_allowed( $user_id, $assignment ) {
		$user_id = absint( $user_id );

		// Check if assignment allows resubmission.
		if ( ! $assignment->allow_resubmission ) {
			return new WP_Error(
				'pressprimer_assignment_resubmission_disabled',
				__( 'Resubmission is not allowed for this assignment.', 'pressprimer-assignment' )
			);
		}

		// Get the user's latest submission (by submission_number).
		$submissions = PressPrimer_Assignment_Submission::find(
			[
				'where'    => [
					'user_id'       => $user_id,
					'assignment_id' => $assignment->id,
				],
				'order_by' => 'submission_number',
				'order'    => 'DESC',
				'limit'    => 1,
			]
		);

		if ( empty( $submissions ) ) {
			return true;
		}

		$latest = $submissions[0];

		// Allow if there's only a draft (no completed submissions yet).
		if ( PressPrimer_Assignment_Submission::STATUS_DRAFT === $latest->status ) {
			return true;
		}

		// Check resubmission count against the limit (0 = unlimited).
		// Uses submission_number to match the renderer's can_user_resubmit() logic.
		if ( (int) $assignment->max_resubmissions > 0 && $latest->submission_number >= $assignment->max_resubmissions + 1 ) {
			return new WP_Error(
				'pressprimer_assignment_max_resubmissions',
				__( 'You have reached the maximum number of submissions for this assignment.', 'pressprimer-assignment' )
			);
		}

		return true;
	}

	/**
	 * Get the next submission number for a user/assignment pair
	 *
	 * Uses MAX(submission_number) rather than COUNT(*) to avoid
	 * unique constraint violations when submissions have been deleted.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id       User ID.
	 * @param int $assignment_id Assignment ID.
	 * @return int Next submission number.
	 */
	private function get_next_submission_number( $user_id, $assignment_id ) {
		global $wpdb;

		$user_id       = absint( $user_id );
		$assignment_id = absint( $assignment_id );
		$table         = $wpdb->prefix . 'ppa_submissions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$max = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(submission_number) FROM {$table} WHERE user_id = %d AND assignment_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$assignment_id
			)
		);

		return $max + 1;
	}

	/**
	 * Count non-draft submissions for a user/assignment pair
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id       User ID.
	 * @param int $assignment_id Assignment ID.
	 * @return int Number of non-draft submissions.
	 */
	private function count_user_submissions( $user_id, $assignment_id ) {
		global $wpdb;

		$user_id       = absint( $user_id );
		$assignment_id = absint( $assignment_id );
		$table         = $wpdb->prefix . 'ppa_submissions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND assignment_id = %d AND status != %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$assignment_id,
				PressPrimer_Assignment_Submission::STATUS_DRAFT
			)
		);

		return $count;
	}
}
