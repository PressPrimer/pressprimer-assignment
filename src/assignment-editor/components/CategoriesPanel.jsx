/**
 * Assignment Categories Panel Component
 *
 * Category and tag selection for the assignment editor.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Card,
	Select,
	Space,
	Typography,
	Button,
	Input,
	message,
	Tooltip,
} from 'antd';
import {
	FolderOutlined,
	TagOutlined,
	PlusOutlined,
	QuestionCircleOutlined,
} from '@ant-design/icons';

const { Title, Text } = Typography;

/**
 * Categories Panel Component
 *
 * @param {Object}   props                     Component props.
 * @param {Array}    props.categories          Selected category IDs.
 * @param {Function} props.onCategoriesChange  Callback when categories change.
 * @param {Array}    props.availableCategories Available categories from server.
 * @param {Array}    props.availableTags       Available tags from server.
 */
const CategoriesPanel = ( {
	categories = [],
	onCategoriesChange,
	availableCategories: initialCategories = [],
	availableTags: initialTags = [],
} ) => {
	const [ availableCategories, setAvailableCategories ] =
		useState( initialCategories );
	const [ availableTags, setAvailableTags ] = useState( initialTags );
	const [ newCategoryName, setNewCategoryName ] = useState( '' );
	const [ newTagName, setNewTagName ] = useState( '' );
	const [ creatingCategory, setCreatingCategory ] = useState( false );
	const [ creatingTag, setCreatingTag ] = useState( false );

	// Split selected into categories and tags based on available data.
	const selectedCategoryIds = categories.filter( ( id ) =>
		availableCategories.some( ( c ) => c.id === id )
	);
	const selectedTagIds = categories.filter( ( id ) =>
		availableTags.some( ( t ) => t.id === id )
	);

	/**
	 * Build hierarchical category options with indent.
	 *
	 * @param {Array}  cats   Flat category list.
	 * @param {number} parent Parent ID.
	 * @param {number} depth  Nesting depth.
	 * @return {Array} Options for Select component.
	 */
	const buildCategoryOptions = useCallback(
		( cats, parent = null, depth = 0 ) => {
			const options = [];
			cats.filter( ( c ) => c.parent_id === parent ).forEach( ( cat ) => {
				const prefix = '\u00A0\u00A0'.repeat( depth );
				options.push( {
					value: cat.id,
					label: `${ prefix }${ cat.name }`,
				} );
				options.push(
					...buildCategoryOptions( cats, cat.id, depth + 1 )
				);
			} );
			return options;
		},
		[]
	);

	const categoryOptions = buildCategoryOptions( availableCategories );

	const tagOptions = availableTags.map( ( tag ) => ( {
		value: tag.id,
		label: tag.name,
	} ) );

	/**
	 * Handle category selection change.
	 *
	 * @param {Array} newCatIds Selected category IDs.
	 */
	const handleCategoryChange = ( newCatIds ) => {
		const combined = [ ...newCatIds, ...selectedTagIds ];
		onCategoriesChange( combined );
	};

	/**
	 * Handle tag selection change.
	 *
	 * @param {Array} newTagIds Selected tag IDs.
	 */
	const handleTagChange = ( newTagIds ) => {
		const combined = [ ...selectedCategoryIds, ...newTagIds ];
		onCategoriesChange( combined );
	};

	/**
	 * Create a new category inline.
	 */
	const handleCreateCategory = async () => {
		const name = newCategoryName.trim();
		if ( ! name ) {
			return;
		}

		try {
			setCreatingCategory( true );
			const result = await apiFetch( {
				path: '/ppa/v1/categories',
				method: 'POST',
				data: { name, taxonomy: 'category' },
			} );

			// Add to available categories.
			const newCat = {
				id: result.id,
				name: result.name,
				slug: result.slug,
				parent_id: result.parent_id,
			};
			setAvailableCategories( ( prev ) => [ ...prev, newCat ] );

			// Auto-select the new category.
			onCategoriesChange( [ ...categories, result.id ] );

			setNewCategoryName( '' );
			message.success(
				__( 'Category created successfully.', 'pressprimer-assignment' )
			);
		} catch ( error ) {
			message.error(
				error.message ||
					__( 'Failed to create category.', 'pressprimer-assignment' )
			);
		} finally {
			setCreatingCategory( false );
		}
	};

	/**
	 * Create a new tag inline.
	 */
	const handleCreateTag = async () => {
		const name = newTagName.trim();
		if ( ! name ) {
			return;
		}

		try {
			setCreatingTag( true );
			const result = await apiFetch( {
				path: '/ppa/v1/categories',
				method: 'POST',
				data: { name, taxonomy: 'tag' },
			} );

			// Add to available tags.
			const newTag = {
				id: result.id,
				name: result.name,
				slug: result.slug,
			};
			setAvailableTags( ( prev ) => [ ...prev, newTag ] );

			// Auto-select the new tag.
			onCategoriesChange( [ ...categories, result.id ] );

			setNewTagName( '' );
			message.success(
				__( 'Tag created successfully.', 'pressprimer-assignment' )
			);
		} catch ( error ) {
			message.error(
				error.message ||
					__( 'Failed to create tag.', 'pressprimer-assignment' )
			);
		} finally {
			setCreatingTag( false );
		}
	};

	return (
		<Space direction="vertical" size="large" style={ { width: '100%' } }>
			{ /* Categories */ }
			<Card
				title={
					<Space>
						<Title level={ 4 } style={ { margin: 0 } }>
							{ __( 'Categories', 'pressprimer-assignment' ) }
						</Title>
					</Space>
				}
				style={ { marginBottom: 24 } }
			>
				<div style={ { marginBottom: 16 } }>
					<Space>
						<FolderOutlined />
						<Text>
							{ __(
								'Select Categories',
								'pressprimer-assignment'
							) }
						</Text>
						<Tooltip
							title={ __(
								'Organize assignments by selecting one or more categories.',
								'pressprimer-assignment'
							) }
						>
							<QuestionCircleOutlined
								style={ {
									fontSize: 12,
									color: '#8c8c8c',
								} }
							/>
						</Tooltip>
					</Space>
				</div>

				<Select
					mode="multiple"
					style={ { width: 300 } }
					size="small"
					placeholder={ __(
						'Select categories…',
						'pressprimer-assignment'
					) }
					value={ selectedCategoryIds }
					onChange={ handleCategoryChange }
					options={ categoryOptions }
					optionFilterProp="label"
					notFoundContent={ __(
						'No categories found.',
						'pressprimer-assignment'
					) }
				/>

				<div style={ { marginTop: 16 } }>
					<Text
						type="secondary"
						style={ {
							fontSize: 12,
							display: 'block',
							marginBottom: 8,
						} }
					>
						{ __(
							'Create a new category:',
							'pressprimer-assignment'
						) }
					</Text>
					<Space>
						<Input
							size="small"
							style={ { width: 200 } }
							placeholder={ __(
								'Category name',
								'pressprimer-assignment'
							) }
							value={ newCategoryName }
							onChange={ ( e ) =>
								setNewCategoryName( e.target.value )
							}
							onPressEnter={ handleCreateCategory }
							disabled={ creatingCategory }
						/>
						<Button
							size="small"
							icon={ <PlusOutlined /> }
							onClick={ handleCreateCategory }
							loading={ creatingCategory }
							disabled={ ! newCategoryName.trim() }
						>
							{ __( 'Add', 'pressprimer-assignment' ) }
						</Button>
					</Space>
				</div>
			</Card>

			{ /* Tags */ }
			<Card
				title={
					<Space>
						<Title level={ 4 } style={ { margin: 0 } }>
							{ __( 'Tags', 'pressprimer-assignment' ) }
						</Title>
					</Space>
				}
				style={ { marginBottom: 24 } }
			>
				<div style={ { marginBottom: 16 } }>
					<Space>
						<TagOutlined />
						<Text>
							{ __( 'Select Tags', 'pressprimer-assignment' ) }
						</Text>
						<Tooltip
							title={ __(
								'Add tags to help filter and find assignments.',
								'pressprimer-assignment'
							) }
						>
							<QuestionCircleOutlined
								style={ {
									fontSize: 12,
									color: '#8c8c8c',
								} }
							/>
						</Tooltip>
					</Space>
				</div>

				<Select
					mode="multiple"
					style={ { width: 300 } }
					size="small"
					placeholder={ __(
						'Select tags…',
						'pressprimer-assignment'
					) }
					value={ selectedTagIds }
					onChange={ handleTagChange }
					options={ tagOptions }
					optionFilterProp="label"
					notFoundContent={ __(
						'No tags found.',
						'pressprimer-assignment'
					) }
				/>

				<div style={ { marginTop: 16 } }>
					<Text
						type="secondary"
						style={ {
							fontSize: 12,
							display: 'block',
							marginBottom: 8,
						} }
					>
						{ __( 'Create a new tag:', 'pressprimer-assignment' ) }
					</Text>
					<Space>
						<Input
							size="small"
							style={ { width: 200 } }
							placeholder={ __(
								'Tag name',
								'pressprimer-assignment'
							) }
							value={ newTagName }
							onChange={ ( e ) =>
								setNewTagName( e.target.value )
							}
							onPressEnter={ handleCreateTag }
							disabled={ creatingTag }
						/>
						<Button
							size="small"
							icon={ <PlusOutlined /> }
							onClick={ handleCreateTag }
							loading={ creatingTag }
							disabled={ ! newTagName.trim() }
						>
							{ __( 'Add', 'pressprimer-assignment' ) }
						</Button>
					</Space>
				</div>
			</Card>
		</Space>
	);
};

export default CategoriesPanel;
