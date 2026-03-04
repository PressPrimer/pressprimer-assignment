<?php
/**
 * Email notification service
 *
 * Handles sending email notifications for assignment submissions
 * and grading. Uses the same HTML email template pattern as
 * PressPrimer Quiz for visual consistency across the plugin suite.
 *
 * Email templates use {token} placeholders that are replaced with
 * real values at send time. Templates are customizable via the
 * Settings > Email tab.
 *
 * @package PressPrimer_Assignment
 * @subpackage Services
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email service class
 *
 * Provides static methods for sending notification emails to
 * students and administrators. Email templates match the Quiz
 * plugin's HTML structure for a consistent experience.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Email_Service {

	/**
	 * Send submission confirmation to student
	 *
	 * Notifies the student that their assignment submission was received.
	 * Uses a customizable template with token placeholders.
	 *
	 * @since 1.0.0
	 *
	 * @param int $submission_id Submission ID.
	 * @return bool Whether email was sent.
	 */
	public static function send_submission_confirmation( $submission_id ) {
		$settings = self::get_settings();

		if ( ! $settings['student_submission_confirmation'] ) {
			return false;
		}

		$submission = PressPrimer_Assignment_Submission::get( $submission_id );
		if ( ! $submission ) {
			return false;
		}

		$assignment = PressPrimer_Assignment_Assignment::get( $submission->assignment_id );
		if ( ! $assignment ) {
			return false;
		}

		$user = get_userdata( $submission->user_id );
		if ( ! $user || ! $user->user_email ) {
			return false;
		}

		// Get templates from settings.
		$subject_template = ! empty( $settings['email_submission_subject'] )
			? $settings['email_submission_subject']
			: self::get_default_submission_subject();
		$body_template    = ! empty( $settings['email_submission_body'] )
			? $settings['email_submission_body']
			: self::get_default_submission_body();

		$first_name = self::get_first_name( $user );
		$view_url   = self::get_submission_view_url( $submission );

		// Build token replacements.
		$tokens = [
			'{first_name}'       => $first_name,
			'{student_name}'     => $user->display_name,
			'{assignment_title}' => $assignment->title,
			'{date}'             => wp_date(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				strtotime( $submission->submitted_at )
			),
			'{site_name}'        => get_bloginfo( 'name' ),
			'{view_url}'         => self::build_button_html(
				$view_url,
				__( 'View Submission Status', 'pressprimer-assignment' )
			),
		];

		// Replace tokens.
		$subject   = str_replace( array_keys( $tokens ), array_values( $tokens ), $subject_template );
		$body_text = str_replace( array_keys( $tokens ), array_values( $tokens ), $body_template );

		$html_body = self::build_html_email_from_template( $body_text );

		return self::send( $user->user_email, $subject, $html_body );
	}

	/**
	 * Send grade notification to student
	 *
	 * Notifies the student that their assignment has been graded
	 * and returned. Uses a customizable template with token placeholders.
	 *
	 * @since 1.0.0
	 *
	 * @param int $submission_id Submission ID.
	 * @return bool Whether email was sent.
	 */
	public static function send_grade_notification( $submission_id ) {
		$settings = self::get_settings();

		if ( ! $settings['student_grade_notification'] ) {
			return false;
		}

		$submission = PressPrimer_Assignment_Submission::get( $submission_id );
		if ( ! $submission || PressPrimer_Assignment_Submission::STATUS_RETURNED !== $submission->status ) {
			return false;
		}

		$assignment = PressPrimer_Assignment_Assignment::get( $submission->assignment_id );
		if ( ! $assignment ) {
			return false;
		}

		$user = get_userdata( $submission->user_id );
		if ( ! $user || ! $user->user_email ) {
			return false;
		}

		// Get templates from settings.
		$subject_template = ! empty( $settings['email_grade_subject'] )
			? $settings['email_grade_subject']
			: self::get_default_grade_subject();
		$body_template    = ! empty( $settings['email_grade_body'] )
			? $settings['email_grade_body']
			: self::get_default_grade_body();

		$first_name = self::get_first_name( $user );
		$view_url   = self::get_submission_view_url( $submission );

		$passed      = (bool) $submission->passed;
		$passed_text = $passed
			? __( 'Passed', 'pressprimer-assignment' )
			: __( 'Did not pass', 'pressprimer-assignment' );

		// Build token replacements.
		$tokens = [
			'{first_name}'       => $first_name,
			'{student_name}'     => $user->display_name,
			'{assignment_title}' => $assignment->title,
			'{score}'            => number_format_i18n( (float) $submission->score ),
			'{max_points}'       => number_format_i18n( (float) $assignment->max_points ),
			'{passed}'           => $passed_text,
			'{date}'             => wp_date(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				strtotime( $submission->submitted_at )
			),
			'{site_name}'        => get_bloginfo( 'name' ),
			'{score_summary}'    => self::build_score_summary_html( $submission, $assignment ),
			'{view_url}'         => self::build_button_html(
				$view_url,
				__( 'View Your Grade & Feedback', 'pressprimer-assignment' )
			),
		];

		// Replace tokens.
		$subject   = str_replace( array_keys( $tokens ), array_values( $tokens ), $subject_template );
		$body_text = str_replace( array_keys( $tokens ), array_values( $tokens ), $body_template );

		$html_body = self::build_html_email_from_template( $body_text );

		return self::send( $user->user_email, $subject, $html_body );
	}

	/**
	 * Send new submission notification to assignment owner
	 *
	 * Notifies the assignment author (and any additional notification
	 * email addresses configured on the assignment) that a student
	 * has submitted. Uses a customizable template with token placeholders.
	 *
	 * @since 1.0.0
	 *
	 * @param int $submission_id Submission ID.
	 * @return bool Whether email was sent.
	 */
	public static function send_admin_notification( $submission_id ) {
		$settings = self::get_settings();

		if ( ! $settings['admin_new_submission'] ) {
			return false;
		}

		$submission = PressPrimer_Assignment_Submission::get( $submission_id );
		if ( ! $submission ) {
			return false;
		}

		$assignment = PressPrimer_Assignment_Assignment::get( $submission->assignment_id );
		if ( ! $assignment ) {
			return false;
		}

		$student = get_userdata( $submission->user_id );
		if ( ! $student ) {
			return false;
		}

		// Build list of recipients: assignment author + notification_email addresses.
		$recipients = [];

		$author = get_userdata( $assignment->author_id );
		if ( $author && $author->user_email ) {
			$recipients[] = $author->user_email;
		}

		// Add any per-assignment notification emails.
		if ( ! empty( $assignment->notification_email ) ) {
			$extra_emails = array_map( 'trim', explode( ',', $assignment->notification_email ) );
			foreach ( $extra_emails as $extra_email ) {
				$sanitized_extra = sanitize_email( $extra_email );
				if ( is_email( $sanitized_extra ) && ! in_array( $sanitized_extra, $recipients, true ) ) {
					$recipients[] = $sanitized_extra;
				}
			}
		}

		if ( empty( $recipients ) ) {
			return false;
		}

		// Get templates from settings.
		$subject_template = ! empty( $settings['email_admin_subject'] )
			? $settings['email_admin_subject']
			: self::get_default_admin_subject();
		$body_template    = ! empty( $settings['email_admin_body'] )
			? $settings['email_admin_body']
			: self::get_default_admin_body();

		$grade_url = admin_url( 'admin.php?page=pressprimer-assignment-grading&submission_id=' . $submission_id );

		// Build token replacements.
		$tokens = [
			'{student_name}'     => $student->display_name,
			'{student_email}'    => $student->user_email,
			'{assignment_title}' => $assignment->title,
			'{date}'             => wp_date(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				strtotime( $submission->submitted_at )
			),
			'{site_name}'        => get_bloginfo( 'name' ),
			'{grade_url}'        => self::build_button_html(
				$grade_url,
				__( 'Grade This Submission', 'pressprimer-assignment' )
			),
		];

		// Replace tokens.
		$subject   = str_replace( array_keys( $tokens ), array_values( $tokens ), $subject_template );
		$body_text = str_replace( array_keys( $tokens ), array_values( $tokens ), $body_template );

		$html_body = self::build_html_email_from_template( $body_text );

		// Send to all recipients.
		$sent = false;
		foreach ( $recipients as $recipient ) {
			$result = self::send( $recipient, $subject, $html_body );
			if ( $result ) {
				$sent = true;
			}
		}

		return $sent;
	}

	// =========================================================================
	// Default Templates.
	// =========================================================================

	/**
	 * Get default submission confirmation subject
	 *
	 * @since 1.0.0
	 *
	 * @return string Default subject.
	 */
	public static function get_default_submission_subject() {
		return __( 'Submission Received: {assignment_title}', 'pressprimer-assignment' );
	}

	/**
	 * Get default submission confirmation body
	 *
	 * @since 1.0.0
	 *
	 * @return string Default body template.
	 */
	public static function get_default_submission_body() {
		return __(
			'Hi {first_name},

Your submission for "{assignment_title}" has been received.

Submitted: {date}

Your instructor will review your submission and provide feedback. You\'ll receive an email when your grade is ready.

{view_url}',
			'pressprimer-assignment'
		);
	}

	/**
	 * Get default grade notification subject
	 *
	 * @since 1.0.0
	 *
	 * @return string Default subject.
	 */
	public static function get_default_grade_subject() {
		return __( 'Your Grade is Ready: {assignment_title}', 'pressprimer-assignment' );
	}

	/**
	 * Get default grade notification body
	 *
	 * @since 1.0.0
	 *
	 * @return string Default body template.
	 */
	public static function get_default_grade_body() {
		return __(
			'{score_summary}

Hi {first_name},

Your assignment "{assignment_title}" has been graded.

- Score: {score} / {max_points}
- Status: {passed}

Your instructor has provided feedback. Click the button below to view your complete results.

{view_url}',
			'pressprimer-assignment'
		);
	}

	/**
	 * Get default admin notification subject
	 *
	 * @since 1.0.0
	 *
	 * @return string Default subject.
	 */
	public static function get_default_admin_subject() {
		return __( 'New Submission: {student_name} submitted {assignment_title}', 'pressprimer-assignment' );
	}

	/**
	 * Get default admin notification body
	 *
	 * @since 1.0.0
	 *
	 * @return string Default body template.
	 */
	public static function get_default_admin_body() {
		return __(
			'{student_name} has submitted "{assignment_title}".

- Student: {student_name} ({student_email})
- Assignment: {assignment_title}
- Submitted: {date}

{grade_url}',
			'pressprimer-assignment'
		);
	}

	// =========================================================================
	// Email Template Building (matches Quiz HTML pattern).
	// =========================================================================

	/**
	 * Build HTML email from template text
	 *
	 * Takes plaintext template content (with tokens already replaced)
	 * and wraps it in the HTML email structure. Uses nl2br() to
	 * convert line breaks, matching the Quiz pattern.
	 *
	 * @since 1.0.0
	 *
	 * @param string $body_text Template text with tokens replaced.
	 * @return string Complete HTML email.
	 */
	private static function build_html_email_from_template( $body_text ) {
		/**
		 * Filter the complete email HTML before the default template is built.
		 *
		 * Return a non-null string to completely replace the default HTML
		 * template. Developers can use this to provide a fully custom email
		 * layout while still using the token-based body text.
		 *
		 * @since 1.0.0
		 *
		 * @param string|null $html      Return a string to override, or null for default.
		 * @param string      $body_text The template body text with tokens already replaced.
		 */
		$custom_html = apply_filters( 'pressprimer_assignment_email_html', null, $body_text );
		if ( is_string( $custom_html ) ) {
			return $custom_html;
		}

		$header_html = self::build_email_header();
		$footer_html = self::build_email_footer();

		// Inline styles required: Email clients do not support external stylesheets.
		ob_start();
		?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<style>
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
			line-height: 1.6;
			color: #333333;
			background-color: #f5f5f5;
			margin: 0;
			padding: 0;
		}
		.email-container {
			max-width: 600px;
			margin: 20px auto;
			background-color: #ffffff;
			border-radius: 8px;
			overflow: hidden;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
		}
		.email-header {
			background-color: #ffffff;
			padding: 30px 20px;
			text-align: center;
			border-bottom: 1px solid #e5e5e5;
		}
		.email-header img {
			max-width: 400px;
			max-height: 150px;
			height: auto;
		}
		.email-header h1 {
			margin: 0;
			font-size: 24px;
			font-weight: 700;
			color: #1a1a1a;
		}
		.email-body {
			padding: 30px 20px;
		}
		.message-content {
			line-height: 1.8;
			color: #4b5563;
		}
		.message-content p {
			margin: 0 0 15px;
		}
		.cta-button {
			display: inline-block;
			padding: 12px 30px;
			background-color: #3b82f6;
			color: #ffffff;
			text-decoration: none;
			border-radius: 6px;
			font-weight: 600;
			margin: 20px 0;
		}
		.email-footer {
			background-color: #f9fafb;
			padding: 20px;
			text-align: center;
			color: #6b7280;
			font-size: 14px;
			border-top: 1px solid #e5e5e5;
		}
		.email-footer p {
			margin: 5px 0;
		}
	</style>
</head>
<body>
	<div class="email-container">
		<?php echo wp_kses_post( $header_html ); ?>

		<div class="email-body">
			<div class="message-content">
				<?php echo wp_kses_post( nl2br( $body_text ) ); ?>
			</div>
		</div>

		<?php echo wp_kses_post( $footer_html ); ?>
	</div>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Build email header HTML
	 *
	 * Renders the header section with site logo or name.
	 * Matches the Quiz email header pattern.
	 *
	 * @since 1.0.0
	 *
	 * @return string Header HTML.
	 */
	private static function build_email_header() {
		$settings = self::get_settings();
		$logo_url = isset( $settings['email_logo_url'] ) ? $settings['email_logo_url'] : '';

		ob_start();
		?>
		<div class="email-header">
			<?php if ( $logo_url ) : ?>
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			<?php else : ?>
				<h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
			<?php endif; ?>
		</div>
		<?php
		$header = ob_get_clean();

		/**
		 * Filter the email header HTML.
		 *
		 * @since 1.0.0
		 *
		 * @param string $header   Header HTML.
		 * @param string $logo_url Logo URL.
		 */
		return apply_filters( 'pressprimer_assignment_email_header', $header, $logo_url );
	}

	/**
	 * Build email footer HTML
	 *
	 * Renders the footer section with site name and URL.
	 * Matches the Quiz email footer pattern.
	 *
	 * @since 1.0.0
	 *
	 * @return string Footer HTML.
	 */
	private static function build_email_footer() {
		ob_start();
		?>
		<div class="email-footer">
			<p>
				<?php
				printf(
					/* translators: %s: site name */
					esc_html__( 'This email was sent from %s', 'pressprimer-assignment' ),
					esc_html( get_bloginfo( 'name' ) )
				);
				?>
			</p>
			<p><?php echo esc_html( home_url() ); ?></p>
		</div>
		<?php
		$footer = ob_get_clean();

		/**
		 * Filter the email footer HTML.
		 *
		 * @since 1.0.0
		 *
		 * @param string $footer Footer HTML.
		 */
		return apply_filters( 'pressprimer_assignment_email_footer', $footer );
	}

	// =========================================================================
	// Reusable HTML Builders.
	// =========================================================================

	/**
	 * Build score summary HTML box
	 *
	 * Creates a visual score box similar to Quiz's results summary.
	 * Shows score, max points, and pass/fail status with color coding.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Submission $submission Submission object.
	 * @param PressPrimer_Assignment_Assignment $assignment Assignment object.
	 * @return string HTML for score summary.
	 */
	private static function build_score_summary_html( $submission, $assignment ) {
		$passed       = (bool) $submission->passed;
		$passed_color = $passed ? '#10b981' : '#ef4444';
		$passed_bg    = $passed ? '#f0fdf4' : '#fef2f2';
		$passed_text  = $passed
			? __( 'PASSED', 'pressprimer-assignment' )
			: __( 'DID NOT PASS', 'pressprimer-assignment' );

		$score_display = number_format_i18n( (float) $submission->score )
			. ' / ' . number_format_i18n( (float) $assignment->max_points );

		$html  = '<div style="background-color: ' . esc_attr( $passed_bg ) . '; border: 2px solid ' . esc_attr( $passed_color ) . '; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0;">';
		$html .= '<div style="font-size: 36px; font-weight: 700; color: #1a1a1a; margin: 0 0 10px;">' . esc_html( $score_display ) . '</div>';
		$html .= '<div style="display: inline-block; padding: 8px 20px; background-color: ' . esc_attr( $passed_color ) . '; color: #ffffff; border-radius: 4px; font-weight: 600; font-size: 14px;">' . esc_html( $passed_text ) . '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Build test score summary HTML
	 *
	 * Creates a sample score summary for test emails.
	 *
	 * @since 1.0.0
	 *
	 * @return string HTML for test score summary.
	 */
	private static function build_test_score_summary_html() {
		$html  = '<div style="background-color: #f0fdf4; border: 2px solid #10b981; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0;">';
		$html .= '<div style="font-size: 36px; font-weight: 700; color: #1a1a1a; margin: 0 0 10px;">85 / 100</div>';
		$html .= '<div style="display: inline-block; padding: 8px 20px; background-color: #10b981; color: #ffffff; border-radius: 4px; font-weight: 600; font-size: 14px;">' . esc_html__( 'PASSED', 'pressprimer-assignment' ) . '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Build CTA button HTML
	 *
	 * Creates a call-to-action button matching Quiz's button style.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url   Button URL.
	 * @param string $label Button text.
	 * @return string HTML for button.
	 */
	private static function build_button_html( $url, $label ) {
		$html  = '<div style="text-align: center; margin: 24px 0;">';
		$html .= '<a href="' . esc_url( $url ) . '" style="display: inline-block; padding: 12px 30px; background-color: #3b82f6; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600;">';
		$html .= esc_html( $label );
		$html .= '</a>';
		$html .= '</div>';

		return $html;
	}

	// =========================================================================
	// Helpers.
	// =========================================================================

	/**
	 * Send email
	 *
	 * Sends an HTML email using wp_mail().
	 *
	 * @since 1.0.0
	 *
	 * @param string $to      Recipient email address.
	 * @param string $subject Email subject.
	 * @param string $message Email body (HTML).
	 * @return bool Whether email was sent.
	 */
	private static function send( $to, $subject, $message ) {
		$settings   = self::get_settings();
		$from_name  = ! empty( $settings['email_from_name'] )
			? $settings['email_from_name']
			: get_bloginfo( 'name' );
		$from_email = ! empty( $settings['email_from_email'] )
			? $settings['email_from_email']
			: get_bloginfo( 'admin_email' );

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
		];

		/**
		 * Filter email headers before sending.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $headers Email headers.
		 * @param string $to      Recipient email.
		 * @param string $subject Email subject.
		 */
		$headers = apply_filters( 'pressprimer_assignment_email_headers', $headers, $to, $subject );

		$sent = wp_mail( $to, $subject, $message, $headers );

		if ( $sent ) {
			/**
			 * Fires after an assignment email is sent successfully.
			 *
			 * @since 1.0.0
			 *
			 * @param string $to      Recipient email.
			 * @param string $subject Email subject.
			 */
			do_action( 'pressprimer_assignment_email_sent', $to, $subject );
		}

		return $sent;
	}

	/**
	 * Get email notification settings
	 *
	 * Returns the email settings with defaults applied.
	 *
	 * @since 1.0.0
	 *
	 * @return array Settings array.
	 */
	public static function get_settings() {
		$defaults = [
			'student_submission_confirmation' => true,
			'student_grade_notification'      => true,
			'admin_new_submission'            => true,
			'email_from_name'                 => '',
			'email_from_email'                => '',
			'email_logo_url'                  => '',
			'email_logo_id'                   => 0,
		];

		$settings = get_option( PressPrimer_Assignment_Admin_Settings::OPTION_NAME, [] );

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Send test email
	 *
	 * Sends a test email using the current template settings.
	 * Uses sample data for token replacement.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Recipient email address.
	 * @param string $type  Email type: 'submission', 'grade', or 'admin'.
	 * @return bool Whether email was sent.
	 */
	public static function send_test_email( $email, $type = 'submission' ) {
		$settings = self::get_settings();

		// Get current user info for test data.
		$current_user = wp_get_current_user();
		$first_name   = $current_user->first_name ? $current_user->first_name : $current_user->display_name;
		$parts        = explode( ' ', $first_name );
		$first_name   = $parts[0];

		switch ( $type ) {
			case 'grade':
				$subject_template = ! empty( $settings['email_grade_subject'] )
					? $settings['email_grade_subject']
					: self::get_default_grade_subject();
				$body_template    = ! empty( $settings['email_grade_body'] )
					? $settings['email_grade_body']
					: self::get_default_grade_body();

				$tokens = [
					'{first_name}'       => $first_name,
					'{student_name}'     => $current_user->display_name ?: __( 'Test Student', 'pressprimer-assignment' ),
					'{assignment_title}' => __( 'Sample Assignment', 'pressprimer-assignment' ),
					'{score}'            => '85',
					'{max_points}'       => '100',
					'{passed}'           => __( 'Passed', 'pressprimer-assignment' ),
					'{date}'             => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
					'{site_name}'        => get_bloginfo( 'name' ),
					'{score_summary}'    => self::build_test_score_summary_html(),
					'{view_url}'         => self::build_button_html( home_url(), __( 'View Your Grade & Feedback', 'pressprimer-assignment' ) ),
				];
				break;

			case 'admin':
				$subject_template = ! empty( $settings['email_admin_subject'] )
					? $settings['email_admin_subject']
					: self::get_default_admin_subject();
				$body_template    = ! empty( $settings['email_admin_body'] )
					? $settings['email_admin_body']
					: self::get_default_admin_body();

				$tokens = [
					'{student_name}'     => $current_user->display_name ?: __( 'Test Student', 'pressprimer-assignment' ),
					'{student_email}'    => $email,
					'{assignment_title}' => __( 'Sample Assignment', 'pressprimer-assignment' ),
					'{date}'             => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
					'{site_name}'        => get_bloginfo( 'name' ),
					'{grade_url}'        => self::build_button_html( admin_url(), __( 'Grade This Submission', 'pressprimer-assignment' ) ),
				];
				break;

			default: // 'submission'.
				$subject_template = ! empty( $settings['email_submission_subject'] )
					? $settings['email_submission_subject']
					: self::get_default_submission_subject();
				$body_template    = ! empty( $settings['email_submission_body'] )
					? $settings['email_submission_body']
					: self::get_default_submission_body();

				$tokens = [
					'{first_name}'       => $first_name,
					'{student_name}'     => $current_user->display_name ?: __( 'Test Student', 'pressprimer-assignment' ),
					'{assignment_title}' => __( 'Sample Assignment', 'pressprimer-assignment' ),
					'{date}'             => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
					'{site_name}'        => get_bloginfo( 'name' ),
					'{view_url}'         => self::build_button_html( home_url(), __( 'View Submission Status', 'pressprimer-assignment' ) ),
				];
				break;
		}

		// Replace tokens.
		$subject   = str_replace( array_keys( $tokens ), array_values( $tokens ), $subject_template );
		$subject   = '[' . __( 'TEST', 'pressprimer-assignment' ) . '] ' . $subject;
		$body_text = str_replace( array_keys( $tokens ), array_values( $tokens ), $body_template );

		$html_body = self::build_html_email_from_template( $body_text );

		return self::send( $email, $subject, $html_body );
	}

	/**
	 * Get first name for a user
	 *
	 * Tries first_name user meta, then falls back to the first
	 * word of display_name.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_User $user WordPress user object.
	 * @return string First name.
	 */
	private static function get_first_name( $user ) {
		$first_name = get_user_meta( $user->ID, 'first_name', true );
		if ( $first_name ) {
			return $first_name;
		}

		// Fall back to first word of display name.
		$parts = explode( ' ', $user->display_name );

		return $parts[0];
	}

	/**
	 * Get URL for student to view their submission
	 *
	 * Tries to find a page with the [pressprimer_assignment_my_submissions] shortcode.
	 * Falls back to the home URL.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Submission $submission Submission object.
	 * @return string URL.
	 */
	private static function get_submission_view_url( $submission ) {
		// Try the configured My Submissions page from plugin settings.
		$settings = get_option( PressPrimer_Assignment_Admin_Settings::OPTION_NAME, [] );
		$page_id  = ! empty( $settings['my_submissions_page_id'] )
			? absint( $settings['my_submissions_page_id'] )
			: 0;

		if ( $page_id ) {
			$url = get_permalink( $page_id );
			if ( $url ) {
				return $url;
			}
		}

		// Fallback: find any page containing the shortcode.
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$found_page_id = $wpdb->get_var(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_content LIKE '%[pressprimer_assignment_my_submissions%'
			 AND post_status = 'publish'
			 AND post_type IN ('page', 'post')
			 LIMIT 1"
		);

		if ( $found_page_id ) {
			return get_permalink( $found_page_id );
		}

		// Final fallback.
		return home_url();
	}

	// =========================================================================
	// Hook Registration.
	// =========================================================================

	/**
	 * Register email notification hooks
	 *
	 * Connects email service methods to the appropriate action hooks.
	 * Called during plugin initialization.
	 *
	 * @since 1.0.0
	 */
	public static function register_hooks() {
		// Send emails when a submission is finalized.
		add_action( 'pressprimer_assignment_submission_submitted', [ __CLASS__, 'on_submission_submitted' ], 10, 2 );

		// Send email when a graded submission is returned to the student.
		add_action( 'pressprimer_assignment_submission_returned', [ __CLASS__, 'on_submission_returned' ], 10, 1 );
	}

	/**
	 * Handle submission submitted action
	 *
	 * Sends confirmation to student and notification to admin.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Submission $submission Submission instance.
	 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
	 */
	public static function on_submission_submitted( $submission, $assignment ) {
		$submission_id = is_object( $submission ) ? $submission->id : absint( $submission );

		// Send confirmation to student.
		self::send_submission_confirmation( $submission_id );

		// Send notification to admin.
		self::send_admin_notification( $submission_id );
	}

	/**
	 * Handle submission returned action
	 *
	 * Sends grade notification to student.
	 *
	 * @since 1.0.0
	 *
	 * @param int $submission_id Submission ID.
	 */
	public static function on_submission_returned( $submission_id ) {
		$submission_id = absint( $submission_id );

		// Send grade notification to student.
		self::send_grade_notification( $submission_id );
	}
}
