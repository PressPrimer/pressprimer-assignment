<?php
/**
 * Category model
 *
 * Represents a category or tag for organizing assignments.
 *
 * @package PressPrimer_Assignment
 * @subpackage Models
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Category model class
 *
 * Handles CRUD operations for categories and tags, including
 * hierarchical relationships, slug generation, and assignment counts.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Category extends PressPrimer_Assignment_Model {

	/**
	 * Category name
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $name = '';

	/**
	 * URL-safe slug
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $slug = '';

	/**
	 * Description
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $description = null;

	/**
	 * Parent category ID (for hierarchical categories)
	 *
	 * @since 1.0.0
	 * @var int|null
	 */
	public $parent_id = null;

	/**
	 * Taxonomy type
	 *
	 * @since 1.0.0
	 * @var string category|tag
	 */
	public $taxonomy = 'category';

	/**
	 * Cached assignment count
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $assignment_count = 0;

	/**
	 * Creator user ID
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $created_by = 0;

	/**
	 * Created timestamp
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $created_at = '';

	/**
	 * Get table name
	 *
	 * @since 1.0.0
	 *
	 * @return string Table name without prefix.
	 */
	protected static function get_table_name() {
		return 'ppa_categories';
	}

	/**
	 * Get fillable fields
	 *
	 * @since 1.0.0
	 *
	 * @return array Field names that can be mass-assigned.
	 */
	protected static function get_fillable_fields() {
		return [
			'name',
			'slug',
			'description',
			'parent_id',
			'taxonomy',
			'assignment_count',
			'created_by',
		];
	}

	/**
	 * Create new category or tag
	 *
	 * Validates input and creates a new category or tag record.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Category data.
	 * @return int|WP_Error Category ID on success, WP_Error on failure.
	 */
	public static function create( array $data ) {
		// Validate required fields.
		if ( empty( $data['name'] ) ) {
			return new WP_Error(
				'ppa_missing_name',
				__( 'Category name is required.', 'pressprimer-assignment' )
			);
		}

		// Set taxonomy default if not provided.
		if ( ! isset( $data['taxonomy'] ) ) {
			$data['taxonomy'] = 'category';
		}

		// Validate taxonomy.
		if ( ! in_array( $data['taxonomy'], [ 'category', 'tag' ], true ) ) {
			return new WP_Error(
				'ppa_invalid_taxonomy',
				__( 'Taxonomy must be either "category" or "tag".', 'pressprimer-assignment' )
			);
		}

		// Generate slug if not provided.
		if ( empty( $data['slug'] ) ) {
			$data['slug'] = sanitize_title( $data['name'] );
		}

		// Ensure slug is unique for this taxonomy.
		$data['slug'] = self::generate_unique_slug( $data['slug'], $data['taxonomy'] );

		// Sanitize name.
		$data['name'] = sanitize_text_field( $data['name'] );

		// Sanitize description if provided.
		if ( ! empty( $data['description'] ) ) {
			$data['description'] = sanitize_textarea_field( $data['description'] );
		}

		// Validate parent_id for categories.
		if ( 'category' === $data['taxonomy'] && ! empty( $data['parent_id'] ) ) {
			$parent = self::get( absint( $data['parent_id'] ) );
			if ( ! $parent || 'category' !== $parent->taxonomy ) {
				return new WP_Error(
					'ppa_invalid_parent',
					__( 'Invalid parent category.', 'pressprimer-assignment' )
				);
			}
		}

		// Tags cannot have a parent.
		if ( 'tag' === $data['taxonomy'] ) {
			$data['parent_id'] = null;
		}

		// Set created_by to current user if not provided.
		if ( empty( $data['created_by'] ) ) {
			$data['created_by'] = get_current_user_id();
		}

		// Initialize count.
		$data['assignment_count'] = 0;

		// Call parent create.
		return parent::create( $data );
	}

	/**
	 * Get all categories
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Optional query arguments.
	 * @return array Array of Category instances.
	 */
	public static function get_categories( array $args = [] ) {
		$defaults = [
			'where'    => [ 'taxonomy' => 'category' ],
			'order_by' => 'name',
			'order'    => 'ASC',
		];

		$args = wp_parse_args( $args, $defaults );

		// Ensure we always filter by category taxonomy.
		$args['where']['taxonomy'] = 'category';

		return static::find( $args );
	}

	/**
	 * Get all tags
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Optional query arguments.
	 * @return array Array of Category instances with taxonomy 'tag'.
	 */
	public static function get_tags( array $args = [] ) {
		$defaults = [
			'where'    => [ 'taxonomy' => 'tag' ],
			'order_by' => 'name',
			'order'    => 'ASC',
		];

		$args = wp_parse_args( $args, $defaults );

		// Ensure we always filter by tag taxonomy.
		$args['where']['taxonomy'] = 'tag';

		return static::find( $args );
	}

	/**
	 * Get assignments for this category
	 *
	 * Retrieves all assignments associated with this category/tag.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Optional query arguments for assignments.
	 * @return array Array of Assignment instances.
	 */
	public function get_assignments( array $args = [] ) {
		global $wpdb;

		$tax_table        = $wpdb->prefix . 'ppa_assignment_tax';
		$assignment_table = $wpdb->prefix . 'ppa_assignments';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT a.* FROM {$assignment_table} a INNER JOIN {$tax_table} t ON a.id = t.assignment_id WHERE t.category_id = %d ORDER BY a.created_at DESC",
				$this->id
			)
		);

		$results = [];
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$results[] = PressPrimer_Assignment_Assignment::from_row( $row );
			}
		}

		return $results;
	}

	/**
	 * Get category by slug
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug     Category slug.
	 * @param string $taxonomy Taxonomy type (default: 'category').
	 * @return static|null Category instance or null if not found.
	 */
	public static function get_by_slug( $slug, $taxonomy = 'category' ) {
		global $wpdb;

		$table = static::get_full_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE slug = %s AND taxonomy = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$slug,
				$taxonomy
			)
		);

		return $row ? static::from_row( $row ) : null;
	}

	/**
	 * Get hierarchical category tree
	 *
	 * Returns categories with their children nested.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $parent_id Parent ID (null for root categories).
	 * @return array Array of Category instances with 'children' property.
	 */
	public static function get_hierarchy( $parent_id = null ) {
		global $wpdb;

		$table = static::get_full_table_name();

		if ( null === $parent_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( "SELECT * FROM {$table} WHERE taxonomy = 'category' AND parent_id IS NULL ORDER BY name ASC" );
		} else {
			$parent_id = absint( $parent_id );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE taxonomy = 'category' AND parent_id = %d ORDER BY name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$parent_id
				)
			);
		}

		if ( ! $rows ) {
			return [];
		}

		$categories = [];
		foreach ( $rows as $row ) {
			$category           = static::from_row( $row );
			$category->children = self::get_hierarchy( $category->id );

			$categories[] = $category;
		}

		return $categories;
	}

	/**
	 * Generate a unique slug for the given taxonomy
	 *
	 * Appends a numeric suffix if the slug already exists.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug     Desired slug.
	 * @param string $taxonomy Taxonomy type.
	 * @param int    $exclude  Category ID to exclude from uniqueness check.
	 * @return string Unique slug.
	 */
	public static function generate_unique_slug( $slug, $taxonomy, $exclude = 0 ) {
		global $wpdb;

		$table         = static::get_full_table_name();
		$original_slug = $slug;
		$suffix        = 2;

		while ( true ) {
			if ( $exclude > 0 ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$table} WHERE slug = %s AND taxonomy = %s AND id != %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$slug,
						$taxonomy,
						$exclude
					)
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$table} WHERE slug = %s AND taxonomy = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$slug,
						$taxonomy
					)
				);
			}

			if ( ! $exists ) {
				break;
			}

			$slug = $original_slug . '-' . $suffix;
			++$suffix;
		}

		return $slug;
	}

	/**
	 * Update assignment count
	 *
	 * Recalculates the cached assignment count from the pivot table.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $category_id Specific category ID, or null for all.
	 * @return bool True on success.
	 */
	public static function update_counts( $category_id = null ) {
		global $wpdb;

		$table     = static::get_full_table_name();
		$tax_table = $wpdb->prefix . 'ppa_assignment_tax';

		if ( null !== $category_id ) {
			$category_id = absint( $category_id );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT assignment_id) FROM {$tax_table} WHERE category_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$category_id
				)
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				[ 'assignment_count' => (int) $count ],
				[ 'id' => $category_id ],
				[ '%d' ],
				[ '%d' ]
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				"UPDATE {$table} c SET c.assignment_count = (SELECT COUNT(DISTINCT t.assignment_id) FROM {$tax_table} t WHERE t.category_id = c.id)"
			);
		}

		return true;
	}

	/**
	 * Delete category and handle relationships
	 *
	 * Moves children to parent (or root) and removes assignment relationships.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete() {
		global $wpdb;

		if ( empty( $this->id ) ) {
			return new WP_Error(
				'ppa_no_id',
				__( 'Cannot delete category without ID.', 'pressprimer-assignment' )
			);
		}

		// If this is a category with children, move children to parent or root.
		if ( 'category' === $this->taxonomy ) {
			$children = self::find(
				[
					'where' => [ 'parent_id' => $this->id ],
				]
			);

			if ( ! empty( $children ) ) {
				foreach ( $children as $child ) {
					$child->parent_id = $this->parent_id;
					$child->save();
				}
			}
		}

		// Remove all assignment relationships.
		$tax_table = $wpdb->prefix . 'ppa_assignment_tax';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$tax_table,
			[ 'category_id' => $this->id ],
			[ '%d' ]
		);

		// Delete the category.
		return parent::delete();
	}
}
