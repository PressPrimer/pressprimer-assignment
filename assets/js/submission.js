/**
 * PressPrimer Assignment - Submission JavaScript
 *
 * Handles file upload (drag/drop, progress, validation) and
 * submission form interactions (character count, submit button).
 *
 * Follows the same IIFE + jQuery pattern as PressPrimer Quiz.
 *
 * @package
 * @since 1.0.0
 */

/* global jQuery, pressprimerAssignmentFrontend, XMLHttpRequest */

( function ( $ ) {
	'use strict';

	/**
	 * Global PPA namespace.
	 */
	window.PPA = window.PPA || {};

	/* =========================================================================
	   PPA.Upload - File Upload Module
	   ========================================================================= */

	/**
	 * File upload module.
	 *
	 * Manages drag-and-drop, click-to-browse, client-side validation,
	 * XHR uploads with progress, and file list management.
	 */
	window.PPA.Upload = {
		/**
		 * Uploaded file records (server responses).
		 *
		 * @type {Array}
		 */
		files: [],

		/**
		 * jQuery reference to the upload container element.
		 *
		 * @type {jQuery|null}
		 */
		$container: null,

		/**
		 * jQuery reference to the drop zone element.
		 *
		 * @type {jQuery|null}
		 */
		$dropZone: null,

		/**
		 * jQuery reference to the hidden file input.
		 *
		 * @type {jQuery|null}
		 */
		$fileInput: null,

		/**
		 * jQuery reference to the file list container.
		 *
		 * @type {jQuery|null}
		 */
		$fileList: null,

		/**
		 * Allowed file extensions from assignment config.
		 *
		 * @type {Array}
		 */
		allowedTypes: [],

		/**
		 * Maximum file size in bytes.
		 *
		 * @type {number}
		 */
		maxSize: 0,

		/**
		 * Maximum number of files.
		 *
		 * @type {number}
		 */
		maxFiles: 0,

		/**
		 * Assignment ID.
		 *
		 * @type {number}
		 */
		assignmentId: 0,

		/**
		 * Number of uploads currently in progress.
		 *
		 * @type {number}
		 */
		uploadsInProgress: 0,

		/**
		 * Initialize the upload module.
		 *
		 * Reads configuration from the container's data attributes
		 * and binds all event handlers.
		 *
		 * @param {jQuery} $container The .ppa-upload-container element.
		 */
		init( $container ) {
			if ( ! $container.length ) {
				return;
			}

			this.$container = $container;
			this.$dropZone = $container.find( '.ppa-upload-zone' );
			this.$fileInput = $container.find( '.ppa-upload-input' );
			this.$fileList = $container.find( '.ppa-file-list' );

			// Read configuration from data attributes.
			this.assignmentId =
				parseInt( $container.data( 'assignment-id' ), 10 ) || 0;
			this.maxSize = parseInt( $container.data( 'max-size' ), 10 ) || 0;
			this.maxFiles = parseInt( $container.data( 'max-files' ), 10 ) || 5;

			const rawTypes = $container.data( 'allowed-types' );
			if ( typeof rawTypes === 'string' ) {
				try {
					this.allowedTypes = JSON.parse( rawTypes );
				} catch ( e ) {
					this.allowedTypes = [];
				}
			} else if ( Array.isArray( rawTypes ) ) {
				this.allowedTypes = rawTypes;
			}

			this.files = [];
			this.uploadsInProgress = 0;

			this.createScreenReaderRegion();
			this.bindEvents();
		},

		/**
		 * Bind all event handlers.
		 *
		 * Sets up drag/drop, click-to-browse, keyboard, and
		 * delegated remove-button events.
		 */
		bindEvents() {
			const self = this;

			// Drag-and-drop events.
			this.$dropZone.on( 'dragover.ppaUpload', function ( e ) {
				self.handleDragOver( e );
			} );
			this.$dropZone.on( 'dragleave.ppaUpload', function ( e ) {
				self.handleDragLeave( e );
			} );
			this.$dropZone.on( 'drop.ppaUpload', function ( e ) {
				self.handleDrop( e );
			} );

			// Click to open file browser.
			this.$dropZone.on( 'click.ppaUpload', function () {
				self.$fileInput.trigger( 'click' );
			} );

			// File input change.
			this.$fileInput.on( 'change.ppaUpload', function () {
				self.handleFileSelect( this );
			} );

			// Keyboard accessibility: Enter or Space opens file browser.
			this.$dropZone.on( 'keydown.ppaUpload', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					self.$fileInput.trigger( 'click' );
				}
			} );

			// Delegated click on remove buttons within the file list.
			this.$fileList.on(
				'click.ppaUpload',
				'.ppa-file-remove',
				function ( e ) {
					e.preventDefault();
					self.removeFile( $( this ).closest( '.ppa-file-item' ) );
				}
			);
		},

		/* -----------------------------------------------------------------
		   Drag-and-Drop Handlers
		   ----------------------------------------------------------------- */

		/**
		 * Handle dragover event.
		 *
		 * @param {jQuery.Event} e The dragover event.
		 */
		handleDragOver( e ) {
			e.preventDefault();
			e.originalEvent.dataTransfer.dropEffect = 'copy';
			this.$dropZone.addClass( 'ppa-upload-active' );
		},

		/**
		 * Handle dragleave event.
		 *
		 * @param {jQuery.Event} e The dragleave event.
		 */
		handleDragLeave( e ) {
			e.preventDefault();
			this.$dropZone.removeClass( 'ppa-upload-active' );
		},

		/**
		 * Handle drop event.
		 *
		 * @param {jQuery.Event} e The drop event.
		 */
		handleDrop( e ) {
			e.preventDefault();
			this.$dropZone.removeClass( 'ppa-upload-active' );

			const files = e.originalEvent.dataTransfer.files;
			if ( files && files.length ) {
				this.processFiles( files );
			}
		},

		/**
		 * Handle file input change.
		 *
		 * @param {HTMLInputElement} input The file input element.
		 */
		handleFileSelect( input ) {
			if ( input.files && input.files.length ) {
				this.processFiles( input.files );
			}

			// Reset input so re-selecting the same file triggers change.
			$( input ).val( '' );
		},

		/* -----------------------------------------------------------------
		   File Processing
		   ----------------------------------------------------------------- */

		/**
		 * Process a FileList, validating and uploading each file.
		 *
		 * @param {FileList} fileList The FileList from input or drop.
		 */
		processFiles( fileList ) {
			const files = Array.prototype.slice.call( fileList );

			for ( let i = 0; i < files.length; i++ ) {
				if ( this.validateFile( files[ i ] ) ) {
					this.uploadFile( files[ i ] );
				}
			}
		},

		/**
		 * Validate a file against assignment constraints.
		 *
		 * Checks extension, file size, file count, and zero-byte files.
		 * Shows an error and announces to screen readers on failure.
		 *
		 * @param {File} file The File object to validate.
		 * @return {boolean} True if valid, false otherwise.
		 */
		validateFile( file ) {
			const totalFiles = this.files.length + this.uploadsInProgress;

			// Check file count limit.
			if ( totalFiles >= this.maxFiles ) {
				this.showError(
					pressprimerAssignmentFrontend.i18n.maxFilesReached
				);
				return false;
			}

			const extension = file.name.split( '.' ).pop().toLowerCase();

			// Check file type.
			if (
				this.allowedTypes.length &&
				this.allowedTypes.indexOf( extension ) === -1
			) {
				this.showError(
					this.escapeHtml( file.name ) +
						': ' +
						pressprimerAssignmentFrontend.i18n.invalidFileType
				);
				return false;
			}

			// Check file size.
			if ( this.maxSize > 0 && file.size > this.maxSize ) {
				this.showError(
					this.escapeHtml( file.name ) +
						': ' +
						pressprimerAssignmentFrontend.i18n.fileTooLarge
				);
				return false;
			}

			// Reject zero-byte files.
			if ( file.size === 0 ) {
				this.showError(
					this.escapeHtml( file.name ) +
						': ' +
						pressprimerAssignmentFrontend.i18n.uploadFailed
				);
				return false;
			}

			return true;
		},

		/* -----------------------------------------------------------------
		   Upload via XHR
		   ----------------------------------------------------------------- */

		/**
		 * Upload a single file via XMLHttpRequest with progress tracking.
		 *
		 * Creates a file item in the list, sends the file to the server,
		 * and updates the UI based on the response.
		 *
		 * @param {File} file The File object to upload.
		 */
		uploadFile( file ) {
			const self = this;
			const $item = this.createFileItem( file );
			const $progressBar = $item.find( '.ppa-file-progress-bar' );
			const $progressText = $item.find( '.ppa-file-progress-text' );
			const formData = new FormData();

			this.uploadsInProgress++;
			window.PPA.SubmissionForm.updateSubmitButton();

			formData.append( 'action', 'ppa_upload_file' );
			formData.append( 'file', file );
			formData.append( 'assignment_id', this.assignmentId );
			formData.append( 'nonce', pressprimerAssignmentFrontend.nonce );

			// Send known file IDs so the server can sync stale drafts.
			const knownIds = $.map( this.files, function ( f ) {
				return f.id;
			} );
			formData.append( 'known_file_ids', JSON.stringify( knownIds ) );

			const xhr = new XMLHttpRequest();

			// Track upload progress.
			xhr.upload.addEventListener( 'progress', function ( e ) {
				if ( e.lengthComputable ) {
					const percent = Math.round( ( e.loaded / e.total ) * 100 );
					$progressBar.css( 'width', percent + '%' );
					$progressText.text( percent + '%' );

					// Announce milestones to screen readers.
					if ( percent === 50 || percent === 100 ) {
						self.announceToScreenReader(
							self.escapeHtml( file.name ) + ' ' + percent + '%'
						);
					}
				}
			} );

			// Handle successful response.
			xhr.addEventListener( 'load', function () {
				self.uploadsInProgress--;

				if ( xhr.status === 200 ) {
					let response;
					try {
						response = JSON.parse( xhr.responseText );
					} catch ( e ) {
						self.handleUploadError( $item, file.name );
						return;
					}

					if ( response.success && response.data ) {
						self.handleUploadSuccess(
							$item,
							file.name,
							response.data
						);
					} else {
						const message =
							response.data && response.data.message
								? response.data.message
								: pressprimerAssignmentFrontend.i18n
										.uploadFailed;
						self.handleUploadError( $item, file.name, message );
					}
				} else {
					// Parse error message from non-200 responses.
					let errorMessage =
						pressprimerAssignmentFrontend.i18n.uploadFailed;
					try {
						const errorResponse = JSON.parse( xhr.responseText );
						if (
							errorResponse.data &&
							errorResponse.data.message
						) {
							errorMessage = errorResponse.data.message;
						}
					} catch ( e ) {
						// Use default message if response isn't valid JSON.
					}
					self.handleUploadError( $item, file.name, errorMessage );
				}

				window.PPA.SubmissionForm.updateSubmitButton();
			} );

			// Handle network errors.
			xhr.addEventListener( 'error', function () {
				self.uploadsInProgress--;
				self.handleUploadError(
					$item,
					file.name,
					pressprimerAssignmentFrontend.i18n.networkError
				);
				window.PPA.SubmissionForm.updateSubmitButton();
			} );

			// Handle aborted uploads.
			xhr.addEventListener( 'abort', function () {
				self.uploadsInProgress--;
				$item.remove();
				window.PPA.SubmissionForm.updateSubmitButton();
			} );

			// Store XHR reference for potential cancellation.
			$item.data( 'xhr', xhr );

			xhr.open( 'POST', pressprimerAssignmentFrontend.ajaxUrl );
			xhr.send( formData );
		},

		/**
		 * Handle a successful file upload.
		 *
		 * @param {jQuery} $item    The file item element.
		 * @param {string} fileName The original file name.
		 * @param {Object} data     Server response data (id, name, size, type).
		 */
		handleUploadSuccess( $item, fileName, data ) {
			this.files.push( data );
			$item
				.removeClass( 'ppa-file-uploading' )
				.addClass( 'ppa-file-complete' );
			$item.attr( 'data-file-id', data.id );

			// Replace progress bar with file size.
			$item.find( '.ppa-file-progress' ).remove();
			$item.find( '.ppa-file-size' ).text( this.formatSize( data.size ) );

			// Show remove button.
			$item.find( '.ppa-file-remove' ).removeClass( 'ppa-hidden' );

			// Show PDF text preview or extraction failure notice.
			if ( data.text_preview ) {
				this.showTextPreview( $item, data.text_preview );
			} else if (
				data.text_extractable === false ||
				data.text_extractable === 0
			) {
				this.showExtractionNotice( $item );
			}

			this.announceToScreenReader(
				this.escapeHtml( fileName ) +
					' — ' +
					pressprimerAssignmentFrontend.i18n.uploadComplete
			);

			this.updateUploadZoneState();
		},

		/**
		 * Handle a failed file upload.
		 *
		 * @param {jQuery} $item    The file item element.
		 * @param {string} fileName The original file name.
		 * @param {string} message  Optional error message.
		 */
		handleUploadError( $item, fileName, message ) {
			message =
				message || pressprimerAssignmentFrontend.i18n.uploadFailed;

			$item
				.removeClass( 'ppa-file-uploading' )
				.addClass( 'ppa-file-error' );

			// Replace progress with error message.
			$item.find( '.ppa-file-progress' ).remove();
			$item.find( '.ppa-file-size' ).text( message );

			// Show remove button so user can dismiss.
			$item.find( '.ppa-file-remove' ).removeClass( 'ppa-hidden' );

			this.announceToScreenReader(
				this.escapeHtml( fileName ) + ' — ' + message,
				'assertive'
			);
		},

		/* -----------------------------------------------------------------
		   PDF Text Preview
		   ----------------------------------------------------------------- */

		/**
		 * Show a collapsible text preview below a PDF file item.
		 *
		 * Displays the first ~1000 characters of extracted PDF text
		 * in a scrollable box, matching the PressPrimer Quiz pattern.
		 *
		 * @param {jQuery} $item       The file item element.
		 * @param {string} previewText The extracted text to display.
		 */
		showTextPreview( $item, previewText ) {
			const i18n = pressprimerAssignmentFrontend.i18n || {};
			const label = i18n.textPreviewLabel || 'Extracted Text Preview';
			const toggleShow = i18n.textPreviewShow || 'Show preview';
			const toggleHide = i18n.textPreviewHide || 'Hide preview';

			const $wrapper = $( '<div>' ).addClass( 'ppa-file-text-preview' );

			// Toggle button with dashicon span (matches Quiz pattern).
			const $toggle = $( '<button>' )
				.attr( 'type', 'button' )
				.addClass( 'ppa-text-preview-toggle' )
				.attr( 'aria-expanded', 'false' )
				.append(
					$( '<span>' ).addClass( 'dashicons dashicons-arrow-right' )
				)
				.append( ' ' + toggleShow );

			// Content (initially hidden, shown with slideDown like Quiz).
			const $content = $( '<div>' )
				.addClass( 'ppa-text-preview-content' )
				.css( 'display', 'none' );

			$content.append(
				$( '<div>' ).addClass( 'ppa-text-preview-label' ).text( label )
			);

			$content.append(
				$( '<p>' )
					.addClass( 'ppa-text-preview-description' )
					.text(
						i18n.textPreviewDescription ||
							'This is the beginning of the text extracted from your file. It is provided so you can verify the content was captured correctly.'
					)
			);

			$content.append(
				$( '<div>' )
					.addClass( 'ppa-text-preview-text' )
					.text( previewText )
			);

			$toggle.on( 'click', function () {
				const $icon = $toggle.find( '.dashicons' );
				if ( $content.is( ':visible' ) ) {
					$content.slideUp( 200 );
					$icon
						.removeClass( 'dashicons-arrow-down' )
						.addClass( 'dashicons-arrow-right' );
					$toggle
						.contents()
						.filter( function () {
							return this.nodeType === 3;
						} )
						.last()
						.replaceWith( ' ' + toggleShow );
					$toggle.attr( 'aria-expanded', 'false' );
				} else {
					$content.slideDown( 200 );
					$icon
						.removeClass( 'dashicons-arrow-right' )
						.addClass( 'dashicons-arrow-down' );
					$toggle
						.contents()
						.filter( function () {
							return this.nodeType === 3;
						} )
						.last()
						.replaceWith( ' ' + toggleHide );
					$toggle.attr( 'aria-expanded', 'true' );
				}
			} );

			$wrapper.append( $toggle );
			$wrapper.append( $content );
			$item.after( $wrapper );
		},

		/**
		 * Show a notice when PDF text extraction failed.
		 *
		 * Displays a small inline message below the file item
		 * explaining that text could not be extracted.
		 *
		 * @param {jQuery} $item The file item element.
		 */
		showExtractionNotice( $item ) {
			const i18n = pressprimerAssignmentFrontend.i18n || {};
			const $notice = $( '<div>' )
				.addClass( 'ppa-file-text-preview ppa-extraction-notice' )
				.append(
					$( '<span>' )
						.addClass( 'dashicons dashicons-info-outline' )
						.attr( 'aria-hidden', 'true' )
				)
				.append(
					' ' +
						( i18n.extractionFailed ||
							'Text could not be extracted from this file. It may be a scanned document or contain only images.' )
				);

			$item.after( $notice );
		},

		/* -----------------------------------------------------------------
		   Upload Zone State
		   ----------------------------------------------------------------- */

		/**
		 * Update the upload zone visibility based on file count.
		 *
		 * When the maximum number of files has been reached, hides the
		 * drop zone and shows a status message. Restores it when files
		 * are removed and the count drops below the limit.
		 */
		updateUploadZoneState() {
			const atLimit = this.files.length >= this.maxFiles;
			const i18n = pressprimerAssignmentFrontend.i18n || {};

			if ( atLimit ) {
				this.$dropZone.addClass( 'ppa-upload-disabled' ).slideUp( 200 );
				this.$fileInput.prop( 'disabled', true );

				// Show status message if not already present.
				if (
					! this.$container.find( '.ppa-upload-limit-notice' ).length
				) {
					const statusText = (
						i18n.filesUploaded || '%1$d of %2$d files uploaded.'
					)
						.replace( '%1$d', this.files.length )
						.replace( '%2$d', this.maxFiles );

					const $notice = $( '<div>' )
						.addClass( 'ppa-upload-limit-notice' )
						.attr( 'role', 'status' )
						.append(
							$( '<span>' )
								.addClass( 'dashicons dashicons-yes-alt' )
								.attr( 'aria-hidden', 'true' )
						)
						.append( ' ' + statusText );

					this.$dropZone.before( $notice );
					$notice.hide().slideDown( 200 );
				}
			} else {
				// Remove the notice and restore the drop zone.
				this.$container
					.find( '.ppa-upload-limit-notice' )
					.slideUp( 200, function () {
						$( this ).remove();
					} );
				this.$dropZone
					.removeClass( 'ppa-upload-disabled' )
					.slideDown( 200 );
				this.$fileInput.prop( 'disabled', false );
			}
		},

		/* -----------------------------------------------------------------
		   File List DOM
		   ----------------------------------------------------------------- */

		/**
		 * Create a file item element in the file list.
		 *
		 * Builds the DOM for a single file row with progress bar,
		 * name, size placeholder, and hidden remove button.
		 *
		 * @param {File} file The File object.
		 * @return {jQuery} The created file item element.
		 */
		createFileItem( file ) {
			const extension = file.name.split( '.' ).pop().toUpperCase();
			const removeLabel =
				pressprimerAssignmentFrontend.i18n.removeFile +
				' ' +
				this.escapeHtml( file.name );

			const $item = $( '<div>' )
				.addClass( 'ppa-file-item ppa-file-uploading' )
				.attr( 'role', 'listitem' );

			const $info = $( '<div>' ).addClass( 'ppa-file-info' );

			$info.append(
				$( '<span>' )
					.addClass( 'ppa-file-icon' )
					.attr( 'aria-hidden', 'true' )
					.text( extension )
			);

			$info.append(
				$( '<span>' ).addClass( 'ppa-file-name' ).text( file.name )
			);

			$info.append( $( '<span>' ).addClass( 'ppa-file-size' ) );

			$item.append( $info );

			// Progress bar.
			const $progress = $( '<div>' ).addClass( 'ppa-file-progress' );
			$progress.append(
				$( '<div>' )
					.addClass( 'ppa-file-progress-bar' )
					.css( 'width', '0%' )
			);
			$progress.append(
				$( '<span>' ).addClass( 'ppa-file-progress-text' ).text( '0%' )
			);
			$item.append( $progress );

			// Actions (remove button, hidden until upload completes or errors).
			const $actions = $( '<div>' ).addClass( 'ppa-file-actions' );
			$actions.append(
				$( '<button>' )
					.attr( 'type', 'button' )
					.addClass( 'ppa-file-remove ppa-hidden' )
					.attr( 'aria-label', removeLabel )
					.html( '&times;' )
			);
			$item.append( $actions );

			this.$fileList.append( $item );
			return $item;
		},

		/**
		 * Remove a file item from the list and optionally from the server.
		 *
		 * If the file was successfully uploaded (has a file ID), sends
		 * a server request to delete it. Cancels in-progress uploads.
		 *
		 * @param {jQuery} $item The file item element to remove.
		 */
		removeFile( $item ) {
			const xhr = $item.data( 'xhr' );

			// Cancel in-progress upload.
			if ( $item.hasClass( 'ppa-file-uploading' ) && xhr ) {
				xhr.abort();
				return; // abort handler removes the item.
			}

			const fileId = $item.attr( 'data-file-id' );
			const self = this;

			// Remove from server if uploaded.
			if ( fileId ) {
				$.ajax( {
					url: pressprimerAssignmentFrontend.ajaxUrl,
					type: 'POST',
					data: {
						action: 'ppa_remove_file',
						file_id: parseInt( fileId, 10 ),
						nonce: pressprimerAssignmentFrontend.nonce,
					},
				} );

				// Remove from local files array.
				this.files = $.grep( this.files, function ( f ) {
					return parseInt( f.id, 10 ) !== parseInt( fileId, 10 );
				} );
			}

			// Remove the text preview sibling (if any).
			$item.next( '.ppa-file-text-preview' ).remove();

			// Animate removal.
			$item.fadeOut( 200, function () {
				$( this ).remove();
				self.announceToScreenReader(
					pressprimerAssignmentFrontend.i18n.removeFile
				);
				self.updateUploadZoneState();
				window.PPA.SubmissionForm.updateSubmitButton();
			} );
		},

		/* -----------------------------------------------------------------
		   Utilities
		   ----------------------------------------------------------------- */

		/**
		 * Format bytes into a human-readable size string.
		 *
		 * @param {number} bytes The number of bytes.
		 * @return {string} Formatted size (e.g., "2.5 MB").
		 */
		formatSize( bytes ) {
			const units = [ 'B', 'KB', 'MB', 'GB' ];
			let i = 0;
			let size = parseFloat( bytes ) || 0;

			while ( size >= 1024 && i < units.length - 1 ) {
				size /= 1024;
				i++;
			}

			return size.toFixed( 1 ) + ' ' + units[ i ];
		},

		/**
		 * Escape HTML entities to prevent XSS.
		 *
		 * @param {string} text The string to escape.
		 * @return {string} The escaped string.
		 */
		escapeHtml( text ) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;',
			};
			return String( text ).replace( /[&<>"']/g, function ( m ) {
				return map[ m ];
			} );
		},

		/**
		 * Show an inline error message below the upload zone.
		 *
		 * Errors auto-dismiss after 5 seconds. Uses role="alert"
		 * for screen reader announcement.
		 *
		 * @param {string} message The error message to display.
		 */
		showError( message ) {
			const $error = $( '<div>' )
				.addClass( 'ppa-upload-error' )
				.attr( 'role', 'alert' )
				.text( message )
				.hide();

			this.$container.append( $error );
			$error.slideDown( 200 );

			setTimeout( function () {
				$error.slideUp( 200, function () {
					$( this ).remove();
				} );
			}, 5000 );
		},

		/**
		 * Create the screen reader announcement region if it doesn't exist.
		 *
		 * Matches the PressPrimer Quiz pattern for consistent accessibility.
		 */
		createScreenReaderRegion() {
			if ( $( '#ppa-sr-announcements' ).length === 0 ) {
				$( 'body' ).append(
					'<div id="ppa-sr-announcements" class="ppa-sr-only" ' +
						'role="status" aria-live="polite" aria-atomic="true"></div>'
				);
			}
		},

		/**
		 * Announce a message to screen readers.
		 *
		 * Clears then sets text with a short delay to ensure
		 * re-announcement even for identical messages.
		 *
		 * @param {string} message  The message to announce.
		 * @param {string} priority 'polite' (default) or 'assertive'.
		 */
		announceToScreenReader( message, priority ) {
			priority = priority || 'polite';
			const $region = $( '#ppa-sr-announcements' );

			$region.attr( 'aria-live', priority );
			$region.text( '' );

			setTimeout( function () {
				$region.text( message );
			}, 100 );
		},
	};

	/* =========================================================================
	   PPA.TypeSelector - Submission Type Selector Module
	   ========================================================================= */

	/**
	 * Submission type selector module.
	 *
	 * Handles the "either" mode type selector where students choose
	 * between file upload and text submission.
	 */
	window.PPA.TypeSelector = {
		/**
		 * Initialize the type selector module.
		 *
		 * Binds click handlers on type option buttons and back links.
		 */
		init() {
			const $selector = $( '.ppa-submission-type-selector' );
			if ( ! $selector.length ) {
				return;
			}

			this.bindEvents();

			// Auto-select type if user has an existing draft.
			const autoType = $selector.data( 'auto-type' );
			if ( autoType ) {
				const $autoButton = $selector.find(
					'.ppa-type-option[data-submission-type="' + autoType + '"]'
				);
				if ( $autoButton.length ) {
					this.selectType( $autoButton );
				}
			}
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents() {
			const self = this;

			// Type option buttons.
			$( document ).on(
				'click.ppaTypeSelector',
				'.ppa-type-option',
				function () {
					self.selectType( $( this ) );
				}
			);

			// Back button to return to selector.
			$( document ).on(
				'click.ppaTypeSelector',
				'.ppa-type-back-btn',
				function ( e ) {
					e.preventDefault();
					self.showSelector( $( this ) );
				}
			);
		},

		/**
		 * Handle type selection.
		 *
		 * Hides the type selector and reveals the chosen panel.
		 *
		 * @param {jQuery} $button The clicked type option button.
		 */
		selectType( $button ) {
			const type = $button.data( 'submission-type' );
			const assignmentId = $button.data( 'assignment-id' );
			const $assignment = $(
				'.ppa-assignment[data-assignment-id="' + assignmentId + '"]'
			);

			if ( ! $assignment.length || ! type ) {
				return;
			}

			// Hide the type selector.
			$assignment.find( '.ppa-submission-type-selector' ).slideUp( 200 );

			// Show the selected panel.
			const $panel = $assignment.find( '.ppa-submission-type-' + type );
			$panel.removeClass( 'ppa-hidden' ).slideDown( 200 );

			// Add a back button if not already present.
			if ( ! $panel.find( '.ppa-type-back-btn' ).length ) {
				const changeLabel =
					pressprimerAssignmentFrontend.i18n.changeType ||
					'Change submission type';
				const $icon = $( '<span>' )
					.addClass( 'dashicons dashicons-arrow-left-alt2' )
					.attr( 'aria-hidden', 'true' );
				const $label = $( '<span>' ).text( changeLabel );
				const $back = $( '<button>' )
					.attr( 'type', 'button' )
					.addClass(
						'ppa-button ppa-button-secondary ppa-type-back-btn'
					)
					.append( $icon )
					.append( ' ' )
					.append( $label );

				$panel.prepend( $back );
			}

			// Initialize the selected panel's module.
			if ( 'file' === type ) {
				const $uploadContainer = $panel.find( '.ppa-upload-container' );
				if (
					$uploadContainer.length &&
					! window.PPA.Upload.$container
				) {
					window.PPA.Upload.init( $uploadContainer );
				}
				window.PPA.SubmissionForm.init();
			} else if (
				'text' === type &&
				window.PPA.TextEditor &&
				! window.PPA.TextEditor.editor
			) {
				window.PPA.TextEditor.init();
			}
		},

		/**
		 * Show the type selector again (go back).
		 *
		 * Hides the currently visible panel and shows the type selector.
		 *
		 * @param {jQuery} $link The back link that was clicked.
		 */
		showSelector( $link ) {
			const $panel = $link.closest( '.ppa-submission-type-panel' );
			const $assignment = $link.closest( '.ppa-assignment' );

			// Hide the panel.
			$panel.slideUp( 200, function () {
				$( this ).addClass( 'ppa-hidden' );
			} );

			// Show the type selector.
			$assignment
				.find( '.ppa-submission-type-selector' )
				.slideDown( 200 );
		},
	};

	/* =========================================================================
	   PPA.SubmissionForm - Form Interaction Module
	   ========================================================================= */

	/**
	 * Submission form module.
	 *
	 * Manages character counting for the notes textarea,
	 * submit button enable/disable state, confirmation dialog,
	 * and resubmission flow.
	 */
	window.PPA.SubmissionForm = {
		/**
		 * jQuery reference to the form element.
		 *
		 * @type {jQuery|null}
		 */
		$form: null,

		/**
		 * jQuery reference to the submit button.
		 *
		 * @type {jQuery|null}
		 */
		$submitBtn: null,

		/**
		 * jQuery reference to the notes textarea.
		 *
		 * @type {jQuery|null}
		 */
		$notesField: null,

		/**
		 * jQuery reference to the character count display.
		 *
		 * @type {jQuery|null}
		 */
		$charCount: null,

		/**
		 * Whether a submission is currently being processed.
		 *
		 * @type {boolean}
		 */
		isSubmitting: false,

		/**
		 * Initialize the submission form module.
		 *
		 * Finds form elements and binds event handlers.
		 */
		init() {
			this.$form = $( '#ppa-submission-form' );
			this.$submitBtn = $( '#ppa-submit-btn' );
			this.$notesField = $( '#ppa-student-notes' );
			this.$charCount = $( '.ppa-char-current' );

			// Always bind delegated handlers (delete, resubmit) since
			// they target elements outside the form (status view).
			this.bindGlobalEvents();

			if ( ! this.$form.length ) {
				return;
			}

			this.isSubmitting = false;

			this.bindFormEvents();
			this.updateSubmitButton();
		},

		/**
		 * Bind delegated handlers that live outside the form.
		 *
		 * These target the status view (resubmit / delete) and must
		 * work even when no submission form is present on the page.
		 */
		bindGlobalEvents() {
			const self = this;

			$( document ).off( 'click.ppaForm', '#ppa-start-resubmit' );
			$( document ).off( 'click.ppaForm', '.ppa-delete-submission' );

			// Resubmission button (in the status view).
			$( document ).on(
				'click.ppaForm',
				'#ppa-start-resubmit',
				function ( e ) {
					e.preventDefault();
					self.startResubmission( $( this ) );
				}
			);

			// Delete previous submission button.
			$( document ).on(
				'click.ppaForm',
				'.ppa-delete-submission',
				function ( e ) {
					e.preventDefault();
					self.deleteSubmission( $( this ) );
				}
			);
		},

		/**
		 * Bind form-specific event handlers.
		 */
		bindFormEvents() {
			const self = this;

			// Remove any previous bindings to prevent duplicate handlers
			// when init() is called more than once (e.g. type selector flow).
			this.$notesField.off( 'input.ppaForm' );
			this.$form.off( 'submit.ppaForm' );

			// Character count on notes field.
			this.$notesField.on( 'input.ppaForm', function () {
				self.updateCharCount();
			} );

			// Form submission with confirmation.
			this.$form.on( 'submit.ppaForm', function ( e ) {
				e.preventDefault();
				self.handleSubmit();
			} );
		},

		/* -----------------------------------------------------------------
		   Character Count
		   ----------------------------------------------------------------- */

		/**
		 * Update the character count display for the notes textarea.
		 */
		updateCharCount() {
			const length = this.$notesField.val().length;
			this.$charCount.text( length.toLocaleString() );
		},

		/* -----------------------------------------------------------------
		   Submit Button State
		   ----------------------------------------------------------------- */

		/**
		 * Update the submit button disabled state.
		 *
		 * The button is enabled only when at least one file has been
		 * successfully uploaded and no uploads are in progress.
		 */
		updateSubmitButton() {
			if ( ! this.$submitBtn || ! this.$submitBtn.length ) {
				return;
			}

			const hasFiles =
				window.PPA.Upload.files && window.PPA.Upload.files.length > 0;
			const uploading = window.PPA.Upload.uploadsInProgress > 0;
			const shouldEnable = hasFiles && ! uploading && ! this.isSubmitting;

			this.$submitBtn.prop( 'disabled', ! shouldEnable );

			// Show tooltip on disabled button (matches Quiz pattern).
			if ( ! shouldEnable && ! uploading && ! this.isSubmitting ) {
				const hint =
					pressprimerAssignmentFrontend.i18n.uploadHint ||
					'Upload at least one file to enable submission.';
				this.$submitBtn.attr( 'title', hint );
			} else {
				this.$submitBtn.removeAttr( 'title' );
			}
		},

		/* -----------------------------------------------------------------
		   Form Submission
		   ----------------------------------------------------------------- */

		/**
		 * Handle form submission.
		 *
		 * Shows a preview of files and notes, then submits
		 * the assignment via AJAX on confirmation.
		 */
		handleSubmit() {
			const self = this;

			if ( this.isSubmitting ) {
				return;
			}

			// Require at least one uploaded file.
			if ( ! window.PPA.Upload.files.length ) {
				return;
			}

			// Gather preview data.
			const canResubmit =
				this.$form.data( 'can-resubmit' ) === 1 ||
				this.$form.data( 'can-resubmit' ) === '1';
			const remaining = parseInt(
				this.$form.data( 'resubmissions-remaining' ) || 0,
				10
			);
			const title = this.$form.data( 'assignment-title' ) || '';
			const notes = this.$notesField ? this.$notesField.val() : '';

			// Show submission preview modal.
			window.PPA.SubmissionPreview.showFilePreview( {
				title,
				files: window.PPA.Upload.files,
				notes,
				canResubmit,
				remaining,
				onConfirm() {
					self.doSubmit();
				},
			} );
		},

		/**
		 * Execute the actual submission.
		 *
		 * Called after the user confirms in the modal.
		 */
		doSubmit() {
			const self = this;

			this.isSubmitting = true;
			this.$submitBtn
				.prop( 'disabled', true )
				.text( pressprimerAssignmentFrontend.i18n.submitting );

			window.PPA.Upload.announceToScreenReader(
				pressprimerAssignmentFrontend.i18n.submitting
			);

			// Collect uploaded file IDs.
			const fileIds = $.map( window.PPA.Upload.files, function ( f ) {
				return f.id;
			} );

			$.ajax( {
				url: pressprimerAssignmentFrontend.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ppa_submit_assignment',
					assignment_id: window.PPA.Upload.assignmentId,
					file_ids: fileIds,
					student_notes: self.$notesField.val(),
					nonce: pressprimerAssignmentFrontend.nonce,
				},
				success( response ) {
					if ( response.success ) {
						self.handleSubmitSuccess( response.data );
					} else {
						const message =
							response.data && response.data.message
								? response.data.message
								: pressprimerAssignmentFrontend.i18n
										.uploadFailed;
						self.handleSubmitError( message );
					}
				},
				error( jqXHR ) {
					// Extract server error message from response if available.
					let errorMessage =
						pressprimerAssignmentFrontend.i18n.networkError;
					try {
						const errorResponse =
							typeof jqXHR.responseJSON !== 'undefined'
								? jqXHR.responseJSON
								: JSON.parse( jqXHR.responseText );
						if (
							errorResponse.data &&
							errorResponse.data.message
						) {
							errorMessage = errorResponse.data.message;
						}
					} catch ( e ) {
						// Use default network error message.
					}
					self.handleSubmitError( errorMessage );
				},
			} );
		},

		/**
		 * Handle successful submission.
		 *
		 * @param {Object} data Server response data.
		 */
		handleSubmitSuccess( data ) {
			this.isSubmitting = false;

			// Close the preview modal.
			window.PPA.SubmissionPreview.hide();

			window.PPA.Upload.announceToScreenReader(
				pressprimerAssignmentFrontend.i18n.submitted
			);

			// If the server provides a redirect URL, navigate there.
			if ( data && data.redirect_url ) {
				window.location.href = data.redirect_url;
				return;
			}

			// Otherwise reload to show updated submission status.
			window.location.reload();
		},

		/**
		 * Handle failed submission.
		 *
		 * @param {string} message Error message to display.
		 */
		handleSubmitError( message ) {
			this.isSubmitting = false;

			// Close the preview modal.
			window.PPA.SubmissionPreview.hide();

			this.$submitBtn
				.prop( 'disabled', false )
				.text(
					pressprimerAssignmentFrontend.i18n.submitAssignment ||
						'Submit Assignment'
				);

			window.PPA.Upload.showError( message );
			window.PPA.Upload.announceToScreenReader( message, 'assertive' );
		},

		/* -----------------------------------------------------------------
		   Delete Previous Submission
		   ----------------------------------------------------------------- */

		/**
		 * Delete a previous submission.
		 *
		 * Shows a confirmation prompt, then sends an AJAX request
		 * to delete the submission and removes its card from the DOM.
		 *
		 * @param {jQuery} $button The delete button that was clicked.
		 */
		deleteSubmission( $button ) {
			const i18n = pressprimerAssignmentFrontend.i18n || {};

			window.PPA.SubmissionPreview.showConfirm( {
				title: i18n.confirmDeleteTitle || 'Delete Submission',
				message:
					i18n.confirmDelete ||
					'Are you sure you want to delete this submission? This cannot be undone.',
				confirm: i18n.confirmDeleteButton || 'Delete',
				cancel: i18n.cancel || 'Cancel',
				variant: 'danger',
				onConfirm() {
					const submissionId = parseInt(
						$button.data( 'submission-id' ),
						10
					);
					const $card = $button.closest( '.ppa-submission-card' );

					$button.prop( 'disabled', true );

					$.ajax( {
						url: pressprimerAssignmentFrontend.ajaxUrl,
						type: 'POST',
						data: {
							action: 'ppa_delete_submission',
							submission_id: submissionId,
							nonce: pressprimerAssignmentFrontend.nonce,
						},
						success( response ) {
							if ( response.success ) {
								$card.fadeOut( 200, function () {
									$( this ).remove();

									// If no more cards, remove the entire section.
									const $list = $(
										'.ppa-previous-submissions .ppa-submissions-list'
									);
									if (
										$list.length &&
										! $list.find( '.ppa-submission-card' )
											.length
									) {
										$list
											.closest(
												'.ppa-previous-submissions'
											)
											.fadeOut( 200, function () {
												$( this ).remove();
											} );
									}
								} );
							} else {
								const message =
									response.data && response.data.message
										? response.data.message
										: i18n.uploadFailed;
								window.PPA.Upload.showError( message );
								$button.prop( 'disabled', false );
							}
						},
						error() {
							window.PPA.Upload.showError(
								i18n.networkError || 'Network error.'
							);
							$button.prop( 'disabled', false );
						},
					} );
				},
			} );
		},

		/* -----------------------------------------------------------------
		   Resubmission
		   ----------------------------------------------------------------- */

		/**
		 * Start the resubmission flow.
		 *
		 * Hides the submission status view and shows the submission
		 * form with the upload zone, then scrolls to it.
		 *
		 * @param {jQuery} $button The "Submit Again" button.
		 */
		startResubmission( $button ) {
			const assignmentId = $button.data( 'assignment-id' );
			const $assignment = $(
				'.ppa-assignment[data-assignment-id="' + assignmentId + '"]'
			);

			if ( ! $assignment.length ) {
				return;
			}

			// Hide the status card.
			$assignment.find( '.ppa-submission-status-card' ).slideUp( 200 );

			// Show the resubmission form wrapper (hidden in DOM by default).
			const $wrapper = $assignment.find(
				'.ppa-resubmission-form-wrapper'
			);
			if ( $wrapper.length ) {
				$wrapper.removeClass( 'ppa-hidden' ).slideDown( 200 );

				// Scroll to the form.
				$( 'html, body' ).animate(
					{
						scrollTop: $wrapper.offset().top - 50,
					},
					300
				);

				// Initialize upload if needed.
				const $uploadContainer = $wrapper.find(
					'.ppa-upload-container'
				);
				if ( $uploadContainer.length ) {
					window.PPA.Upload.init( $uploadContainer );
				}

				window.PPA.Upload.announceToScreenReader(
					pressprimerAssignmentFrontend.i18n.dragDropHere
				);
			}
		},
	};

	/* =========================================================================
	   PPA.SubmissionPreview - Submission Preview Module
	   ========================================================================= */

	/**
	 * Submission preview module.
	 *
	 * Displays a review screen before final submission showing either
	 * the file list (with PDF warnings) or text content, assignment
	 * title, student notes, and confirm/back actions.
	 */
	window.PPA.SubmissionPreview = {
		/**
		 * Callback to execute when the user confirms submission.
		 *
		 * @type {Function|null}
		 */
		onConfirm: null,

		/**
		 * The current overlay element.
		 *
		 * @type {jQuery|null}
		 */
		$overlay: null,

		/**
		 * Show the preview modal for a file upload submission.
		 *
		 * Builds the preview with file list, total size, PDF warnings,
		 * student notes, and confirm/cancel buttons.
		 *
		 * @param {Object}   options             Preview options.
		 * @param {string}   options.title       Assignment title.
		 * @param {Array}    options.files       Array of file data objects.
		 * @param {string}   options.notes       Student notes text.
		 * @param {boolean}  options.canResubmit Whether resubmission is allowed.
		 * @param {number}   options.remaining   Resubmissions remaining.
		 * @param {Function} options.onConfirm   Callback when confirmed.
		 */
		showFilePreview( options ) {
			this.onConfirm = options.onConfirm;

			const i18n = pressprimerAssignmentFrontend.i18n || {};
			const esc = this.escapeHtml;

			// Build file list HTML.
			let filesHtml = '';
			let totalSize = 0;
			let hasPdfWarning = false;

			for ( let i = 0; i < options.files.length; i++ ) {
				const file = options.files[ i ];
				const ext = ( file.name || '' )
					.split( '.' )
					.pop()
					.toUpperCase();
				const sizeStr = this.formatSize( file.size || 0 );

				totalSize += parseInt( file.size, 10 ) || 0;

				let warningHtml = '';
				if (
					file.text_extractable === false ||
					file.text_extractable === 0
				) {
					const fileName = ( file.name || '' ).toLowerCase();
					if (
						fileName.endsWith( '.pdf' ) ||
						file.type === 'application/pdf'
					) {
						hasPdfWarning = true;
						warningHtml =
							'<span class="ppa-preview-file-warning dashicons dashicons-warning" ' +
							'title="' +
							esc(
								i18n.pdfWarningShort || 'Text extraction issue'
							) +
							'" aria-hidden="true"></span>';
					}
				}

				filesHtml +=
					'<li class="ppa-preview-file-item">' +
					'<span class="ppa-preview-file-icon" aria-hidden="true">' +
					esc( ext ) +
					'</span>' +
					'<span class="ppa-preview-file-name">' +
					esc( file.name ) +
					'</span>' +
					'<span class="ppa-preview-file-size">' +
					esc( sizeStr ) +
					'</span>' +
					warningHtml +
					'</li>';
			}

			// Build PDF warning section.
			let pdfWarningHtml = '';
			if ( hasPdfWarning ) {
				pdfWarningHtml =
					'<div class="ppa-preview-pdf-warning">' +
					'<div class="ppa-notice ppa-notice-warning">' +
					'<strong>' +
					esc( i18n.pdfWarningTitle || 'Text Extraction Issue' ) +
					'</strong>' +
					'<p>' +
					esc(
						i18n.pdfWarningMessage ||
							'We could not extract readable text from one or more PDF files. If the assignment is text-based, your instructor may have trouble extracting the text for review.'
					) +
					'</p>' +
					'<details><summary>' +
					esc( i18n.pdfWarningWhy || 'Why does this matter?' ) +
					'</summary>' +
					'<p>' +
					esc(
						i18n.pdfWarningDetails ||
							'Some PDFs are scanned images without embedded text. For best results, consider using the text editor or uploading a DOCX file instead.'
					) +
					'</p></details>' +
					'</div>' +
					'</div>';
			}

			// Build notes section.
			let notesHtml = '';
			if ( options.notes && options.notes.trim() ) {
				notesHtml =
					'<div class="ppa-preview-section">' +
					'<h4 class="ppa-preview-section-title">' +
					esc( i18n.previewNotes || 'Your Notes' ) +
					'</h4>' +
					'<div class="ppa-preview-notes-content">' +
					esc( options.notes ) +
					'</div>' +
					'</div>';
			}

			const bodyHtml =
				'<div class="ppa-preview-section">' +
				'<div class="ppa-preview-assignment-info">' +
				'<strong>' +
				esc( i18n.previewAssignment || 'Assignment' ) +
				':</strong> ' +
				esc( options.title || '' ) +
				'</div>' +
				'</div>' +
				'<div class="ppa-preview-section">' +
				'<h4 class="ppa-preview-section-title">' +
				esc( i18n.previewFiles || 'Files' ) +
				'</h4>' +
				'<ul class="ppa-preview-file-list">' +
				filesHtml +
				'</ul>' +
				'<div class="ppa-preview-total-size">' +
				'<strong>' +
				esc( i18n.previewTotalSize || 'Total size' ) +
				':</strong> ' +
				esc( this.formatSize( totalSize ) ) +
				'</div>' +
				'</div>' +
				pdfWarningHtml +
				notesHtml;

			this.showModal( bodyHtml, options );
		},

		/**
		 * Show the preview modal for a text submission.
		 *
		 * Builds the preview with text content, word count,
		 * and confirm/cancel buttons.
		 *
		 * @param {Object}   options             Preview options.
		 * @param {string}   options.title       Assignment title.
		 * @param {string}   options.content     HTML content from editor.
		 * @param {number}   options.wordCount   Word count.
		 * @param {boolean}  options.canResubmit Whether resubmission is allowed.
		 * @param {number}   options.remaining   Resubmissions remaining.
		 * @param {Function} options.onConfirm   Callback when confirmed.
		 */
		showTextPreview( options ) {
			this.onConfirm = options.onConfirm;

			const i18n = pressprimerAssignmentFrontend.i18n || {};
			const esc = this.escapeHtml;
			const maxWords = 500;

			// Truncate preview if content exceeds maxWords.
			let displayContent = options.content || '';
			let truncatedNotice = '';
			if ( options.wordCount > maxWords ) {
				displayContent = this.truncateHtmlByWords(
					displayContent,
					maxWords
				);
				truncatedNotice =
					'<p class="ppa-form-hint">' +
					esc(
						i18n.previewTruncated ||
							'This is a preview showing the first 500 words only.'
					) +
					'</p>';
			}

			const bodyHtml =
				'<div class="ppa-preview-section">' +
				'<div class="ppa-preview-assignment-info">' +
				'<strong>' +
				esc( i18n.previewAssignment || 'Assignment' ) +
				':</strong> ' +
				esc( options.title || '' ) +
				'</div>' +
				'</div>' +
				'<div class="ppa-preview-section">' +
				'<h4 class="ppa-preview-section-title">' +
				esc( i18n.previewYourSubmission || 'Your Submission' ) +
				'</h4>' +
				'<div class="ppa-preview-text-content">' +
				displayContent +
				'</div>' +
				truncatedNotice +
				'<div class="ppa-preview-word-count">' +
				'<strong>' +
				esc( i18n.previewWordCount || 'Word count' ) +
				':</strong> ' +
				esc( String( options.wordCount || 0 ) ) +
				'</div>' +
				'</div>';

			this.showModal( bodyHtml, options );
		},

		/**
		 * Build and display the preview modal.
		 *
		 * @param {string} bodyHtml The preview body HTML content.
		 * @param {Object} options  Options with canResubmit and remaining.
		 */
		showModal( bodyHtml, options ) {
			const self = this;
			const i18n = pressprimerAssignmentFrontend.i18n || {};
			const esc = this.escapeHtml;

			// Build contextual resubmission message.
			let resubMessage = '';
			if ( options.canResubmit && options.remaining > 0 ) {
				const remainingTemplate =
					options.remaining === 1
						? i18n.resubmissionLeft
						: i18n.resubmissionsLeft;
				if ( remainingTemplate ) {
					resubMessage =
						'<p class="ppa-preview-resubmission-info">' +
						esc(
							remainingTemplate.replace( '%d', options.remaining )
						) +
						'</p>';
				}
			}

			const titleLabel = esc(
				i18n.previewTitle || 'Review Your Submission'
			);
			const backLabel = esc( i18n.previewGoBack || 'Go Back & Edit' );
			const confirmLabel = esc(
				i18n.previewConfirm || 'Confirm & Submit'
			);

			const $overlay = $(
				'<div class="ppa-confirm-overlay ppa-preview-overlay">' +
					'<div class="ppa-confirm-dialog ppa-preview-dialog" role="dialog" ' +
					'aria-modal="true" aria-labelledby="ppa-preview-title">' +
					'<div class="ppa-confirm-header ppa-preview-header">' +
					'<h3 class="ppa-confirm-title" id="ppa-preview-title">' +
					titleLabel +
					'</h3>' +
					'<button type="button" class="ppa-confirm-close" ' +
					'aria-label="' +
					esc( i18n.cancel || 'Cancel' ) +
					'">&times;</button>' +
					'</div>' +
					'<div class="ppa-confirm-body ppa-preview-body">' +
					bodyHtml +
					resubMessage +
					'</div>' +
					'<div class="ppa-confirm-footer ppa-preview-footer">' +
					'<button type="button" class="ppa-button ppa-button-secondary ppa-preview-back">' +
					backLabel +
					'</button>' +
					'<button type="button" class="ppa-button ppa-button-primary ppa-preview-confirm">' +
					confirmLabel +
					'</button>' +
					'</div>' +
					'</div>' +
					'</div>'
			);

			this.$overlay = $overlay;

			function closeModal() {
				$( document ).off( 'keydown.ppaPreview' );
				$overlay.removeClass( 'ppa-confirm-overlay--visible' );
				setTimeout( function () {
					$overlay.remove();
				}, 200 );
				self.$overlay = null;
			}

			// Backdrop click closes.
			$overlay.on( 'click', function ( e ) {
				if ( $( e.target ).hasClass( 'ppa-preview-overlay' ) ) {
					closeModal();
				}
			} );

			// Close (X) button.
			$overlay.find( '.ppa-confirm-close' ).on( 'click', closeModal );

			// Back button.
			$overlay.find( '.ppa-preview-back' ).on( 'click', closeModal );

			// Confirm button.
			$overlay.find( '.ppa-preview-confirm' ).on( 'click', function () {
				// Disable buttons to prevent double-click.
				$overlay
					.find( '.ppa-preview-confirm' )
					.prop( 'disabled', true )
					.text( i18n.submitting || 'Submitting...' );
				$overlay.find( '.ppa-preview-back' ).prop( 'disabled', true );

				if ( self.onConfirm ) {
					self.onConfirm();
				}
			} );

			// Escape key closes.
			$( document ).on( 'keydown.ppaPreview', function ( e ) {
				if ( e.key === 'Escape' ) {
					closeModal();
				}
			} );

			$( 'body' ).append( $overlay );

			// Animate in after append.
			setTimeout( function () {
				$overlay.addClass( 'ppa-confirm-overlay--visible' );
				// Focus the confirm button for keyboard users.
				$overlay.find( '.ppa-preview-confirm' ).trigger( 'focus' );
			}, 10 );
		},

		/**
		 * Close the preview modal (called on submit success/error).
		 */
		hide() {
			if ( this.$overlay ) {
				$( document ).off( 'keydown.ppaPreview' );
				this.$overlay.remove();
				this.$overlay = null;
			}
		},

		/**
		 * Show a generic confirmation modal.
		 *
		 * Reuses the same styled modal as submission preview for
		 * consistent UI across all confirmation dialogs.
		 *
		 * @param {Object}   options           Confirm options.
		 * @param {string}   options.title     Modal title.
		 * @param {string}   options.message   Body message text.
		 * @param {string}   options.confirm   Confirm button label.
		 * @param {string}   options.cancel    Cancel button label.
		 * @param {string}   options.variant   Button style: 'danger' or 'primary'.
		 * @param {Function} options.onConfirm Callback when confirmed.
		 */
		showConfirm( options ) {
			const esc = this.escapeHtml;
			const i18n = pressprimerAssignmentFrontend.i18n || {};

			const titleLabel = esc( options.title || '' );
			const messageText = esc( options.message || '' );
			const confirmLabel = esc(
				options.confirm || i18n.previewConfirm || 'Confirm'
			);
			const cancelLabel = esc(
				options.cancel || i18n.cancel || 'Cancel'
			);
			const variant = options.variant || 'primary';

			const confirmClass =
				variant === 'danger'
					? 'ppa-button ppa-button-danger'
					: 'ppa-button ppa-button-primary';

			const $overlay = $(
				'<div class="ppa-confirm-overlay">' +
					'<div class="ppa-confirm-dialog" role="alertdialog" ' +
					'aria-modal="true" aria-labelledby="ppa-confirm-title" ' +
					'aria-describedby="ppa-confirm-message">' +
					'<div class="ppa-confirm-header">' +
					'<h3 class="ppa-confirm-title" id="ppa-confirm-title">' +
					titleLabel +
					'</h3>' +
					'<button type="button" class="ppa-confirm-close" ' +
					'aria-label="' +
					esc( i18n.cancel || 'Cancel' ) +
					'">&times;</button>' +
					'</div>' +
					'<div class="ppa-confirm-body">' +
					'<p class="ppa-confirm-message" id="ppa-confirm-message">' +
					messageText +
					'</p>' +
					'</div>' +
					'<div class="ppa-confirm-footer">' +
					'<button type="button" class="ppa-button ppa-button-secondary ppa-confirm-cancel">' +
					cancelLabel +
					'</button>' +
					'<button type="button" class="' +
					confirmClass +
					' ppa-confirm-ok">' +
					confirmLabel +
					'</button>' +
					'</div>' +
					'</div>' +
					'</div>'
			);

			function closeConfirm() {
				$( document ).off( 'keydown.ppaConfirm' );
				$overlay.removeClass( 'ppa-confirm-overlay--visible' );
				setTimeout( function () {
					$overlay.remove();
				}, 200 );
			}

			// Backdrop click.
			$overlay.on( 'click', function ( e ) {
				if ( $( e.target ).hasClass( 'ppa-confirm-overlay' ) ) {
					closeConfirm();
				}
			} );

			// Close / Cancel buttons.
			$overlay.find( '.ppa-confirm-close' ).on( 'click', closeConfirm );
			$overlay.find( '.ppa-confirm-cancel' ).on( 'click', closeConfirm );

			// Confirm button.
			$overlay.find( '.ppa-confirm-ok' ).on( 'click', function () {
				closeConfirm();
				if ( options.onConfirm ) {
					options.onConfirm();
				}
			} );

			// Escape key.
			$( document ).on( 'keydown.ppaConfirm', function ( e ) {
				if ( e.key === 'Escape' ) {
					closeConfirm();
				}
			} );

			$( 'body' ).append( $overlay );

			setTimeout( function () {
				$overlay.addClass( 'ppa-confirm-overlay--visible' );
				$overlay.find( '.ppa-confirm-cancel' ).trigger( 'focus' );
			}, 10 );
		},

		/* -----------------------------------------------------------------
		   Utilities
		   ----------------------------------------------------------------- */

		/**
		 * Escape HTML entities.
		 *
		 * @param {string} text Raw text.
		 * @return {string} Escaped HTML.
		 */
		escapeHtml( text ) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;',
			};
			return String( text || '' ).replace( /[&<>"']/g, function ( m ) {
				return map[ m ];
			} );
		},

		/**
		 * Truncate HTML content to a maximum number of words.
		 *
		 * Parses the HTML via a temporary DOM element, walks the
		 * text nodes, and removes content after the word limit.
		 *
		 * @param {string} html     HTML content.
		 * @param {number} maxWords Maximum number of words.
		 * @return {string} Truncated HTML with trailing ellipsis.
		 */
		truncateHtmlByWords( html, maxWords ) {
			const container = document.createElement( 'div' );
			container.innerHTML = html;
			let wordCount = 0;
			let truncated = false;

			const walk = function ( node ) {
				if ( truncated ) {
					node.parentNode.removeChild( node );
					return;
				}

				if ( node.nodeType === 3 ) {
					const words = node.textContent
						.split( /\s+/ )
						.filter( Boolean );
					if ( wordCount + words.length > maxWords ) {
						const keep = maxWords - wordCount;
						node.textContent =
							words.slice( 0, keep ).join( ' ' ) + '\u2026';
						wordCount = maxWords;
						truncated = true;
					} else {
						wordCount += words.length;
					}
				} else if ( node.nodeType === 1 ) {
					// Process children in order; collect first to avoid
					// live NodeList issues when removing nodes.
					const children = Array.prototype.slice.call(
						node.childNodes
					);
					for ( let i = 0; i < children.length; i++ ) {
						walk( children[ i ] );
					}
				}
			};

			const topChildren = Array.prototype.slice.call(
				container.childNodes
			);
			for ( let i = 0; i < topChildren.length; i++ ) {
				walk( topChildren[ i ] );
			}

			return container.innerHTML;
		},

		/**
		 * Format bytes into a human-readable size string.
		 *
		 * @param {number} bytes The number of bytes.
		 * @return {string} Formatted size.
		 */
		formatSize( bytes ) {
			const units = [ 'B', 'KB', 'MB', 'GB' ];
			let i = 0;
			let size = parseFloat( bytes ) || 0;

			while ( size >= 1024 && i < units.length - 1 ) {
				size /= 1024;
				i++;
			}

			return size.toFixed( 1 ) + ' ' + units[ i ];
		},
	};

	/* =========================================================================
	   Initialization
	   ========================================================================= */

	$( document ).ready( function () {
		// Initialize type selector (for "either" mode assignments).
		window.PPA.TypeSelector.init();

		// Initialize upload module on each upload container.
		// Skip containers inside hidden panels (type selector reveals them).
		$( '.ppa-upload-container' ).each( function () {
			if ( ! $( this ).closest( '.ppa-hidden' ).length ) {
				window.PPA.Upload.init( $( this ) );
			}
		} );

		// Initialize submission form module.
		window.PPA.SubmissionForm.init();
	} );
} )( jQuery );
