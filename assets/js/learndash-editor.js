/**
 * LearnDash Integration - Block Editor Sidebar
 *
 * Adds a sidebar panel to LearnDash content types for selecting a PPA Assignment.
 *
 * @param {Object} wp WordPress global object.
 * @package
 * @since 1.0.0
 */

( function ( wp ) {
	const { registerPlugin } = wp.plugins;
	// Use wp.editor for WP 6.6+, fall back to wp.editPost for older versions
	const { PluginDocumentSettingPanel } = wp.editor || wp.editPost;
	const { useSelect, useDispatch } = wp.data;
	const { useState, useEffect, useCallback, createElement: el } = wp.element;
	const { Button, Spinner } = wp.components;
	const { __ } = wp.i18n;
	const apiFetch = wp.apiFetch;

	// Get configuration from localized data
	const config = window.pressprimerAssignmentLearnDash || {};
	const strings = config.strings || {};

	/**
	 * Assignment Selector Component
	 * @param {Object}   root0              Props object.
	 * @param {number}   root0.assignmentId Selected assignment ID.
	 * @param {Function} root0.onSelect     Callback when assignment is selected.
	 */
	const AssignmentSelector = ( { assignmentId, onSelect } ) => {
		const [ searchQuery, setSearchQuery ] = useState( '' );
		const [ searchResults, setSearchResults ] = useState( [] );
		const [ isSearching, setIsSearching ] = useState( false );
		const [ selectedAssignment, setSelectedAssignment ] = useState( null );
		const [ showResults, setShowResults ] = useState( false );

		// Format assignment display string
		const formatAssignmentDisplay = ( assignment ) =>
			assignment.id + ' - ' + assignment.title;

		// Load assignment title if we have an ID
		useEffect( () => {
			if ( assignmentId && ! selectedAssignment ) {
				apiFetch( {
					path: '/ppa/v1/assignments/' + assignmentId,
				} )
					.then( ( response ) => {
						if ( response && response.title ) {
							setSelectedAssignment( {
								id: assignmentId,
								title: response.title,
							} );
						}
					} )
					.catch( () => {
						// Assignment might not exist
					} );
			}
		}, [ assignmentId ] ); // eslint-disable-line react-hooks/exhaustive-deps

		// Load recent assignments on focus
		const handleFocus = useCallback( () => {
			if ( selectedAssignment ) {
				return;
			}

			setIsSearching( true );
			apiFetch( {
				path: '/ppa/v1/learndash/assignments/search?recent=1',
			} )
				.then( ( response ) => {
					if ( response.success && response.assignments ) {
						setSearchResults( response.assignments );
						setShowResults( true );
					}
				} )
				.catch( () => {
					setSearchResults( [] );
				} )
				.finally( () => {
					setIsSearching( false );
				} );
		}, [ selectedAssignment ] );

		// Debounced search - only triggers when user types 2+ characters
		useEffect( () => {
			if ( searchQuery.length < 2 ) {
				return;
			}

			const timeoutId = setTimeout( () => {
				setIsSearching( true );
				apiFetch( {
					path:
						'/ppa/v1/learndash/assignments/search?search=' +
						encodeURIComponent( searchQuery ),
				} )
					.then( ( response ) => {
						if ( response.success ) {
							setSearchResults( response.assignments || [] );
							setShowResults( true );
						}
					} )
					.catch( () => {
						setSearchResults( [] );
					} )
					.finally( () => {
						setIsSearching( false );
					} );
			}, 300 );

			return () => clearTimeout( timeoutId );
		}, [ searchQuery ] );

		const handleSelect = useCallback(
			( assignment ) => {
				setSelectedAssignment( assignment );
				setSearchQuery( '' );
				setShowResults( false );
				onSelect( assignment.id );
			},
			[ onSelect ]
		);

		const handleRemove = useCallback( () => {
			setSelectedAssignment( null );
			onSelect( 0 );
		}, [ onSelect ] );

		// Render selected assignment view
		if ( selectedAssignment ) {
			return el(
				'div',
				{ className: 'ppa-assignment-selector-gutenberg' },
				el(
					'div',
					{ className: 'ppa-selected-assignment-gutenberg' },
					el(
						'span',
						{ className: 'ppa-assignment-name' },
						formatAssignmentDisplay( selectedAssignment )
					),
					el(
						Button,
						{
							isDestructive: true,
							isSmall: true,
							onClick: handleRemove,
							'aria-label': __(
								'Remove assignment',
								'pressprimer-assignment'
							),
						},
						__( 'Remove', 'pressprimer-assignment' )
					)
				)
			);
		}

		// Render search view - use native input for better onFocus support
		return el(
			'div',
			{ className: 'ppa-assignment-selector-gutenberg' },
			el(
				'div',
				{ className: 'ppa-assignment-search-gutenberg' },
				el( 'input', {
					type: 'text',
					className: 'components-text-control__input',
					placeholder:
						strings.searchPlaceholder ||
						__(
							'Click to browse or type to search…',
							'pressprimer-assignment'
						),
					value: searchQuery,
					onChange( e ) {
						setSearchQuery( e.target.value );
					},
					onFocus: handleFocus,
					autoComplete: 'off',
				} ),
				isSearching && el( Spinner, null ),
				showResults &&
					searchResults.length > 0 &&
					el(
						'div',
						{ className: 'ppa-search-results-gutenberg' },
						searchResults.map( ( assignment ) =>
							el(
								Button,
								{
									key: assignment.id,
									className: 'ppa-search-result-item',
									onClick: () => handleSelect( assignment ),
								},
								el(
									'span',
									{ className: 'ppa-assignment-id' },
									assignment.id
								),
								' - ' + assignment.title
							)
						)
					),
				showResults &&
					searchResults.length === 0 &&
					! isSearching &&
					el(
						'p',
						{ className: 'ppa-no-results' },
						__( 'No assignments found', 'pressprimer-assignment' )
					)
			)
		);
	};

	/**
	 * PPA LearnDash Panel Component
	 */
	const PPALearnDashPanel = () => {
		const { editPost } = useDispatch( 'core/editor' );

		// Try REST field first (ppa_assignment_id), fall back to meta for compatibility
		const postData = useSelect( ( select ) => {
			const editor = select( 'core/editor' );
			return {
				ppaAssignmentId:
					editor.getEditedPostAttribute( 'ppa_assignment_id' ),
				meta: editor.getEditedPostAttribute( 'meta' ) || {},
			};
		} );

		// Use REST field if available, otherwise fall back to meta
		const assignmentId =
			postData.ppaAssignmentId !== undefined
				? postData.ppaAssignmentId
				: postData.meta[ config.metaKeyAssignmentId ] || 0;

		const handleAssignmentSelect = useCallback(
			( newAssignmentId ) => {
				// Update both REST field and meta for compatibility
				editPost( {
					ppa_assignment_id: newAssignmentId,
					meta: {
						[ config.metaKeyAssignmentId ]: newAssignmentId,
					},
				} );
			},
			[ editPost ]
		);

		const children = [
			el(
				'p',
				null,
				el(
					'strong',
					null,
					strings.selectAssignment ||
						__( 'Select Assignment', 'pressprimer-assignment' )
				)
			),
			el( AssignmentSelector, {
				assignmentId,
				onSelect: handleAssignmentSelect,
			} ),
		];

		// Add help text when an assignment is selected
		if ( assignmentId > 0 ) {
			children.push(
				el(
					'p',
					{
						className: 'description',
						style: { marginTop: '8px', color: '#757575' },
					},
					strings.assignmentHelp ||
						__(
							'The assignment will appear at the end of this content. Users must pass to mark it complete.',
							'pressprimer-assignment'
						)
				)
			);
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'ppa-learndash-panel',
				title:
					strings.panelTitle ||
					__( 'PressPrimer Assignment', 'pressprimer-assignment' ),
				className: 'ppa-learndash-panel',
			},
			children
		);
	};

	// Only register if we're on a supported post type
	if ( config.postType ) {
		registerPlugin( 'ppa-learndash', {
			render: PPALearnDashPanel,
			icon: 'welcome-learn-more',
		} );
	}

	// Add styles
	const style = document.createElement( 'style' );
	style.textContent = `
		.ppa-learndash-panel .ppa-selected-assignment-gutenberg {
			display: flex;
			align-items: center;
			gap: 8px;
			padding: 8px 12px;
			background: #f0f6fc;
			border-radius: 4px;
			margin-top: 8px;
		}
		.ppa-learndash-panel .ppa-assignment-name {
			flex: 1;
			font-weight: 500;
		}
		.ppa-learndash-panel .ppa-assignment-search-gutenberg {
			position: relative;
			margin-top: 8px;
		}
		.ppa-learndash-panel .ppa-search-results-gutenberg {
			position: absolute;
			top: 100%;
			left: 0;
			right: 0;
			background: #fff;
			border: 1px solid #ddd;
			border-top: none;
			max-height: 200px;
			overflow-y: auto;
			z-index: 1000;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
		}
		.ppa-learndash-panel .ppa-search-result-item {
			display: block;
			width: 100%;
			padding: 8px 12px;
			text-align: left;
			border: none;
			border-bottom: 1px solid #f0f0f0;
			background: none;
			cursor: pointer;
		}
		.ppa-learndash-panel .ppa-search-result-item:hover {
			background: #f0f0f0;
		}
		.ppa-learndash-panel .ppa-search-result-item:last-child {
			border-bottom: none;
		}
		.ppa-learndash-panel .ppa-no-results {
			padding: 12px;
			color: #666;
			font-style: italic;
			margin: 0;
			background: #f9f9f9;
			border: 1px solid #ddd;
			border-top: none;
		}
		.ppa-learndash-panel .ppa-assignment-id {
			color: #666;
			font-weight: 600;
		}
		.ppa-learndash-panel .ppa-assignment-search-gutenberg input {
			width: 100%;
			padding: 8px 12px;
			border: 1px solid #8c8f94;
			border-radius: 2px;
			font-size: 13px;
			line-height: 1.4;
			min-height: 36px;
			box-sizing: border-box;
		}
		.ppa-learndash-panel .ppa-assignment-search-gutenberg input:focus {
			border-color: #2271b1;
			box-shadow: 0 0 0 1px #2271b1;
			outline: none;
		}
	`;
	document.head.appendChild( style );
} )( window.wp );
