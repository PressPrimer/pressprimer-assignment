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
			$sanitized['default_passing_score'] = min( 100, max( 0, absint( $data['default_passing_score'] ) ) );
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
