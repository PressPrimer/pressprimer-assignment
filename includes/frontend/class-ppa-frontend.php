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
	 * Whether text editor assets have been enqueued
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $text_editor_enqueued = false;

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
	 * Enqueue text editor assets
	 *
	 * Loads the text editor stylesheet when an assignment uses
	 * text-based submissions. Called by the renderer when rendering
	 * a text editor template.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_text_editor_assets() {
		if ( $this->text_editor_enqueued ) {
			return;
		}

		$version = PRESSPRIMER_ASSIGNMENT_VERSION;

		wp_enqueue_style(
			'ppa-text-editor',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/css/text-editor.css',
			[ 'ppa-submission' ],
			$version
		);

		wp_enqueue_script(
			'ppa-text-editor',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/js/text-editor.js',
			[ 'jquery', 'ppa-submission' ],
			$version,
			true
		);

		// Ensure WordPress editor scripts are loaded for wp_editor() on frontend.
		wp_enqueue_editor();

		$this->text_editor_enqueued = true;
	}

	/**
	 * Enqueue dashboard assets
	 *
	 * Loads the base assets plus the My Submissions dashboard stylesheet.
	 * Called by the [pressprimer_assignment_my_submissions] shortcode.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_dashboard_assets() {
		$this->enqueue_assets();

		$version = PRESSPRIMER_ASSIGNMENT_VERSION;

		wp_enqueue_style(
			'ppa-my-submissions',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/css/my-submissions.css',
			[ 'ppa-submission' ],
			$version
		);
	}

	/**
	 * Enqueue frontend styles
	 *
	 * Loads the base submission stylesheet, all theme stylesheets
	 * (to support per-assignment theme overrides), and any appearance
	 * setting overrides as inline CSS.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_styles() {
		$version = PRESSPRIMER_ASSIGNMENT_VERSION;

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'ppa-submission',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/css/submission.css',
			[ 'dashicons' ],
			$version
		);

		// Load all theme CSS files so per-assignment themes work.
		$themes = [ 'default', 'modern', 'minimal' ];
		foreach ( $themes as $theme_slug ) {
			wp_enqueue_style(
				'ppa-theme-' . $theme_slug,
				PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/css/themes/' . $theme_slug . '.css',
				[ 'ppa-submission' ],
				$version
			);
		}

		// Apply appearance setting overrides to all themes.
		$inline_css = $this->generate_appearance_overrides( $themes );
		if ( $inline_css ) {
			// Attach to the last theme handle so it appears after all theme CSS.
			$last_theme = end( $themes );
			wp_add_inline_style( 'ppa-theme-' . $last_theme, $inline_css );
		}
	}

	/**
	 * Generate inline CSS from appearance settings
	 *
	 * Reads customization values from plugin settings and produces
	 * CSS variable overrides scoped to all theme selectors so that
	 * overrides work regardless of which theme an assignment uses.
	 *
	 * @since 1.0.0
	 *
	 * @param array $themes List of theme slugs to generate selectors for.
	 * @return string Inline CSS string, or empty string if no overrides.
	 */
	private function generate_appearance_overrides( $themes ) {
		$settings = get_option( 'pressprimer_assignment_settings', [] );

		$overrides = [];

		// Color overrides.
		$color_keys = [
			'appearance_primary_color'    => '--ppa-primary',
			'appearance_text_color'       => '--ppa-text',
			'appearance_background_color' => '--ppa-background',
			'appearance_success_color'    => '--ppa-success',
			'appearance_error_color'      => '--ppa-error',
		];

		foreach ( $color_keys as $setting_key => $css_var ) {
			if ( ! empty( $settings[ $setting_key ] ) ) {
				$color = sanitize_hex_color( $settings[ $setting_key ] );
				if ( $color ) {
					$overrides[ $css_var ] = $color;

					// Generate derived variants for primary color.
					if ( '--ppa-primary' === $css_var ) {
						$overrides['--ppa-primary-hover'] = $this->adjust_brightness( $color, -20 );
						$overrides['--ppa-primary-dark']  = $this->adjust_brightness( $color, -20 );
						$overrides['--ppa-primary-light'] = $this->hex_to_rgba( $color, 0.1 );
						$overrides['--ppa-primary-rgb']   = $this->hex_to_rgb_values( $color );
						$overrides['--ppa-border-focus']  = $color;
						$overrides['--ppa-shadow-focus']  = '0 0 0 3px ' . $this->hex_to_rgba( $color, 0.25 );
					}

					// Generate derived variants for success color.
					if ( '--ppa-success' === $css_var ) {
						$overrides['--ppa-success-hover'] = $this->adjust_brightness( $color, -15 );
						$overrides['--ppa-success-light'] = $this->hex_to_rgba( $color, 0.1 );
					}

					// Generate derived variants for error color.
					if ( '--ppa-error' === $css_var ) {
						$overrides['--ppa-error-hover'] = $this->adjust_brightness( $color, -15 );
						$overrides['--ppa-error-light'] = $this->hex_to_rgba( $color, 0.1 );
					}

					// Generate derived variants for text color.
					if ( '--ppa-text' === $css_var ) {
						$overrides['--ppa-text-secondary'] = $this->adjust_brightness( $color, 40 );
						$overrides['--ppa-text-light']     = $this->adjust_brightness( $color, 80 );
					}

					// Generate derived variants for background color.
					if ( '--ppa-background' === $css_var ) {
						$overrides['--ppa-background-gray']  = $this->adjust_brightness( $color, -3 );
						$overrides['--ppa-background-hover'] = $this->adjust_brightness( $color, -6 );
					}
				}
			}
		}

		// Typography overrides.
		// Font family is stored as a full CSS font-family string (e.g., 'Georgia, "Times New Roman", Times, serif').
		if ( ! empty( $settings['appearance_font_family'] ) ) {
			$overrides['--ppa-font-sans'] = sanitize_text_field( $settings['appearance_font_family'] );
		}

		// Font size is stored as a pixel string (e.g., '18px').
		if ( ! empty( $settings['appearance_font_size'] ) ) {
			$base = (int) $settings['appearance_font_size'];
			if ( $base >= 12 && $base <= 24 ) {
				$overrides['--ppa-font-size-base'] = $base . 'px';
				$overrides['--ppa-font-size-xs']   = round( $base * 0.75 ) . 'px';
				$overrides['--ppa-font-size-sm']   = round( $base * 0.875 ) . 'px';
				$overrides['--ppa-font-size-lg']   = round( $base * 1.125 ) . 'px';
				$overrides['--ppa-font-size-xl']   = round( $base * 1.25 ) . 'px';
				$overrides['--ppa-font-size-2xl']  = round( $base * 1.5 ) . 'px';
				$overrides['--ppa-font-size-3xl']  = round( $base * 2.0 ) . 'px';
			}
		}

		// Layout overrides.
		if ( ! empty( $settings['appearance_border_radius'] ) || 0 === ( $settings['appearance_border_radius'] ?? '' ) ) {
			$radius = (int) $settings['appearance_border_radius'];
			if ( $radius >= 0 && $radius <= 24 ) {
				$sm                           = max( 0, $radius - 2 );
				$overrides['--ppa-radius-sm'] = $sm . 'px';
				$overrides['--ppa-radius-md'] = $radius . 'px';
				$overrides['--ppa-radius-lg'] = round( $radius * 1.33 ) . 'px';
				$overrides['--ppa-radius-xl'] = ( $radius * 2 ) . 'px';
			}
		}

		if ( ! empty( $settings['appearance_max_width'] ) ) {
			$max_width = (int) $settings['appearance_max_width'];
			if ( $max_width >= 400 && $max_width <= 1200 ) {
				$overrides['--ppa-max-width'] = $max_width . 'px';
			}
		}

		// Spacing overrides.
		if ( ! empty( $settings['appearance_line_height'] ) ) {
			$line_height = (float) $settings['appearance_line_height'];
			if ( $line_height >= 1.2 && $line_height <= 1.8 ) {
				$overrides['--ppa-line-height'] = number_format( $line_height, 2 );
			}
		}

		if ( empty( $overrides ) ) {
			return '';
		}

		// Build CSS variable declarations.
		$declarations = '';
		foreach ( $overrides as $property => $value ) {
			$declarations .= "\t" . $property . ': ' . $value . ";\n";
		}

		// Build combined selector covering all themes.
		$selectors = [];
		foreach ( $themes as $theme_slug ) {
			$selectors[] = '.ppa-theme-' . $theme_slug;
			$selectors[] = '.ppa-assignment.ppa-theme-' . $theme_slug;
			$selectors[] = '.ppa-my-submissions.ppa-theme-' . $theme_slug;
		}

		$css  = implode( ",\n", $selectors ) . " {\n";
		$css .= $declarations;
		$css .= "}\n";

		return $css;
	}

	/**
	 * Adjust hex color brightness
	 *
	 * @since 1.0.0
	 *
	 * @param string $hex    Hex color value.
	 * @param int    $amount Amount to adjust (-255 to 255).
	 * @return string Adjusted hex color.
	 */
	private function adjust_brightness( $hex, $amount ) {
		$hex = ltrim( $hex, '#' );

		$r = max( 0, min( 255, hexdec( substr( $hex, 0, 2 ) ) + $amount ) );
		$g = max( 0, min( 255, hexdec( substr( $hex, 2, 2 ) ) + $amount ) );
		$b = max( 0, min( 255, hexdec( substr( $hex, 4, 2 ) ) + $amount ) );

		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}

	/**
	 * Convert hex color to RGBA string
	 *
	 * @since 1.0.0
	 *
	 * @param string $hex   Hex color value.
	 * @param float  $alpha Alpha transparency (0-1).
	 * @return string RGBA CSS value.
	 */
	private function hex_to_rgba( $hex, $alpha ) {
		$hex = ltrim( $hex, '#' );

		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		return 'rgba(' . $r . ', ' . $g . ', ' . $b . ', ' . $alpha . ')';
	}

	/**
	 * Convert hex color to comma-separated RGB values
	 *
	 * @since 1.0.0
	 *
	 * @param string $hex Hex color value.
	 * @return string Comma-separated RGB values (e.g., "0, 115, 170").
	 */
	private function hex_to_rgb_values( $hex ) {
		$hex = ltrim( $hex, '#' );

		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		return $r . ', ' . $g . ', ' . $b;
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
			'nonce'     => wp_create_nonce( 'pressprimer_assignment_frontend_nonce' ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'textNonce' => wp_create_nonce( 'pressprimer_assignment_save_text_draft' ),
			'i18n'      => [
				'uploading'              => __( 'Uploading...', 'pressprimer-assignment' ),
				'uploadComplete'         => __( 'Upload complete.', 'pressprimer-assignment' ),
				'uploadFailed'           => __( 'Upload failed. Please try again.', 'pressprimer-assignment' ),
				'submitting'             => __( 'Submitting...', 'pressprimer-assignment' ),
				'submitted'              => __( 'Submission complete.', 'pressprimer-assignment' ),
				'confirmSubmit'          => __( 'Are you sure you want to submit? This action cannot be undone.', 'pressprimer-assignment' ),
				'removeFile'             => __( 'Remove file', 'pressprimer-assignment' ),
				'dragDropHere'           => __( 'Drag and drop files here or click to browse', 'pressprimer-assignment' ),
				'maxFilesReached'        => __( 'Maximum number of files reached.', 'pressprimer-assignment' ),
				/* translators: %1$d: number of files uploaded, %2$d: maximum files allowed */
				'filesUploaded'          => __( '%1$d of %2$d files uploaded.', 'pressprimer-assignment' ),
				'fileTooLarge'           => __( 'File is too large.', 'pressprimer-assignment' ),
				'invalidFileType'        => __( 'File type not allowed.', 'pressprimer-assignment' ),
				'networkError'           => __( 'A network error occurred. Please try again.', 'pressprimer-assignment' ),
				'submitAssignment'       => __( 'Submit Assignment', 'pressprimer-assignment' ),
				'changeType'             => __( 'Change submission type', 'pressprimer-assignment' ),
				'confirmTitle'           => __( 'Ready to submit?', 'pressprimer-assignment' ),
				'confirmMessage'         => __( 'Once submitted, your assignment will be sent for review.', 'pressprimer-assignment' ),
				'confirmMessageResub'    => __( 'Once submitted, your assignment will be sent for review. You can submit again if needed.', 'pressprimer-assignment' ),
				/* translators: %d: number of submissions remaining */
				'resubmissionLeft'       => __( '%d submission remaining', 'pressprimer-assignment' ),
				/* translators: %d: number of submissions remaining */
				'resubmissionsLeft'      => __( '%d submissions remaining', 'pressprimer-assignment' ),
				'cancel'                 => __( 'Cancel', 'pressprimer-assignment' ),
				'draftSaved'             => __( 'Draft saved', 'pressprimer-assignment' ),
				'saving'                 => __( 'Saving...', 'pressprimer-assignment' ),
				'unsavedChanges'         => __( 'Unsaved changes', 'pressprimer-assignment' ),
				'saveFailed'             => __( 'Save failed', 'pressprimer-assignment' ),
				'emptyContent'           => __( 'Please write something before submitting.', 'pressprimer-assignment' ),
				'saveDraft'              => __( 'Save Draft', 'pressprimer-assignment' ),
				'confirmDelete'          => __( 'Are you sure you want to delete this submission? This cannot be undone.', 'pressprimer-assignment' ),
				'confirmDeleteTitle'     => __( 'Delete Submission', 'pressprimer-assignment' ),
				'confirmDeleteButton'    => __( 'Delete', 'pressprimer-assignment' ),
				'previewTitle'           => __( 'Review Your Submission', 'pressprimer-assignment' ),
				'previewGoBack'          => __( 'Go Back & Edit', 'pressprimer-assignment' ),
				'previewConfirm'         => __( 'Confirm & Submit', 'pressprimer-assignment' ),
				'previewAssignment'      => __( 'Assignment', 'pressprimer-assignment' ),
				'previewFiles'           => __( 'Files', 'pressprimer-assignment' ),
				'previewTotalSize'       => __( 'Total size', 'pressprimer-assignment' ),
				'previewNotes'           => __( 'Your Notes', 'pressprimer-assignment' ),
				'previewYourSubmission'  => __( 'Your Submission', 'pressprimer-assignment' ),
				'previewWordCount'       => __( 'Word count', 'pressprimer-assignment' ),
				'textPreviewLabel'       => __( 'Extracted Text Preview', 'pressprimer-assignment' ),
				'textPreviewShow'        => __( 'Show preview', 'pressprimer-assignment' ),
				'textPreviewHide'        => __( 'Hide preview', 'pressprimer-assignment' ),
				'textPreviewDescription' => __( 'This is the beginning of the text extracted from your file. It is provided so you can verify the content was captured correctly.', 'pressprimer-assignment' ),
				'pdfWarningShort'        => __( 'Text extraction issue', 'pressprimer-assignment' ),
				'pdfWarningTitle'        => __( 'Text Extraction Issue', 'pressprimer-assignment' ),
				'pdfWarningMessage'      => __( 'We could not extract readable text from one or more PDF files. If the assignment is text-based, your instructor may have trouble extracting the text for review.', 'pressprimer-assignment' ),
				'pdfWarningWhy'          => __( 'Why does this matter?', 'pressprimer-assignment' ),
				'pdfWarningDetails'      => __( 'Some PDFs are scanned images without embedded text. For best results, consider using the text editor or uploading a DOCX file instead.', 'pressprimer-assignment' ),
				'uploadHint'             => __( 'Upload at least one file to enable submission.', 'pressprimer-assignment' ),
				'extractionFailed'       => __( 'Text could not be extracted from this file. It may be a scanned document or contain only images.', 'pressprimer-assignment' ),
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

		wp_localize_script( 'ppa-submission', 'pressprimerAssignmentFrontend', $data );
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
		$theme = get_option( 'pressprimer_assignment_frontend_theme', 'default' );

		$valid_themes = [ 'default', 'modern', 'minimal' ];

		if ( ! in_array( $theme, $valid_themes, true ) ) {
			$theme = 'default';
		}

		return $theme;
	}

	/**
	 * Handle file download/view requests
	 *
	 * Intercepts requests with pressprimer_assignment_file_action parameter and serves
	 * the requested file after permission checks.
	 *
	 * URL format: ?pressprimer_assignment_file_action=download&pressprimer_assignment_file_id=123&pressprimer_assignment_nonce=abc
	 *
	 * @since 1.0.0
	 */
	public function handle_file_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below.
		if ( empty( $_GET['pressprimer_assignment_file_action'] ) || empty( $_GET['pressprimer_assignment_file_id'] ) ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_GET['pressprimer_assignment_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['pressprimer_assignment_nonce'] ) ), 'pressprimer_assignment_file_action' ) ) {
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

		$action  = sanitize_text_field( wp_unslash( $_GET['pressprimer_assignment_file_action'] ) );
		$file_id = absint( wp_unslash( $_GET['pressprimer_assignment_file_id'] ) );

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
				'pressprimer_assignment_file_action' => $action,
				'pressprimer_assignment_file_id'     => absint( $file_id ),
				'pressprimer_assignment_nonce'       => wp_create_nonce( 'pressprimer_assignment_file_action' ),
			],
			home_url( '/' )
		);
	}
}
