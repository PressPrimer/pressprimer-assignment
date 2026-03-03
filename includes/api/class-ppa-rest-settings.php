<?php
/**
 * REST API settings endpoint
 *
 * Provides GET and POST endpoints for reading and saving
 * plugin settings. Follows the same pattern as PressPrimer Quiz.
 *
 * @package PressPrimer_Assignment
 * @subpackage API
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST settings endpoint class
 *
 * Registers /ppa/v1/settings routes for the React settings panel.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_REST_Settings {

	/**
	 * Initialize the REST endpoint
	 *
	 * Hooks into rest_api_init to register routes.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST routes
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			'ppa/v1',
			'/settings',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);

		register_rest_route(
			'ppa/v1',
			'/email/test',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'send_test_email' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Check permission for settings endpoints
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if user has permission.
	 */
	public function check_permission() {
		return current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_SETTINGS );
	}

	/**
	 * Get settings
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_settings( $request ) {
		$settings = get_option( PressPrimer_Assignment_Admin_Settings::OPTION_NAME, [] );

		// Include the active theme from its separate option.
		$settings['appearance_theme'] = get_option( 'pressprimer_assignment_frontend_theme', 'default' );

		return new WP_REST_Response(
			[
				'success'  => true,
				'settings' => $settings,
			],
			200
		);
	}

	/**
	 * Update settings
	 *
	 * Sanitizes incoming data and merges with existing settings.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function update_settings( $request ) {
		$data     = $request->get_json_params();
		$existing = get_option( PressPrimer_Assignment_Admin_Settings::OPTION_NAME, [] );

		$sanitized = [];

		// =====================================================================
		// General settings.
		// =====================================================================

		if ( isset( $data['default_passing_score'] ) ) {
			$sanitized['default_passing_score'] = min( 100000, max( 0, absint( $data['default_passing_score'] ) ) );
		}

		if ( isset( $data['default_max_file_size'] ) ) {
			$sanitized['default_max_file_size'] = min( 100, max( 1, absint( $data['default_max_file_size'] ) ) );
		}

		if ( isset( $data['default_max_files'] ) ) {
			$sanitized['default_max_files'] = min( 20, max( 1, absint( $data['default_max_files'] ) ) );
		}

		// Page mapping.
		if ( isset( $data['my_submissions_page_id'] ) ) {
			$sanitized['my_submissions_page_id'] = absint( $data['my_submissions_page_id'] );
		}

		// =====================================================================
		// Email settings.
		// =====================================================================

		if ( isset( $data['email_from_name'] ) ) {
			$sanitized['email_from_name'] = sanitize_text_field( $data['email_from_name'] );
		}

		if ( isset( $data['email_from_email'] ) ) {
			$email = sanitize_email( $data['email_from_email'] );
			if ( is_email( $email ) ) {
				$sanitized['email_from_email'] = $email;
			}
		}

		if ( isset( $data['email_logo_url'] ) ) {
			$sanitized['email_logo_url'] = esc_url_raw( $data['email_logo_url'] );
		}

		if ( isset( $data['email_logo_id'] ) ) {
			$sanitized['email_logo_id'] = absint( $data['email_logo_id'] );
		}

		// Email notification toggles.
		if ( isset( $data['student_submission_confirmation'] ) ) {
			$sanitized['student_submission_confirmation'] = (bool) $data['student_submission_confirmation'];
		}

		if ( isset( $data['student_grade_notification'] ) ) {
			$sanitized['student_grade_notification'] = (bool) $data['student_grade_notification'];
		}

		if ( isset( $data['admin_new_submission'] ) ) {
			$sanitized['admin_new_submission'] = (bool) $data['admin_new_submission'];
		}

		// Email template subjects (plain text).
		$subject_fields = [
			'email_submission_subject',
			'email_grade_subject',
			'email_admin_subject',
		];
		foreach ( $subject_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		// Email template bodies (allow basic HTML via wp_kses_post).
		$body_fields = [
			'email_submission_body',
			'email_grade_body',
			'email_admin_body',
		];
		foreach ( $body_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$sanitized[ $field ] = wp_kses_post( $data[ $field ] );
			}
		}

		// =====================================================================
		// Appearance settings.
		// =====================================================================

		// Theme selection (stored in separate option for frontend use).
		if ( isset( $data['appearance_theme'] ) ) {
			$valid_themes = [ 'default', 'modern', 'minimal' ];
			$theme_value  = sanitize_text_field( $data['appearance_theme'] );
			if ( in_array( $theme_value, $valid_themes, true ) ) {
				update_option( 'pressprimer_assignment_frontend_theme', $theme_value );
				$sanitized['appearance_theme'] = $theme_value;
			}
		}

		// Color settings.
		$color_fields = [
			'appearance_primary_color',
			'appearance_text_color',
			'appearance_background_color',
			'appearance_success_color',
			'appearance_error_color',
		];
		foreach ( $color_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				if ( '' === $data[ $field ] ) {
					$sanitized[ $field ] = '';
				} else {
					$color = sanitize_hex_color( $data[ $field ] );
					if ( $color ) {
						$sanitized[ $field ] = $color;
					}
				}
			}
		}

		// Typography settings.
		// Font family stores the full CSS font-family value (e.g., 'Georgia, "Times New Roman", Times, serif').
		if ( isset( $data['appearance_font_family'] ) ) {
			$sanitized['appearance_font_family'] = sanitize_text_field( $data['appearance_font_family'] );
		}

		// Font size stores a pixel string (e.g., '18px') or empty for default.
		if ( isset( $data['appearance_font_size'] ) ) {
			$font_size = sanitize_text_field( $data['appearance_font_size'] );
			if ( '' === $font_size || preg_match( '/^\d{2}px$/', $font_size ) ) {
				$sanitized['appearance_font_size'] = $font_size;
			}
		}

		// Layout settings.
		if ( isset( $data['appearance_border_radius'] ) ) {
			if ( null === $data['appearance_border_radius'] || '' === $data['appearance_border_radius'] ) {
				$sanitized['appearance_border_radius'] = '';
			} else {
				$sanitized['appearance_border_radius'] = min( 24, max( 0, absint( $data['appearance_border_radius'] ) ) );
			}
		}

		if ( isset( $data['appearance_max_width'] ) ) {
			if ( '' === $data['appearance_max_width'] ) {
				$sanitized['appearance_max_width'] = '';
			} else {
				$sanitized['appearance_max_width'] = min( 1200, max( 400, absint( $data['appearance_max_width'] ) ) );
			}
		}

		// Spacing settings.
		if ( isset( $data['appearance_line_height'] ) ) {
			if ( '' === $data['appearance_line_height'] ) {
				$sanitized['appearance_line_height'] = '';
			} else {
				$value                               = floatval( $data['appearance_line_height'] );
				$sanitized['appearance_line_height'] = min( 1.8, max( 1.2, round( $value, 2 ) ) );
			}
		}

		// =====================================================================
		// Advanced settings.
		// =====================================================================

		$sanitized['remove_data_on_uninstall'] = false;
		if ( isset( $data['remove_data_on_uninstall'] ) ) {
			$sanitized['remove_data_on_uninstall'] = ( true === $data['remove_data_on_uninstall']
				|| '1' === $data['remove_data_on_uninstall']
				|| 1 === $data['remove_data_on_uninstall'] );
		}

		/**
		 * Filter the sanitized settings before saving.
		 *
		 * Allows addons to add their own settings to be saved via the core REST API.
		 *
		 * @since 1.0.0
		 *
		 * @param array $sanitized Already sanitized settings.
		 * @param array $data      Raw input data from the REST request.
		 */
		$sanitized = apply_filters( 'pressprimer_assignment_sanitize_settings', $sanitized, $data );

		// Merge with existing and save.
		$merged = array_merge( $existing, $sanitized );
		update_option( PressPrimer_Assignment_Admin_Settings::OPTION_NAME, $merged );

		return new WP_REST_Response(
			[
				'success'  => true,
				'settings' => $merged,
			],
			200
		);
	}

	/**
	 * Send test email
	 *
	 * Sends a test email to the given address using the email service.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function send_test_email( $request ) {
		$data  = $request->get_json_params();
		$email = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
		$type  = isset( $data['type'] ) ? sanitize_key( $data['type'] ) : 'submission';

		if ( ! is_email( $email ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Please enter a valid email address.', 'pressprimer-assignment' ),
				],
				400
			);
		}

		if ( ! class_exists( 'PressPrimer_Assignment_Email_Service' ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Email service is not available.', 'pressprimer-assignment' ),
				],
				500
			);
		}

		$sent = PressPrimer_Assignment_Email_Service::send_test_email( $email, $type );

		if ( $sent ) {
			return new WP_REST_Response(
				[
					'success' => true,
					'message' => __( 'Test email sent successfully!', 'pressprimer-assignment' ),
				],
				200
			);
		}

		return new WP_REST_Response(
			[
				'success' => false,
				'message' => __( 'Failed to send test email. Please check your WordPress email configuration.', 'pressprimer-assignment' ),
			],
			500
		);
	}
}
