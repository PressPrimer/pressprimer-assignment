<?php
/**
 * Template: My Submissions Dashboard
 *
 * Displays a table of the student's submissions across all assignments.
 * Visual style aligned with PressPrimer Quiz's My Attempts table.
 *
 * This template can be overridden by copying it to:
 * yourtheme/pressprimer-assignment/dashboard/my-submissions.php
 *
 * Available variables:
 *
 * @var array  $submissions Array of submission item objects (see below).
 * @var array  $display     Display options (show_status, show_score, show_date, per_page).
 * @var string $theme_class Theme CSS class (e.g. 'ppa-theme-default').
 * @var PressPrimer_Assignment_Submissions_Renderer $this Renderer instance.
 *
 * Each item in $submissions has:
 *   ->submission        PressPrimer_Assignment_Submission instance
 *   ->assignment        PressPrimer_Assignment_Assignment instance or null
 *   ->title             string  Assignment title
 *   ->submission_number int     Attempt number (1, 2, 3, etc.)
 *   ->formatted_date    string  Formatted submission date
 *   ->date_value        string  Raw datetime value
 *   ->status_label      string  Human-readable status label
 *   ->view_url          string  URL to view the submission (empty if unavailable)
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

<div class="ppa-my-submissions <?php echo esc_attr( $theme_class ); ?>">

	<?php if ( empty( $submissions ) ) : ?>

		<div class="ppa-empty-state">
			<div class="ppa-empty-state-icon" aria-hidden="true">
				<span class="dashicons dashicons-clipboard"></span>
			</div>
			<p class="ppa-empty-state-message">
				<?php esc_html_e( 'You have not submitted any assignments yet.', 'pressprimer-assignment' ); ?>
			</p>
		</div>

	<?php else : ?>

		<div class="ppa-submissions-table-wrapper">
			<table class="ppa-submissions-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Assignment', 'pressprimer-assignment' ); ?></th>
						<?php if ( ! empty( $display['show_date'] ) ) : ?>
							<th scope="col"><?php esc_html_e( 'Submitted', 'pressprimer-assignment' ); ?></th>
						<?php endif; ?>
						<?php if ( ! empty( $display['show_status'] ) ) : ?>
							<th scope="col"><?php esc_html_e( 'Status', 'pressprimer-assignment' ); ?></th>
						<?php endif; ?>
						<?php if ( ! empty( $display['show_score'] ) ) : ?>
							<th scope="col"><?php esc_html_e( 'Score', 'pressprimer-assignment' ); ?></th>
						<?php endif; ?>
						<th scope="col"><?php esc_html_e( 'Actions', 'pressprimer-assignment' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $submissions as $item ) : ?>
						<tr class="ppa-submission-row">
							<td class="ppa-col-assignment" data-label="<?php esc_attr_e( 'Assignment', 'pressprimer-assignment' ); ?>">
								<?php echo esc_html( $item->title ); ?>
								<?php if ( $item->submission_number > 1 ) : ?>
									<span class="ppa-attempt-number">
										<?php
										printf(
											/* translators: %d: attempt number */
											esc_html__( '(Attempt #%d)', 'pressprimer-assignment' ),
											(int) $item->submission_number
										);
										?>
									</span>
								<?php endif; ?>
							</td>

							<?php if ( ! empty( $display['show_date'] ) ) : ?>
								<td class="ppa-col-date" data-label="<?php esc_attr_e( 'Submitted', 'pressprimer-assignment' ); ?>">
									<?php if ( ! empty( $item->date_value ) ) : ?>
										<time datetime="<?php echo esc_attr( $item->date_value ); ?>">
											<?php echo esc_html( $item->formatted_date ); ?>
										</time>
									<?php else : ?>
										<span class="ppa-score-pending">&mdash;</span>
									<?php endif; ?>
								</td>
							<?php endif; ?>

							<?php if ( ! empty( $display['show_status'] ) ) : ?>
								<td class="ppa-col-status" data-label="<?php esc_attr_e( 'Status', 'pressprimer-assignment' ); ?>">
									<span class="ppa-status-badge ppa-status-<?php echo esc_attr( $item->submission->status ); ?>">
										<?php echo esc_html( $item->status_label ); ?>
									</span>
								</td>
							<?php endif; ?>

							<?php if ( ! empty( $display['show_score'] ) ) : ?>
								<td class="ppa-col-score" data-label="<?php esc_attr_e( 'Score', 'pressprimer-assignment' ); ?>">
									<?php if ( PressPrimer_Assignment_Submission::STATUS_RETURNED === $item->submission->status && null !== $item->submission->score ) : ?>
										<?php
										$ppa_passed_class = $item->submission->passed ? 'ppa-score-passed' : 'ppa-score-failed';
										$ppa_max_points   = $item->assignment ? $item->assignment->max_points : 0;
										?>
										<span class="ppa-score-display <?php echo esc_attr( $ppa_passed_class ); ?>">
											<?php
											echo esc_html(
												sprintf(
													/* translators: %1$s: score, %2$s: max points */
													__( '%1$s / %2$s', 'pressprimer-assignment' ),
													number_format_i18n( $item->submission->score, 0 ),
													number_format_i18n( $ppa_max_points, 0 )
												)
											);
											?>
										</span>
									<?php else : ?>
										<span class="ppa-score-pending">&mdash;</span>
									<?php endif; ?>
								</td>
							<?php endif; ?>

							<td class="ppa-col-actions" data-label="<?php esc_attr_e( 'Actions', 'pressprimer-assignment' ); ?>">
								<div class="ppa-submission-actions">
									<?php if ( ! empty( $item->view_url ) ) : ?>
										<a href="<?php echo esc_url( $item->view_url ); ?>" class="ppa-button ppa-button-small ppa-button-primary">
											<?php esc_html_e( 'View', 'pressprimer-assignment' ); ?>
										</a>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

	<?php endif; ?>

</div>
