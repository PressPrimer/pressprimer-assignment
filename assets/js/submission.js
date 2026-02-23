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

/* global jQuery, ppaFrontend, XMLHttpRequest */

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
				this.showError( ppaFrontend.i18n.maxFilesReached );
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
						ppaFrontend.i18n.invalidFileType
				);
				return false;
			}

			// Check file size.
			if ( this.maxSize > 0 && file.size > this.maxSize ) {
				this.showError(
					this.escapeHtml( file.name ) +
						': ' +
						ppaFrontend.i18n.fileTooLarge
				);
				return false;
			}

			// Reject zero-byte files.
			if ( file.size === 0 ) {
				this.showError(
					this.escapeHtml( file.name ) +
						': ' +
						ppaFrontend.i18n.uploadFailed
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
			formData.append( 'nonce', ppaFrontend.nonce );

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
								: ppaFrontend.i18n.uploadFailed;
						self.handleUploadError( $item, file.name, message );
					}
				} else {
					self.handleUploadError( $item, file.name );
				}

				window.PPA.SubmissionForm.updateSubmitButton();
			} );

			// Handle network errors.
			xhr.addEventListener( 'error', function () {
				self.uploadsInProgress--;
				self.handleUploadError(
					$item,
					file.name,
					ppaFrontend.i18n.networkError
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

			xhr.open( 'POST', ppaFrontend.ajaxUrl );
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

			this.announceToScreenReader(
				this.escapeHtml( fileName ) +
					' — ' +
					ppaFrontend.i18n.uploadComplete
			);
		},

		/**
		 * Handle a failed file upload.
		 *
		 * @param {jQuery} $item    The file item element.
		 * @param {string} fileName The original file name.
		 * @param {string} message  Optional error message.
		 */
		handleUploadError( $item, fileName, message ) {
			message = message || ppaFrontend.i18n.uploadFailed;

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
				ppaFrontend.i18n.removeFile +
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
					url: ppaFrontend.ajaxUrl,
					type: 'POST',
					data: {
						action: 'ppa_remove_file',
						file_id: parseInt( fileId, 10 ),
						nonce: ppaFrontend.nonce,
					},
				} );

				// Remove from local files array.
				this.files = $.grep( this.files, function ( f ) {
					return f.id !== parseInt( fileId, 10 );
				} );
			}

			// Animate removal.
			$item.fadeOut( 200, function () {
				$( this ).remove();
				self.announceToScreenReader( ppaFrontend.i18n.removeFile );
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
			let size = bytes;

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

			if ( ! this.$form.length ) {
				return;
			}

			this.isSubmitting = false;

			this.bindEvents();
			this.updateSubmitButton();
		},

		/**
		 * Bind form event handlers.
		 */
		bindEvents() {
			const self = this;

			// Character count on notes field.
			this.$notesField.on( 'input.ppaForm', function () {
				self.updateCharCount();
			} );

			// Form submission with confirmation.
			this.$form.on( 'submit.ppaForm', function ( e ) {
				e.preventDefault();
				self.handleSubmit();
			} );

			// Resubmission button (in the status view).
			$( document ).on(
				'click.ppaForm',
				'#ppa-start-resubmit',
				function ( e ) {
					e.preventDefault();
					self.startResubmission( $( this ) );
				}
			);
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
		},

		/* -----------------------------------------------------------------
		   Form Submission
		   ----------------------------------------------------------------- */

		/**
		 * Handle form submission.
		 *
		 * Shows a confirmation dialog, then submits the assignment
		 * via AJAX. Redirects or updates the page on success.
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

			// Confirmation dialog.
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( ppaFrontend.i18n.confirmSubmit ) ) {
				return;
			}

			this.isSubmitting = true;
			this.$submitBtn
				.prop( 'disabled', true )
				.text( ppaFrontend.i18n.submitting );

			window.PPA.Upload.announceToScreenReader(
				ppaFrontend.i18n.submitting
			);

			// Collect uploaded file IDs.
			const fileIds = $.map( window.PPA.Upload.files, function ( f ) {
				return f.id;
			} );

			$.ajax( {
				url: ppaFrontend.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ppa_submit_assignment',
					assignment_id: window.PPA.Upload.assignmentId,
					file_ids: fileIds,
					student_notes: self.$notesField.val(),
					nonce: ppaFrontend.nonce,
				},
				success( response ) {
					if ( response.success ) {
						self.handleSubmitSuccess( response.data );
					} else {
						const message =
							response.data && response.data.message
								? response.data.message
								: ppaFrontend.i18n.uploadFailed;
						self.handleSubmitError( message );
					}
				},
				error() {
					self.handleSubmitError( ppaFrontend.i18n.networkError );
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

			window.PPA.Upload.announceToScreenReader(
				ppaFrontend.i18n.submitted
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
			this.$submitBtn
				.prop( 'disabled', false )
				.text(
					ppaFrontend.i18n.submitAssignment || 'Submit Assignment'
				);

			window.PPA.Upload.showError( message );
			window.PPA.Upload.announceToScreenReader( message, 'assertive' );
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

			// Show the submission form (it may be hidden or need to be loaded).
			const $form = $assignment.find( '.ppa-submission-form' );
			if ( $form.length ) {
				$form.slideDown( 200 );

				// Scroll to the form.
				$( 'html, body' ).animate(
					{
						scrollTop: $form.offset().top - 50,
					},
					300
				);

				// Re-initialize upload if needed.
				const $uploadContainer = $form.find( '.ppa-upload-container' );
				if (
					$uploadContainer.length &&
					! window.PPA.Upload.$container
				) {
					window.PPA.Upload.init( $uploadContainer );
				}

				window.PPA.Upload.announceToScreenReader(
					ppaFrontend.i18n.dragDropHere
				);
			}
		},
	};

	/* =========================================================================
	   Initialization
	   ========================================================================= */

	$( document ).ready( function () {
		// Initialize upload module on each upload container.
		$( '.ppa-upload-container' ).each( function () {
			window.PPA.Upload.init( $( this ) );
		} );

		// Initialize submission form module.
		window.PPA.SubmissionForm.init();
	} );
} )( jQuery );
