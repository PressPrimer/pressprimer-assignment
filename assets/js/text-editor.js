/**
 * PressPrimer Assignment - Text Editor JavaScript
 *
 * Handles TinyMCE integration, live word count, auto-save via
 * WordPress heartbeat, manual save, save status display,
 * unsaved changes warning, and text submission.
 *
 * Follows the same IIFE + jQuery pattern as submission.js.
 *
 * @package
 * @since 1.0.0
 */

/* global jQuery, ppaFrontend, tinymce */

( function ( $ ) {
	'use strict';

	/**
	 * Global PPA namespace.
	 */
	window.PPA = window.PPA || {};

	/* =========================================================================
	   PPA.TextEditor - Text Editor Module
	   ========================================================================= */

	/**
	 * Text editor module.
	 *
	 * Manages TinyMCE editor integration, word count, auto-save via
	 * WordPress heartbeat, manual save, and submission flow.
	 */
	window.PPA.TextEditor = {
		/**
		 * TinyMCE editor instance.
		 *
		 * @type {Object|null}
		 */
		editor: null,

		/**
		 * Assignment ID for this text editor.
		 *
		 * @type {number}
		 */
		assignmentId: 0,

		/**
		 * Current draft submission ID (0 if none).
		 *
		 * @type {number}
		 */
		submissionId: 0,

		/**
		 * jQuery reference to the text submission form.
		 *
		 * @type {jQuery|null}
		 */
		$form: null,

		/**
		 * Content at last save (to detect changes).
		 *
		 * @type {string}
		 */
		lastSavedContent: '',

		/**
		 * Whether there are unsaved changes.
		 *
		 * @type {boolean}
		 */
		isDirty: false,

		/**
		 * Auto-save timeout handle.
		 *
		 * @type {number|null}
		 */
		saveTimeout: null,

		/**
		 * Whether a save request is currently in progress.
		 *
		 * @type {boolean}
		 */
		isSaving: false,

		/**
		 * Whether a submit request is currently in progress.
		 *
		 * @type {boolean}
		 */
		isSubmitting: false,

		/**
		 * Initialize the text editor module.
		 *
		 * Finds the text editor form, reads data attributes,
		 * and begins polling for the TinyMCE instance.
		 */
		init() {
			this.$form = $( '#ppa-text-submission-form' );

			if ( ! this.$form.length ) {
				return;
			}

			this.assignmentId = parseInt(
				this.$form.find( '[name="assignment_id"]' ).val() || 0,
				10
			);
			this.submissionId = parseInt(
				this.$form.find( '[name="submission_id"]' ).val() || 0,
				10
			);

			this.initEditor();
			this.bindEvents();
			this.initHeartbeat();
		},

		/**
		 * Wait for TinyMCE to be ready, then set up the editor.
		 *
		 * Polls until the TinyMCE instance for ppa_text_content
		 * is available.
		 */
		initEditor() {
			const self = this;

			if ( typeof tinymce === 'undefined' ) {
				setTimeout( function () {
					self.initEditor();
				}, 100 );
				return;
			}

			const checkEditor = function () {
				self.editor = tinymce.get( 'ppa_text_content' );
				if ( self.editor ) {
					self.setupEditor();
				} else {
					setTimeout( checkEditor, 100 );
				}
			};
			checkEditor();
		},

		/**
		 * Configure editor event listeners once TinyMCE is ready.
		 *
		 * Tracks input/change events for dirty state, word count,
		 * save status, and auto-save scheduling.
		 */
		setupEditor() {
			const self = this;

			this.lastSavedContent = this.editor.getContent();
			this.updateWordCount();
			this.updateSubmitButton();

			// Track changes on input (keystrokes).
			this.editor.on( 'input', function () {
				self.isDirty = true;
				self.updateWordCount();
				self.updateSaveStatus( 'unsaved' );
				self.updateSubmitButton();
				self.scheduleAutoSave();
			} );

			// Track changes on structural edits (paste, undo, format).
			this.editor.on( 'change', function () {
				self.isDirty = true;
				self.updateWordCount();
				self.updateSubmitButton();
			} );
		},

		/* -----------------------------------------------------------------
		   Word Count
		   ----------------------------------------------------------------- */

		/**
		 * Update the live word count and character count displays.
		 *
		 * Extracts plain text from the editor and counts words by
		 * splitting on whitespace. Also counts characters.
		 *
		 * @return {number} Current word count.
		 */
		updateWordCount() {
			if ( ! this.editor ) {
				return 0;
			}

			const content = this.editor.getContent( { format: 'text' } );
			const trimmed = content.trim();
			const words = trimmed ? trimmed.split( /\s+/ ).length : 0;
			const chars = trimmed.length;

			$( '#ppa-word-count-value' ).text( words );
			$( '#ppa-char-count-value' ).text( chars );

			return words;
		},

		/* -----------------------------------------------------------------
		   Save Status
		   ----------------------------------------------------------------- */

		/**
		 * Update the save status indicator.
		 *
		 * @param {string} status One of: 'saved', 'saving', 'unsaved', 'error'.
		 */
		updateSaveStatus( status ) {
			const $el = $( '#ppa-save-status' );
			if ( ! $el.length ) {
				return;
			}

			const i18n = ppaFrontend.i18n || {};
			let html = '';

			switch ( status ) {
				case 'saved':
					html =
						'<span class="ppa-status-saved">' +
						'<span class="dashicons dashicons-saved" aria-hidden="true"></span> ' +
						this.escapeHtml( i18n.draftSaved || 'Draft saved' ) +
						'</span>';
					break;

				case 'saving':
					html =
						'<span class="ppa-status-saving">' +
						this.escapeHtml( i18n.saving || 'Saving...' ) +
						'</span>';
					break;

				case 'unsaved':
					html =
						'<span class="ppa-status-unsaved">' +
						this.escapeHtml(
							i18n.unsavedChanges || 'Unsaved changes'
						) +
						'</span>';
					break;

				case 'error':
					html =
						'<span class="ppa-status-error">' +
						this.escapeHtml( i18n.saveFailed || 'Save failed' ) +
						'</span>';
					break;
			}

			$el.html( html );
		},

		/* -----------------------------------------------------------------
		   Submit Button State
		   ----------------------------------------------------------------- */

		/**
		 * Update the submit button enabled/disabled state.
		 *
		 * Disabled when editor is empty or a submit is in progress.
		 */
		updateSubmitButton() {
			const $btn = $( '#ppa-submit-text-btn' );
			if ( ! $btn.length || ! this.editor ) {
				return;
			}

			const content = this.editor.getContent( { format: 'text' } );
			const hasContent = content.trim().length > 0;

			$btn.prop( 'disabled', ! hasContent || this.isSubmitting );
		},

		/* -----------------------------------------------------------------
		   Auto-Save
		   ----------------------------------------------------------------- */

		/**
		 * Schedule an auto-save after 60 seconds of inactivity.
		 *
		 * Resets the timer on each keystroke so the save only fires
		 * after the user pauses.
		 */
		scheduleAutoSave() {
			const self = this;

			if ( this.saveTimeout ) {
				clearTimeout( this.saveTimeout );
			}

			this.saveTimeout = setTimeout( function () {
				if ( self.isDirty && ! self.isSaving ) {
					self.saveDraft( true );
				}
			}, 60000 );
		},

		/* -----------------------------------------------------------------
		   Save Draft (Manual + Auto)
		   ----------------------------------------------------------------- */

		/**
		 * Save the current draft to the server.
		 *
		 * @param {boolean} isAuto Whether this is an auto-save (skips if not dirty).
		 */
		saveDraft( isAuto ) {
			if ( isAuto && ! this.isDirty ) {
				return;
			}

			if ( this.isSaving || ! this.editor ) {
				return;
			}

			const self = this;
			const content = this.editor.getContent();
			const wordCount = this.updateWordCount();

			this.isSaving = true;
			this.updateSaveStatus( 'saving' );

			// Disable save draft button while saving.
			$( '#ppa-save-draft-btn' )
				.prop( 'disabled', true )
				.addClass( 'ppa-button-loading' );

			$.ajax( {
				url: ppaFrontend.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ppa_save_text_draft',
					nonce: ppaFrontend.textNonce,
					assignment_id: self.assignmentId,
					submission_id: self.submissionId,
					text_content: content,
					word_count: wordCount,
				},
				success( response ) {
					if ( response.success ) {
						self.submissionId = response.data.submission_id;
						self.lastSavedContent = content;
						self.isDirty = false;
						self.updateSaveStatus( 'saved' );

						// Update hidden field.
						self.$form
							.find( '[name="submission_id"]' )
							.val( self.submissionId );
					} else {
						self.updateSaveStatus( 'error' );
					}
				},
				error() {
					self.updateSaveStatus( 'error' );
				},
				complete() {
					self.isSaving = false;

					// Re-enable save draft button.
					$( '#ppa-save-draft-btn' )
						.prop( 'disabled', false )
						.removeClass( 'ppa-button-loading' );
				},
			} );
		},

		/* -----------------------------------------------------------------
		   WordPress Heartbeat Auto-Save
		   ----------------------------------------------------------------- */

		/**
		 * Initialize WordPress heartbeat integration.
		 *
		 * Hooks into heartbeat-send to include draft data when dirty,
		 * and heartbeat-tick to process save responses.
		 */
		initHeartbeat() {
			const self = this;

			$( document ).on( 'heartbeat-send', function ( e, data ) {
				if ( self.isDirty && self.editor && ! self.isSaving ) {
					data.ppa_text_autosave = {
						assignment_id: self.assignmentId,
						submission_id: self.submissionId,
						text_content: self.editor.getContent(),
						word_count: self.updateWordCount(),
					};
				}
			} );

			$( document ).on( 'heartbeat-tick', function ( e, data ) {
				if ( data.ppa_text_autosave_response ) {
					if ( data.ppa_text_autosave_response.success ) {
						self.submissionId =
							data.ppa_text_autosave_response.submission_id;
						self.isDirty = false;
						self.updateSaveStatus( 'saved' );

						// Update hidden field.
						self.$form
							.find( '[name="submission_id"]' )
							.val( self.submissionId );
					}
				}
			} );
		},

		/* -----------------------------------------------------------------
		   Event Binding
		   ----------------------------------------------------------------- */

		/**
		 * Bind UI event handlers.
		 *
		 * Sets up click handlers for save draft and submit buttons,
		 * and the beforeunload warning for unsaved changes.
		 */
		bindEvents() {
			const self = this;

			// Manual save button.
			$( '#ppa-save-draft-btn' ).on( 'click', function ( e ) {
				e.preventDefault();
				self.saveDraft( false );
			} );

			// Submit button.
			$( '#ppa-submit-text-btn' ).on( 'click', function ( e ) {
				e.preventDefault();
				self.handleSubmit();
			} );

			// Warn on page leave with unsaved changes.
			$( window ).on( 'beforeunload.ppaTextEditor', function () {
				if ( self.isDirty ) {
					return '';
				}
			} );
		},

		/* -----------------------------------------------------------------
		   Submit Flow
		   ----------------------------------------------------------------- */

		/**
		 * Handle the submit button click.
		 *
		 * Validates that content is not empty, then shows the
		 * confirmation modal. Reuses the shared PPA.SubmissionForm
		 * modal when available.
		 */
		handleSubmit() {
			if ( ! this.editor || this.isSubmitting ) {
				return;
			}

			const content = this.editor.getContent( { format: 'text' } );
			if ( ! content.trim() ) {
				// eslint-disable-next-line no-alert -- Intentional user feedback.
				window.alert(
					ppaFrontend.i18n.emptyContent ||
						'Please write something before submitting.'
				);
				return;
			}

			// Save any unsaved changes first, then show confirmation.
			if ( this.isDirty ) {
				this.saveDraftThenConfirm();
			} else {
				this.showConfirmAndSubmit();
			}
		},

		/**
		 * Save the draft, then show the confirmation modal.
		 *
		 * Ensures the latest content is persisted before submitting.
		 */
		saveDraftThenConfirm() {
			const self = this;
			const content = this.editor.getContent();
			const wordCount = this.updateWordCount();

			this.isSaving = true;
			this.updateSaveStatus( 'saving' );

			$.ajax( {
				url: ppaFrontend.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ppa_save_text_draft',
					nonce: ppaFrontend.textNonce,
					assignment_id: self.assignmentId,
					submission_id: self.submissionId,
					text_content: content,
					word_count: wordCount,
				},
				success( response ) {
					if ( response.success ) {
						self.submissionId = response.data.submission_id;
						self.lastSavedContent = content;
						self.isDirty = false;
						self.updateSaveStatus( 'saved' );

						self.$form
							.find( '[name="submission_id"]' )
							.val( self.submissionId );

						self.showConfirmAndSubmit();
					} else {
						self.updateSaveStatus( 'error' );
					}
				},
				error() {
					self.updateSaveStatus( 'error' );
				},
				complete() {
					self.isSaving = false;
				},
			} );
		},

		/**
		 * Show the submission preview and submit on confirm.
		 *
		 * Uses the shared PPA.SubmissionPreview module to display
		 * a text content preview before final submission.
		 */
		showConfirmAndSubmit() {
			const self = this;
			const content = this.editor.getContent();
			const wordCount = this.updateWordCount();

			const canResubmit =
				this.$form.data( 'can-resubmit' ) === 1 ||
				this.$form.data( 'can-resubmit' ) === '1';
			const remaining = parseInt(
				this.$form.data( 'resubmissions-remaining' ) || 0,
				10
			);
			const title = this.$form.data( 'assignment-title' ) || '';

			window.PPA.SubmissionPreview.showTextPreview( {
				title,
				content,
				wordCount,
				canResubmit,
				remaining,
				onConfirm() {
					self.doSubmit();
				},
			} );
		},

		/**
		 * Execute the actual text submission.
		 *
		 * Sends the text content via AJAX to submit the assignment.
		 * Called after the user confirms in the modal.
		 */
		doSubmit() {
			const self = this;
			const content = this.editor.getContent();
			const wordCount = this.updateWordCount();

			this.isSubmitting = true;

			$( '#ppa-submit-text-btn' )
				.prop( 'disabled', true )
				.text( ppaFrontend.i18n.submitting || 'Submitting...' );

			// Remove beforeunload warning during submit.
			$( window ).off( 'beforeunload.ppaTextEditor' );

			$.ajax( {
				url: ppaFrontend.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ppa_submit_text_assignment',
					nonce: ppaFrontend.nonce,
					assignment_id: self.assignmentId,
					submission_id: self.submissionId,
					text_content: content,
					word_count: wordCount,
				},
				success( response ) {
					if ( response.success ) {
						self.handleSubmitSuccess( response.data );
					} else {
						const errorMessage =
							response.data && response.data.message
								? response.data.message
								: ppaFrontend.i18n.networkError;
						self.handleSubmitError( errorMessage );
					}
				},
				error( jqXHR ) {
					let errorMessage = ppaFrontend.i18n.networkError;
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
			this.isDirty = false;

			// Close the preview modal.
			window.PPA.SubmissionPreview.hide();

			if ( data && data.redirect_url ) {
				window.location.href = data.redirect_url;
				return;
			}

			// Reload to show updated submission status.
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

			$( '#ppa-submit-text-btn' )
				.prop( 'disabled', false )
				.text(
					ppaFrontend.i18n.submitAssignment || 'Submit Assignment'
				);

			// Re-bind beforeunload warning.
			const self = this;
			$( window ).on( 'beforeunload.ppaTextEditor', function () {
				if ( self.isDirty ) {
					return '';
				}
			} );

			// Show error (use PPA.Upload's error display if available).
			if (
				window.PPA.Upload &&
				typeof window.PPA.Upload.showError === 'function'
			) {
				window.PPA.Upload.showError( message );
			} else {
				// eslint-disable-next-line no-alert -- Fallback error display.
				window.alert( message );
			}
		},

		/* -----------------------------------------------------------------
		   Utilities
		   ----------------------------------------------------------------- */

		/**
		 * Escape HTML for safe insertion.
		 *
		 * @param {string} text Raw text.
		 * @return {string} Escaped HTML.
		 */
		escapeHtml( text ) {
			const div = document.createElement( 'div' );
			div.textContent = text || '';
			return div.innerHTML;
		},
	};

	/* =========================================================================
	   Initialization
	   ========================================================================= */

	$( document ).ready( function () {
		// Initialize text editor if the form is visible (not in a hidden panel).
		const $textForm = $( '#ppa-text-submission-form' );
		if ( $textForm.length && ! $textForm.closest( '.ppa-hidden' ).length ) {
			window.PPA.TextEditor.init();
		}
	} );
} )( jQuery );
