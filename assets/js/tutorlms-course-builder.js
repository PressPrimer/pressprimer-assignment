/**
 * TutorLMS Course Builder Integration - Lesson Editor
 *
 * Adds a PressPrimer Assignment selector to the lesson editor modal sidebar.
 * Uses Tutor LMS's CourseBuilder slot/fill API (v3.3.0+) to inject into the
 * "bottom_of_sidebar" slot of the lesson editor modal.
 *
 * Assignments are linked at the lesson level, not topic level.
 *
 * @package
 * @since 1.0.0
 */

( function () {
	'use strict';

	// Get configuration from localized data.
	const config = window.pressprimerAssignmentTutorCourseBuilder || {};
	const strings = config.strings || {};
	const courseId = config.courseId || 0;

	// State for lesson assignment associations.
	const lessonAssignments = config.lessonAssignments || {};

	// Track the currently editing lesson ID (set when user clicks edit on a lesson).
	let currentEditingLessonId = null;

	/**
	 * Initialize the integration
	 */
	function init() {
		// Inject styles.
		injectStyles();

		// Set up click tracking to capture lesson IDs before modals open.
		setupLessonClickTracking();

		// Register our component via Tutor's slot/fill API.
		registerSlot();
	}

	/**
	 * Register our assignment selector in Tutor's lesson editor sidebar slot.
	 *
	 * Tutor LMS v3.3.0+ exposes window.Tutor.CourseBuilder with registerContent().
	 * The "component" property must be a React element (JSX), not a component function.
	 * It is rendered inside an error boundary with no props passed through.
	 */
	function registerSlot() {
		// Wait for Tutor's API to be available. The course builder React app
		// initializes asynchronously, so we poll until ready.
		let attempts = 0;
		const maxAttempts = 50; // 5 seconds max.

		const interval = setInterval( function () {
			attempts++;

			if (
				window.Tutor &&
				window.Tutor.CourseBuilder &&
				window.Tutor.CourseBuilder.Curriculum &&
				window.Tutor.CourseBuilder.Curriculum.Lesson &&
				typeof window.Tutor.CourseBuilder.Curriculum.Lesson
					.registerContent === 'function'
			) {
				clearInterval( interval );

				// Use React.createElement to wrap our container in a React element.
				// Tutor's slot system expects a React element for the "component" field.
				const React =
					window.React || ( window.wp && window.wp.element );
				if ( ! React || ! React.createElement ) {
					return;
				}

				const element = React.createElement( PPASlotComponent, null );

				window.Tutor.CourseBuilder.Curriculum.Lesson.registerContent(
					'bottom_of_sidebar',
					{
						name: 'pressprimer_assignment_selector',
						priority: 20,
						component: element,
					}
				);
			} else if ( attempts >= maxAttempts ) {
				clearInterval( interval );
			}
		}, 100 );
	}

	/**
	 * Get the lesson ID by walking up the React fiber tree from a DOM element.
	 *
	 * Our component is rendered inside Tutor's LessonModal via the slot/fill
	 * system. The LessonModal receives `lessonId` as a prop. By walking up the
	 * fiber tree from our own DOM element, we can find this prop reliably.
	 *
	 * Returns the raw lessonId including temp-* IDs for new unsaved lessons.
	 * Callers must check for temp IDs themselves.
	 *
	 * @param {HTMLElement} el DOM element inside the modal.
	 * @return {string|null} Lesson ID (may be temp-*) or null.
	 */
	function getLessonIdFromOwnFiber( el ) {
		if ( ! el ) {
			return null;
		}

		// Find the React fiber key on our DOM element.
		const keys = Object.keys( el );
		let fiberKey = null;
		for ( let i = 0; i < keys.length; i++ ) {
			if ( keys[ i ].startsWith( '__reactFiber' ) ) {
				fiberKey = keys[ i ];
				break;
			}
		}

		if ( ! fiberKey ) {
			return null;
		}

		// Walk up the fiber tree looking for a component with lessonId prop.
		let fiber = el[ fiberKey ];
		let maxDepth = 30; // LessonModal is several levels up.

		while ( fiber && maxDepth > 0 ) {
			try {
				const props = fiber.memoizedProps || fiber.pendingProps;
				if ( props ) {
					// The LessonModal component receives lessonId as a direct prop.
					// Return it even if it's a temp-* ID so callers can distinguish
					// "new unsaved lesson" from "fiber not ready yet".
					if ( props.lessonId ) {
						return String( props.lessonId );
					}
				}
			} catch ( err ) {
				// Ignore errors accessing React internals.
			}
			fiber = fiber.return;
			maxDepth--;
		}

		return null;
	}

	/**
	 * React component for the slot.
	 *
	 * Renders a mount point and populates it with vanilla DOM once mounted.
	 * This approach avoids depending on Tutor's React internals while working
	 * within their slot/fill system.
	 */
	function PPASlotComponent() {
		const React = window.React || ( window.wp && window.wp.element );
		const ref = React.useRef( null );
		const lastBuiltLessonId = React.useRef( null );

		// This effect runs on EVERY render. Tutor re-renders the modal (and our
		// slot component) each time a lesson modal opens. We detect the lesson ID
		// from the fiber tree and rebuild the assignment box if it changed.
		React.useEffect( function () {
			if ( ! ref.current ) {
				return;
			}

			// Primary: get lesson ID from fiber tree (most reliable).
			// This now returns temp-* IDs for new unsaved lessons.
			let lessonId = getLessonIdFromOwnFiber( ref.current );

			// Only fall back to click tracking if fiber lookup truly failed
			// (null means fiber not ready yet). Do NOT fall back when the fiber
			// returned a temp-* ID — that means this is a new unsaved lesson
			// and we must NOT reuse the previous lesson's ID.
			if ( lessonId === null ) {
				lessonId = currentEditingLessonId;
			}

			// Update the global so other functions can use it.
			if ( lessonId ) {
				currentEditingLessonId = lessonId;
			}

			// Only rebuild if the lesson ID changed (or first mount).
			if ( lessonId !== lastBuiltLessonId.current ) {
				lastBuiltLessonId.current = lessonId;
				buildAssignmentBox( ref.current );
			} else if ( ! ref.current.hasChildNodes() ) {
				// First mount — no children yet.
				buildAssignmentBox( ref.current );
			}
		} );

		// Also poll for lesson ID changes as a safety net. The fiber tree may
		// not be ready on the very first render, so we retry a few times.
		React.useEffect( function () {
			let pollAttempts = 0;
			const maxPollAttempts = 10; // 5 seconds.

			const checkInterval = setInterval( function () {
				pollAttempts++;
				if ( ! ref.current ) {
					return;
				}

				const lessonId = getLessonIdFromOwnFiber( ref.current );
				if (
					lessonId !== null &&
					lessonId !== lastBuiltLessonId.current
				) {
					currentEditingLessonId = lessonId;
					lastBuiltLessonId.current = lessonId;
					buildAssignmentBox( ref.current );
					clearInterval( checkInterval );
				}

				if ( pollAttempts >= maxPollAttempts ) {
					clearInterval( checkInterval );
				}
			}, 500 );

			return function () {
				clearInterval( checkInterval );
			};
		}, [] );

		return React.createElement( 'div', {
			ref,
			className: 'ppa-lesson-assignment-slot',
		} );
	}

	/**
	 * Build the assignment selector box inside the given container element.
	 *
	 * @param {HTMLElement} container The mount point element.
	 */
	function buildAssignmentBox( container ) {
		// Clear previous content.
		container.innerHTML = '';

		// Primary: get lesson ID from fiber tree (most reliable).
		let lessonId = getLessonIdFromOwnFiber( container );

		// Fallback: use the global from click tracking or modal inspection.
		if ( ! lessonId ) {
			lessonId = currentEditingLessonId || getLessonIdFromModal();
		}

		// Update the global.
		if ( lessonId ) {
			currentEditingLessonId = lessonId;
		}

		const isValidLessonId =
			lessonId && ! lessonId.toString().startsWith( 'temp-' );

		renderAssignmentBoxContent( container, lessonId, isValidLessonId );
	}

	/**
	 * Render the assignment box content after lesson ID resolution.
	 *
	 * @param {HTMLElement} container       The mount point element.
	 * @param {string|null} lessonId        Lesson ID.
	 * @param {boolean}     isValidLessonId Whether lessonId is a real ID.
	 */
	function renderAssignmentBoxContent(
		container,
		lessonId,
		isValidLessonId
	) {
		container.innerHTML = '';

		// Get current assignment if any.
		const currentAssignment = lessonId
			? lessonAssignments[ lessonId ]
			: null;

		// Create the assignment selector box.
		const box = createAssignmentBox(
			currentAssignment,
			lessonId,
			isValidLessonId
		);
		container.appendChild( box );

		// Set up event handlers.
		setupBoxHandlers( box, lessonId );
	}

	/**
	 * Try to get the lesson ID from the currently open modal's DOM.
	 *
	 * Uses Tutor's stable data-cy selectors to locate the lesson modal,
	 * then inspects React fiber props for the lesson ID.
	 *
	 * @return {string|null} Lesson ID or null.
	 */
	function getLessonIdFromModal() {
		// Use the captured ID from click tracking if available.
		if ( currentEditingLessonId ) {
			return currentEditingLessonId;
		}

		// Try to find the modal via stable Tutor data-cy selectors.
		const saveBtn = document.querySelector( '[data-cy="save-lesson"]' );
		if ( ! saveBtn ) {
			return null;
		}

		// Walk up to find the modal container.
		const modal =
			saveBtn.closest( '[data-focus-trap="true"]' ) ||
			saveBtn.closest( '[data-cy="tutor-modal"]' );
		if ( ! modal ) {
			return null;
		}

		// Try to extract the lesson ID from React fiber.
		return extractLessonIdFromFiber( modal );
	}

	/**
	 * Extract lesson ID from React fiber tree of a modal element.
	 *
	 * @param {HTMLElement} el DOM element to inspect.
	 * @return {string|null} Lesson ID or null.
	 */
	function extractLessonIdFromFiber( el ) {
		const keys = Object.keys( el );
		for ( let i = 0; i < keys.length; i++ ) {
			if (
				keys[ i ].startsWith( '__reactFiber' ) ||
				keys[ i ].startsWith( '__reactProps' )
			) {
				try {
					const fiber = el[ keys[ i ] ];
					const id = searchFiberForLessonId( fiber, 0 );
					if ( id ) {
						return id;
					}
				} catch ( err ) {
					// Ignore errors accessing React internals.
				}
			}
		}
		return null;
	}

	/**
	 * Search through React fiber for a lesson ID.
	 *
	 * @param {Object} fiber React fiber node.
	 * @param {number} depth Current recursion depth.
	 * @return {string|null} Lesson ID or null.
	 */
	function searchFiberForLessonId( fiber, depth ) {
		if ( ! fiber || depth > 8 ) {
			return null;
		}

		try {
			// Check memoizedProps for lessonId (the prop name used by LessonModal).
			if ( fiber.memoizedProps ) {
				const props = fiber.memoizedProps;
				if ( props.lessonId && parseInt( props.lessonId ) > 0 ) {
					return String( props.lessonId );
				}
				if ( props.lesson && props.lesson.id ) {
					return String( props.lesson.id );
				}
				if (
					props.item &&
					props.item.id &&
					props.item.post_type === 'lesson'
				) {
					return String( props.item.id );
				}
			}

			// Check pendingProps.
			if ( fiber.pendingProps ) {
				const pending = fiber.pendingProps;
				if ( pending.lessonId && parseInt( pending.lessonId ) > 0 ) {
					return String( pending.lessonId );
				}
			}

			// Recurse into parent fiber.
			if ( fiber.return ) {
				const parentResult = searchFiberForLessonId(
					fiber.return,
					depth + 1
				);
				if ( parentResult ) {
					return parentResult;
				}
			}
		} catch ( err ) {
			// Ignore errors.
		}

		return null;
	}

	/**
	 * Set up click listeners to capture lesson IDs before modals open.
	 *
	 * Uses Tutor's stable data attributes to identify lesson edit actions.
	 * Also detects "+ Lesson" / "+ Content" button clicks and clears the
	 * tracked lesson ID so new lesson modals don't inherit state.
	 */
	function setupLessonClickTracking() {
		document.addEventListener(
			'click',
			function ( e ) {
				const target = e.target;

				// Detect clicks on "Add Lesson" / "Add Content" buttons.
				// These create new lessons with temp IDs. Clear the tracked ID
				// so the assignment selector doesn't show the previous lesson's assignment.
				let addEl = target;
				let addDepth = 5;
				while ( addEl && addDepth > 0 ) {
					if ( addEl.getAttribute ) {
						const dataCy = addEl.getAttribute( 'data-cy' );
						if (
							dataCy === 'add-lesson' ||
							dataCy === 'add-content' ||
							dataCy === 'add-topic-content'
						) {
							currentEditingLessonId = null;
							return;
						}
						// Also check button text as fallback.
						const btnText = ( addEl.textContent || '' )
							.trim()
							.toLowerCase();
						if (
							addEl.tagName === 'BUTTON' &&
							( btnText === 'lesson' || btnText === '+ lesson' )
						) {
							currentEditingLessonId = null;
							return;
						}
					}
					addEl = addEl.parentElement;
					addDepth--;
				}

				let el = target;
				let maxDepth = 10;

				while ( el && maxDepth > 0 ) {
					// Check for Tutor's stable data-cy="edit-lesson" attribute.
					if (
						el.getAttribute &&
						el.getAttribute( 'data-cy' ) === 'edit-lesson'
					) {
						const lessonId = extractLessonIdFromClickTarget( el );
						if ( lessonId ) {
							currentEditingLessonId = lessonId;
							return;
						}
					}

					// Check for data-lesson-icon attribute (another Tutor stable marker).
					if (
						el.getAttribute &&
						el.getAttribute( 'data-lesson-icon' ) !== null
					) {
						const iconId = extractLessonIdFromClickTarget( el );
						if ( iconId ) {
							currentEditingLessonId = iconId;
							return;
						}
					}

					// Check for generic data attributes.
					if ( el.getAttribute ) {
						const dataId =
							el.getAttribute( 'data-lesson-id' ) ||
							el.getAttribute( 'data-id' ) ||
							el.getAttribute( 'data-content-id' );
						if ( dataId && parseInt( dataId ) > 0 ) {
							currentEditingLessonId = dataId;
							return;
						}
					}

					el = el.parentElement;
					maxDepth--;
				}

				// Fallback: inspect React fiber on click target's ancestors.
				el = target;
				maxDepth = 10;
				while ( el && maxDepth > 0 ) {
					const elKeys = Object.keys( el );
					for ( let i = 0; i < elKeys.length; i++ ) {
						if (
							elKeys[ i ].startsWith( '__reactFiber' ) ||
							elKeys[ i ].startsWith( '__reactProps' )
						) {
							try {
								const fiber = el[ elKeys[ i ] ];
								const fiberLessonId = searchFiberForContentId(
									fiber,
									0
								);
								if ( fiberLessonId ) {
									currentEditingLessonId = fiberLessonId;
									return;
								}
							} catch ( err ) {
								// Ignore.
							}
						}
					}
					el = el.parentElement;
					maxDepth--;
				}
			},
			true
		); // Capture phase to run before React handlers.
	}

	/**
	 * Extract lesson ID from a click target element via React fiber.
	 *
	 * @param {HTMLElement} el The clicked element.
	 * @return {string|null} Lesson ID or null.
	 */
	function extractLessonIdFromClickTarget( el ) {
		const keys = Object.keys( el );
		for ( let i = 0; i < keys.length; i++ ) {
			if (
				keys[ i ].startsWith( '__reactFiber' ) ||
				keys[ i ].startsWith( '__reactProps' )
			) {
				try {
					const fiber = el[ keys[ i ] ];
					const id = searchFiberForContentId( fiber, 0 );
					if ( id ) {
						return id;
					}
				} catch ( err ) {
					// Ignore.
				}
			}
		}
		return null;
	}

	/**
	 * Search React fiber for a content/lesson ID.
	 *
	 * Looks for common patterns in Tutor's curriculum data structure.
	 *
	 * @param {Object} fiber React fiber node.
	 * @param {number} depth Current recursion depth.
	 * @return {string|null} Content ID or null.
	 */
	function searchFiberForContentId( fiber, depth ) {
		if ( ! fiber || depth > 6 ) {
			return null;
		}

		try {
			// Check memoizedProps.
			if ( fiber.memoizedProps ) {
				const props = fiber.memoizedProps;

				// Direct ID properties.
				if (
					props.id &&
					( props.post_type === 'lesson' ||
						props.type === 'lesson' ||
						props.content_type === 'lesson' )
				) {
					return String( props.id );
				}
				if ( props.contentId && parseInt( props.contentId ) > 0 ) {
					return String( props.contentId );
				}
				if ( props.lessonId && parseInt( props.lessonId ) > 0 ) {
					return String( props.lessonId );
				}

				// Nested objects.
				if ( props.lesson && props.lesson.id ) {
					return String( props.lesson.id );
				}
				if (
					props.item &&
					props.item.id &&
					( props.item.post_type === 'lesson' ||
						props.item.type === 'lesson' )
				) {
					return String( props.item.id );
				}
				if (
					props.content &&
					props.content.id &&
					props.content.type === 'lesson'
				) {
					return String( props.content.id );
				}
				if (
					props.data &&
					props.data.id &&
					props.data.post_type === 'lesson'
				) {
					return String( props.data.id );
				}

				// onClick handlers often have the ID in closure - check children props.
				if ( props.onClick && props.id && parseInt( props.id ) > 0 ) {
					return String( props.id );
				}
			}

			// Check pendingProps.
			if ( fiber.pendingProps ) {
				const pending = fiber.pendingProps;
				if ( pending.lessonId && parseInt( pending.lessonId ) > 0 ) {
					return String( pending.lessonId );
				}
				if ( pending.contentId && parseInt( pending.contentId ) > 0 ) {
					return String( pending.contentId );
				}
			}

			// Recurse into parent fiber.
			if ( fiber.return ) {
				const parentResult = searchFiberForContentId(
					fiber.return,
					depth + 1
				);
				if ( parentResult ) {
					return parentResult;
				}
			}
		} catch ( err ) {
			// Ignore errors.
		}

		return null;
	}

	/**
	 * Create the assignment selector box element.
	 *
	 * @param {Object|null} currentAssignment Current assignment data or null.
	 * @param {string|null} lessonId          Lesson ID.
	 * @param {boolean}     isValidLessonId   Whether lessonId is a real ID.
	 * @return {HTMLElement} The assignment box element.
	 */
	function createAssignmentBox(
		currentAssignment,
		lessonId,
		isValidLessonId
	) {
		const box = document.createElement( 'div' );
		box.className = 'ppa-lesson-assignment-box';

		if ( currentAssignment ) {
			box.innerHTML =
				'<div class="ppa-box-header">' +
				'<span class="ppa-box-title">PressPrimer Assignment</span>' +
				'</div>' +
				'<div class="ppa-box-content">' +
				'<div class="ppa-assignment-selector">' +
				'<div class="ppa-selected-assignment">' +
				'<div class="ppa-selected-info">' +
				'<span class="ppa-assignment-id">' +
				currentAssignment.id +
				'</span>' +
				'<span class="ppa-assignment-title">' +
				escapeHtml( currentAssignment.title ) +
				'</span>' +
				'</div>' +
				'<div class="ppa-selected-actions">' +
				'<a href="' +
				config.adminUrl +
				'admin.php?page=pressprimer-assignment-assignments&action=edit&assignment=' +
				currentAssignment.id +
				'" target="_blank" class="ppa-action-btn ppa-edit-btn" title="Edit Assignment">' +
				'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>' +
				'</a>' +
				'<button type="button" class="ppa-action-btn ppa-remove-btn" title="Remove Assignment">' +
				'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
				'</button>' +
				'</div>' +
				'</div>' +
				'</div>' +
				'<p class="ppa-help-text">Students must pass this assignment to complete the lesson.</p>' +
				'</div>';
		} else if ( isValidLessonId ) {
			// We have a valid lesson ID - show the search interface.
			box.innerHTML =
				'<div class="ppa-box-header">' +
				'<span class="ppa-box-title">PressPrimer Assignment</span>' +
				'</div>' +
				'<div class="ppa-box-content">' +
				'<div class="ppa-assignment-selector">' +
				'<div class="ppa-search-container">' +
				'<input type="text" class="ppa-assignment-search" placeholder="' +
				( strings.searchPlaceholder || 'Search assignments...' ) +
				'" autocomplete="off" />' +
				'</div>' +
				'<div class="ppa-results-container">' +
				'<div class="ppa-results-heading">Recent Assignments</div>' +
				'<div class="ppa-assignment-results"></div>' +
				'</div>' +
				'</div>' +
				'<p class="ppa-help-text">Link an assignment to this lesson. Students must pass to complete.</p>' +
				'</div>';
		} else {
			// No valid lesson ID - show helpful message.
			box.innerHTML =
				'<div class="ppa-box-header">' +
				'<span class="ppa-box-title">PressPrimer Assignment</span>' +
				'</div>' +
				'<div class="ppa-box-content">' +
				'<div class="ppa-no-lesson-id">' +
				'<p class="ppa-notice">' +
				'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">' +
				'<circle cx="12" cy="12" r="10"/>' +
				'<line x1="12" y1="8" x2="12" y2="12"/>' +
				'<line x1="12" y1="16" x2="12.01" y2="16"/>' +
				'</svg>' +
				'This lesson must be saved before an assignment can be linked to it. Give it a name, save it, then reopen it to attach a PressPrimer Assignment.' +
				'</p>' +
				'</div>' +
				'</div>';
		}

		return box;
	}

	/**
	 * Set up event handlers for the assignment box.
	 *
	 * @param {HTMLElement} box      The assignment box element.
	 * @param {string|null} lessonId Lesson ID.
	 */
	function setupBoxHandlers( box, lessonId ) {
		const searchInput = box.querySelector( '.ppa-assignment-search' );
		const resultsContainer = box.querySelector( '.ppa-assignment-results' );
		const resultsHeading = box.querySelector( '.ppa-results-heading' );
		const removeBtn = box.querySelector( '.ppa-remove-btn' );

		if ( searchInput && resultsContainer ) {
			let searchTimeout;

			// Load recent assignments on focus.
			searchInput.addEventListener( 'focus', function () {
				if ( this.value.length < 2 ) {
					loadAssignments(
						resultsContainer,
						resultsHeading,
						null,
						box,
						lessonId
					);
				}
			} );

			// Search on input.
			searchInput.addEventListener( 'input', function () {
				const query = this.value.trim();
				clearTimeout( searchTimeout );

				if ( query.length < 2 ) {
					loadAssignments(
						resultsContainer,
						resultsHeading,
						null,
						box,
						lessonId
					);
					return;
				}

				searchTimeout = setTimeout( function () {
					loadAssignments(
						resultsContainer,
						resultsHeading,
						query,
						box,
						lessonId
					);
				}, 300 );
			} );

			// Auto-load recent assignments.
			loadAssignments(
				resultsContainer,
				resultsHeading,
				null,
				box,
				lessonId
			);
		}

		if ( removeBtn ) {
			removeBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				removeAssignmentFromLesson( box, lessonId );
			} );
		}
	}

	/**
	 * Load assignments (recent or search).
	 *
	 * @param {HTMLElement} container Results container.
	 * @param {HTMLElement} heading   Results heading.
	 * @param {string|null} query     Search query or null for recent.
	 * @param {HTMLElement} box       The assignment box element.
	 * @param {string|null} lessonId  Lesson ID.
	 */
	function loadAssignments( container, heading, query, box, lessonId ) {
		heading.textContent = query ? 'Search Results' : 'Recent Assignments';
		container.innerHTML = '<div class="ppa-loading">Loading...</div>';

		let url = config.restUrl + 'ppa/v1/tutorlms/assignments/search';
		url += query ? '?search=' + encodeURIComponent( query ) : '?recent=1';

		fetch( url, {
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': config.restNonce,
			},
		} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				return response.json();
			} )
			.then( function ( data ) {
				if (
					data.success &&
					data.assignments &&
					data.assignments.length > 0
				) {
					renderAssignmentResults(
						data.assignments,
						container,
						box,
						lessonId
					);
				} else {
					container.innerHTML =
						'<div class="ppa-no-results">' +
						( strings.noAssignments || 'No assignments found' ) +
						'</div>';
				}
			} )
			.catch( function () {
				container.innerHTML =
					'<div class="ppa-error">' +
					( strings.error || 'Error loading assignments' ) +
					'</div>';
			} );
	}

	/**
	 * Render assignment results.
	 *
	 * @param {Array}       assignments Array of assignment objects.
	 * @param {HTMLElement} container   Results container.
	 * @param {HTMLElement} box         The assignment box element.
	 * @param {string|null} lessonId    Lesson ID.
	 */
	function renderAssignmentResults( assignments, container, box, lessonId ) {
		container.innerHTML = '';

		assignments.forEach( function ( assignment ) {
			const item = document.createElement( 'div' );
			item.className = 'ppa-assignment-item';
			item.innerHTML =
				'<span class="ppa-assignment-id">' +
				assignment.id +
				'</span>' +
				'<span class="ppa-assignment-title">' +
				escapeHtml( assignment.title ) +
				'</span>';

			item.addEventListener( 'click', function () {
				selectAssignment( assignment, box, lessonId );
			} );

			container.appendChild( item );
		} );
	}

	/**
	 * Select an assignment and save the association.
	 *
	 * @param {Object}      assignment Assignment data.
	 * @param {HTMLElement} box        The assignment box element.
	 * @param {string|null} lessonId   Lesson ID.
	 */
	function selectAssignment( assignment, box, lessonId ) {
		saveLessonAssignment(
			lessonId,
			assignment.id,
			function ( success, data ) {
				if ( success ) {
					// Update lessonId if we got a real one back.
					if ( data && data.lesson_id ) {
						lessonId = data.lesson_id;
					}

					lessonAssignments[ lessonId ] = assignment;

					// Update the box to show selected assignment.
					const selector = box.querySelector(
						'.ppa-assignment-selector'
					);
					selector.innerHTML =
						'<div class="ppa-selected-assignment">' +
						'<div class="ppa-selected-info">' +
						'<span class="ppa-assignment-id">' +
						assignment.id +
						'</span>' +
						'<span class="ppa-assignment-title">' +
						escapeHtml( assignment.title ) +
						'</span>' +
						'</div>' +
						'<div class="ppa-selected-actions">' +
						'<a href="' +
						config.adminUrl +
						'admin.php?page=pressprimer-assignment-assignments&action=edit&assignment=' +
						assignment.id +
						'" target="_blank" class="ppa-action-btn ppa-edit-btn" title="Edit Assignment">' +
						'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>' +
						'</a>' +
						'<button type="button" class="ppa-action-btn ppa-remove-btn" title="Remove Assignment">' +
						'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
						'</button>' +
						'</div>' +
						'</div>';

					// Reattach remove handler.
					const removeBtn =
						selector.querySelector( '.ppa-remove-btn' );
					removeBtn.addEventListener( 'click', function ( e ) {
						e.preventDefault();
						removeAssignmentFromLesson( box, lessonId );
					} );
				} else {
					/* eslint-disable-next-line no-alert */
					window.alert(
						'Failed to save assignment. Please try again.'
					);
				}
			}
		);
	}

	/**
	 * Remove assignment from lesson.
	 *
	 * @param {HTMLElement} box      The assignment box element.
	 * @param {string|null} lessonId Lesson ID.
	 */
	function removeAssignmentFromLesson( box, lessonId ) {
		saveLessonAssignment( lessonId, 0, function ( success ) {
			if ( success ) {
				delete lessonAssignments[ lessonId ];

				// Update box to show search again.
				const selector = box.querySelector(
					'.ppa-assignment-selector'
				);
				selector.innerHTML =
					'<div class="ppa-search-container">' +
					'<input type="text" class="ppa-assignment-search" placeholder="' +
					( strings.searchPlaceholder || 'Search assignments...' ) +
					'" autocomplete="off" />' +
					'</div>' +
					'<div class="ppa-results-container">' +
					'<div class="ppa-results-heading">Recent Assignments</div>' +
					'<div class="ppa-assignment-results"></div>' +
					'</div>';

				// Reattach handlers.
				setupBoxHandlers( box, lessonId );
			}
		} );
	}

	/**
	 * Save lesson assignment association via REST API.
	 *
	 * @param {string|null} lessonId     Lesson ID.
	 * @param {number}      assignmentId Assignment ID (0 to remove).
	 * @param {Function}    callback     Callback(success, data).
	 */
	function saveLessonAssignment( lessonId, assignmentId, callback ) {
		fetch( config.restUrl + 'ppa/v1/tutorlms/lesson-assignment', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.restNonce,
			},
			body: JSON.stringify( {
				course_id: courseId,
				lesson_id: lessonId,
				assignment_id: assignmentId,
			} ),
		} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				return response.json();
			} )
			.then( function ( data ) {
				callback( data.success, data );
			} )
			.catch( function () {
				callback( false );
			} );
	}

	/**
	 * Escape HTML entities.
	 *
	 * @param {string} text Text to escape.
	 * @return {string} Escaped text.
	 */
	function escapeHtml( text ) {
		const div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}

	/**
	 * Inject CSS styles.
	 */
	function injectStyles() {
		const style = document.createElement( 'style' );
		style.id = 'ppa-tutorlms-lesson-editor-styles';
		style.textContent =
			'/* PPA Assignment Box in Lesson Editor */' +
			'.ppa-lesson-assignment-box {' +
			'background: #fff;' +
			'border: 1px solid #e2e8f0;' +
			'border-radius: 8px;' +
			'margin: 16px 0;' +
			'overflow: hidden;' +
			'font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;' +
			'}' +
			'.ppa-box-header {' +
			'background: linear-gradient(135deg, #0073aa 0%, #005a87 100%);' +
			'padding: 12px 16px;' +
			'}' +
			'.ppa-box-title {' +
			'color: #fff;' +
			'font-weight: 600;' +
			'font-size: 14px;' +
			'}' +
			'.ppa-box-content {' +
			'padding: 16px;' +
			'}' +
			'.ppa-help-text {' +
			'margin: 12px 0 0;' +
			'font-size: 12px;' +
			'color: #666;' +
			'line-height: 1.4;' +
			'}' +
			/* Search */
			'.ppa-search-container {' +
			'margin-bottom: 12px;' +
			'}' +
			'.ppa-assignment-search {' +
			'width: 100%;' +
			'padding: 8px 12px;' +
			'border: 1px solid #d0d5dd;' +
			'border-radius: 6px;' +
			'font-size: 13px;' +
			'box-sizing: border-box;' +
			'}' +
			'.ppa-assignment-search:focus {' +
			'border-color: #0073aa;' +
			'outline: none;' +
			'box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.15);' +
			'}' +
			/* Results */
			'.ppa-results-container {' +
			'max-height: 200px;' +
			'overflow: hidden;' +
			'display: flex;' +
			'flex-direction: column;' +
			'}' +
			'.ppa-results-heading {' +
			'font-size: 11px;' +
			'font-weight: 600;' +
			'color: #888;' +
			'text-transform: uppercase;' +
			'letter-spacing: 0.5px;' +
			'margin-bottom: 8px;' +
			'}' +
			'.ppa-assignment-results {' +
			'flex: 1;' +
			'overflow-y: auto;' +
			'border: 1px solid #e8e8e8;' +
			'border-radius: 6px;' +
			'max-height: 150px;' +
			'}' +
			'.ppa-assignment-item {' +
			'padding: 10px 12px;' +
			'cursor: pointer;' +
			'border-bottom: 1px solid #f0f0f0;' +
			'display: flex;' +
			'align-items: center;' +
			'gap: 8px;' +
			'transition: background 0.15s;' +
			'font-size: 13px;' +
			'}' +
			'.ppa-assignment-item:hover {' +
			'background: #f0f6fc;' +
			'}' +
			'.ppa-assignment-item:last-child {' +
			'border-bottom: none;' +
			'}' +
			'.ppa-assignment-id {' +
			'color: #0073aa;' +
			'font-weight: 600;' +
			'font-size: 12px;' +
			'}' +
			'.ppa-assignment-title {' +
			'flex: 1;' +
			'color: #333;' +
			'white-space: nowrap;' +
			'overflow: hidden;' +
			'text-overflow: ellipsis;' +
			'}' +
			'.ppa-loading,' +
			'.ppa-no-results,' +
			'.ppa-error {' +
			'padding: 16px;' +
			'text-align: center;' +
			'color: #888;' +
			'font-size: 13px;' +
			'}' +
			/* Selected Assignment */
			'.ppa-selected-assignment {' +
			'display: flex;' +
			'align-items: center;' +
			'justify-content: space-between;' +
			'padding: 12px;' +
			'background: linear-gradient(135deg, #f0f6fc 0%, #e8f0fe 100%);' +
			'border: 1px solid #b8d4ea;' +
			'border-radius: 6px;' +
			'}' +
			'.ppa-selected-info {' +
			'display: flex;' +
			'align-items: center;' +
			'gap: 8px;' +
			'flex: 1;' +
			'min-width: 0;' +
			'}' +
			'.ppa-selected-info .ppa-assignment-id {' +
			'flex-shrink: 0;' +
			'}' +
			'.ppa-selected-info .ppa-assignment-title {' +
			'white-space: nowrap;' +
			'overflow: hidden;' +
			'text-overflow: ellipsis;' +
			'}' +
			'.ppa-selected-actions {' +
			'display: flex;' +
			'gap: 4px;' +
			'flex-shrink: 0;' +
			'}' +
			'.ppa-action-btn {' +
			'background: none;' +
			'border: none;' +
			'padding: 6px;' +
			'cursor: pointer;' +
			'color: #666;' +
			'border-radius: 4px;' +
			'display: flex;' +
			'align-items: center;' +
			'justify-content: center;' +
			'text-decoration: none;' +
			'}' +
			'.ppa-edit-btn:hover {' +
			'background: #dce8f5;' +
			'color: #0073aa;' +
			'}' +
			'.ppa-remove-btn:hover {' +
			'background: #ffe8e8;' +
			'color: #dc3545;' +
			'}' +
			/* No Lesson ID Notice */
			'.ppa-no-lesson-id {' +
			'text-align: center;' +
			'padding: 8px 0;' +
			'}' +
			'.ppa-notice {' +
			'margin: 0 0 8px;' +
			'padding: 10px 12px;' +
			'background: #fff8e5;' +
			'border: 1px solid #f0c36d;' +
			'border-radius: 4px;' +
			'font-size: 13px;' +
			'color: #6d5a20;' +
			'line-height: 1.4;' +
			'}';
		document.head.appendChild( style );
	}

	// Initialize when DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
