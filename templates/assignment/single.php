<?php
/**
 * Template: Assignment Single
 *
 * Displays a single assignment with its information and either
 * the submission form, submission status, or login prompt.
 *
 * This template can be overridden by copying it to:
 * yourtheme/pressprimer-assignment/assignment/single.php
 *
 * Available variables:
 *
 * @var PressPrimer_Assignment_Assignment          $assignment      Assignment instance.
 * @var PressPrimer_Assignment_Submission|null      $user_submission Current user submission or null.
 * @var bool                                        $can_submit      Whether user can submit.
 * @var bool                                        $can_resubmit    Whether user can resubmit.
 * @var bool                                        $is_logged_in    Whether user is logged in.
 * @var array                                       $display         Display options.
 * @var string                                      $theme_class     Theme CSS class.
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

<div class="ppa-assignment <?php echo esc_attr( $theme_class ); ?>" data-assignment-id="<?php echo esc_attr( $assignment->id ); ?>">
	<div class="ppa-assignment-content">

		<?php
		/**
		 * Fires before assignment info is rendered.
		 *
		 * @since 1.0.0
		 *
		 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
		 */
		do_action( 'pressprimer_assignment_before_info', $assignment );

		// Render assignment info (title, description, meta, instructions).
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in render_assignment_info().
		echo $this->render_assignment_info( $assignment, $display );

		/**
		 * Fires after assignment info is rendered.
		 *
		 * @since 1.0.0
		 *
		 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
		 */
		do_action( 'pressprimer_assignment_after_info', $assignment );
		?>

		<?php if ( ! $is_logged_in ) : ?>

			<div class="ppa-notice ppa-notice-info" role="status">
				<p class="ppa-notice-message">
					<?php
					printf(
						/* translators: %1$s: opening anchor tag, %2$s: closing anchor tag */
						esc_html__( 'Please %1$slog in%2$s to submit this assignment.', 'pressprimer-assignment' ),
						'<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="ppa-login-link">',
						'</a>'
					);
					?>
				</p>
			</div>

		<?php elseif ( $user_submission && PressPrimer_Assignment_Submission::STATUS_DRAFT !== $user_submission->status ) : ?>

			<?php
			/**
			 * Fires before submission status is rendered.
			 *
			 * @since 1.0.0
			 *
			 * @param PressPrimer_Assignment_Submission $user_submission Submission instance.
			 * @param PressPrimer_Assignment_Assignment $assignment      Assignment instance.
			 */
			do_action( 'pressprimer_assignment_before_submission_status', $user_submission, $assignment );

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in render_user_submission().
			echo $this->render_user_submission( $user_submission, $assignment );

			/**
			 * Fires after submission status is rendered.
			 *
			 * @since 1.0.0
			 *
			 * @param PressPrimer_Assignment_Submission $user_submission Submission instance.
			 * @param PressPrimer_Assignment_Assignment $assignment      Assignment instance.
			 */
			do_action( 'pressprimer_assignment_after_submission_status', $user_submission, $assignment );
			?>

		<?php elseif ( $can_submit || $can_resubmit ) : ?>

			<?php
			/**
			 * Fires before submission form is rendered.
			 *
			 * @since 1.0.0
			 *
			 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
			 */
			do_action( 'pressprimer_assignment_before_submission_form', $assignment );

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in render_submission_form().
			echo $this->render_submission_form( $assignment, $can_resubmit );

			/**
			 * Fires after submission form is rendered.
			 *
			 * @since 1.0.0
			 *
			 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
			 */
			do_action( 'pressprimer_assignment_after_submission_form', $assignment );
			?>

		<?php else : ?>

			<div class="ppa-notice ppa-notice-info" role="status">
				<p class="ppa-notice-message">
					<?php esc_html_e( 'This assignment is not currently accepting submissions.', 'pressprimer-assignment' ); ?>
				</p>
			</div>

		<?php endif; ?>

	</div>
</div>
