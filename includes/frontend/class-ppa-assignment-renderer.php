<?php
/**
 * Assignment renderer
 *
 * Handles rendering of the assignment frontend display including
 * assignment info, submission form, and submission status.
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
 * Assignment renderer class
 *
 * Renders assignment details, submission forms, and submission
 * status displays for the frontend shortcode.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Assignment_Renderer {

	/**
	 * Get allowed HTML tags for assignment output
	 *
	 * Returns an array of allowed HTML tags and attributes for use with wp_kses().
	 * Extends wp_kses_post allowed tags to include form elements required for
	 * the submission interface.
	 *
	 * @since 1.0.0
	 *
	 * @return array Allowed HTML tags and attributes.
	 */
	public function get_allowed_html() {
		// Start with post kses allowed tags.
		$allowed = wp_kses_allowed_html( 'post' );

		// Add form elements for submission functionality.
		$allowed['form'] = [
			'id'      => true,
			'class'   => true,
			'method'  => true,
			'enctype' => true,
			'action'  => true,
			'data-*'  => true,
		];

		$allowed['input'] = [
			'type'        => true,
			'id'          => true,
			'name'        => true,
			'value'       => true,
			'class'       => true,
			'checked'     => true,
			'disabled'    => true,
			'readonly'    => true,
			'required'    => true,
			'multiple'    => true,
			'accept'      => true,
			'tabindex'    => true,
			'placeholder' => true,
			'maxlength'   => true,
			'data-*'      => true,
			'aria-*'      => true,
		];

		$allowed['label'] = [
			'for'   => true,
			'class' => true,
			'id'    => true,
		];

		$allowed['textarea'] = [
			'id'          => true,
			'name'        => true,
			'class'       => true,
			'rows'        => true,
			'cols'        => true,
			'maxlength'   => true,
			'placeholder' => true,
			'required'    => true,
			'disabled'    => true,
			'readonly'    => true,
			'aria-*'      => true,
		];

		$allowed['button'] = [
			'type'     => true,
			'class'    => true,
			'id'       => true,
			'name'     => true,
			'value'    => true,
			'disabled' => true,
			'data-*'   => true,
			'aria-*'   => true,
			'tabindex' => true,
		];

		$allowed['select'] = [
			'id'       => true,
			'name'     => true,
			'class'    => true,
			'required' => true,
			'disabled' => true,
			'aria-*'   => true,
		];

		$allowed['option'] = [
			'value'    => true,
			'selected' => true,
		];

		// Ensure div and span have data/aria attributes.
		if ( ! isset( $allowed['div'] ) ) {
			$allowed['div'] = [];
		}
		$allowed['div']['data-*']   = true;
		$allowed['div']['aria-*']   = true;
		$allowed['div']['tabindex'] = true;
		$allowed['div']['role']     = true;

		if ( ! isset( $allowed['span'] ) ) {
			$allowed['span'] = [];
		}
		$allowed['span']['data-*'] = true;
		$allowed['span']['aria-*'] = true;
		$allowed['span']['role']   = true;

		// Add section, header, details, summary for semantic HTML.
		$allowed['section'] = [
			'class'      => true,
			'id'         => true,
			'aria-*'     => true,
			'role'       => true,
			'aria-label' => true,
		];

		$allowed['header'] = [
			'class' => true,
			'id'    => true,
		];

		$allowed['details'] = [
			'class' => true,
			'open'  => true,
		];

		$allowed['summary'] = [
			'class' => true,
		];

		$allowed['time'] = [
			'datetime' => true,
			'class'    => true,
		];

		return $allowed;
	}

	/**
	 * Render an assignment
	 *
	 * Main entry point for rendering an assignment. Determines the
	 * display mode based on user state and delegates to sub-renderers.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
	 * @param array                             $display    Display options from shortcode.
	 * @return string Rendered HTML.
	 */
	public function render( $assignment, $display = [] ) {
		$defaults = [
			'show_description'  => true,
			'show_instructions' => true,
			'show_max_points'   => true,
			'show_file_info'    => true,
		];

		$display = wp_parse_args( $display, $defaults );

		$user_id      = get_current_user_id();
		$is_logged_in = $user_id > 0;
		$theme        = $this->resolve_theme( $assignment );
		$theme_class  = 'ppa-theme-' . sanitize_html_class( $theme );

		// Get user's current submission for this assignment.
		$user_submission = null;
		$can_submit      = false;
		$can_resubmit    = false;

		if ( $is_logged_in ) {
			// Check if a specific submission was requested (e.g., from My Submissions "View" link).
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display, no state change.
			$requested_submission_id = isset( $_GET['pressprimer_assignment_submission'] ) ? absint( wp_unslash( $_GET['pressprimer_assignment_submission'] ) ) : 0;

			if ( $requested_submission_id > 0 ) {
				$user_submission = $this->get_specific_submission( $requested_submission_id, $assignment->id, $user_id );
			}

			// Fall back to the latest submission if no specific one was requested or found.
			if ( null === $user_submission ) {
				$user_submission = $this->get_user_submission( $assignment->id, $user_id );
			}

			$can_submit   = $this->can_user_submit( $assignment, $user_id, $user_submission );
			$can_resubmit = $this->can_user_resubmit( $assignment, $user_id, $user_submission );
		}

		// Brand filter values — available in templates for white-label support.
		/**
		 * Filter the brand name used in student-facing strings.
		 *
		 * Enterprise addon hooks this to return the configured organization name.
		 *
		 * @since 2.0.0
		 *
		 * @param string $name Default: site name from get_bloginfo('name').
		 */
		$brand_name = apply_filters( 'pressprimer_assignment_brand_name', get_bloginfo( 'name' ) );

		/**
		 * Filter the brand logo URL used in student-facing pages.
		 *
		 * Enterprise addon hooks this to return the configured logo URL.
		 *
		 * @since 2.0.0
		 *
		 * @param string $url Default: the PressPrimer logo URL bundled with the plugin.
		 */
		$brand_logo_url = apply_filters(
			'pressprimer_assignment_brand_logo_url',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/images/logo.png'
		);

		/**
		 * Filter the header background color used in assignment headers.
		 *
		 * Enterprise addon hooks this to return a custom hex color.
		 *
		 * @since 2.0.0
		 *
		 * @param string $color Default: '#334155' (the PressPrimer dark slate).
		 */
		$header_bg_color = apply_filters( 'pressprimer_assignment_header_bg_color', '#334155' );

		// Start output buffering.
		ob_start();

		// Load the main template.
		$template_path = $this->get_template_path( 'assignment/single.php' );

		if ( $template_path ) {
			include $template_path;
		}

		$html = ob_get_clean();

		/**
		 * Filters the rendered assignment output.
		 *
		 * @since 1.0.0
		 *
		 * @param string                               $html       Rendered HTML.
		 * @param PressPrimer_Assignment_Assignment     $assignment Assignment instance.
		 * @param array                                $display    Display options.
		 */
		$html = apply_filters( 'pressprimer_assignment_render_output', $html, $assignment, $display );

		return wp_kses( $html, $this->get_allowed_html() );
	}

	/**
	 * Render assignment info section
	 *
	 * Displays assignment title, description, instructions,
	 * grading guidelines, and meta information.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
	 * @param array                             $display    Display options.
	 * @return string Rendered HTML.
	 */
	public function render_assignment_info( $assignment, $display = [] ) {
		ob_start();

		// Title.
		?>
		<header class="ppa-assignment-header">
			<h2 class="ppa-assignment-title"><?php echo esc_html( $assignment->title ); ?></h2>
		</header>
		<?php

		// Description.
		if ( ! empty( $display['show_description'] ) && ! empty( $assignment->description ) ) {
			?>
			<div class="ppa-assignment-description">
				<div class="ppa-assignment-description-text"><?php echo wp_kses_post( wpautop( $assignment->description ) ); ?></div>
			</div>
			<?php
		}

		// Meta (points, passing score, file info).
		if ( $this->should_show_meta( $assignment, $display ) ) {
			$this->render_meta( $assignment, $display );
		}

		// Instructions.
		if ( ! empty( $display['show_instructions'] ) && ! empty( $assignment->instructions ) ) {
			?>
			<div class="ppa-assignment-instructions">
				<h3 class="ppa-instructions-heading"><?php esc_html_e( 'Instructions', 'pressprimer-assignment' ); ?></h3>
				<div class="ppa-instructions-content"><?php echo wp_kses_post( wpautop( $assignment->instructions ) ); ?></div>
			</div>
			<?php
		}

		// Grading guidelines.
		if ( ! empty( $assignment->grading_guidelines ) ) {
			?>
			<div class="ppa-assignment-instructions">
				<h3 class="ppa-instructions-heading"><?php esc_html_e( 'How You\'ll Be Graded', 'pressprimer-assignment' ); ?></h3>
				<div class="ppa-instructions-content"><?php echo wp_kses_post( wpautop( $assignment->grading_guidelines ) ); ?></div>
			</div>
			<?php
		}

		return ob_get_clean();
	}

	/**
	 * Render submission form section
	 *
	 * Routes to the appropriate template based on the assignment's
	 * submission_type setting:
	 * - 'file': shows file upload form
	 * - 'text': shows text editor (placeholder until Phase 5)
	 * - 'either': shows type selector for student to choose
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Assignment $assignment      Assignment instance.
	 * @param bool                              $is_resubmission Whether this is a resubmission.
	 * @return string Rendered HTML.
	 */
	public function render_submission_form( $assignment, $is_resubmission = false ) {
		ob_start();

		if ( 'either' === $assignment->submission_type ) {
			// Check if user has an existing text draft (to auto-open the text panel).
			$draft_submission = $this->get_text_draft( $assignment->id );
			$has_text_draft   = $draft_submission && ! empty( $draft_submission->text_content );

			// Also check for existing file draft.
			$has_file_draft = false;
			if ( ! $has_text_draft ) {
				$file_draft     = $this->get_file_draft( $assignment->id );
				$has_file_draft = $file_draft && count( $file_draft->get_files() ) > 0;
			}

			// Show type selector — student chooses between file and text.
			$template_path = $this->get_template_path( 'assignment/submission-type-selector.php' );

			if ( $template_path ) {
				include $template_path;
			}

			// Also include the file upload form (hidden initially, revealed by JS).
			$allowed_types = $assignment->get_allowed_file_types();
			$accept_string = $this->build_accept_string( $allowed_types );

			$form_template = $this->get_template_path( 'assignment/submission-form.php' );
			if ( $form_template ) {
				echo '<div class="ppa-submission-type-panel ppa-submission-type-file ppa-hidden">';
				include $form_template;
				echo '</div>';
			}

			// Text editor panel.
			$draft_submission = $this->get_text_draft( $assignment->id );
			$editor_template  = $this->get_template_path( 'assignment/text-editor.php' );
			if ( $editor_template ) {
				echo '<div class="ppa-submission-type-panel ppa-submission-type-text ppa-hidden">';
				include $editor_template;
				echo '</div>';
			}
		} elseif ( 'text' === $assignment->submission_type ) {
			// Text-only submission.
			$draft_submission = $this->get_text_draft( $assignment->id );
			$template_path    = $this->get_template_path( 'assignment/text-editor.php' );

			if ( $template_path ) {
				include $template_path;
			}
		} else {
			// Default: file upload form.
			$allowed_types = $assignment->get_allowed_file_types();
			$accept_string = $this->build_accept_string( $allowed_types );

			$template_path = $this->get_template_path( 'assignment/submission-form.php' );

			if ( $template_path ) {
				include $template_path;
			}
		}

		return ob_get_clean();
	}

	/**
	 * Render user submission status
	 *
	 * Displays the user's submission details including status,
	 * files, grade, and feedback.
	 * Loaded via the submission-status.php template.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Submission $submission Submission instance.
	 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
	 * @return string Rendered HTML.
	 */
	public function render_user_submission( $submission, $assignment ) {
		$files = $submission->get_files();

		// Format dates.
		$formatted_submitted_date = '';
		if ( ! empty( $submission->submitted_at ) ) {
			$formatted_submitted_date = date_i18n(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				strtotime( $submission->submitted_at )
			);
		}

		$formatted_graded_date = '';
		if ( ! empty( $submission->graded_at ) ) {
			$formatted_graded_date = date_i18n(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				strtotime( $submission->graded_at )
			);
		}

		$grader_display_name = '';
		if ( ! empty( $submission->grader_id ) ) {
			$grader = get_userdata( (int) $submission->grader_id );
			if ( $grader ) {
				$grader_display_name = $grader->display_name;
			}
		}

		// Check resubmission.
		$can_resubmit            = $this->can_user_resubmit( $assignment, $submission->user_id, $submission );
		$resubmissions_remaining = 0;

		if ( $can_resubmit && $assignment->allow_resubmission ) {
			$resubmissions_remaining = max( 0, $assignment->max_resubmissions - $submission->submission_number + 1 );
		}

		// Get previous submissions for this user/assignment.
		$previous_submissions = [];
		if ( $submission->submission_number > 1 ) {
			$all_submissions = PressPrimer_Assignment_Submission::get_for_assignment(
				$assignment->id,
				[
					'where' => [
						'assignment_id' => $assignment->id,
						'user_id'       => $submission->user_id,
					],
				]
			);

			foreach ( $all_submissions as $prev ) {
				if ( $prev->id !== $submission->id ) {
					$previous_submissions[] = $prev;
				}
			}
		}

		ob_start();

		$template_path = $this->get_template_path( 'assignment/submission-status.php' );

		if ( $template_path ) {
			include $template_path;
		}

		return ob_get_clean();
	}

	/**
	 * Check whether meta section should display
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
	 * @param array                             $display    Display options.
	 * @return bool True if meta should display.
	 */
	private function should_show_meta( $assignment, $display ) {
		if ( ! empty( $display['show_max_points'] ) && $assignment->max_points > 0 ) {
			return true;
		}

		// Only show file info for assignments that accept file uploads.
		if ( ! empty( $display['show_file_info'] ) && $assignment->accepts_file_upload() ) {
			return true;
		}

		return false;
	}

	/**
	 * Render assignment meta section
	 *
	 * Displays points, passing score, and file requirements.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
	 * @param array                             $display    Display options.
	 */
	private function render_meta( $assignment, $display ) {
		?>
		<div class="ppa-assignment-meta">
			<?php if ( ! empty( $display['show_max_points'] ) && $assignment->max_points > 0 ) : ?>
				<div class="ppa-meta-item">
					<span class="ppa-meta-label"><?php esc_html_e( 'Points', 'pressprimer-assignment' ); ?></span>
					<span class="ppa-meta-value"><?php echo esc_html( number_format_i18n( $assignment->max_points, 0 ) ); ?></span>
				</div>

				<?php if ( $assignment->passing_score > 0 ) : ?>
					<div class="ppa-meta-item">
						<span class="ppa-meta-label"><?php esc_html_e( 'Passing Score', 'pressprimer-assignment' ); ?></span>
						<span class="ppa-meta-value"><?php echo esc_html( number_format_i18n( $assignment->passing_score, 0 ) ); ?></span>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( ! empty( $display['show_file_info'] ) && $assignment->accepts_file_upload() ) : ?>
				<div class="ppa-meta-item">
					<span class="ppa-meta-label"><?php esc_html_e( 'Max File Size', 'pressprimer-assignment' ); ?></span>
					<span class="ppa-meta-value"><?php echo esc_html( size_format( $assignment->max_file_size ) ); ?></span>
				</div>

				<div class="ppa-meta-item">
					<span class="ppa-meta-label"><?php esc_html_e( 'Max Files', 'pressprimer-assignment' ); ?></span>
					<span class="ppa-meta-value"><?php echo esc_html( number_format_i18n( $assignment->max_files, 0 ) ); ?></span>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get the user's current submission for an assignment
	 *
	 * Returns the most recent non-draft submission, or the current
	 * draft if no submitted version exists.
	 *
	 * @since 1.0.0
	 *
	 * @param int $assignment_id Assignment ID.
	 * @param int $user_id       User ID.
	 * @return PressPrimer_Assignment_Submission|null Submission or null.
	 */
	private function get_user_submission( $assignment_id, $user_id ) {
		$submissions = PressPrimer_Assignment_Submission::find(
			[
				'where'    => [
					'assignment_id' => $assignment_id,
					'user_id'       => $user_id,
				],
				'order_by' => 'submission_number',
				'order'    => 'DESC',
				'limit'    => 1,
			]
		);

		return ! empty( $submissions ) ? $submissions[0] : null;
	}

	/**
	 * Get a specific submission by ID
	 *
	 * Validates that the submission belongs to the given assignment and user
	 * to prevent unauthorized access.
	 *
	 * @since 1.0.0
	 *
	 * @param int $submission_id Submission ID.
	 * @param int $assignment_id Assignment ID.
	 * @param int $user_id       User ID.
	 * @return PressPrimer_Assignment_Submission|null Submission or null.
	 */
	private function get_specific_submission( $submission_id, $assignment_id, $user_id ) {
		$submission = PressPrimer_Assignment_Submission::get( $submission_id );

		if ( ! $submission ) {
			return null;
		}

		// Verify the submission belongs to this assignment and this user.
		if ( (int) $submission->assignment_id !== (int) $assignment_id || (int) $submission->user_id !== (int) $user_id ) {
			return null;
		}

		return $submission;
	}

	/**
	 * Check if user can submit to this assignment
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Assignment      $assignment      Assignment instance.
	 * @param int                                    $user_id         User ID.
	 * @param PressPrimer_Assignment_Submission|null $user_submission Current submission or null.
	 * @return bool True if user can submit.
	 */
	private function can_user_submit( $assignment, $user_id, $user_submission ) {
		// Assignment must be published.
		if ( ! $assignment->accepts_submissions() ) {
			return false;
		}

		// No existing submission means user can submit.
		if ( null === $user_submission ) {
			return true;
		}

		// Draft submissions can be completed.
		if ( PressPrimer_Assignment_Submission::STATUS_DRAFT === $user_submission->status ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if user can resubmit to this assignment
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Assignment      $assignment      Assignment instance.
	 * @param int                                    $user_id         User ID.
	 * @param PressPrimer_Assignment_Submission|null $user_submission Current submission or null.
	 * @return bool True if user can resubmit.
	 */
	private function can_user_resubmit( $assignment, $user_id, $user_submission ) {
		// Must have resubmission enabled.
		if ( ! $assignment->allow_resubmission ) {
			return false;
		}

		// Assignment must be published.
		if ( ! $assignment->accepts_submissions() ) {
			return false;
		}

		// Must have an existing non-draft submission.
		if ( null === $user_submission || PressPrimer_Assignment_Submission::STATUS_DRAFT === $user_submission->status ) {
			return false;
		}

		// Check resubmission limit.
		if ( $user_submission->submission_number >= $assignment->max_resubmissions + 1 ) {
			return false;
		}

		/**
		 * Filters whether the user can resubmit.
		 *
		 * @since 1.0.0
		 *
		 * @param bool                                  $can_resubmit   Whether user can resubmit.
		 * @param PressPrimer_Assignment_Assignment     $assignment     Assignment instance.
		 * @param PressPrimer_Assignment_Submission     $user_submission Current submission.
		 * @param int                                   $user_id        User ID.
		 */
		return apply_filters(
			'pressprimer_assignment_can_resubmit',
			true,
			$assignment,
			$user_submission,
			$user_id
		);
	}

	/**
	 * Build the accept attribute string for file input
	 *
	 * Converts file extensions to MIME types for the accept attribute.
	 *
	 * @since 1.0.0
	 *
	 * @param array $allowed_types Array of file extensions.
	 * @return string Accept attribute value.
	 */
	private function build_accept_string( $allowed_types ) {
		$accept = [];

		foreach ( $allowed_types as $ext ) {
			$accept[] = '.' . ltrim( $ext, '.' );
		}

		return implode( ',', $accept );
	}

	/**
	 * Get status label for display
	 *
	 * Returns a human-readable label for a submission status.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Submission status.
	 * @return string Translated status label.
	 */
	public function get_status_label( $status ) {
		$labels = [
			'draft'     => __( 'Draft', 'pressprimer-assignment' ),
			'submitted' => __( 'Submitted - Awaiting Review', 'pressprimer-assignment' ),
			'grading'   => __( 'Being Reviewed', 'pressprimer-assignment' ),
			'graded'    => __( 'Graded', 'pressprimer-assignment' ),
			'returned'  => __( 'Graded & Returned', 'pressprimer-assignment' ),
		];

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Get existing text draft for the current user
	 *
	 * Looks for an existing draft submission for the current user
	 * and assignment. Returns null if none exists.
	 *
	 * @since 1.0.0
	 *
	 * @param int $assignment_id Assignment ID.
	 * @return PressPrimer_Assignment_Submission|null Draft submission or null.
	 */
	private function get_text_draft( $assignment_id ) {
		$user_id = get_current_user_id();
		if ( 0 === $user_id ) {
			return null;
		}

		$drafts = PressPrimer_Assignment_Submission::find(
			[
				'where'    => [
					'assignment_id' => $assignment_id,
					'user_id'       => $user_id,
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
	 * Get existing file draft for the current user
	 *
	 * Looks for an existing draft submission that has uploaded files.
	 *
	 * @since 1.0.0
	 *
	 * @param int $assignment_id Assignment ID.
	 * @return PressPrimer_Assignment_Submission|null Draft submission or null.
	 */
	private function get_file_draft( $assignment_id ) {
		$user_id = get_current_user_id();
		if ( 0 === $user_id ) {
			return null;
		}

		$drafts = PressPrimer_Assignment_Submission::find(
			[
				'where'    => [
					'assignment_id' => $assignment_id,
					'user_id'       => $user_id,
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
	 * Check if current user has a draft submission for an assignment
	 *
	 * Used by the single template to prioritize showing the form
	 * when a draft exists alongside a completed submission.
	 *
	 * @since 1.0.0
	 *
	 * @param int $assignment_id Assignment ID.
	 * @return bool True if user has an active draft.
	 */
	public function has_draft( $assignment_id ) {
		$text_draft = $this->get_text_draft( $assignment_id );
		if ( $text_draft ) {
			return true;
		}

		$file_draft = $this->get_file_draft( $assignment_id );
		if ( $file_draft && count( $file_draft->get_files() ) > 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Resolve the display theme for an assignment
	 *
	 * Follows the resolution chain:
	 * 1. Per-assignment theme (if set and not 'default')
	 * 2. Global default theme from settings
	 * 3. Hardcoded 'default' fallback
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
	 * @return string Validated theme slug.
	 */
	private function resolve_theme( $assignment ) {
		$valid_themes = [ 'default', 'modern', 'minimal' ];

		// Check per-assignment theme first.
		if ( ! empty( $assignment->theme ) && 'default' !== $assignment->theme ) {
			if ( in_array( $assignment->theme, $valid_themes, true ) ) {
				return $assignment->theme;
			}
		}

		// Fall back to global default.
		$global_theme = get_option( 'pressprimer_assignment_frontend_theme', 'default' );

		if ( in_array( $global_theme, $valid_themes, true ) ) {
			return $global_theme;
		}

		return 'default';
	}

	/**
	 * Get template file path
	 *
	 * Looks for template override in theme first, then falls back
	 * to the plugin template.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template_name Template name relative to templates directory.
	 * @return string|false Template path or false if not found.
	 */
	private function get_template_path( $template_name ) {
		// Check theme override first.
		$theme_path = get_stylesheet_directory() . '/pressprimer-assignment/' . $template_name;
		if ( file_exists( $theme_path ) ) {
			return $theme_path;
		}

		// Check parent theme.
		$parent_path = get_template_directory() . '/pressprimer-assignment/' . $template_name;
		if ( $parent_path !== $theme_path && file_exists( $parent_path ) ) {
			return $parent_path;
		}

		// Plugin template.
		$plugin_path = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'templates/' . $template_name;
		if ( file_exists( $plugin_path ) ) {
			return $plugin_path;
		}

		return false;
	}
}
