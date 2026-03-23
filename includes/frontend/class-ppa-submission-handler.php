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
		add_action( 'wp_ajax_pressprimer_assignment_upload_file', [ $this, 'handle_upload' ] );
		add_action( 'wp_ajax_pressprimer_assignment_remove_file', [ $this, 'handle_remove' ] );
		add_action( 'wp_ajax_pressprimer_assignment_submit_assignment', [ $this, 'handle_submit' ] );
		add_action( 'wp_ajax_pressprimer_assignment_delete_submission', [ $this, 'handle_delete_submission' ] );
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
	 * AJAX action: pressprimer_assignment_upload_file
	 *
	 * @since 1.0.0
	 */
	public function handle_upload() {
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
					'message' => __( 'You must be logged in to upload files.', 'pressprimer-assignment' ),
				],
				401
			);
		}

		// Validate assignment.
		$assignment_id = isset( $_POST['assignment_id'] ) ? absint( wp_unslash( $_POST['assignment_id'] ) ) : 0;
		$assignment    = PressPrimer_Assignment_Assignment::get( $assignment_id );

		// Allow admins to upload to draft assignments for preview/testing.
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

		$user_id = get_current_user_id();

		// File uploads go into draft submissions, so we do NOT gate on
		// can_user_submit() here. The resubmission check is enforced at
		// final submit time in handle_submit(). This allows users to
		// upload files while a previous submission is being reviewed.

		// Check file was uploaded and all required keys are present.
		if (
			empty( $_FILES['file'] )
			|| ! isset( $_FILES['file']['name'], $_FILES['file']['type'], $_FILES['file']['tmp_name'], $_FILES['file']['error'], $_FILES['file']['size'] )
		) {
			wp_send_json_error(
				[
					'code'    => 'no_file',
					'message' => __( 'No file was uploaded.', 'pressprimer-assignment' ),
				],
				400
			);
		}

		// Sanitize the uploaded file array early.
		$uploaded_file = [
			'name'     => sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) ),
			'type'     => sanitize_mime_type( wp_unslash( $_FILES['file']['type'] ) ),
			'tmp_name' => $_FILES['file']['tmp_name'], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp_name is a server-generated temp path, not user input.
			'error'    => absint( $_FILES['file']['error'] ),
			'size'     => absint( $_FILES['file']['size'] ),
		];

		// Validate file.
		$file_service = $this->get_file_service();
		$validation   = $file_service->validate_file( $uploaded_file, $assignment );

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

		// Sync draft files with client state to remove stale uploads
		// from previous sessions. The client sends the IDs of files it
		// currently tracks; any draft files not in that list are removed.
		$known_ids_raw = isset( $_POST['known_file_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['known_file_ids'] ) ) : '[]';
		$known_ids     = json_decode( $known_ids_raw, true );

		if ( ! is_array( $known_ids ) ) {
			$known_ids = [];
		}

		$this->sync_draft_files( $submission, $known_ids );

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
		$file_id = $file_service->store_file( $uploaded_file, $submission->id );

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

		$response_data = [
			'id'   => (int) $file->id,
			'name' => $file->original_filename,
			'size' => (int) $file->file_size,
			'type' => $file->mime_type,
		];

		// Check PDF text extraction if this is a PDF file.
		if ( 'pdf' === strtolower( $file->file_extension ) && class_exists( 'PressPrimer_Assignment_PDF_Service' ) ) {
			$full_path   = $file->get_full_path();
			$pdf_service = new PressPrimer_Assignment_PDF_Service();
			$pdf_check   = $pdf_service->check_text_extractable( $full_path );

			// Store result on the file record.
			$file->text_extractable = $pdf_check['extractable'] ? 1 : 0;
			$file->save();

			// Include in response for the frontend preview.
			$response_data['text_extractable'] = $pdf_check['extractable'];

			// Include extracted text preview (first 3 pages, truncated for display).
			if ( $pdf_check['extractable'] ) {
				$preview_text = $pdf_service->extract_text( $full_path, PressPrimer_Assignment_PDF_Service::QUICK_CHECK_PAGES );

				if ( ! is_wp_error( $preview_text ) && ! empty( trim( $preview_text ) ) ) {
					// Truncate to 1000 characters for the frontend preview.
					$preview_text = trim( $preview_text );
					if ( mb_strlen( $preview_text ) > 1000 ) {
						$preview_text = mb_substr( $preview_text, 0, 1000 );
					}
					$response_data['text_preview'] = $preview_text;
				}
			}

			// Schedule full text extraction via WP Cron.
			PressPrimer_Assignment_PDF_Service::schedule_full_extraction( $file->id );
		}

		wp_send_json_success( $response_data );
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
	 * AJAX action: pressprimer_assignment_remove_file
	 *
	 * @since 1.0.0
	 */
	public function handle_remove() {
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
					'message' => __( 'You must be logged in to remove files.', 'pressprimer-assignment' ),
				],
				401
			);
		}

		$file_id = isset( $_POST['file_id'] ) ? absint( wp_unslash( $_POST['file_id'] ) ) : 0;

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
	 * AJAX action: pressprimer_assignment_submit_assignment
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

		$user_id = get_current_user_id();

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

		// Capture the page URL where the student submitted from.
		$page_url = wp_get_referer();
		if ( $page_url ) {
			$submission->set_meta( 'assignment_page_url', esc_url_raw( $page_url ) );
		}

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
		$submission_number = $this->get_next_submission_number( $user_id, $assignment_id );

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
	 * Sync draft files with the client's known file IDs
	 *
	 * Removes files from a draft submission that the client does not
	 * know about. This handles stale drafts from previous upload
	 * sessions where files were uploaded but never submitted.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Submission $draft         Draft submission instance.
	 * @param array                             $known_file_ids File IDs the client currently tracks.
	 */
	private function sync_draft_files( $draft, $known_file_ids ) {
		$files = $draft->get_files();

		if ( empty( $files ) ) {
			return;
		}

		$known_file_ids = array_map( 'absint', $known_file_ids );

		foreach ( $files as $file ) {
			if ( ! in_array( (int) $file->id, $known_file_ids, true ) ) {
				$file->delete();
			}
		}

		// Clear cached files so next call re-queries.
		$draft->get_files( true );
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

		// Check resubmission count against the limit.
		// Uses submission_number to match the renderer's can_user_resubmit() logic.
		if ( $latest->submission_number >= $assignment->max_resubmissions + 1 ) {
			return new WP_Error(
				'pressprimer_assignment_max_resubmissions',
				__( 'You have reached the maximum number of submissions for this assignment.', 'pressprimer-assignment' )
			);
		}

		return true;
	}

	/**
	 * Handle AJAX request to delete a previous submission
	 *
	 * Allows users to delete their own previous (non-current) submissions.
	 * Only the submission owner can delete their submissions.
	 * Properly cleans up associated files from disk.
	 *
	 * AJAX action: pressprimer_assignment_delete_submission
	 *
	 * @since 1.0.0
	 */
	public function handle_delete_submission() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'pressprimer_assignment_frontend_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Security check failed.', 'pressprimer-assignment' ) ],
				403
			);
		}

		$submission_id = isset( $_POST['submission_id'] ) ? absint( wp_unslash( $_POST['submission_id'] ) ) : 0;

		if ( 0 === $submission_id ) {
			wp_send_json_error(
				[ 'message' => __( 'Invalid submission.', 'pressprimer-assignment' ) ],
				400
			);
		}

		$submission = PressPrimer_Assignment_Submission::get( $submission_id );

		if ( ! $submission ) {
			wp_send_json_error(
				[ 'message' => __( 'Submission not found.', 'pressprimer-assignment' ) ],
				404
			);
		}

		// Only the submission owner can delete it.
		$user_id = get_current_user_id();
		if ( (int) $submission->user_id !== $user_id ) {
			wp_send_json_error(
				[ 'message' => __( 'You do not have permission to delete this submission.', 'pressprimer-assignment' ) ],
				403
			);
		}

		// Graded and returned submissions cannot be deleted by students.
		$protected_statuses = [
			PressPrimer_Assignment_Submission::STATUS_GRADED,
			PressPrimer_Assignment_Submission::STATUS_RETURNED,
		];

		if ( in_array( $submission->status, $protected_statuses, true ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Graded submissions cannot be deleted.', 'pressprimer-assignment' ) ],
				403
			);
		}

		// Delete associated files (with physical file cleanup).
		$files = $submission->get_files();
		foreach ( $files as $file ) {
			$file->delete();
		}

		// Delete the submission record.
		$result = $submission->delete();

		if ( true === $result ) {
			wp_send_json_success(
				[ 'message' => __( 'Submission deleted.', 'pressprimer-assignment' ) ]
			);
		} else {
			wp_send_json_error(
				[ 'message' => __( 'Failed to delete submission.', 'pressprimer-assignment' ) ],
				500
			);
		}
	}

	/**
	 * Get the next submission number for a user/assignment pair
	 *
	 * Uses MAX(submission_number) rather than COUNT(*) to avoid
	 * unique key collisions when earlier submissions are deleted.
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
