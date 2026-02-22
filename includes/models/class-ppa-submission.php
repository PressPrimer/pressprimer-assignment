<?php
/**
 * Submission model
 *
 * Represents a student's submission for an assignment.
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
 * Submission model class
 *
 * Handles CRUD operations for submissions, including status workflow,
 * grading, and file management.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Submission extends PressPrimer_Assignment_Model {

	/**
	 * Status: Student has started but not submitted.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const STATUS_DRAFT = 'draft';

	/**
	 * Status: Student has submitted, awaiting grading.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const STATUS_SUBMITTED = 'submitted';

	/**
	 * Status: Grader has opened the submission.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const STATUS_GRADING = 'grading';

	/**
	 * Status: Grader has assigned a score and feedback.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const STATUS_GRADED = 'graded';

	/**
	 * Status: Feedback has been released to student.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const STATUS_RETURNED = 'returned';

	/**
	 * Submission UUID
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $uuid = '';

	/**
	 * Assignment ID
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $assignment_id = 0;

	/**
	 * User ID
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $user_id = 0;

	/**
	 * Submission number (for resubmissions)
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $submission_number = 1;

	/**
	 * Submission status
	 *
	 * @since 1.0.0
	 * @var string draft|submitted|grading|graded|returned
	 */
	public $status = 'draft';

	/**
	 * Student notes (context provided with upload)
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $student_notes = null;

	/**
	 * Submitted timestamp
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $submitted_at = null;

	/**
	 * Graded timestamp
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $graded_at = null;

	/**
	 * Returned timestamp
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $returned_at = null;

	/**
	 * Grader user ID
	 *
	 * @since 1.0.0
	 * @var int|null
	 */
	public $grader_id = null;

	/**
	 * Score
	 *
	 * @since 1.0.0
	 * @var float|null
	 */
	public $score = null;

	/**
	 * Grader feedback
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $feedback = null;

	/**
	 * Whether submission passed
	 *
	 * @since 1.0.0
	 * @var int|null
	 */
	public $passed = null;

	/**
	 * Cached file count
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $file_count = 0;

	/**
	 * Cached total file size
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $total_file_size = 0;

	/**
	 * Metadata JSON
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $meta_json = null;

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
	 * Cached files
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	private $_files = null;

	/**
	 * Cached assignment
	 *
	 * @since 1.0.0
	 * @var PressPrimer_Assignment_Assignment|null
	 */
	private $_assignment = null;

	/**
	 * Get table name
	 *
	 * @since 1.0.0
	 *
	 * @return string Table name without prefix.
	 */
	protected static function get_table_name() {
		return 'ppa_submissions';
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
			'assignment_id',
			'user_id',
			'submission_number',
			'status',
			'student_notes',
			'submitted_at',
			'graded_at',
			'returned_at',
			'grader_id',
			'score',
			'feedback',
			'passed',
			'file_count',
			'total_file_size',
			'meta_json',
		];
	}

	/**
	 * Get valid statuses
	 *
	 * @since 1.0.0
	 *
	 * @return array Valid status values.
	 */
	public static function get_valid_statuses() {
		return [
			self::STATUS_DRAFT,
			self::STATUS_SUBMITTED,
			self::STATUS_GRADING,
			self::STATUS_GRADED,
			self::STATUS_RETURNED,
		];
	}

	/**
	 * Create new submission
	 *
	 * Validates input and creates a new submission record.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Submission data.
	 * @return int|WP_Error Submission ID on success, WP_Error on failure.
	 */
	public static function create( array $data ) {
		// Validate required fields.
		if ( empty( $data['assignment_id'] ) ) {
			return new WP_Error(
				'ppa_missing_assignment',
				__( 'Assignment ID is required.', 'pressprimer-assignment' )
			);
		}

		// Validate status.
		if ( ! empty( $data['status'] ) && ! in_array( $data['status'], self::get_valid_statuses(), true ) ) {
			return new WP_Error(
				'ppa_invalid_status',
				__( 'Invalid submission status.', 'pressprimer-assignment' )
			);
		}

		// Generate UUID if not provided.
		if ( empty( $data['uuid'] ) ) {
			$data['uuid'] = wp_generate_uuid4();
		}

		// Set user to current user if not provided.
		if ( empty( $data['user_id'] ) ) {
			$data['user_id'] = get_current_user_id();
		}

		// Set default status if not provided.
		if ( empty( $data['status'] ) ) {
			$data['status'] = self::STATUS_DRAFT;
		}

		// Call parent create.
		$submission_id = parent::create( $data );

		// Fire action hook for addons.
		if ( ! is_wp_error( $submission_id ) ) {
			/**
			 * Fires after a submission is created.
			 *
			 * @since 1.0.0
			 *
			 * @param int   $submission_id The submission ID.
			 * @param array $data          The submission data.
			 */
			do_action( 'pressprimer_assignment_submission_created', $submission_id, $data );
		}

		return $submission_id;
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
			 * Fires after a submission is updated.
			 *
			 * @since 1.0.0
			 *
			 * @param PressPrimer_Assignment_Submission $submission The submission instance.
			 */
			do_action( 'pressprimer_assignment_submission_updated', $this );
		}

		return $result;
	}

	/**
	 * Get submissions for an assignment
	 *
	 * @since 1.0.0
	 *
	 * @param int   $assignment_id Assignment ID.
	 * @param array $args          Optional query arguments.
	 * @return array Array of Submission instances.
	 */
	public static function get_for_assignment( $assignment_id, array $args = [] ) {
		$assignment_id = absint( $assignment_id );

		$defaults = [
			'where'    => [ 'assignment_id' => $assignment_id ],
			'order_by' => 'created_at',
			'order'    => 'DESC',
		];

		$args = wp_parse_args( $args, $defaults );

		// Ensure we always filter by this assignment.
		$args['where']['assignment_id'] = $assignment_id;

		return static::find( $args );
	}

	/**
	 * Get submissions for a user
	 *
	 * @since 1.0.0
	 *
	 * @param int   $user_id User ID.
	 * @param array $args    Optional query arguments.
	 * @return array Array of Submission instances.
	 */
	public static function get_for_user( $user_id, array $args = [] ) {
		$user_id = absint( $user_id );

		$defaults = [
			'where'    => [ 'user_id' => $user_id ],
			'order_by' => 'created_at',
			'order'    => 'DESC',
		];

		$args = wp_parse_args( $args, $defaults );

		// Ensure we always filter by this user.
		$args['where']['user_id'] = $user_id;

		return static::find( $args );
	}

	/**
	 * Get files for this submission
	 *
	 * Lazy-loads and caches the file relationships.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force Force reload from database.
	 * @return array Array of Submission_File instances.
	 */
	public function get_files( $force = false ) {
		if ( null !== $this->_files && ! $force ) {
			return $this->_files;
		}

		if ( ! class_exists( 'PressPrimer_Assignment_Submission_File' ) ) {
			return [];
		}

		$this->_files = PressPrimer_Assignment_Submission_File::get_for_submission( $this->id );

		return $this->_files;
	}

	/**
	 * Get the parent assignment
	 *
	 * Lazy-loads and caches the assignment.
	 *
	 * @since 1.0.0
	 *
	 * @return PressPrimer_Assignment_Assignment|null Assignment instance or null.
	 */
	public function get_assignment() {
		if ( null === $this->_assignment ) {
			$this->_assignment = PressPrimer_Assignment_Assignment::get( $this->assignment_id );
		}

		return $this->_assignment;
	}

	/**
	 * Get all metadata
	 *
	 * @since 1.0.0
	 *
	 * @return array Metadata array.
	 */
	public function get_all_meta() {
		if ( empty( $this->meta_json ) ) {
			return [];
		}

		$meta = json_decode( $this->meta_json, true );

		return is_array( $meta ) ? $meta : [];
	}

	/**
	 * Get a single metadata value
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     Metadata key.
	 * @param mixed  $default Default value if key not found.
	 * @return mixed Metadata value or default.
	 */
	public function get_meta( $key, $default = null ) {
		$meta = $this->get_all_meta();

		return isset( $meta[ $key ] ) ? $meta[ $key ] : $default;
	}

	/**
	 * Set a metadata value
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   Metadata key.
	 * @param mixed  $value Metadata value.
	 * @return PressPrimer_Assignment_Submission This instance for chaining.
	 */
	public function set_meta( $key, $value ) {
		$meta            = $this->get_all_meta();
		$meta[ $key ]    = $value;
		$this->meta_json = wp_json_encode( $meta );

		return $this;
	}

	/**
	 * Update cached file stats
	 *
	 * Recalculates file count and total size from the submission_files table.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update_file_stats() {
		global $wpdb;

		$table = $wpdb->prefix . 'ppa_submission_files';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) AS file_count, COALESCE(SUM(file_size), 0) AS total_file_size FROM {$table} WHERE submission_id = %d",
				$this->id
			)
		);

		if ( $stats ) {
			$this->file_count      = (int) $stats->file_count;
			$this->total_file_size = (int) $stats->total_file_size;

			return $this->save();
		}

		return true;
	}

	/**
	 * Delete submission and associated files
	 *
	 * Removes submission and all associated file records.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete() {
		// Capture data for hooks.
		$submission_id = $this->id;
		$assignment_id = $this->assignment_id;
		$user_id       = $this->user_id;

		// Delete associated file records.
		global $wpdb;
		$files_table = $wpdb->prefix . 'ppa_submission_files';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$files_table,
			[ 'submission_id' => $this->id ],
			[ '%d' ]
		);

		$result = parent::delete();

		// Fire action hook for addons.
		if ( true === $result ) {
			/**
			 * Fires after a submission is deleted.
			 *
			 * @since 1.0.0
			 *
			 * @param int $submission_id The submission ID.
			 * @param int $assignment_id The assignment ID.
			 * @param int $user_id       The user ID.
			 */
			do_action( 'pressprimer_assignment_submission_deleted', $submission_id, $assignment_id, $user_id );
		}

		return $result;
	}
}
