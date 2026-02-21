<?php
/**
 * Base model class
 *
 * Abstract base class providing common CRUD functionality for all models.
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
 * Base model class
 *
 * Provides Active Record pattern for database models.
 * All model classes (Assignment, Submission, etc.) extend this base class.
 *
 * @since 1.0.0
 */
abstract class PressPrimer_Assignment_Model {

	/**
	 * Primary key ID
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $id = 0;

	/**
	 * Get table name
	 *
	 * Returns the database table name for this model.
	 * Must be implemented by child classes.
	 *
	 * @since 1.0.0
	 *
	 * @return string Table name without prefix.
	 */
	abstract protected static function get_table_name();

	/**
	 * Get fillable fields
	 *
	 * Returns array of field names that can be mass-assigned.
	 * Must be implemented by child classes.
	 *
	 * @since 1.0.0
	 *
	 * @return array Field names.
	 */
	abstract protected static function get_fillable_fields();

	/**
	 * Get queryable fields
	 *
	 * Returns array of field names that can be used in WHERE clauses.
	 * Includes fillable fields plus standard columns.
	 * Child classes may override to add additional queryable fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array Field names safe for use in queries.
	 */
	protected static function get_queryable_fields() {
		$standard_fields = [ 'id', 'created_at', 'updated_at' ];

		return array_merge( $standard_fields, static::get_fillable_fields() );
	}

	/**
	 * Get full table name with prefix
	 *
	 * Returns the complete table name including WordPress prefix.
	 *
	 * @since 1.0.0
	 *
	 * @return string Full table name with prefix.
	 */
	protected static function get_full_table_name() {
		global $wpdb;
		return $wpdb->prefix . static::get_table_name();
	}

	/**
	 * Get record by ID
	 *
	 * Retrieves a single record from the database by primary key.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Record ID.
	 * @return static|null Model instance or null if not found.
	 */
	public static function get( $id ) {
		global $wpdb;

		$id    = absint( $id );
		$table = static::get_full_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);

		return $row ? static::from_row( $row ) : null;
	}

	/**
	 * Get record by UUID
	 *
	 * Retrieves a single record from the database by UUID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $uuid Record UUID.
	 * @return static|null Model instance or null if not found.
	 */
	public static function get_by_uuid( $uuid ) {
		global $wpdb;

		$table = static::get_full_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE uuid = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$uuid
			)
		);

		return $row ? static::from_row( $row ) : null;
	}

	/**
	 * Create instance from database row
	 *
	 * Factory method to create a model instance from a database row object.
	 *
	 * @since 1.0.0
	 *
	 * @param object $row Database row object.
	 * @return static Model instance.
	 */
	public static function from_row( $row ) {
		$instance = new static();

		foreach ( get_object_vars( $row ) as $key => $value ) {
			if ( property_exists( $instance, $key ) ) {
				$instance->$key = $value;
			}
		}

		return $instance;
	}

	/**
	 * Create new record
	 *
	 * Inserts a new record into the database.
	 * Child classes should override this to add validation.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Record data as associative array.
	 * @return int|WP_Error Record ID on success, WP_Error on failure.
	 */
	public static function create( array $data ) {
		global $wpdb;

		// Filter to only fillable fields.
		$fillable = static::get_fillable_fields();
		$data     = array_intersect_key( $data, array_flip( $fillable ) );

		if ( empty( $data ) ) {
			return new WP_Error(
				'ppa_no_data',
				__( 'No valid data provided for creation.', 'pressprimer-assignment' )
			);
		}

		$table = static::get_full_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $table, $data );

		if ( false === $result ) {
			return new WP_Error(
				'ppa_db_error',
				__( 'Database error: Failed to create record.', 'pressprimer-assignment' )
			);
		}

		return $wpdb->insert_id;
	}

	/**
	 * Save changes to database
	 *
	 * Updates the record in the database.
	 * Only updates fields that are fillable.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function save() {
		global $wpdb;

		if ( empty( $this->id ) ) {
			return new WP_Error(
				'ppa_no_id',
				__( 'Cannot save record without ID.', 'pressprimer-assignment' )
			);
		}

		// Build data array from fillable fields.
		$fillable = static::get_fillable_fields();
		$data     = [];

		foreach ( $fillable as $field ) {
			if ( property_exists( $this, $field ) ) {
				$data[ $field ] = $this->$field;
			}
		}

		if ( empty( $data ) ) {
			return new WP_Error(
				'ppa_no_data',
				__( 'No valid data to save.', 'pressprimer-assignment' )
			);
		}

		$table = static::get_full_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			$data,
			[ 'id' => $this->id ],
			null,
			[ '%d' ]
		);

		if ( false === $result ) {
			return new WP_Error(
				'ppa_db_error',
				__( 'Database error: Failed to save record.', 'pressprimer-assignment' )
			);
		}

		return true;
	}

	/**
	 * Delete record
	 *
	 * Removes the record from the database (hard delete).
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
				__( 'Cannot delete record without ID.', 'pressprimer-assignment' )
			);
		}

		$table = static::get_full_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$table,
			[ 'id' => $this->id ],
			[ '%d' ]
		);

		if ( false === $result ) {
			return new WP_Error(
				'ppa_db_error',
				__( 'Database error: Failed to delete record.', 'pressprimer-assignment' )
			);
		}

		return true;
	}

	/**
	 * Find records
	 *
	 * Retrieves multiple records based on conditions.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Query arguments.
	 *                    - where: array of field => value conditions.
	 *                    - order_by: column to order by.
	 *                    - order: ASC or DESC.
	 *                    - limit: number of records to return.
	 *                    - offset: number of records to skip.
	 * @return array Array of model instances.
	 */
	public static function find( array $args = [] ) {
		global $wpdb;

		$defaults = [
			'where'    => [],
			'order_by' => 'id',
			'order'    => 'DESC',
			'limit'    => null,
			'offset'   => null,
		];

		$args  = wp_parse_args( $args, $defaults );
		$table = static::get_full_table_name();

		// Build WHERE clause with field validation using %i placeholder.
		$where_clauses    = [];
		$prepare_values   = [];
		$queryable_fields = static::get_queryable_fields();

		if ( ! empty( $args['where'] ) ) {
			foreach ( $args['where'] as $field => $value ) {
				if ( ! in_array( $field, $queryable_fields, true ) ) {
					continue;
				}

				if ( null === $value ) {
					$where_clauses[]  = '%i IS NULL';
					$prepare_values[] = $field;
				} else {
					$where_clauses[]  = '%i = %s';
					$prepare_values[] = $field;
					$prepare_values[] = $value;
				}
			}
		}

		// Build ORDER BY with field validation.
		$order_by_field = $args['order_by'];
		if ( ! in_array( $order_by_field, $queryable_fields, true ) ) {
			$order_by_field = 'id';
		}
		$is_asc = 'ASC' === strtoupper( $args['order'] );

		// Build LIMIT clause.
		$limit_sql    = '';
		$limit_values = [];
		if ( null !== $args['limit'] ) {
			$limit  = absint( $args['limit'] );
			$offset = absint( $args['offset'] ?? 0 );
			if ( $offset > 0 ) {
				$limit_sql      = 'LIMIT %d, %d';
				$limit_values[] = $offset;
				$limit_values[] = $limit;
			} else {
				$limit_sql      = 'LIMIT %d';
				$limit_values[] = $limit;
			}
		}

		// Build query with hardcoded ORDER direction.
		if ( ! empty( $where_clauses ) ) {
			$where_sql        = 'WHERE ' . implode( ' AND ', $where_clauses );
			$prepare_values[] = $order_by_field;
			$prepare_values   = array_merge( $prepare_values, $limit_values );
			if ( $is_asc ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$query = $wpdb->prepare( "SELECT * FROM {$table} {$where_sql} ORDER BY %i ASC {$limit_sql}", $prepare_values );
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$query = $wpdb->prepare( "SELECT * FROM {$table} {$where_sql} ORDER BY %i DESC {$limit_sql}", $prepare_values );
			}
		} elseif ( ! empty( $limit_values ) ) {
			if ( $is_asc ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$query = $wpdb->prepare(
					"SELECT * FROM {$table} ORDER BY %i ASC {$limit_sql}",
					array_merge( [ $order_by_field ], $limit_values )
				);
			} else {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$query = $wpdb->prepare(
					"SELECT * FROM {$table} ORDER BY %i DESC {$limit_sql}",
					array_merge( [ $order_by_field ], $limit_values )
				);
			}
		} elseif ( $is_asc ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = $wpdb->prepare( "SELECT * FROM {$table} ORDER BY %i ASC", $order_by_field );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = $wpdb->prepare( "SELECT * FROM {$table} ORDER BY %i DESC", $order_by_field );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $query );

		$results = [];
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$results[] = static::from_row( $row );
			}
		}

		return $results;
	}

	/**
	 * Count records
	 *
	 * Returns the count of records matching conditions.
	 *
	 * @since 1.0.0
	 *
	 * @param array $where Array of field => value conditions.
	 * @return int Record count.
	 */
	public static function count( array $where = [] ) {
		global $wpdb;

		$table = static::get_full_table_name();

		// Build WHERE clause with field validation.
		$where_clauses    = [];
		$prepare_values   = [];
		$queryable_fields = static::get_queryable_fields();

		if ( ! empty( $where ) ) {
			foreach ( $where as $field => $value ) {
				if ( ! in_array( $field, $queryable_fields, true ) ) {
					continue;
				}

				$where_clauses[]  = '%i = %s';
				$prepare_values[] = $field;
				$prepare_values[] = $value;
			}
		}

		if ( ! empty( $where_clauses ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} {$where_sql}",
				$prepare_values
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = "SELECT COUNT(*) FROM {$table}";
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Check if record exists
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Record ID.
	 * @return bool True if exists, false otherwise.
	 */
	public static function exists( $id ) {
		global $wpdb;

		$id    = absint( $id );
		$table = static::get_full_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);

		return (bool) $exists;
	}

	/**
	 * Refresh from database
	 *
	 * Reloads the record data from the database.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function refresh() {
		if ( empty( $this->id ) ) {
			return new WP_Error(
				'ppa_no_id',
				__( 'Cannot refresh record without ID.', 'pressprimer-assignment' )
			);
		}

		$fresh = static::get( $this->id );

		if ( ! $fresh ) {
			return new WP_Error(
				'ppa_not_found',
				__( 'Record not found in database.', 'pressprimer-assignment' )
			);
		}

		foreach ( get_object_vars( $fresh ) as $key => $value ) {
			$this->$key = $value;
		}

		return true;
	}

	/**
	 * Convert to array
	 *
	 * Returns model data as an associative array.
	 *
	 * @since 1.0.0
	 *
	 * @return array Model data.
	 */
	public function to_array() {
		$data = [];

		foreach ( get_object_vars( $this ) as $key => $value ) {
			if ( 0 !== strpos( $key, '_' ) ) {
				$data[ $key ] = $value;
			}
		}

		return $data;
	}
}
