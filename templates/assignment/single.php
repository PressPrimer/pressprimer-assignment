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

		// Check if a draft exists alongside a non-draft submission.
		// Drafts take priority so the user returns to their in-progress work.
		$ppa_has_active_draft = $is_logged_in
			&& $user_submission
			&& PressPrimer_Assignment_Submission::STATUS_DRAFT !== $user_submission->status
			&& $this->has_draft( $assignment->id );
		?>

		<?php if ( ! $is_logged_in ) : ?>

			<?php
			$ppa_login_url = wp_login_url( get_permalink() );

			/** This filter is documented in includes/frontend/class-ppa-shortcodes.php */
			$ppa_login_url = apply_filters( 'pressprimer_assignment_login_url', $ppa_login_url );
			?>
			<div class="ppa-login-required">
				<div class="ppa-login-required-icon" aria-hidden="true">&#x1f512;</div>
				<div class="ppa-login-required-message">
					<p><?php esc_html_e( 'You need to be logged in to submit this assignment. Please log in to continue.', 'pressprimer-assignment' ); ?></p>
				</div>
				<a href="<?php echo esc_url( $ppa_login_url ); ?>" class="ppa-button ppa-button-primary ppa-button-large ppa-login-button">
					<span class="ppa-button-icon" aria-hidden="true">&#x1f510;</span>
					<?php esc_html_e( 'Log In to Submit', 'pressprimer-assignment' ); ?>
				</a>
				<?php if ( get_option( 'users_can_register' ) ) : ?>
					<?php
					$ppa_register_url = wp_registration_url();

					/** This filter is documented in includes/frontend/class-ppa-shortcodes.php */
					$ppa_register_url = apply_filters( 'pressprimer_assignment_register_url', $ppa_register_url );
					?>
					<p class="ppa-register-prompt">
						<?php
						printf(
							/* translators: %s: registration link */
							esc_html__( "Don't have an account? %s", 'pressprimer-assignment' ),
							'<a href="' . esc_url( $ppa_register_url ) . '">' . esc_html__( 'Register here', 'pressprimer-assignment' ) . '</a>'
						);
						?>
					</p>
				<?php endif; ?>
			</div>

		<?php elseif ( $ppa_has_active_draft ) : ?>

			<?php
			/**
			 * Fires before submission form is rendered (draft resume).
			 *
			 * @since 1.0.0
			 *
			 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
			 */
			do_action( 'pressprimer_assignment_before_submission_form', $assignment );

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in render_submission_form().
			echo $this->render_submission_form( $assignment, true );

			/**
			 * Fires after submission form is rendered (draft resume).
			 *
			 * @since 1.0.0
			 *
			 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
			 */
			do_action( 'pressprimer_assignment_after_submission_form', $assignment );
			?>

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

			// When resubmission is allowed, include the form (hidden) so the
			// "Submit Again" button can reveal it via JavaScript.
			if ( $can_resubmit ) :
				?>
				<div class="ppa-resubmission-form-wrapper ppa-hidden">
					<?php
					/**
					 * Fires before submission form is rendered (resubmission).
					 *
					 * @since 1.0.0
					 *
					 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
					 */
					do_action( 'pressprimer_assignment_before_submission_form', $assignment );

					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in render_submission_form().
					echo $this->render_submission_form( $assignment, true );

					/**
					 * Fires after submission form is rendered (resubmission).
					 *
					 * @since 1.0.0
					 *
					 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
					 */
					do_action( 'pressprimer_assignment_after_submission_form', $assignment );
					?>
				</div>
				<?php
			endif;
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
