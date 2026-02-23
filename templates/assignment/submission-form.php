<?php
/**
 * Template: Submission Form
 *
 * Displays the file upload zone, submission notes, and submit button.
 *
 * This template can be overridden by copying it to:
 * yourtheme/pressprimer-assignment/assignment/submission-form.php
 *
 * Available variables:
 *
 * @var PressPrimer_Assignment_Assignment          $assignment      Assignment instance.
 * @var bool                                        $is_resubmission Whether this is a resubmission.
 * @var array                                       $allowed_types   Array of allowed file extensions.
 * @var string                                      $accept_string   Accept attribute value for file input.
 * @var PressPrimer_Assignment_Assignment_Renderer  $this            Renderer instance.
 *
 * @package PressPrimer_Assignment
 * @subpackage Templates
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<section class="ppa-submission-form" aria-label="<?php esc_attr_e( 'Submission form', 'pressprimer-assignment' ); ?>">
	<h3 class="ppa-form-heading">
		<?php
		if ( $is_resubmission ) {
			esc_html_e( 'Submit Again', 'pressprimer-assignment' );
		} else {
			esc_html_e( 'Submit Your Work', 'pressprimer-assignment' );
		}
		?>
	</h3>

	<form id="ppa-submission-form" method="post" enctype="multipart/form-data">
		<?php wp_nonce_field( 'ppa_submit_assignment', 'ppa_nonce' ); ?>
		<input type="hidden" name="assignment_id" value="<?php echo esc_attr( $assignment->id ); ?>">

		<div class="ppa-form-section">
			<div class="ppa-upload-container"
				data-assignment-id="<?php echo esc_attr( $assignment->id ); ?>"
				data-allowed-types="<?php echo esc_attr( wp_json_encode( $allowed_types ) ); ?>"
				data-max-size="<?php echo esc_attr( $assignment->max_file_size ); ?>"
				data-max-files="<?php echo esc_attr( $assignment->max_files ); ?>"
				data-rest-url="<?php echo esc_attr( rest_url( 'ppa/v1/' ) ); ?>"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">

				<div class="ppa-upload-zone"
					tabindex="0"
					role="button"
					aria-label="<?php esc_attr_e( 'Upload files. Drag and drop or press Enter to browse.', 'pressprimer-assignment' ); ?>">
					<div class="ppa-upload-icon" aria-hidden="true">
						<span class="dashicons dashicons-upload"></span>
					</div>
					<p class="ppa-upload-text">
						<?php esc_html_e( 'Drag and drop files here, or click to browse', 'pressprimer-assignment' ); ?>
					</p>
					<p class="ppa-upload-hint">
						<?php
						printf(
							/* translators: %1$s: accepted file types, %2$s: max file size, %3$d: max number of files */
							esc_html__( 'Accepted: %1$s (max %2$s each, up to %3$d files)', 'pressprimer-assignment' ),
							esc_html( implode( ', ', array_map( 'strtoupper', $allowed_types ) ) ),
							esc_html( size_format( $assignment->max_file_size ) ),
							(int) $assignment->max_files
						);
						?>
					</p>
				</div>

				<input
					type="file"
					class="ppa-upload-input"
					multiple
					accept="<?php echo esc_attr( $accept_string ); ?>"
					aria-hidden="true"
					tabindex="-1"
				>

				<div class="ppa-file-list" role="list" aria-label="<?php esc_attr_e( 'Uploaded files', 'pressprimer-assignment' ); ?>">
					<!-- Files added dynamically by JavaScript -->
				</div>
			</div>
		</div>

		<div class="ppa-form-section ppa-student-notes">
			<label for="ppa-student-notes" class="ppa-form-label">
				<?php esc_html_e( 'Notes (optional)', 'pressprimer-assignment' ); ?>
			</label>
			<p class="ppa-form-hint">
				<?php esc_html_e( 'Add any context or comments about your submission.', 'pressprimer-assignment' ); ?>
			</p>
			<textarea
				id="ppa-student-notes"
				name="student_notes"
				rows="4"
				maxlength="2000"
				placeholder="<?php esc_attr_e( 'E.g., "I focused on the second approach discussed in class..."', 'pressprimer-assignment' ); ?>"
				aria-describedby="ppa-char-count"
			></textarea>
			<p class="ppa-form-hint" id="ppa-char-count" aria-live="polite">
				<span class="ppa-char-current">0</span> / 2,000
			</p>
		</div>

		<div class="ppa-form-section ppa-submission-actions">
			<button
				type="submit"
				class="ppa-button ppa-button-primary ppa-submit-button"
				id="ppa-submit-btn"
				disabled
				aria-describedby="ppa-submit-hint"
			>
				<?php esc_html_e( 'Submit Assignment', 'pressprimer-assignment' ); ?>
			</button>
			<p class="ppa-form-hint" id="ppa-submit-hint">
				<?php esc_html_e( 'Upload at least one file to enable submission.', 'pressprimer-assignment' ); ?>
			</p>
		</div>
	</form>
</section>
