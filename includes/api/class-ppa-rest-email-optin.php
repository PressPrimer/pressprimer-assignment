<?php
/**
 * REST API email opt-in endpoint
 *
 * One route for the plugin's single email ask: records an opt-in
 * (with the relay to pressprimer.com), a decline, or a per-surface
 * dismissal. Follows the same controller pattern as the other
 * /ppa/v1 endpoints.
 *
 * @package PressPrimer_Assignment
 * @subpackage API
 * @since 2.2.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST email opt-in endpoint class
 *
 * Registers POST /ppa/v1/email-optin.
 *
 * @since 2.2.0
 */
class PressPrimer_Assignment_REST_Email_Optin {

	/**
	 * Initialize the REST endpoint
	 *
	 * @since 2.2.0
	 */
	public function init() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST routes
	 *
	 * @since 2.2.0
	 */
	public function register_routes() {
		register_rest_route(
			'ppa/v1',
			'/email-optin',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'decision' => [
						'required' => true,
						'type'     => 'string',
						'enum'     => [ 'opt_in', 'decline', 'dismiss' ],
					],
					'source'   => [
						'required' => true,
						'type'     => 'string',
						'enum'     => PressPrimer_Assignment_Email_Optin_Service::SOURCES,
					],
					'email'    => [
						'required' => false,
						'type'     => 'string',
					],
				],
			]
		);
	}

	/**
	 * Check permission for the opt-in endpoint
	 *
	 * Logged-in users with Assignment management access — the same
	 * audience the ask surfaces render for.
	 *
	 * @since 2.2.0
	 *
	 * @return bool True if the user may answer.
	 */
	public function check_permission() {
		return is_user_logged_in()
			&& (
				current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_OWN )
				|| current_user_can( PressPrimer_Assignment_Capabilities::PPA_CAP_MANAGE_ALL )
			);
	}

	/**
	 * Handle an opt-in, decline, or dismissal
	 *
	 * Opt-in order matters: the local consent record is written FIRST
	 * (it is the source of truth), then the non-blocking relay fires,
	 * then the action — so an unreachable pressprimer.com changes
	 * nothing about the user's experience or their recorded consent.
	 *
	 * @since 2.2.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function handle( $request ) {
		if ( ! PressPrimer_Assignment_Email_Optin_Service::is_enabled() ) {
			return new WP_Error(
				'pressprimer_assignment_optin_disabled',
				__( 'The email opt-in is not available on this site.', 'pressprimer-assignment' ),
				[ 'status' => 400 ]
			);
		}

		$decision = sanitize_key( $request->get_param( 'decision' ) );
		$source   = sanitize_key( $request->get_param( 'source' ) );
		$user_id  = get_current_user_id();

		if ( ! PressPrimer_Assignment_Email_Optin_Service::is_valid_source( $source ) ) {
			return new WP_Error(
				'pressprimer_assignment_optin_invalid_source',
				__( 'Unknown opt-in surface.', 'pressprimer-assignment' ),
				[ 'status' => 400 ]
			);
		}

		// Dismissal: hide this one surface, permanently. Separate from
		// answering — the other surfaces stay available.
		if ( 'dismiss' === $decision ) {
			PressPrimer_Assignment_Email_Optin_Service::record_dismissal( $user_id, $source );

			return new WP_REST_Response(
				[
					'success' => true,
					'status'  => 'dismissed',
				],
				200
			);
		}

		// One answer per user, forever: a second answer is a no-op
		// that reports the existing state.
		$existing = PressPrimer_Assignment_Email_Optin_Service::get_consent( $user_id );
		if ( $existing ) {
			return new WP_REST_Response(
				[
					'success'          => true,
					'status'           => $existing['status'],
					'already_answered' => true,
				],
				200
			);
		}

		if ( 'decline' === $decision ) {
			PressPrimer_Assignment_Email_Optin_Service::record_decline( $user_id, $source );

			return new WP_REST_Response(
				[
					'success' => true,
					'status'  => 'declined',
				],
				200
			);
		}

		// Opt-in: a typed, valid email is required.
		$email = sanitize_email( wp_unslash( $request->get_param( 'email' ) ?? '' ) );

		if ( empty( $email ) || ! is_email( $email ) ) {
			return new WP_Error(
				'pressprimer_assignment_optin_invalid_email',
				__( 'Please enter a valid email address.', 'pressprimer-assignment' ),
				[ 'status' => 400 ]
			);
		}

		PressPrimer_Assignment_Email_Optin_Service::record_opt_in( $user_id, $source );
		PressPrimer_Assignment_Email_Optin_Service::relay_opt_in( $email, $source );

		/**
		 * Fires after a user's email opt-in is recorded locally and relayed.
		 *
		 * @since 2.2.0
		 *
		 * @param int    $user_id User ID.
		 * @param string $source  Surface: wizard | whats-new | dashboard-card | milestone.
		 */
		do_action( 'pressprimer_assignment_email_optin_submitted', $user_id, $source );

		return new WP_REST_Response(
			[
				'success' => true,
				'status'  => 'opted_in',
			],
			200
		);
	}
}
