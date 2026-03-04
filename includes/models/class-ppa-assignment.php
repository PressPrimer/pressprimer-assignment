<?php
/**
 * Assignment model
 *
 * Represents an assignment with file settings and grading configuration.
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
 * Assignment model class
 *
 * Handles CRUD operations for assignments, including validation
 * and file type configuration.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Assignment extends PressPrimer_Assignment_Model {

	/**
	 * Assignment UUID
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $uuid = '';

	/**
	 * Assignment title
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $title = '';

	/**
	 * Short description
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $description = null;

	/**
	 * Detailed instructions (HTML allowed)
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $instructions = null;

	/**
	 * Grading guidelines
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $grading_guidelines = null;

	/**
	 * Maximum points
	 *
	 * @since 1.0.0
	 * @var float
	 */
	public $max_points = 100.00;

	/**
	 * Passing score
	 *
	 * @since 1.0.0
	 * @var float
	 */
	public $passing_score = 60.00;

	/**
	 * Whether resubmission is allowed
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $allow_resubmission = 0;

	/**
	 * Maximum number of resubmissions
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $max_resubmissions = 1;

	/**
	 * Allowed file types (JSON)
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $allowed_file_types = null;

	/**
	 * Maximum file size in bytes
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $max_file_size = 5242880;

	/**
	 * Maximum number of files per submission
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $max_files = 5;

	/**
	 * Submission type
	 *
	 * @since 1.0.0
	 * @var string file|text|either
	 */
	public $submission_type = 'file';

	/**
	 * Assignment status
	 *
	 * @since 1.0.0
	 * @var string draft|published|archived
	 */
	public $status = 'draft';

	/**
	 * Display theme
	 *
	 * Per-assignment theme override. When set to 'default', the global
	 * theme setting is used instead.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $theme = 'default';

	/**
	 * Author user ID
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $author_id = 0;

	/**
	 * Notification email addresses (comma-separated)
	 *
	 * Additional email addresses that receive new submission notifications
	 * alongside the assignment author.
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $notification_email = null;

	/**
	 * Cached submission count
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $submission_count = 0;

	/**
	 * Cached graded count
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $graded_count = 0;

	/**
	 * Created timestamp
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $created_at = '';

	/**
	 * Last updated timestamp
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $updated_at = '';

	/**
	 * Cached categories
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	private $_categories = null;

	/**
	 * Get table name
	 *
	 * @since 1.0.0
	 *
	 * @return string Table name without prefix.
	 */
	protected static function get_table_name() {
		return 'ppa_assignments';
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
			'uuid',
			'title',
			'description',
			'instructions',
			'grading_guidelines',
			'max_points',
			'passing_score',
			'allow_resubmission',
			'max_resubmissions',
			'allowed_file_types',
			'max_file_size',
			'max_files',
			'submission_type',
			'status',
			'theme',
			'author_id',
			'notification_email',
			'submission_count',
			'graded_count',
		];
	}

	/**
	 * Create new assignment
	 *
	 * Validates input and creates a new assignment record.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Assignment data.
	 * @return int|WP_Error Assignment ID on success, WP_Error on failure.
	 */
	public static function create( array $data ) {
		// Validate data.
		$validation = self::validate_data( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Generate UUID if not provided.
		if ( empty( $data['uuid'] ) ) {
			$data['uuid'] = wp_generate_uuid4();
		}

		// Set author to current user if not provided.
		if ( empty( $data['author_id'] ) ) {
			$data['author_id'] = get_current_user_id();
		}

		// Set default status if not provided.
		if ( empty( $data['status'] ) ) {
			$data['status'] = 'draft';
		}

		// Call parent create.
		$assignment_id = parent::create( $data );

		// Fire action hook for addons.
		if ( ! is_wp_error( $assignment_id ) ) {
			/**
			 * Fires after an assignment is created.
			 *
			 * @since 1.0.0
			 *
			 * @param int   $assignment_id The assignment ID.
			 * @param array $data          The assignment data.
			 */
			do_action( 'pressprimer_assignment_created', $assignment_id, $data );
		}

		return $assignment_id;
	}

	/**
	 * Save changes to database
	 *
	 * Updates the record in the database with hook for addons.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function save() {
		$result = parent::save();

		// Fire action hook for addons.
		if ( true === $result ) {
			/**
			 * Fires after an assignment is updated.
			 *
			 * @since 1.0.0
			 *
			 * @param PressPrimer_Assignment_Assignment $assignment The assignment instance.
			 */
			do_action( 'pressprimer_assignment_updated', $this );
		}

		return $result;
	}

	/**
	 * Validate assignment data
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Assignment data to validate.
	 * @return true|WP_Error True on success, WP_Error on validation failure.
	 */
	protected static function validate_data( array $data ) {
		// Validate title (required for meaningful assignments).
		if ( isset( $data['title'] ) ) {
			$clean_title = trim( sanitize_text_field( $data['title'] ) );
			if ( '' === $clean_title ) {
				return new WP_Error(
					'ppa_empty_title',
					__( 'Assignment title cannot be empty.', 'pressprimer-assignment' )
				);
			}
		}

		// Validate submission_type.
		if ( ! empty( $data['submission_type'] ) && ! in_array( $data['submission_type'], [ 'file', 'text', 'either' ], true ) ) {
			return new WP_Error(
				'ppa_invalid_submission_type',
				__( 'Invalid submission type. Must be file, text, or either.', 'pressprimer-assignment' )
			);
		}

		// Validate status.
		if ( ! empty( $data['status'] ) && ! in_array( $data['status'], [ 'draft', 'published', 'archived' ], true ) ) {
			return new WP_Error(
				'ppa_invalid_status',
				__( 'Invalid status. Must be draft, published, or archived.', 'pressprimer-assignment' )
			);
		}

		// Validate max_points.
		if ( isset( $data['max_points'] ) ) {
			$points = floatval( $data['max_points'] );
			if ( $points < 0.01 || $points > 100000.00 ) {
				return new WP_Error(
					'ppa_invalid_max_points',
					__( 'Max points must be between 0.01 and 100,000.', 'pressprimer-assignment' )
				);
			}
		}

		// Validate passing_score.
		if ( isset( $data['passing_score'] ) ) {
			$score = floatval( $data['passing_score'] );
			if ( $score < 0.00 || $score > 100000.00 ) {
				return new WP_Error(
					'ppa_invalid_passing_score',
					__( 'Passing score must be between 0 and 100,000.', 'pressprimer-assignment' )
				);
			}
		}

		// Validate max_file_size.
		if ( isset( $data['max_file_size'] ) ) {
			$size = absint( $data['max_file_size'] );
			if ( $size < 1024 || $size > 104857600 ) {
				return new WP_Error(
					'ppa_invalid_file_size',
					__( 'Max file size must be between 1 KB and 100 MB.', 'pressprimer-assignment' )
				);
			}
		}

		// Validate max_files.
		if ( isset( $data['max_files'] ) ) {
			$max = absint( $data['max_files'] );
			if ( $max < 1 || $max > 50 ) {
				return new WP_Error(
					'ppa_invalid_max_files',
					__( 'Max files must be between 1 and 50.', 'pressprimer-assignment' )
				);
			}
		}

		// Validate max_resubmissions (0 = disabled, 1-100 = allowed retakes).
		if ( isset( $data['max_resubmissions'] ) ) {
			$max = absint( $data['max_resubmissions'] );
			if ( $max > 100 ) {
				return new WP_Error(
					'ppa_invalid_max_resubmissions',
					__( 'Max resubmissions must be between 1 and 100.', 'pressprimer-assignment' )
				);
			}
		}

		return true;
	}

	/**
	 * Get published assignments
	 *
	 * Retrieves assignments with status 'published'.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Optional query arguments.
	 *                    - order_by: column to order by (default: created_at).
	 *                    - order: ASC or DESC (default: DESC).
	 *                    - limit: number of records to return.
	 *                    - offset: number of records to skip.
	 * @return array Array of Assignment instances.
	 */
	public static function get_published( array $args = [] ) {
		$defaults = [
			'where'    => [ 'status' => 'published' ],
			'order_by' => 'created_at',
			'order'    => 'DESC',
		];

		$args = wp_parse_args( $args, $defaults );

		// Ensure we always filter by published status.
		$args['where']['status'] = 'published';

		return static::find( $args );
	}

	/**
	 * Get allowed file types as array
	 *
	 * Decodes the JSON-stored file types. Returns default types if null.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of file extension strings.
	 */
	public function get_allowed_file_types() {
		if ( null === $this->allowed_file_types || '' === $this->allowed_file_types ) {
			// Default allowed types when none specified.
			return [ 'pdf', 'docx', 'txt', 'rtf', 'jpg', 'jpeg', 'png', 'gif' ];
		}

		$types = json_decode( $this->allowed_file_types, true );

		if ( ! is_array( $types ) ) {
			return [ 'pdf', 'docx', 'txt', 'rtf', 'jpg', 'jpeg', 'png', 'gif' ];
		}

		return $types;
	}

	/**
	 * Get submissions for this assignment
	 *
	 * Retrieves all submissions associated with this assignment.
	 * Delegates to the Submission model when available.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Optional query arguments passed to Submission::find().
	 * @return array Array of Submission instances.
	 */
	public function get_submissions( array $args = [] ) {
		if ( ! class_exists( 'PressPrimer_Assignment_Submission' ) ) {
			return [];
		}

		$defaults = [
			'where'    => [ 'assignment_id' => $this->id ],
			'order_by' => 'created_at',
			'order'    => 'DESC',
		];

		$args = wp_parse_args( $args, $defaults );

		// Ensure we always filter by this assignment.
		$args['where']['assignment_id'] = $this->id;

		return PressPrimer_Assignment_Submission::find( $args );
	}

	/**
	 * Get categories for this assignment
	 *
	 * Lazy-loads and caches the category relationships.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force Force reload from database.
	 * @return array Array of Category instances.
	 */
	public function get_categories( $force = false ) {
		if ( null !== $this->_categories && ! $force ) {
			return $this->_categories;
		}

		if ( ! class_exists( 'PressPrimer_Assignment_Category' ) ) {
			return [];
		}

		global $wpdb;

		$tax_table      = $wpdb->prefix . 'ppa_assignment_tax';
		$category_table = $wpdb->prefix . 'ppa_categories';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT c.* FROM {$category_table} c INNER JOIN {$tax_table} t ON c.id = t.category_id WHERE t.assignment_id = %d ORDER BY c.name ASC",
				$this->id
			)
		);

		$this->_categories = [];
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$this->_categories[] = PressPrimer_Assignment_Category::from_row( $row );
			}
		}

		return $this->_categories;
	}

	/**
	 * Set categories for this assignment
	 *
	 * Replaces existing category relationships with the provided IDs.
	 *
	 * @since 1.0.0
	 *
	 * @param array $category_ids Array of category IDs.
	 * @return bool True on success.
	 */
	public function set_categories( array $category_ids ) {
		global $wpdb;

		$tax_table = $wpdb->prefix . 'ppa_assignment_tax';

		// Get old category IDs before removing (for count updates).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$old_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT category_id FROM {$tax_table} WHERE assignment_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->id
			)
		);
		$old_ids = array_map( 'absint', $old_ids );

		// Remove existing relationships.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$tax_table,
			[ 'assignment_id' => $this->id ],
			[ '%d' ]
		);

		// Insert new relationships.
		$new_ids = [];
		foreach ( $category_ids as $category_id ) {
			$category_id = absint( $category_id );
			if ( $category_id > 0 ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert(
					$tax_table,
					[
						'assignment_id' => $this->id,
						'category_id'   => $category_id,
					],
					[ '%d', '%d' ]
				);
				$new_ids[] = $category_id;
			}
		}

		// Clear cached categories.
		$this->_categories = null;

		// Update counts for all affected categories.
		if ( class_exists( 'PressPrimer_Assignment_Category' ) ) {
			$affected_ids = array_unique( array_merge( $old_ids, $new_ids ) );
			foreach ( $affected_ids as $cat_id ) {
				PressPrimer_Assignment_Category::update_counts( $cat_id );
			}
		}

		return true;
	}

	/**
	 * Delete assignment and clean up relationships
	 *
	 * Removes taxonomy relationships and updates category counts
	 * before deleting the assignment record.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete() {
		global $wpdb;

		$tax_table = $wpdb->prefix . 'ppa_assignment_tax';

		// Get category IDs before removing relationships.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$category_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT category_id FROM {$tax_table} WHERE assignment_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->id
			)
		);

		// Remove taxonomy relationships.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$tax_table,
			[ 'assignment_id' => $this->id ],
			[ '%d' ]
		);

		// Delete the assignment.
		$result = parent::delete();

		// Update counts for affected categories.
		if ( true === $result && ! empty( $category_ids ) && class_exists( 'PressPrimer_Assignment_Category' ) ) {
			foreach ( $category_ids as $cat_id ) {
				PressPrimer_Assignment_Category::update_counts( absint( $cat_id ) );
			}
		}

		return $result;
	}

	/**
	 * Check if assignment accepts submissions
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if assignment is published.
	 */
	public function accepts_submissions() {
		return 'published' === $this->status;
	}

	/**
	 * Check if assignment accepts text submissions
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if text submissions are allowed.
	 */
	public function accepts_text_submission() {
		return in_array( $this->submission_type, [ 'text', 'either' ], true );
	}

	/**
	 * Check if assignment accepts file uploads
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if file uploads are allowed.
	 */
	public function accepts_file_upload() {
		return in_array( $this->submission_type, [ 'file', 'either' ], true );
	}

	/**
	 * Update cached submission count
	 *
	 * Recalculates and stores the submission count for this assignment.
	 *
	 * @since 1.0.0
	 *
	 * @return int Updated count.
	 */
	public function update_submission_count() {
		global $wpdb;

		$table = $wpdb->prefix . 'ppa_submissions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE assignment_id = %d AND status != %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->id,
				'draft'
			)
		);

		$this->submission_count = $count;
		$this->save();

		return $count;
	}

	/**
	 * Update cached graded count
	 *
	 * Recalculates and stores the graded submission count for this assignment.
	 *
	 * @since 1.0.0
	 *
	 * @return int Updated count.
	 */
	public function update_graded_count() {
		global $wpdb;

		$table = $wpdb->prefix . 'ppa_submissions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE assignment_id = %d AND status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->id,
				'graded'
			)
		);

		$this->graded_count = $count;
		$this->save();

		return $count;
	}
}
