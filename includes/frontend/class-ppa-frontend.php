<?php
/**
 * Frontend initialization
 *
 * Handles frontend asset enqueuing, script localization,
 * and file download/view request handling.
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
 * Frontend class
 *
 * Manages frontend CSS/JS asset loading (conditionally enqueued
 * by shortcodes) and registers the file download/view handler
 * on the init action.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Frontend {

	/**
	 * Whether frontend assets have been enqueued
	 *
	 * Prevents duplicate enqueue calls when multiple shortcodes
	 * appear on the same page.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $assets_enqueued = false;

	/**
	 * Initialize frontend
	 *
	 * Registers the file download handler on init.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'init', [ $this, 'handle_file_request' ] );
	}

	/**
	 * Enqueue frontend assets
	 *
	 * Called by shortcodes when they render. Only enqueues once
	 * per page load regardless of how many shortcodes are present.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_assets() {
		if ( $this->assets_enqueued ) {
			return;
		}

		$this->enqueue_styles();
		$this->enqueue_scripts();

		$this->assets_enqueued = true;
	}

	/**
	 * Enqueue frontend styles
	 *
	 * Loads the base submission stylesheet and the active theme stylesheet.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_styles() {
		$version = PRESSPRIMER_ASSIGNMENT_VERSION;

		wp_enqueue_style(
			'ppa-submission',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/css/submission.css',
			[],
			$version
		);

		// Load active theme CSS.
		$theme = $this->get_active_theme();

		wp_enqueue_style(
			'ppa-theme-' . $theme,
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/css/themes/' . $theme . '.css',
			[ 'ppa-submission' ],
			$version
		);
	}

	/**
	 * Enqueue frontend scripts
	 *
	 * Loads submission and document viewer scripts with localized data.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_scripts() {
		$version = PRESSPRIMER_ASSIGNMENT_VERSION;

		wp_enqueue_script(
			'ppa-submission',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/js/submission.js',
			[ 'jquery' ],
			$version,
			true
		);

		wp_enqueue_script(
			'ppa-document-viewer',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/js/document-viewer.js',
			[ 'jquery' ],
			$version,
			true
		);

		$this->localize_scripts();
	}

	/**
	 * Localize scripts with nonces and i18n strings
	 *
	 * Passes server-side data to JavaScript via wp_localize_script.
	 *
	 * @since 1.0.0
	 */
	private function localize_scripts() {
		$data = [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'restUrl'   => rest_url( 'ppa/v1/' ),
			'nonce'     => wp_create_nonce( 'ppa_frontend_nonce' ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'i18n'      => [
				'uploading'        => __( 'Uploading...', 'pressprimer-assignment' ),
				'uploadComplete'   => __( 'Upload complete.', 'pressprimer-assignment' ),
				'uploadFailed'     => __( 'Upload failed. Please try again.', 'pressprimer-assignment' ),
				'submitting'       => __( 'Submitting...', 'pressprimer-assignment' ),
				'submitted'        => __( 'Submission complete.', 'pressprimer-assignment' ),
				'confirmSubmit'    => __( 'Are you sure you want to submit? This action cannot be undone.', 'pressprimer-assignment' ),
				'removeFile'       => __( 'Remove file', 'pressprimer-assignment' ),
				'dragDropHere'     => __( 'Drag and drop files here or click to browse', 'pressprimer-assignment' ),
				'maxFilesReached'  => __( 'Maximum number of files reached.', 'pressprimer-assignment' ),
				'fileTooLarge'     => __( 'File is too large.', 'pressprimer-assignment' ),
				'invalidFileType'  => __( 'File type not allowed.', 'pressprimer-assignment' ),
				'networkError'     => __( 'A network error occurred. Please try again.', 'pressprimer-assignment' ),
				'submitAssignment' => __( 'Submit Assignment', 'pressprimer-assignment' ),
			],
		];

		/**
		 * Filters the localized frontend script data.
		 *
		 * @since 1.0.0
		 *
		 * @param array $data Localized data array.
		 */
		$data = apply_filters( 'pressprimer_assignment_frontend_script_data', $data );

		wp_localize_script( 'ppa-submission', 'ppaFrontend', $data );
	}

	/**
	 * Get the active frontend theme
	 *
	 * Returns the theme slug from plugin settings, defaulting to 'default'.
	 *
	 * @since 1.0.0
	 *
	 * @return string Theme slug.
	 */
	private function get_active_theme() {
		$theme = get_option( 'ppa_frontend_theme', 'default' );

		$valid_themes = [ 'default', 'modern', 'minimal' ];

		if ( ! in_array( $theme, $valid_themes, true ) ) {
			$theme = 'default';
		}

		return $theme;
	}

	/**
	 * Handle file download/view requests
	 *
	 * Intercepts requests with ppa_file_action parameter and serves
	 * the requested file after permission checks.
	 *
	 * URL format: ?ppa_file_action=download&ppa_file_id=123&ppa_nonce=abc
	 *
	 * @since 1.0.0
	 */
	public function handle_file_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below.
		if ( empty( $_GET['ppa_file_action'] ) || empty( $_GET['ppa_file_id'] ) ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_GET['ppa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['ppa_nonce'] ) ), 'ppa_file_action' ) ) {
			wp_die(
				esc_html__( 'Security check failed.', 'pressprimer-assignment' ),
				esc_html__( 'Error', 'pressprimer-assignment' ),
				[ 'response' => 403 ]
			);
		}

		// Require login.
		if ( ! is_user_logged_in() ) {
			wp_die(
				esc_html__( 'You must be logged in to access files.', 'pressprimer-assignment' ),
				esc_html__( 'Error', 'pressprimer-assignment' ),
				[ 'response' => 403 ]
			);
		}

		$action  = sanitize_text_field( wp_unslash( $_GET['ppa_file_action'] ) );
		$file_id = absint( $_GET['ppa_file_id'] );

		if ( ! in_array( $action, [ 'download', 'view' ], true ) || 0 === $file_id ) {
			wp_die(
				esc_html__( 'Invalid request.', 'pressprimer-assignment' ),
				esc_html__( 'Error', 'pressprimer-assignment' ),
				[ 'response' => 400 ]
			);
		}

		$file_service = new PressPrimer_Assignment_File_Service();
		$result       = $file_service->serve_file( $file_id );

		// serve_file exits on success, so if we get here there was an error.
		if ( is_wp_error( $result ) ) {
			$status_code = 'ppa_access_denied' === $result->get_error_code() ? 403 : 404;
			wp_die(
				esc_html( $result->get_error_message() ),
				esc_html__( 'Error', 'pressprimer-assignment' ),
				[ 'response' => (int) $status_code ]
			);
		}
	}

	/**
	 * Generate a file download URL
	 *
	 * Creates a nonced URL for downloading a submission file.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $file_id File record ID.
	 * @param string $action  Action type: 'download' or 'view'.
	 * @return string Download URL.
	 */
	public static function get_file_url( $file_id, $action = 'download' ) {
		return add_query_arg(
			[
				'ppa_file_action' => $action,
				'ppa_file_id'     => absint( $file_id ),
				'ppa_nonce'       => wp_create_nonce( 'ppa_file_action' ),
			],
			home_url( '/' )
		);
	}
}
