<?php
/**
 * Template: Text Editor
 *
 * Displays a TinyMCE rich text editor for text-based submissions,
 * with word count display, save status indicator, and action buttons.
 *
 * This template can be overridden by copying it to:
 * yourtheme/pressprimer-assignment/assignment/text-editor.php
 *
 * Available variables:
 *
 * @var PressPrimer_Assignment_Assignment          $assignment       Assignment instance.
 * @var bool                                        $is_resubmission  Whether this is a resubmission.
 * @var PressPrimer_Assignment_Submission|null      $draft_submission Existing draft submission or null.
 * @var PressPrimer_Assignment_Assignment_Renderer  $this             Renderer instance.
 *
 * @package PressPrimer_Assignment
 * @subpackage Templates
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$draft_content    = $draft_submission && null !== $draft_submission->text_content ? $draft_submission->text_content : '';
$draft_word_count = $draft_submission ? (int) $draft_submission->word_count : 0;
$draft_char_count = mb_strlen( wp_strip_all_tags( $draft_content ) );
$draft_id         = $draft_submission ? (int) $draft_submission->id : 0;

// Resubmission info for confirmation modal.
$can_resubmit_data       = $assignment->allow_resubmission ? '1' : '0';
$resubmissions_remaining = 0;
if ( $assignment->allow_resubmission && $is_resubmission ) {
	$ppa_user_id     = get_current_user_id();
	$ppa_submissions = PressPrimer_Assignment_Submission::find(
		[
			'where'    => [
				'assignment_id' => $assignment->id,
				'user_id'       => $ppa_user_id,
			],
			'order_by' => 'submission_number',
			'order'    => 'DESC',
			'limit'    => 1,
		]
	);
	if ( ! empty( $ppa_submissions ) ) {
		$resubmissions_remaining = max( 0, ( $assignment->max_resubmissions + 1 ) - $ppa_submissions[0]->submission_number );
	}
}
?>

<section class="ppa-text-editor-form" aria-label="<?php esc_attr_e( 'Text submission', 'pressprimer-assignment' ); ?>">
	<h3 class="ppa-form-heading">
		<?php esc_html_e( 'Assignment Editor', 'pressprimer-assignment' ); ?>
	</h3>

	<p class="ppa-editor-instructions">
		<?php esc_html_e( 'Write your submission below. Your work is automatically saved as a draft, so you can leave and come back to continue later.', 'pressprimer-assignment' ); ?>
	</p>

	<form id="ppa-text-submission-form" method="post"
		data-can-resubmit="<?php echo esc_attr( $can_resubmit_data ); ?>"
		data-resubmissions-remaining="<?php echo esc_attr( $resubmissions_remaining ); ?>"
		data-assignment-title="<?php echo esc_attr( $assignment->title ); ?>">
		<?php wp_nonce_field( 'pressprimer_assignment_save_text_submission', 'pressprimer_assignment_text_nonce' ); ?>
		<input type="hidden" name="assignment_id" value="<?php echo esc_attr( $assignment->id ); ?>">
		<input type="hidden" name="submission_id" value="<?php echo esc_attr( $draft_id ); ?>">

		<div class="ppa-editor-container">
			<?php
			wp_editor(
				$draft_content,
				'ppa_text_content',
				[
					'textarea_name' => 'text_content',
					'textarea_rows' => 30,
					'media_buttons' => false,
					'teeny'         => false,
					'quicktags'     => false,
					'tinymce'       => [
						'toolbar1'              => 'bold,italic,underline,|,bullist,numlist,|,formatselect,|,undo,redo',
						'toolbar2'              => '',
						'block_formats'         => 'Paragraph=p;Heading 2=h2;Heading 3=h3',
						'paste_as_text'         => true,
						'entity_encoding'       => 'raw',
						'resize'                => true,
						'wp_autoresize_on'      => true,
						'autoresize_min_height' => 400,
						'autoresize_max_height' => 750,
					],
				]
			);
			?>
		</div>

		<div class="ppa-editor-footer">
			<div class="ppa-editor-counts" aria-live="polite">
				<span class="ppa-word-count">
					<span id="ppa-word-count-value"><?php echo esc_html( $draft_word_count ); ?></span>
					<?php esc_html_e( 'words', 'pressprimer-assignment' ); ?>
				</span>
				<span class="ppa-count-separator" aria-hidden="true">&middot;</span>
				<span class="ppa-char-count">
					<span id="ppa-char-count-value"><?php echo esc_html( $draft_char_count ); ?></span>
					<?php esc_html_e( 'characters', 'pressprimer-assignment' ); ?>
				</span>
			</div>

			<div class="ppa-save-status" id="ppa-save-status" aria-live="polite">
				<?php if ( $draft_submission ) : ?>
					<span class="ppa-status-saved">
						<span class="dashicons dashicons-saved" aria-hidden="true"></span>
						<?php esc_html_e( 'Draft saved', 'pressprimer-assignment' ); ?>
					</span>
				<?php endif; ?>
			</div>
		</div>

		<div class="ppa-editor-actions">
			<button type="button" class="ppa-button ppa-button-secondary" id="ppa-save-draft-btn">
				<?php esc_html_e( 'Save Draft', 'pressprimer-assignment' ); ?>
			</button>

			<button type="button" class="ppa-button ppa-button-primary ppa-submit-text-btn" id="ppa-submit-text-btn" disabled>
				<?php esc_html_e( 'Submit Assignment', 'pressprimer-assignment' ); ?>
			</button>
		</div>
	</form>
</section>
