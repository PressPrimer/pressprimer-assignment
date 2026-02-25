<?php
/**
 * Template: Submission Type Selector
 *
 * Displayed when an assignment allows either file upload or text submission.
 * Students choose their preferred submission method before proceeding.
 *
 * This template can be overridden by copying it to:
 * yourtheme/pressprimer-assignment/assignment/submission-type-selector.php
 *
 * Available variables:
 *
 * @var PressPrimer_Assignment_Assignment          $assignment      Assignment instance.
 * @var bool                                        $is_resubmission Whether this is a resubmission.
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

<section class="ppa-submission-type-selector"
	aria-label="<?php esc_attr_e( 'Choose submission method', 'pressprimer-assignment' ); ?>"
	<?php if ( ! empty( $has_text_draft ) ) : ?>
		data-auto-type="text"
	<?php elseif ( ! empty( $has_file_draft ) ) : ?>
		data-auto-type="file"
	<?php endif; ?>
>
	<h3 class="ppa-form-heading">
		<?php
		if ( $is_resubmission ) {
			esc_html_e( 'Submit Again', 'pressprimer-assignment' );
		} else {
			esc_html_e( 'Submit Your Work', 'pressprimer-assignment' );
		}
		?>
	</h3>

	<p class="ppa-type-selector-description">
		<?php esc_html_e( 'Choose how you would like to submit your work:', 'pressprimer-assignment' ); ?>
	</p>

	<div class="ppa-type-selector-options" role="group" aria-label="<?php esc_attr_e( 'Submission type options', 'pressprimer-assignment' ); ?>">
		<button
			type="button"
			class="ppa-type-option ppa-type-option-file"
			data-submission-type="file"
			data-assignment-id="<?php echo esc_attr( $assignment->id ); ?>"
		>
			<span class="ppa-type-option-icon" aria-hidden="true">
				<span class="dashicons dashicons-upload"></span>
			</span>
			<span class="ppa-type-option-title">
				<?php esc_html_e( 'Upload Files', 'pressprimer-assignment' ); ?>
			</span>
			<span class="ppa-type-option-description">
				<?php
				printf(
					/* translators: %s: comma-separated list of file types */
					esc_html__( 'Upload documents (%s)', 'pressprimer-assignment' ),
					esc_html( implode( ', ', array_map( 'strtoupper', $assignment->get_allowed_file_types() ) ) )
				);
				?>
			</span>
		</button>

		<button
			type="button"
			class="ppa-type-option ppa-type-option-text"
			data-submission-type="text"
			data-assignment-id="<?php echo esc_attr( $assignment->id ); ?>"
		>
			<span class="ppa-type-option-icon" aria-hidden="true">
				<span class="dashicons dashicons-edit"></span>
			</span>
			<span class="ppa-type-option-title">
				<?php esc_html_e( 'Write Online', 'pressprimer-assignment' ); ?>
			</span>
			<span class="ppa-type-option-description">
				<?php esc_html_e( 'Type your submission using the text editor', 'pressprimer-assignment' ); ?>
			</span>
		</button>
	</div>
</section>
