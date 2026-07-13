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
	 * Due date (UTC), or null when the assignment has no due date
	 *
	 * MySQL DATETIME string. Stored in UTC like the other datetime
	 * columns (submitted_at, graded_at). When null, nothing is ever
	 * late and the late policy is inert.
	 *
	 * @since 2.2.0
	 * @var string|null
	 */
	public $due_at = null;

	/**
	 * Late submission policy
	 *
	 * - accept:  late submissions accepted with no penalty (default —
	 *            matches pre-2.2 behavior, where lateness didn't exist)
	 * - penalty: late submissions accepted; a graduated penalty schedule
	 *            (late_penalty_schedule_json) is applied at grading time
	 * - reject:  late submissions refused at submission time
	 *
	 * @since 2.2.0
	 * @var string accept|penalty|reject
	 */
	public $late_policy = 'accept';

	/**
	 * Graduated late penalty schedule (JSON), used when late_policy is 'penalty'
	 *
	 * Shape: {"tiers":[{"late_by_hours":24,"penalty_percent":10},...],
	 *         "cutoff_hours":168,"basis":"max_points"}
	 * - 1–5 tiers, strictly increasing thresholds, penalties 0–100 non-decreasing
	 * - A single tier may use late_by_hours null (any lateness — the flat case)
	 * - cutoff_hours (optional): submissions refused entirely beyond it
	 * - basis: what the percentages deduct from — 'max_points' (default,
	 *   percent of the assignment's maximum points) or 'raw_score'
	 *   (percent of the student's earned score)
	 *
	 * Always rebuilt server-side from validated numeric fields before
	 * storage — raw client JSON is never stored as-is.
	 *
	 * @since 2.2.0
	 * @var string|null
	 */
	public $late_penalty_schedule_json = null;

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
	 * Whether AI auto-grading is enabled
	 *
	 * When enabled and the School addon is active with a configured
	 * AI provider, new submissions are automatically queued for
	 * background AI grading suggestions.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	public $ai_auto_grade = 0;

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
			'due_at',
			'late_policy',
			'late_penalty_schedule_json',
			'status',
			'theme',
			'ai_auto_grade',
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
		// Cross-field validation on the live instance. The REST update
		// path skips validate_data(), so this guards updates as well as
		// any non-REST save() call. Both fields exist as properties on
		// the instance, so we always have something to compare.
		$validation = self::validate_data(
			array(
				'max_points'    => $this->max_points,
				'passing_score' => $this->passing_score,
			)
		);
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

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
					'pressprimer_assignment_empty_title',
					__( 'Assignment title cannot be empty.', 'pressprimer-assignment' )
				);
			}
		}

		// Validate submission_type.
		if ( ! empty( $data['submission_type'] ) && ! in_array( $data['submission_type'], [ 'file', 'text', 'either' ], true ) ) {
			return new WP_Error(
				'pressprimer_assignment_invalid_submission_type',
				__( 'Invalid submission type. Must be file, text, or either.', 'pressprimer-assignment' )
			);
		}

		// Validate status.
		if ( ! empty( $data['status'] ) && ! in_array( $data['status'], [ 'draft', 'published', 'archived' ], true ) ) {
			return new WP_Error(
				'pressprimer_assignment_invalid_status',
				__( 'Invalid status. Must be draft, published, or archived.', 'pressprimer-assignment' )
			);
		}

		// Validate late_policy.
		if ( ! empty( $data['late_policy'] ) && ! in_array( $data['late_policy'], [ 'accept', 'penalty', 'reject' ], true ) ) {
			return new WP_Error(
				'pressprimer_assignment_invalid_late_policy',
				__( 'Invalid late policy. Must be accept, penalty, or reject.', 'pressprimer-assignment' )
			);
		}

		// Validate due_at (null clears; otherwise a parseable MySQL datetime).
		if ( isset( $data['due_at'] ) && null !== $data['due_at'] && '' !== $data['due_at'] ) {
			$due_timestamp = strtotime( (string) $data['due_at'] );
			if ( false === $due_timestamp ) {
				return new WP_Error(
					'pressprimer_assignment_invalid_due_at',
					__( 'Invalid due date format.', 'pressprimer-assignment' )
				);
			}
		}

		// Validate max_points.
		if ( isset( $data['max_points'] ) ) {
			$points = floatval( $data['max_points'] );
			if ( $points < 0.01 || $points > 100000.00 ) {
				return new WP_Error(
					'pressprimer_assignment_invalid_max_points',
					__( 'Max points must be between 0.01 and 100,000.', 'pressprimer-assignment' )
				);
			}
		}

		// Validate passing_score.
		if ( isset( $data['passing_score'] ) ) {
			$score = floatval( $data['passing_score'] );
			if ( $score < 0.00 || $score > 100000.00 ) {
				return new WP_Error(
					'pressprimer_assignment_invalid_passing_score',
					__( 'Passing score must be between 0 and 100,000.', 'pressprimer-assignment' )
				);
			}
		}

		// Cross-field: passing_score must not exceed max_points. Only
		// enforced when both fields are present in $data, so partial
		// updates that touch only one of the two still validate against
		// their own bounds without false positives.
		if ( isset( $data['max_points'] ) && isset( $data['passing_score'] ) ) {
			if ( floatval( $data['passing_score'] ) > floatval( $data['max_points'] ) ) {
				return new WP_Error(
					'pressprimer_assignment_passing_score_exceeds_max',
					__( 'Passing score cannot be greater than maximum points.', 'pressprimer-assignment' )
				);
			}
		}

		// Validate max_file_size.
		if ( isset( $data['max_file_size'] ) ) {
			$size = absint( $data['max_file_size'] );
			if ( $size < 1024 || $size > 104857600 ) {
				return new WP_Error(
					'pressprimer_assignment_invalid_file_size',
					__( 'Max file size must be between 1 KB and 100 MB.', 'pressprimer-assignment' )
				);
			}
		}

		// Validate max_files.
		if ( isset( $data['max_files'] ) ) {
			$max = absint( $data['max_files'] );
			if ( $max < 1 || $max > 50 ) {
				return new WP_Error(
					'pressprimer_assignment_invalid_max_files',
					__( 'Max files must be between 1 and 50.', 'pressprimer-assignment' )
				);
			}
		}

		// Validate max_resubmissions (0 = unlimited, 1-100 = limited retakes).
		if ( isset( $data['max_resubmissions'] ) ) {
			$max = absint( $data['max_resubmissions'] );
			if ( $max > 100 ) {
				return new WP_Error(
					'pressprimer_assignment_invalid_max_resubmissions',
					__( 'Max resubmissions must be between 0 and 100.', 'pressprimer-assignment' )
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
	 * Get the decoded late penalty schedule
	 *
	 * Decodes late_penalty_schedule_json and normalizes its shape. Returns
	 * null when no usable schedule is stored (empty, malformed, or no
	 * tiers) — callers treat null as "no penalty applies".
	 *
	 * @since 2.2.0
	 *
	 * @return array|null Array with 'tiers' (list of ['late_by_hours' =>
	 *                    float|null, 'penalty_percent' => float]),
	 *                    'cutoff_hours' (float|null), and 'basis'
	 *                    ('max_points'|'raw_score'), or null.
	 */
	public function get_late_penalty_schedule() {
		if ( null === $this->late_penalty_schedule_json || '' === $this->late_penalty_schedule_json ) {
			return null;
		}

		$decoded = json_decode( $this->late_penalty_schedule_json, true );

		if ( ! is_array( $decoded ) || empty( $decoded['tiers'] ) || ! is_array( $decoded['tiers'] ) ) {
			return null;
		}

		$tiers = [];
		foreach ( $decoded['tiers'] as $tier ) {
			if ( ! is_array( $tier ) || ! isset( $tier['penalty_percent'] ) ) {
				continue;
			}

			$tiers[] = [
				'late_by_hours'   => isset( $tier['late_by_hours'] ) && null !== $tier['late_by_hours']
					? (float) $tier['late_by_hours']
					: null,
				'penalty_percent' => (float) $tier['penalty_percent'],
			];
		}

		if ( empty( $tiers ) ) {
			return null;
		}

		return [
			'tiers'        => $tiers,
			'cutoff_hours' => isset( $decoded['cutoff_hours'] ) && null !== $decoded['cutoff_hours']
				? (float) $decoded['cutoff_hours']
				: null,
			'basis'        => isset( $decoded['basis'] ) && 'raw_score' === $decoded['basis']
				? 'raw_score'
				: 'max_points',
		];
	}

	/**
	 * Get the effective due date for a specific user
	 *
	 * Starts from the assignment's own due date and lets addons supersede
	 * it: Educator's per-group dates already hook this filter, and
	 * Educator 2.2's per-student overrides will use the same path. This
	 * is the single effective-due-date calculation path — every lateness
	 * decision (penalty resolution, cutoff enforcement, display) must go
	 * through it. No addon-specific branches.
	 *
	 * @since 2.2.0
	 *
	 * @param int $user_id User ID.
	 * @return string|null Effective due date as a UTC MySQL datetime, or
	 *                     null when the user has no due date.
	 */
	public function get_due_date_for_user( $user_id ) {
		/**
		 * Filters the effective due date for a user on an assignment.
		 *
		 * The default is the assignment's own due_at (UTC MySQL datetime,
		 * or null when the assignment has no due date). Addons return a
		 * superseding date for users they manage — Educator returns
		 * per-group distribution dates (the latest wins when the user is
		 * in several groups); per-student overrides arrive in Educator
		 * 2.2 through this same filter.
		 *
		 * @since 2.2.0
		 *
		 * @param string|null $due_date      Due date (UTC MySQL datetime) or null.
		 * @param int         $assignment_id Assignment ID.
		 * @param int         $user_id       User ID.
		 */
		return apply_filters(
			'pressprimer_assignment_due_date_for_user',
			$this->due_at,
			(int) $this->id,
			absint( $user_id )
		);
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
	 * Duplicate an assignment.
	 *
	 * Creates a new draft assignment with all settings and taxonomy relationships
	 * copied from the source. Submissions, submission files, and cached counts are
	 * not copied. The duplicate is owned by the current user regardless of the
	 * source author. Wraps the row insert and taxonomy copy in a single transaction
	 * so a partial failure leaves no orphan rows.
	 *
	 * @since 2.1.0
	 *
	 * @param int $source_id Source assignment ID.
	 * @return int|WP_Error New assignment ID on success, WP_Error on failure.
	 */
	public static function duplicate( int $source_id ) {
		global $wpdb;

		$source = self::get( $source_id );
		if ( ! $source ) {
			return new WP_Error(
				'not_found',
				__( 'Source assignment not found.', 'pressprimer-assignment' )
			);
		}

		// Build the new row from the source. parent::create() filters by
		// fillable fields, so id/created_at/updated_at are dropped automatically;
		// the DB sets created_at and updated_at via DEFAULT CURRENT_TIMESTAMP.
		$data = $source->to_array();

		$data['uuid']  = wp_generate_uuid4();
		$data['title'] = sprintf(
			/* translators: %s: source assignment title */
			__( '%s (Copy)', 'pressprimer-assignment' ),
			$source->title
		);
		$data['status']           = 'draft';
		$data['author_id']        = get_current_user_id();
		$data['submission_count'] = 0;
		$data['graded_count']     = 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'START TRANSACTION' );

		$new_id = self::create( $data );
		if ( is_wp_error( $new_id ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'ROLLBACK' );
			return $new_id;
		}

		// Copy taxonomy relationships from the source.
		$tax_table = $wpdb->prefix . 'ppa_assignment_tax';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$category_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT category_id FROM {$tax_table} WHERE assignment_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$source_id
			)
		);

		foreach ( $category_ids as $category_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert(
				$tax_table,
				[
					'assignment_id' => $new_id,
					'category_id'   => absint( $category_id ),
				],
				[ '%d', '%d' ]
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'COMMIT' );

		// Bump category counts for the newly attached categories.
		if ( ! empty( $category_ids ) && class_exists( 'PressPrimer_Assignment_Category' ) ) {
			foreach ( $category_ids as $cat_id ) {
				PressPrimer_Assignment_Category::update_counts( absint( $cat_id ) );
			}
		}

		/**
		 * Fires after an assignment is duplicated.
		 *
		 * Addons hook this to copy their own per-assignment data, e.g.
		 * Educator copies the rubric attachment, Enterprise records an audit
		 * event. The hook signature is stable for the lifetime of the suite.
		 *
		 * @since 2.1.0
		 *
		 * @param int $new_assignment_id    The duplicate's assignment ID.
		 * @param int $source_assignment_id The source assignment's ID.
		 */
		do_action( 'pressprimer_assignment_assignment_duplicated', $new_id, $source_id );

		return $new_id;
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
