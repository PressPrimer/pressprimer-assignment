<?php
/**
 * Template: Submission Status
 *
 * Displays the user's submission status, files, grade, and feedback.
 *
 * This template can be overridden by copying it to:
 * yourtheme/pressprimer-assignment/assignment/submission-status.php
 *
 * Available variables:
 *
 * @var PressPrimer_Assignment_Submission          $submission               Submission instance.
 * @var PressPrimer_Assignment_Assignment          $assignment               Assignment instance.
 * @var array                                       $files                    Array of Submission_File instances.
 * @var string                                      $formatted_submitted_date Formatted submission date.
 * @var string                                      $formatted_graded_date    Formatted graded date.
 * @var bool                                        $can_resubmit             Whether resubmission is allowed.
 * @var int                                         $resubmissions_remaining  Number of resubmissions left.
 * @var array                                       $previous_submissions     Previous submissions array.
 * @var PressPrimer_Assignment_Assignment_Renderer  $this                     Renderer instance.
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

<section class="ppa-submission-status-card" aria-label="<?php esc_attr_e( 'Your submission', 'pressprimer-assignment' ); ?>">

	<div class="ppa-status-header">
		<h3 class="ppa-status-title"><?php esc_html_e( 'Your Submission', 'pressprimer-assignment' ); ?></h3>
		<span class="ppa-status-badge ppa-status-<?php echo esc_attr( $submission->status ); ?>">
			<?php echo esc_html( $this->get_status_label( $submission->status ) ); ?>
		</span>
	</div>

	<div class="ppa-submission-details">
		<?php if ( ! empty( $formatted_submitted_date ) ) : ?>
			<p class="ppa-detail-row">
				<span class="ppa-detail-label"><?php esc_html_e( 'Submitted:', 'pressprimer-assignment' ); ?></span>
				<time datetime="<?php echo esc_attr( $submission->submitted_at ); ?>" class="ppa-detail-value">
					<?php echo esc_html( $formatted_submitted_date ); ?>
				</time>
			</p>
		<?php endif; ?>

		<?php if ( $submission->submission_number > 1 ) : ?>
			<p class="ppa-detail-row">
				<span class="ppa-detail-label"><?php esc_html_e( 'Submission #:', 'pressprimer-assignment' ); ?></span>
				<span class="ppa-detail-value"><?php echo esc_html( number_format_i18n( $submission->submission_number ) ); ?></span>
			</p>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $submission->student_notes ) ) : ?>
		<div class="ppa-feedback">
			<h4 class="ppa-feedback-heading"><?php esc_html_e( 'Your Notes', 'pressprimer-assignment' ); ?></h4>
			<div class="ppa-feedback-content">
				<?php echo esc_html( $submission->student_notes ); ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $files ) ) : ?>
		<div class="ppa-submitted-files">
			<h4 class="ppa-feedback-heading"><?php esc_html_e( 'Submitted Files', 'pressprimer-assignment' ); ?></h4>
			<ul class="ppa-file-list" role="list">
				<?php foreach ( $files as $file ) : ?>
					<li class="ppa-file-item" role="listitem">
						<div class="ppa-file-info">
							<span class="ppa-file-icon" aria-hidden="true">
								<?php echo esc_html( strtoupper( $file->file_extension ) ); ?>
							</span>
							<span class="ppa-file-name"><?php echo esc_html( $file->original_filename ); ?></span>
							<span class="ppa-file-size"><?php echo esc_html( $file->get_formatted_size() ); ?></span>
						</div>
						<div class="ppa-file-actions">
							<a href="<?php echo esc_url( PressPrimer_Assignment_Frontend::get_file_url( $file->id, 'download' ) ); ?>"
								class="ppa-button ppa-button-secondary ppa-button-small">
								<?php esc_html_e( 'Download', 'pressprimer-assignment' ); ?>
							</a>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( PressPrimer_Assignment_Submission::STATUS_RETURNED === $submission->status ) : ?>

		<div class="ppa-grade-display <?php echo $submission->passed ? 'ppa-grade-passed' : 'ppa-grade-failed'; ?>">
			<h4 class="ppa-sr-only"><?php esc_html_e( 'Your Grade', 'pressprimer-assignment' ); ?></h4>

			<div class="ppa-grade-score">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %1$s: score, %2$s: max points */
						__( '%1$s / %2$s', 'pressprimer-assignment' ),
						number_format_i18n( $submission->score, 1 ),
						number_format_i18n( $assignment->max_points, 0 )
					)
				);
				?>
			</div>
			<div class="ppa-grade-label">
				<?php esc_html_e( 'points', 'pressprimer-assignment' ); ?>
			</div>

			<?php if ( null !== $submission->passed ) : ?>
				<div class="ppa-pass-badge <?php echo $submission->passed ? 'ppa-passed' : 'ppa-failed'; ?>">
					<?php
					if ( $submission->passed ) {
						esc_html_e( 'Passed', 'pressprimer-assignment' );
					} else {
						esc_html_e( 'Did Not Pass', 'pressprimer-assignment' );
					}
					?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $submission->feedback ) ) : ?>
			<div class="ppa-feedback">
				<h4 class="ppa-feedback-heading"><?php esc_html_e( 'Instructor Feedback', 'pressprimer-assignment' ); ?></h4>
				<div class="ppa-feedback-content">
					<?php echo wp_kses_post( wpautop( $submission->feedback ) ); ?>
				</div>
				<?php if ( ! empty( $formatted_graded_date ) ) : ?>
					<p class="ppa-form-hint">
						<?php
						printf(
							/* translators: %s: date the submission was graded */
							esc_html__( 'Graded on %s', 'pressprimer-assignment' ),
							esc_html( $formatted_graded_date )
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

	<?php endif; ?>

	<?php if ( $can_resubmit ) : ?>
		<div class="ppa-resubmit-section">
			<p class="ppa-resubmit-info">
				<?php
				printf(
					/* translators: %1$d: remaining resubmissions, %2$d: total max resubmissions */
					esc_html__( 'You have %1$d of %2$d resubmissions remaining.', 'pressprimer-assignment' ),
					(int) $resubmissions_remaining,
					(int) $assignment->max_resubmissions
				);
				?>
			</p>
			<button type="button" class="ppa-button ppa-button-secondary" id="ppa-start-resubmit" data-assignment-id="<?php echo esc_attr( $assignment->id ); ?>">
				<?php esc_html_e( 'Submit Again', 'pressprimer-assignment' ); ?>
			</button>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $previous_submissions ) ) : ?>
		<details class="ppa-previous-submissions">
			<summary class="ppa-previous-summary">
				<?php esc_html_e( 'View Previous Submissions', 'pressprimer-assignment' ); ?>
			</summary>
			<ul class="ppa-previous-list">
				<?php foreach ( $previous_submissions as $prev ) : ?>
					<?php
					$prev_date = '';
					if ( ! empty( $prev->submitted_at ) ) {
						$prev_date = date_i18n(
							get_option( 'date_format' ),
							strtotime( $prev->submitted_at )
						);
					}
					?>
					<li class="ppa-previous-item">
						<?php
						printf(
							/* translators: %1$d: submission number, %2$s: date, %3$s: score or "Not graded" */
							esc_html__( 'Submission #%1$d - %2$s - Score: %3$s', 'pressprimer-assignment' ),
							(int) $prev->submission_number,
							esc_html( $prev_date ),
							null !== $prev->score
								? esc_html( number_format_i18n( $prev->score, 1 ) )
								: esc_html__( 'Not graded', 'pressprimer-assignment' )
						);
						?>
					</li>
				<?php endforeach; ?>
			</ul>
		</details>
	<?php endif; ?>

</section>
