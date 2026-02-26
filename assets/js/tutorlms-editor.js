/**
 * TutorLMS Integration - Block Editor Sidebar
 *
 * Adds a sidebar panel to TutorLMS lesson post type for selecting a PPA Assignment.
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
	const { Button, Spinner, ToggleControl } = wp.components;
	const { __ } = wp.i18n;
	const apiFetch = wp.apiFetch;

	// Get configuration from localized data.
	const config = window.pressprimerAssignmentTutorLMS || {};
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

		// Format assignment display string.
		const formatAssignmentDisplay = ( assignment ) =>
			assignment.id + ' - ' + assignment.title;

		// Load assignment title if we have an ID.
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
						// Assignment might not exist.
					} );
			}
		}, [ assignmentId ] ); // eslint-disable-line react-hooks/exhaustive-deps

		// Load recent assignments on focus.
		const handleFocus = useCallback( () => {
			if ( selectedAssignment ) {
				return;
			}

			setIsSearching( true );
			apiFetch( {
				path: '/ppa/v1/tutorlms/assignments/search?recent=1',
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

		// Debounced search.
		useEffect( () => {
			if ( searchQuery.length < 2 ) {
				return;
			}

			const timeoutId = setTimeout( () => {
				setIsSearching( true );
				apiFetch( {
					path:
						'/ppa/v1/tutorlms/assignments/search?search=' +
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

		// Render selected assignment view.
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

		// Render search view - use native input for better onFocus support.
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
							'Click to browse or type to search\u2026',
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
	 * PPA TutorLMS Panel Component
	 */
	const PPATutorLMSPanel = () => {
		const { editPost } = useDispatch( 'core/editor' );
		const meta = useSelect(
			( select ) =>
				select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {}
		);

		const assignmentId = meta[ config.metaKeyAssignmentId ] || 0;
		const requirePass = meta[ config.metaKeyRequirePass ] || '';

		const handleAssignmentSelect = useCallback(
			( newAssignmentId ) => {
				editPost( {
					meta: {
						[ config.metaKeyAssignmentId ]: newAssignmentId,
					},
				} );
			},
			[ editPost ]
		);

		const handleRequirePassToggle = useCallback(
			( value ) => {
				editPost( {
					meta: {
						[ config.metaKeyRequirePass ]: value ? '1' : '',
					},
				} );
			},
			[ editPost ]
		);

		const children = [
			el(
				'p',
				{ key: 'label' },
				el(
					'strong',
					null,
					strings.selectAssignment ||
						__( 'Select Assignment', 'pressprimer-assignment' )
				)
			),
			el( AssignmentSelector, {
				key: 'selector',
				assignmentId,
				onSelect: handleAssignmentSelect,
			} ),
		];

		// Add require pass toggle.
		children.push(
			el(
				'div',
				{ key: 'toggle', style: { marginTop: '16px' } },
				el( ToggleControl, {
					label:
						strings.requirePassLabel ||
						__(
							'Require passing grade to complete lesson',
							'pressprimer-assignment'
						),
					checked: requirePass === '1',
					onChange: handleRequirePassToggle,
				} )
			),
			el(
				'p',
				{
					key: 'help',
					className: 'description',
					style: { marginTop: '8px', color: '#757575' },
				},
				strings.requirePassHelp ||
					__(
						'When enabled, students must pass this assignment to mark the lesson complete.',
						'pressprimer-assignment'
					)
			)
		);

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'ppa-tutorlms-panel',
				title:
					strings.panelTitle ||
					__( 'PressPrimer Assignment', 'pressprimer-assignment' ),
				className: 'ppa-tutorlms-panel',
			},
			children
		);
	};

	// Only register if we're on a supported post type.
	if ( config.postType ) {
		registerPlugin( 'ppa-tutorlms', {
			render: PPATutorLMSPanel,
			icon: 'welcome-learn-more',
		} );
	}

	// Add styles.
	const style = document.createElement( 'style' );
	style.textContent = `
		.ppa-tutorlms-panel .ppa-selected-assignment-gutenberg {
			display: flex;
			align-items: center;
			gap: 8px;
			padding: 8px 12px;
			background: #f0f6fc;
			border-radius: 4px;
			margin-top: 8px;
		}
		.ppa-tutorlms-panel .ppa-assignment-name {
			flex: 1;
			font-weight: 500;
		}
		.ppa-tutorlms-panel .ppa-assignment-search-gutenberg {
			position: relative;
			margin-top: 8px;
		}
		.ppa-tutorlms-panel .ppa-search-results-gutenberg {
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
		.ppa-tutorlms-panel .ppa-search-result-item {
			display: block;
			width: 100%;
			padding: 8px 12px;
			text-align: left;
			border: none;
			border-bottom: 1px solid #f0f0f0;
			background: none;
			cursor: pointer;
		}
		.ppa-tutorlms-panel .ppa-search-result-item:hover {
			background: #f0f0f0;
		}
		.ppa-tutorlms-panel .ppa-search-result-item:last-child {
			border-bottom: none;
		}
		.ppa-tutorlms-panel .ppa-no-results {
			padding: 12px;
			color: #666;
			font-style: italic;
			margin: 0;
			background: #f9f9f9;
			border: 1px solid #ddd;
			border-top: none;
		}
		.ppa-tutorlms-panel .ppa-assignment-id {
			color: #666;
			font-weight: 600;
		}
		.ppa-tutorlms-panel .ppa-assignment-search-gutenberg input {
			width: 100%;
			padding: 8px 12px;
			border: 1px solid #8c8f94;
			border-radius: 2px;
			font-size: 13px;
			line-height: 1.4;
			min-height: 36px;
			box-sizing: border-box;
		}
		.ppa-tutorlms-panel .ppa-assignment-search-gutenberg input:focus {
			border-color: #2271b1;
			box-shadow: 0 0 0 1px #2271b1;
			outline: none;
		}
	`;
	document.head.appendChild( style );
} )( window.wp );
