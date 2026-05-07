/**
 * Assignment Block
 *
 * Gutenberg block for displaying an assignment.
 *
 * @package
 * @since 1.0.0
 */

import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	Placeholder,
	Spinner,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Assignment icon (document)
 */
const assignmentIcon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		width="24"
		height="24"
	>
		<path
			fill="currentColor"
			d="M6 2a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6H6zm7 1.5L18.5 9H13V3.5zM7 13h10v1.5H7V13zm0 3h10v1.5H7V16zm0-6h4v1.5H7V10z"
		/>
	</svg>
);

/**
 * Edit component for Assignment block
 *
 * @param {Object} props Block props.
 * @return {JSX.Element} Block edit component.
 */
function Edit( props ) {
	const { attributes, setAttributes } = props;
	const {
		assignmentId,
		showDescription,
		showInstructions,
		showMaxPoints,
		showFileInfo,
	} = attributes;
	const blockProps = useBlockProps();

	const [ assignments, setAssignments ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ selectedAssignment, setSelectedAssignment ] = useState( null );

	// Fetch available assignments.
	useEffect( () => {
		setLoading( true );
		apiFetch( {
			path: '/ppa/v1/assignments?status=published&per_page=100',
		} )
			.then( ( response ) => {
				const items =
					response?.data?.items || response?.items || response || [];
				setAssignments( Array.isArray( items ) ? items : [] );
				setLoading( false );
			} )
			.catch( () => {
				setAssignments( [] );
				setLoading( false );
			} );
	}, [] );

	// Find selected assignment from loaded assignments.
	useEffect( () => {
		if ( assignmentId && assignmentId > 0 && assignments.length > 0 ) {
			const assignment = assignments.find(
				( a ) => parseInt( a.id, 10 ) === parseInt( assignmentId, 10 )
			);
			setSelectedAssignment( assignment || null );
		} else {
			setSelectedAssignment( null );
		}
	}, [ assignmentId, assignments ] );

	// Build options for select.
	const assignmentOptions = [
		{
			value: 0,
			label: __( '— Select an Assignment —', 'pressprimer-assignment' ),
		},
		...assignments.map( ( assignment ) => ( {
			value: assignment.id,
			label: assignment.title,
		} ) ),
	];

	// Render loading placeholder.
	const renderLoading = () => (
		<Placeholder
			icon={ assignmentIcon }
			label={ __( 'PPA Assignment', 'pressprimer-assignment' ) }
		>
			<p>
				<Spinner />{ ' ' }
				{ __( 'Loading assignments…', 'pressprimer-assignment' ) }
			</p>
		</Placeholder>
	);

	// Render assignment selector placeholder.
	const renderSelector = () => (
		<Placeholder
			icon={ assignmentIcon }
			label={ __( 'PPA Assignment', 'pressprimer-assignment' ) }
			instructions={ __(
				'Select an assignment to display from the dropdown below or in the sidebar settings.',
				'pressprimer-assignment'
			) }
		>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				value={ assignmentId }
				options={ assignmentOptions }
				onChange={ ( value ) =>
					setAttributes( { assignmentId: parseInt( value, 10 ) } )
				}
			/>
		</Placeholder>
	);

	// Render assignment preview.
	const renderPreview = () => (
		<div className="ppa-assignment-block-preview">
			<div className="ppa-assignment-block-preview-header">
				<span className="ppa-assignment-block-preview-icon">
					{ assignmentIcon }
				</span>
				<span className="ppa-assignment-block-preview-label">
					{ __( 'PPA Assignment', 'pressprimer-assignment' ) }
				</span>
			</div>
			<div className="ppa-assignment-block-preview-content">
				{ selectedAssignment ? (
					<>
						<h3 className="ppa-assignment-block-preview-title">
							{ selectedAssignment.title }
						</h3>
						<div className="ppa-assignment-block-preview-meta">
							{ selectedAssignment.max_points > 0 && (
								<span className="ppa-assignment-block-preview-meta-item">
									<strong>
										{ selectedAssignment.max_points }
									</strong>{ ' ' }
									{ __(
										'max points',
										'pressprimer-assignment'
									) }
								</span>
							) }
							{ selectedAssignment.passing_score > 0 && (
								<span className="ppa-assignment-block-preview-meta-item">
									<strong>
										{ selectedAssignment.passing_score }
									</strong>{ ' ' }
									{ __(
										'pts to pass',
										'pressprimer-assignment'
									) }
								</span>
							) }
						</div>
						{ ( () => {
							// Strip HTML tags from the rich text description
							// before truncating — otherwise we render raw
							// `<p><strong>…` markup as plain text in the
							// editor preview.
							const plain = new window.DOMParser()
								.parseFromString(
									selectedAssignment.description || '',
									'text/html'
								)
								.body.textContent.trim();

							if ( ! plain ) {
								return null;
							}

							return (
								<p className="ppa-assignment-block-preview-description">
									{ plain.substring( 0, 150 ) }
									{ plain.length > 150 ? '…' : '' }
								</p>
							);
						} )() }
					</>
				) : (
					<p>
						{ __(
							'Loading assignment details…',
							'pressprimer-assignment'
						) }
					</p>
				) }
			</div>
			<div className="ppa-assignment-block-preview-footer">
				<p className="ppa-assignment-block-preview-note">
					{ __(
						'The full assignment will be displayed on the frontend.',
						'pressprimer-assignment'
					) }
				</p>
			</div>
		</div>
	);

	// Determine what to render.
	const renderContent = () => {
		if ( loading ) {
			return renderLoading();
		}
		if ( ! assignmentId || assignmentId === 0 ) {
			return renderSelector();
		}
		return renderPreview();
	};

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Assignment Settings',
						'pressprimer-assignment'
					) }
					initialOpen={ true }
				>
					{ loading ? (
						<p>
							<Spinner />{ ' ' }
							{ __(
								'Loading assignments…',
								'pressprimer-assignment'
							) }
						</p>
					) : (
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __(
								'Select Assignment',
								'pressprimer-assignment'
							) }
							value={ assignmentId }
							options={ assignmentOptions }
							onChange={ ( value ) =>
								setAttributes( {
									assignmentId: parseInt( value, 10 ),
								} )
							}
							help={ __(
								'Choose the assignment to display on this page.',
								'pressprimer-assignment'
							) }
						/>
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Display Options', 'pressprimer-assignment' ) }
					initialOpen={ false }
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show Description',
							'pressprimer-assignment'
						) }
						checked={ showDescription }
						onChange={ ( value ) =>
							setAttributes( { showDescription: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show Instructions',
							'pressprimer-assignment'
						) }
						checked={ showInstructions }
						onChange={ ( value ) =>
							setAttributes( { showInstructions: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show Max Points',
							'pressprimer-assignment'
						) }
						checked={ showMaxPoints }
						onChange={ ( value ) =>
							setAttributes( { showMaxPoints: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show File Info',
							'pressprimer-assignment'
						) }
						checked={ showFileInfo }
						onChange={ ( value ) =>
							setAttributes( { showFileInfo: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			{ renderContent() }
		</div>
	);
}

/**
 * Register Assignment block
 */
registerBlockType( 'pressprimer-assignment/assignment', {
	icon: assignmentIcon,
	edit: Edit,
	save: () => null,
} );
