/**
 * My Submissions Block
 *
 * Gutenberg block for displaying user's assignment submissions.
 *
 * @package
 * @since 1.0.0
 */

import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	RangeControl,
	Placeholder,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * List icon
 */
const listIcon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		width="24"
		height="24"
	>
		<path
			fill="currentColor"
			d="M4 4h4v4H4V4zm6 1v2h10V5H10zm-6 5h4v4H4v-4zm6 1v2h10v-2H10zm-6 5h4v4H4v-4zm6 1v2h10v-2H10z"
		/>
	</svg>
);

/**
 * Edit component for My Submissions block
 *
 * @param {Object} props Block props.
 * @return {JSX.Element} Block edit component.
 */
function Edit( props ) {
	const { attributes, setAttributes } = props;
	const { perPage, showStatus, showScore, showDate } = attributes;
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody
					title={ __( 'Display Settings', 'pressprimer-assignment' ) }
					initialOpen={ true }
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show Status', 'pressprimer-assignment' ) }
						help={ __(
							'Display the submission status for each entry',
							'pressprimer-assignment'
						) }
						checked={ showStatus }
						onChange={ ( value ) =>
							setAttributes( { showStatus: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show Score', 'pressprimer-assignment' ) }
						help={ __(
							'Display the score for graded submissions',
							'pressprimer-assignment'
						) }
						checked={ showScore }
						onChange={ ( value ) =>
							setAttributes( { showScore: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show Date', 'pressprimer-assignment' ) }
						help={ __(
							'Display the date when each submission was made',
							'pressprimer-assignment'
						) }
						checked={ showDate }
						onChange={ ( value ) =>
							setAttributes( { showDate: value } )
						}
					/>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Submissions Per Page',
							'pressprimer-assignment'
						) }
						help={ __(
							'Number of submissions to show per page',
							'pressprimer-assignment'
						) }
						value={ perPage }
						onChange={ ( value ) =>
							setAttributes( { perPage: value } )
						}
						min={ 5 }
						max={ 50 }
						step={ 5 }
					/>
				</PanelBody>
			</InspectorControls>

			<Placeholder
				icon={ listIcon }
				label={ __( 'PPA My Submissions', 'pressprimer-assignment' ) }
				instructions={ __(
					"This block displays the current user's assignment submissions. Configure display options in the sidebar.",
					'pressprimer-assignment'
				) }
			>
				<div
					style={ {
						textAlign: 'left',
						width: '100%',
						padding: '20px',
					} }
				>
					<p
						style={ {
							margin: '0 0 10px',
							color: '#666',
							fontSize: '14px',
						} }
					>
						<strong>
							{ __( 'Settings:', 'pressprimer-assignment' ) }
						</strong>
					</p>
					<ul
						style={ {
							margin: 0,
							paddingLeft: '20px',
							color: '#666',
							fontSize: '13px',
						} }
					>
						<li>
							{ __( 'Show Status:', 'pressprimer-assignment' ) }{ ' ' }
							<strong>
								{ showStatus
									? __( 'Yes', 'pressprimer-assignment' )
									: __( 'No', 'pressprimer-assignment' ) }
							</strong>
						</li>
						<li>
							{ __( 'Show Score:', 'pressprimer-assignment' ) }{ ' ' }
							<strong>
								{ showScore
									? __( 'Yes', 'pressprimer-assignment' )
									: __( 'No', 'pressprimer-assignment' ) }
							</strong>
						</li>
						<li>
							{ __( 'Show Date:', 'pressprimer-assignment' ) }{ ' ' }
							<strong>
								{ showDate
									? __( 'Yes', 'pressprimer-assignment' )
									: __( 'No', 'pressprimer-assignment' ) }
							</strong>
						</li>
						<li>
							{ __( 'Per Page:', 'pressprimer-assignment' ) }{ ' ' }
							<strong>{ perPage }</strong>
						</li>
					</ul>
					<p
						style={ {
							marginTop: '15px',
							color: '#999',
							fontSize: '12px',
							fontStyle: 'italic',
						} }
					>
						{ __(
							'Preview not available in editor. The list will display on the frontend for logged-in users.',
							'pressprimer-assignment'
						) }
					</p>
				</div>
			</Placeholder>
		</div>
	);
}

/**
 * Register My Submissions block
 */
registerBlockType( 'pressprimer-assignment/my-submissions', {
	icon: listIcon,
	edit: Edit,
	save: () => null,
} );
