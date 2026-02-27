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

	<?php if ( $submission->is_text_submission() ) : ?>
		<?php
		$ppa_max_preview_words = 500;
		$ppa_text_content      = $submission->text_content;
		$ppa_is_truncated      = false;

		if ( (int) $submission->word_count > $ppa_max_preview_words ) {
			// Strip HTML but preserve line breaks for wpautop.
			$ppa_plain_text = wp_strip_all_tags( $ppa_text_content );
			$ppa_lines      = preg_split( '/\r\n|\r|\n/', $ppa_plain_text );
			$ppa_word_total = 0;
			$ppa_kept_lines = [];

			foreach ( $ppa_lines as $ppa_line ) {
				$ppa_line_words = preg_split( '/\s+/', $ppa_line, -1, PREG_SPLIT_NO_EMPTY );

				if ( $ppa_word_total + count( $ppa_line_words ) > $ppa_max_preview_words ) {
					$ppa_keep         = $ppa_max_preview_words - $ppa_word_total;
					$ppa_kept_lines[] = implode( ' ', array_slice( $ppa_line_words, 0, $ppa_keep ) ) . "\xe2\x80\xa6";
					$ppa_word_total   = $ppa_max_preview_words;
					break;
				}

				$ppa_kept_lines[] = $ppa_line;
				$ppa_word_total  += count( $ppa_line_words );
			}

			if ( $ppa_word_total >= $ppa_max_preview_words ) {
				$ppa_text_content = implode( "\n", $ppa_kept_lines );
				$ppa_is_truncated = true;
			}
		}
		?>
		<div class="ppa-submitted-text">
			<h4 class="ppa-feedback-heading"><?php esc_html_e( 'Your Submission', 'pressprimer-assignment' ); ?></h4>
			<div class="ppa-submitted-text-content">
				<?php echo wp_kses_post( wpautop( $ppa_text_content ) ); ?>
			</div>
			<?php if ( $ppa_is_truncated ) : ?>
				<p class="ppa-form-hint">
					<?php
					printf(
						/* translators: %1$s: number of preview words shown, %2$s: total word count */
						esc_html__( 'Preview showing the first %1$s of %2$s words.', 'pressprimer-assignment' ),
						esc_html( number_format_i18n( $ppa_max_preview_words ) ),
						esc_html( number_format_i18n( $submission->word_count ) )
					);
					?>
				</p>
			<?php elseif ( $submission->word_count > 0 ) : ?>
				<p class="ppa-form-hint">
					<?php
					printf(
						/* translators: %s: word count */
						esc_html__( '%s words', 'pressprimer-assignment' ),
						esc_html( number_format_i18n( $submission->word_count ) )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
	<?php elseif ( ! empty( $files ) ) : ?>
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
						number_format_i18n( $submission->score, 0 ),
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
		<div class="ppa-previous-submissions">
			<h4 class="ppa-feedback-heading"><?php esc_html_e( 'Previous Submissions', 'pressprimer-assignment' ); ?></h4>
			<div class="ppa-submissions-list">
				<?php
				$shown = 0;
				foreach ( $previous_submissions as $prev ) :
					if ( $shown >= 3 ) {
						break;
					}
					++$shown;

					$prev_date = '';
					if ( ! empty( $prev->submitted_at ) ) {
						$prev_date = date_i18n(
							get_option( 'date_format' ),
							strtotime( $prev->submitted_at )
						);
					}

					$prev_is_graded = in_array(
						$prev->status,
						[
							PressPrimer_Assignment_Submission::STATUS_GRADED,
							PressPrimer_Assignment_Submission::STATUS_RETURNED,
						],
						true
					);
					?>
					<div class="ppa-submission-card" data-submission-id="<?php echo esc_attr( $prev->id ); ?>">
						<div class="ppa-submission-card-info">
							<span class="ppa-submission-card-number">
								<?php
								printf(
									/* translators: %d: submission number */
									esc_html__( 'Submission #%d', 'pressprimer-assignment' ),
									(int) $prev->submission_number
								);
								?>
							</span>
							<span class="ppa-submission-card-date">
								<?php echo esc_html( $prev_date ); ?>
							</span>
						</div>
						<div class="ppa-submission-card-actions">
							<?php if ( null !== $prev->score ) : ?>
								<span class="ppa-submission-card-points <?php echo esc_attr( $prev->passed ? 'ppa-passed' : 'ppa-failed' ); ?>">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %1$s: score, %2$s: max points */
											__( '%1$s / %2$s', 'pressprimer-assignment' ),
											number_format_i18n( $prev->score, 0 ),
											number_format_i18n( $assignment->max_points, 0 )
										)
									);
									?>
								</span>
							<?php else : ?>
								<span class="ppa-submission-card-ungraded">
									<?php esc_html_e( 'Not graded', 'pressprimer-assignment' ); ?>
								</span>
							<?php endif; ?>
							<?php if ( $prev_is_graded ) : ?>
								<a href="<?php echo esc_url( add_query_arg( 'ppa_submission', absint( $prev->id ), get_permalink() ) ); ?>"
									class="ppa-view-submission"
									aria-label="
									<?php
										printf(
											/* translators: %d: submission number */
											esc_attr__( 'View details for submission #%d', 'pressprimer-assignment' ),
											(int) $prev->submission_number
										);
									?>
									">
									<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
								</a>
							<?php else : ?>
								<button type="button"
									class="ppa-delete-submission"
									data-submission-id="<?php echo esc_attr( $prev->id ); ?>"
									aria-label="<?php esc_attr_e( 'Delete this submission', 'pressprimer-assignment' ); ?>">
									<span class="dashicons dashicons-trash" aria-hidden="true"></span>
								</button>
							<?php endif; ?>
						</div>
						<?php if ( $prev_is_graded && ! empty( $prev->feedback ) ) : ?>
							<div class="ppa-submission-card-feedback">
								<span class="ppa-submission-card-feedback-label"><?php esc_html_e( 'Feedback:', 'pressprimer-assignment' ); ?></span>
								<div class="ppa-submission-card-feedback-text">
									<?php echo wp_kses_post( wpautop( $prev->feedback ) ); ?>
								</div>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

</section>
