<?php
/**
 * Submission handler
 *
 * Handles AJAX requests for file upload, file removal,
 * and assignment submission from the frontend.
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
 * Submission handler class
 *
 * Registers AJAX actions for the frontend submission workflow:
 * upload file, remove file, and submit assignment.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Submission_Handler {

	/**
	 * File service instance
	 *
	 * @since 1.0.0
	 * @var PressPrimer_Assignment_File_Service|null
	 */
	private $file_service = null;

	/**
	 * Initialize the handler
	 *
	 * Registers AJAX actions for logged-in users.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'wp_ajax_ppa_upload_file', [ $this, 'handle_upload' ] );
		add_action( 'wp_ajax_ppa_remove_file', [ $this, 'handle_remove' ] );
		add_action( 'wp_ajax_ppa_submit_assignment', [ $this, 'handle_submit' ] );
	}

	/**
	 * Get file service instance
	 *
	 * Lazy-loads the file service.
	 *
	 * @since 1.0.0
	 *
	 * @return PressPrimer_Assignment_File_Service File service instance.
	 */
	private function get_file_service() {
		if ( null === $this->file_service ) {
			$this->file_service = new PressPrimer_Assignment_File_Service();
		}

		return $this->file_service;
	}

	// =========================================================================
	// AJAX: Upload File.
	// =========================================================================

	/**
	 * Handle single file upload
	 *
	 * Validates nonce, checks permissions, validates the file,
	 * creates or retrieves a draft submission, and stores the file.
	 *
	 * AJAX action: ppa_upload_file
	 *
	 * @since 1.0.0
	 */
	public function handle_upload() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'ppa_frontend_nonce', 'nonce', false ) ) {
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
					'message' => __( 'You must be logged in to upload files.', 'pressprimer-assignment' ),
				],
				401
			);
		}

		// Validate assignment.
		$assignment_id = isset( $_POST['assignment_id'] ) ? absint( $_POST['assignment_id'] ) : 0;
		$assignment    = PressPrimer_Assignment_Assignment::get( $assignment_id );

		if ( ! $assignment || ! $assignment->accepts_submissions() ) {
			wp_send_json_error(
				[
					'code'    => 'invalid_assignment',
					'message' => __( 'This assignment is not accepting submissions.', 'pressprimer-assignment' ),
				],
				400
			);
		}

		// Check if user can submit.
		$user_id    = get_current_user_id();
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

		// Check file was uploaded.
		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error(
				[
					'code'    => 'no_file',
					'message' => __( 'No file was uploaded.', 'pressprimer-assignment' ),
				],
				400
			);
		}

		// Validate file.
		$file_service = $this->get_file_service();

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File validation is handled by validate_file().
		$validation = $file_service->validate_file( $_FILES['file'], $assignment );

		if ( is_wp_error( $validation ) ) {
			wp_send_json_error(
				[
					'code'    => $validation->get_error_code(),
					'message' => $validation->get_error_message(),
				],
				400
			);
		}

		// Get or create draft submission.
		$submission = $this->get_or_create_draft( $user_id, $assignment_id );

		if ( is_wp_error( $submission ) ) {
			wp_send_json_error(
				[
					'code'    => $submission->get_error_code(),
					'message' => $submission->get_error_message(),
				],
				400
			);
		}

		// Check file count limit.
		$existing_files = $submission->get_files();
		if ( count( $existing_files ) >= $assignment->max_files ) {
			wp_send_json_error(
				[
					'code'    => 'max_files_reached',
					'message' => __( 'Maximum number of files reached.', 'pressprimer-assignment' ),
				],
				400
			);
		}

		// Store file.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File storage is handled by store_file().
		$file_id = $file_service->store_file( $_FILES['file'], $submission->id );

		if ( is_wp_error( $file_id ) ) {
			wp_send_json_error(
				[
					'code'    => $file_id->get_error_code(),
					'message' => $file_id->get_error_message(),
				],
				400
			);
		}

		// Get file record for the response.
		$file = PressPrimer_Assignment_Submission_File::get( $file_id );

		wp_send_json_success(
			[
				'id'   => $file->id,
				'name' => $file->original_filename,
				'size' => $file->file_size,
				'type' => $file->mime_type,
			]
		);
	}

	// =========================================================================
	// AJAX: Remove File.
	// =========================================================================

	/**
	 * Handle file removal
	 *
	 * Removes an uploaded file from a draft submission.
	 * Only the file owner can remove files from their draft.
	 *
	 * AJAX action: ppa_remove_file
	 *
	 * @since 1.0.0
	 */
	public function handle_remove() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'ppa_frontend_nonce', 'nonce', false ) ) {
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
					'message' => __( 'You must be logged in to remove files.', 'pressprimer-assignment' ),
				],
				401
			);
		}

		$file_id = isset( $_POST['file_id'] ) ? absint( $_POST['file_id'] ) : 0;

		if ( 0 === $file_id ) {
			wp_send_json_error(
				[
					'code'    => 'invalid_file_id',
					'message' => __( 'Invalid file ID.', 'pressprimer-assignment' ),
				],
				400
			);
		}

		// Get file record.
		$file = PressPrimer_Assignment_Submission_File::get( $file_id );

		if ( ! $file ) {
			wp_send_json_error(
				[
					'code'    => 'file_not_found',
					'message' => __( 'File not found.', 'pressprimer-assignment' ),
				],
				404
			);
		}

		// Get submission to verify ownership and draft status.
		$submission = PressPrimer_Assignment_Submission::get( $file->submission_id );

		if ( ! $submission ) {
			wp_send_json_error(
				[
					'code'    => 'submission_not_found',
					'message' => __( 'Submission not found.', 'pressprimer-assignment' ),
				],
				404
			);
		}

		// Only the owner can remove files from a draft.
		if ( (int) $submission->user_id !== get_current_user_id() ) {
			wp_send_json_error(
				[
					'code'    => 'access_denied',
					'message' => __( 'You do not have permission to remove this file.', 'pressprimer-assignment' ),
				],
				403
			);
		}

		// Only allow removal from draft submissions.
		if ( PressPrimer_Assignment_Submission::STATUS_DRAFT !== $submission->status ) {
			wp_send_json_error(
				[
					'code'    => 'not_draft',
					'message' => __( 'Files can only be removed from draft submissions.', 'pressprimer-assignment' ),
				],
				400
			);
		}

		// Delete file.
		$file_service = $this->get_file_service();
		$result       = $file_service->delete_file( $file_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				[
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				],
				400
			);
		}

		wp_send_json_success(
			[
				'message' => __( 'File removed.', 'pressprimer-assignment' ),
			]
		);
	}

	// =========================================================================
	// AJAX: Submit Assignment.
	// =========================================================================

	/**
	 * Handle assignment submission
	 *
	 * Finalizes a draft submission: validates files exist, saves student
	 * notes, updates status to 'submitted', and fires action hooks.
	 *
	 * AJAX action: ppa_submit_assignment
	 *
	 * @since 1.0.0
	 */
	public function handle_submit() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'ppa_frontend_nonce', 'nonce', false ) ) {
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
		$assignment_id = isset( $_POST['assignment_id'] ) ? absint( $_POST['assignment_id'] ) : 0;
		$assignment    = PressPrimer_Assignment_Assignment::get( $assignment_id );

		if ( ! $assignment || ! $assignment->accepts_submissions() ) {
			wp_send_json_error(
				[
					'code'    => 'invalid_assignment',
					'message' => __( 'This assignment is not accepting submissions.', 'pressprimer-assignment' ),
				],
				400
			);
		}

		$user_id = get_current_user_id();

		// Check if user can submit.
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

		// Get draft submission.
		$submission = $this->get_draft_submission( $user_id, $assignment_id );

		if ( ! $submission ) {
			wp_send_json_error(
				[
					'code'    => 'no_draft',
					'message' => __( 'No draft submission found. Please upload files first.', 'pressprimer-assignment' ),
				],
				400
			);
		}

		// Validate at least one file exists.
		$files = $submission->get_files( true );

		if ( empty( $files ) ) {
			wp_send_json_error(
				[
					'code'    => 'no_files',
					'message' => __( 'Please upload at least one file before submitting.', 'pressprimer-assignment' ),
				],
				400
			);
		}

		// Sanitize and save student notes.
		$student_notes = isset( $_POST['student_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['student_notes'] ) ) : '';

		if ( mb_strlen( $student_notes ) > 2000 ) {
			$student_notes = mb_substr( $student_notes, 0, 2000 );
		}

		$submission->student_notes = $student_notes;

		// Update submission status.
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
		 * Fires after a submission is finalized.
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
	 * Retrieves an existing draft submission or creates a new one.
	 * For resubmissions, increments the submission_number.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id       User ID.
	 * @param int $assignment_id Assignment ID.
	 * @return PressPrimer_Assignment_Submission|WP_Error Submission instance or WP_Error.
	 */
	private function get_or_create_draft( $user_id, $assignment_id ) {
		$user_id       = absint( $user_id );
		$assignment_id = absint( $assignment_id );

		// Look for existing draft.
		$draft = $this->get_draft_submission( $user_id, $assignment_id );

		if ( $draft ) {
			return $draft;
		}

		// Determine submission number (for resubmissions).
		$submission_number = $this->count_user_submissions( $user_id, $assignment_id ) + 1;

		// Create new draft.
		$submission_id = PressPrimer_Assignment_Submission::create(
			[
				'assignment_id'     => $assignment_id,
				'user_id'           => $user_id,
				'status'            => PressPrimer_Assignment_Submission::STATUS_DRAFT,
				'submission_number' => $submission_number,
			]
		);

		if ( is_wp_error( $submission_id ) ) {
			return $submission_id;
		}

		return PressPrimer_Assignment_Submission::get( $submission_id );
	}

	/**
	 * Get the user's existing draft submission for an assignment
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id       User ID.
	 * @param int $assignment_id Assignment ID.
	 * @return PressPrimer_Assignment_Submission|null Submission instance or null.
	 */
	private function get_draft_submission( $user_id, $assignment_id ) {
		$user_id       = absint( $user_id );
		$assignment_id = absint( $assignment_id );

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

		return ! empty( $drafts ) ? $drafts[0] : null;
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

		// First submission is always allowed (if assignment accepts submissions).
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

		// This is a resubmission — check if resubmission is allowed.
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
				'ppa_resubmission_disabled',
				__( 'Resubmission is not allowed for this assignment.', 'pressprimer-assignment' )
			);
		}

		// Get the user's latest non-draft submission.
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

		// Resubmission requires the latest submission to be returned.
		if ( PressPrimer_Assignment_Submission::STATUS_RETURNED !== $latest->status ) {
			return new WP_Error(
				'ppa_awaiting_grading',
				__( 'Your submission is still being reviewed. Resubmission will be available after it is returned.', 'pressprimer-assignment' )
			);
		}

		// Check resubmission count against the limit.
		$submitted_count = $this->count_user_submissions( $user_id, $assignment->id );

		if ( $submitted_count > $assignment->max_resubmissions ) {
			return new WP_Error(
				'ppa_max_resubmissions',
				__( 'You have reached the maximum number of submissions for this assignment.', 'pressprimer-assignment' )
			);
		}

		return true;
	}

	/**
	 * Count the user's non-draft submissions for an assignment
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
