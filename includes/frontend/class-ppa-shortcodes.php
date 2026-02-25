<?php
/**
 * Shortcodes
 *
 * Registers and handles all PressPrimer Assignment shortcodes.
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
 * Shortcodes class
 *
 * Provides shortcodes for embedding assignments and submission
 * dashboards into posts and pages.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Shortcodes {

	/**
	 * Frontend instance for asset enqueuing
	 *
	 * @since 1.0.0
	 * @var PressPrimer_Assignment_Frontend|null
	 */
	private $frontend = null;

	/**
	 * Initialize shortcodes
	 *
	 * Registers all shortcodes with WordPress.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_shortcodes' ] );
	}

	/**
	 * Register all shortcodes
	 *
	 * @since 1.0.0
	 */
	public function register_shortcodes() {
		add_shortcode( 'ppa_assignment', [ $this, 'render_assignment' ] );
		add_shortcode( 'ppa_my_submissions', [ $this, 'render_my_submissions' ] );
	}

	/**
	 * Get Frontend instance
	 *
	 * Lazy-loads the frontend instance for asset enqueuing.
	 *
	 * @since 1.0.0
	 *
	 * @return PressPrimer_Assignment_Frontend Frontend instance.
	 */
	private function get_frontend() {
		if ( null === $this->frontend ) {
			$this->frontend = new PressPrimer_Assignment_Frontend();
		}

		return $this->frontend;
	}

	/**
	 * Parse a string boolean value
	 *
	 * Handles various boolean representations from shortcode attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Value to parse.
	 * @return bool Parsed boolean value.
	 */
	private function parse_boolean( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		$value = strtolower( trim( (string) $value ) );

		return in_array( $value, [ 'true', '1', 'yes', 'on' ], true );
	}

	/**
	 * Render assignment shortcode
	 *
	 * Displays a single assignment with its submission form or
	 * the user's existing submission status.
	 *
	 * Usage: [ppa_assignment id="123"]
	 *
	 * @since 1.0.0
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content (unused).
	 * @return string Rendered HTML.
	 */
	public function render_assignment( $atts, $content = '' ) {
		$defaults = [
			'id'                => 0,
			'show_description'  => 'true',
			'show_instructions' => 'true',
			'show_max_points'   => 'true',
			'show_file_info'    => 'true',
		];

		$atts = shortcode_atts( $defaults, $atts, 'ppa_assignment' );

		$assignment_id = absint( $atts['id'] );

		if ( 0 === $assignment_id ) {
			return $this->render_error( __( 'Assignment ID is required.', 'pressprimer-assignment' ) );
		}

		// Build display options.
		$display = [];
		foreach ( $atts as $key => $value ) {
			if ( 0 === strpos( $key, 'show_' ) ) {
				$display[ $key ] = $this->parse_boolean( $value );
			}
		}

		// Get the assignment.
		$assignment = PressPrimer_Assignment_Assignment::get( $assignment_id );

		if ( ! $assignment ) {
			return $this->render_error( __( 'Assignment not found.', 'pressprimer-assignment' ) );
		}

		// Check if published (admins can preview drafts).
		if ( 'published' !== $assignment->status && ! current_user_can( 'manage_options' ) ) {
			return $this->render_error( __( 'This assignment is not available.', 'pressprimer-assignment' ) );
		}

		// Enqueue assets.
		$frontend = $this->get_frontend();
		$frontend->enqueue_assets();

		// Enqueue text editor assets if assignment accepts text submissions.
		if ( $assignment->accepts_text_submission() ) {
			$frontend->enqueue_text_editor_assets();
		}

		// Delegate to renderer.
		if ( class_exists( 'PressPrimer_Assignment_Assignment_Renderer' ) ) {
			$renderer = new PressPrimer_Assignment_Assignment_Renderer();
			return $renderer->render( $assignment, $display );
		}

		// Fallback if renderer not yet created.
		return $this->render_assignment_fallback( $assignment, $display );
	}

	/**
	 * Render my submissions shortcode
	 *
	 * Displays the current user's submission history across all assignments.
	 *
	 * Usage: [ppa_my_submissions]
	 *
	 * @since 1.0.0
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content (unused).
	 * @return string Rendered HTML.
	 */
	public function render_my_submissions( $atts, $content = '' ) {
		$defaults = [
			'per_page'    => 10,
			'show_status' => 'true',
			'show_score'  => 'true',
			'show_date'   => 'true',
		];

		$atts = shortcode_atts( $defaults, $atts, 'ppa_my_submissions' );

		// Require login.
		if ( ! is_user_logged_in() ) {
			return $this->render_login_required( 'submissions' );
		}

		// Prevent caching of user-specific content.
		nocache_headers();

		// Build display options.
		$display = [
			'per_page'    => absint( $atts['per_page'] ),
			'show_status' => $this->parse_boolean( $atts['show_status'] ),
			'show_score'  => $this->parse_boolean( $atts['show_score'] ),
			'show_date'   => $this->parse_boolean( $atts['show_date'] ),
		];

		// Enqueue assets (includes dashboard-specific stylesheet).
		$this->get_frontend()->enqueue_dashboard_assets();

		// Delegate to renderer.
		if ( class_exists( 'PressPrimer_Assignment_Submissions_Renderer' ) ) {
			$renderer = new PressPrimer_Assignment_Submissions_Renderer();
			return $renderer->render( get_current_user_id(), $display );
		}

		// Fallback if renderer not yet created.
		return $this->render_submissions_fallback( $display );
	}

	/**
	 * Render an error message
	 *
	 * Returns an HTML notice for display in the frontend.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Error message text.
	 * @return string HTML error notice.
	 */
	private function render_error( $message ) {
		$allowed_html = [
			'div' => [
				'class' => true,
				'role'  => true,
			],
			'p'   => [
				'class' => true,
			],
		];

		$html  = '<div class="ppa-notice ppa-notice-error" role="alert">';
		$html .= '<p class="ppa-notice-message">' . esc_html( $message ) . '</p>';
		$html .= '</div>';

		return wp_kses( $html, $allowed_html );
	}

	/**
	 * Render login required message
	 *
	 * Returns a centered login prompt matching the PressPrimer Quiz pattern.
	 * Includes a prominent login button, optional registration link,
	 * and filterable URLs for membership plugin compatibility.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context 'submissions' or 'assignment'. Controls the message text.
	 * @return string HTML login prompt.
	 */
	private function render_login_required( $context = 'submissions' ) {
		$allowed_html = [
			'div'  => [
				'class'       => true,
				'role'        => true,
				'aria-hidden' => true,
			],
			'p'    => [
				'class' => true,
			],
			'a'    => [
				'href'  => true,
				'class' => true,
			],
			'span' => [
				'class'       => true,
				'aria-hidden' => true,
			],
		];

		$login_url = wp_login_url( get_permalink() );

		/**
		 * Filters the login URL shown when login is required.
		 *
		 * Use this to redirect to a custom login page such as
		 * WooCommerce, MemberPress, or another membership plugin.
		 *
		 * @since 1.0.0
		 *
		 * @param string $login_url The login URL.
		 */
		$login_url = apply_filters( 'pressprimer_assignment_login_url', $login_url );

		if ( 'assignment' === $context ) {
			$message = __( 'You need to be logged in to submit this assignment. Please log in to continue.', 'pressprimer-assignment' );
			$button  = __( 'Log In to Submit', 'pressprimer-assignment' );
		} else {
			$message = __( 'You need to be logged in to view your submissions. Please log in to continue.', 'pressprimer-assignment' );
			$button  = __( 'Log In to View Submissions', 'pressprimer-assignment' );
		}

		$html  = '<div class="ppa-login-required">';
		$html .= '<div class="ppa-login-required-icon" aria-hidden="true">&#x1f512;</div>';
		$html .= '<div class="ppa-login-required-message">';
		$html .= '<p>' . esc_html( $message ) . '</p>';
		$html .= '</div>';
		$html .= '<a href="' . esc_url( $login_url ) . '" class="ppa-button ppa-button-primary ppa-button-large ppa-login-button">';
		$html .= '<span class="ppa-button-icon" aria-hidden="true">&#x1f510;</span>';
		$html .= esc_html( $button );
		$html .= '</a>';

		if ( get_option( 'users_can_register' ) ) {
			$register_url = wp_registration_url();

			/**
			 * Filters the registration URL shown when login is required.
			 *
			 * @since 1.0.0
			 *
			 * @param string $register_url The registration URL.
			 */
			$register_url = apply_filters( 'pressprimer_assignment_register_url', $register_url );

			$html .= '<p class="ppa-register-prompt">';
			$html .= sprintf(
				/* translators: %s: registration link */
				esc_html__( "Don't have an account? %s", 'pressprimer-assignment' ),
				'<a href="' . esc_url( $register_url ) . '">' . esc_html__( 'Register here', 'pressprimer-assignment' ) . '</a>'
			);
			$html .= '</p>';
		}

		$html .= '</div>';

		return wp_kses( $html, $allowed_html );
	}

	/**
	 * Render assignment fallback
	 *
	 * Provides basic assignment display when the full renderer
	 * class is not yet available (during phased development).
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Assignment_Assignment $assignment Assignment instance.
	 * @param array                             $display    Display options.
	 * @return string HTML output.
	 */
	private function render_assignment_fallback( $assignment, $display ) {
		$theme = get_option( 'ppa_frontend_theme', 'default' );

		$allowed_html = [
			'div'  => [
				'class' => true,
			],
			'h2'   => [
				'class' => true,
			],
			'p'    => [
				'class' => true,
			],
			'span' => [
				'class' => true,
			],
		];

		$html  = '<div class="ppa-assignment ppa-theme-' . esc_attr( $theme ) . '">';
		$html .= '<div class="ppa-assignment-content">';
		$html .= '<div class="ppa-assignment-header">';
		$html .= '<h2 class="ppa-assignment-title">' . esc_html( $assignment->title ) . '</h2>';
		$html .= '</div>';

		if ( ! empty( $display['show_description'] ) && ! empty( $assignment->description ) ) {
			$html .= '<div class="ppa-assignment-description">';
			$html .= '<p class="ppa-assignment-description-text">' . esc_html( $assignment->description ) . '</p>';
			$html .= '</div>';
		}

		if ( ! empty( $display['show_max_points'] ) && $assignment->max_points > 0 ) {
			$html .= '<div class="ppa-assignment-meta">';
			$html .= '<span class="ppa-meta-label">' . esc_html__( 'Max Points:', 'pressprimer-assignment' ) . '</span> ';
			$html .= '<span class="ppa-meta-value">' . esc_html( number_format_i18n( $assignment->max_points, 0 ) ) . '</span>';
			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= '</div>';

		return wp_kses( $html, $allowed_html );
	}

	/**
	 * Render submissions fallback
	 *
	 * Provides basic submissions list when the full renderer
	 * class is not yet available (during phased development).
	 *
	 * @since 1.0.0
	 *
	 * @param array $display Display options.
	 * @return string HTML output.
	 */
	private function render_submissions_fallback( $display ) {
		$user_id     = get_current_user_id();
		$submissions = PressPrimer_Assignment_Submission::get_for_user( $user_id );

		$theme = get_option( 'ppa_frontend_theme', 'default' );

		$allowed_html = [
			'div'  => [
				'class' => true,
			],
			'h2'   => [
				'class' => true,
			],
			'p'    => [
				'class' => true,
			],
			'span' => [
				'class' => true,
			],
			'ul'   => [
				'class' => true,
			],
			'li'   => [
				'class' => true,
			],
		];

		$html  = '<div class="ppa-my-submissions ppa-theme-' . esc_attr( $theme ) . '">';
		$html .= '<h2 class="ppa-submissions-title">' . esc_html__( 'My Submissions', 'pressprimer-assignment' ) . '</h2>';

		if ( empty( $submissions ) ) {
			$html .= '<p class="ppa-submissions-empty">' . esc_html__( 'You have no submissions yet.', 'pressprimer-assignment' ) . '</p>';
		} else {
			$html .= '<ul class="ppa-submissions-list">';

			foreach ( $submissions as $submission ) {
				$assignment = $submission->get_assignment();
				$title      = $assignment ? $assignment->title : __( 'Unknown Assignment', 'pressprimer-assignment' );

				$html .= '<li class="ppa-submission-item">';
				$html .= '<span class="ppa-submission-title">' . esc_html( $title ) . '</span>';

				if ( ! empty( $display['show_status'] ) ) {
					$html .= ' <span class="ppa-submission-status ppa-status-' . esc_attr( $submission->status ) . '">';
					$html .= esc_html( ucfirst( $submission->status ) );
					$html .= '</span>';
				}

				if ( ! empty( $display['show_score'] ) && null !== $submission->score ) {
					$html .= ' <span class="ppa-submission-score">';
					$html .= esc_html( number_format_i18n( $submission->score, 1 ) );
					$html .= '</span>';
				}

				$html .= '</li>';
			}

			$html .= '</ul>';
		}

		$html .= '</div>';

		return wp_kses( $html, $allowed_html );
	}
}
