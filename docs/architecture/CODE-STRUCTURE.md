# Code Structure

## Directory Layout

```
pressprimer-assignment/
├── pressprimer-assignment.php          # Main plugin file
├── uninstall.php                        # Cleanup on uninstall
├── readme.txt                           # WordPress.org readme
├── CHANGELOG.md                         # Version history
├── LICENSE                              # GPL v2+
│
├── package.json                         # npm dependencies
├── composer.json                        # PHP dependencies
├── webpack.config.js                    # Build configuration
├── phpcs.xml.dist                       # PHPCS rules
├── .distignore                          # Files excluded from release
├── .gitignore                           # Git ignores
│
├── includes/                            # PHP classes
│   ├── class-ppa-activator.php          # Activation logic
│   ├── class-ppa-deactivator.php        # Deactivation logic
│   ├── class-ppa-autoloader.php         # Class autoloader
│   ├── class-ppa-plugin.php             # Main plugin class
│   ├── class-ppa-addon-manager.php      # Premium addon detection
│   │
│   ├── models/                          # Data models
│   │   ├── class-ppa-model.php          # Base model class
│   │   ├── class-ppa-assignment.php     # Assignment model
│   │   ├── class-ppa-submission.php     # Submission model
│   │   ├── class-ppa-submission-file.php # File model
│   │   └── class-ppa-category.php       # Category/tag model
│   │
│   ├── admin/                           # Admin functionality
│   │   ├── class-ppa-admin.php          # Admin initialization
│   │   ├── class-ppa-admin-assignments.php  # Assignment list/edit
│   │   ├── class-ppa-admin-submissions.php  # Submission list/grading
│   │   ├── class-ppa-admin-reports.php      # Reports page
│   │   ├── class-ppa-admin-settings.php     # Settings page
│   │   ├── class-ppa-admin-categories.php   # Categories management
│   │   └── class-ppa-onboarding.php         # First-run experience
│   │
│   ├── frontend/                        # Frontend functionality
│   │   ├── class-ppa-frontend.php       # Frontend initialization
│   │   ├── class-ppa-shortcodes.php     # Shortcode handlers
│   │   ├── class-ppa-assignment-renderer.php  # Assignment display
│   │   ├── class-ppa-submission-handler.php   # Submission processing
│   │   └── class-ppa-document-viewer.php      # Document preview
│   │
│   ├── api/                             # REST API
│   │   ├── class-ppa-rest-controller.php      # Base controller
│   │   ├── class-ppa-rest-assignments.php     # /assignments endpoint
│   │   ├── class-ppa-rest-submissions.php     # /submissions endpoint
│   │   ├── class-ppa-rest-files.php           # /files endpoint
│   │   ├── class-ppa-rest-categories.php      # /categories endpoint
│   │   └── class-ppa-rest-reports.php         # /reports endpoint
│   │
│   ├── services/                        # Business logic services
│   │   ├── class-ppa-grading-service.php      # Grading calculations
│   │   ├── class-ppa-file-service.php         # File handling
│   │   ├── class-ppa-email-service.php        # Email notifications
│   │   └── class-ppa-statistics-service.php   # Stats calculations
│   │
│   ├── integrations/                    # Third-party integrations
│   │   ├── class-ppa-learndash.php            # LearnDash integration
│   │   ├── class-ppa-tutorlms.php             # TutorLMS integration
│   │   └── uncanny-automator/                 # Automator integration
│   │       ├── class-ppa-automator-loader.php
│   │       ├── class-ppa-automator-integration.php
│   │       └── triggers/
│   │           ├── class-ppa-trigger-submitted.php
│   │           ├── class-ppa-trigger-graded.php
│   │           ├── class-ppa-trigger-passed.php
│   │           └── class-ppa-trigger-failed.php
│   │
│   ├── database/                        # Database management
│   │   ├── class-ppa-schema.php         # Table definitions
│   │   └── class-ppa-migrator.php       # Migration runner
│   │
│   └── utilities/                       # Helper classes
│       ├── class-ppa-helpers.php        # General helpers
│       └── class-ppa-capabilities.php   # Role/capability setup
│
├── assets/                              # Static assets
│   ├── css/
│   │   ├── admin.css                    # Admin styles
│   │   ├── submission.css               # Submission form styles
│   │   ├── grading.css                  # Grading interface styles
│   │   ├── document-viewer.css          # Document viewer styles
│   │   └── themes/
│   │       ├── default.css              # Default theme
│   │       ├── modern.css               # Modern theme
│   │       └── minimal.css              # Minimal theme
│   │
│   ├── js/
│   │   ├── admin.js                     # Admin scripts (compiled)
│   │   ├── submission.js                # Submission form scripts
│   │   ├── document-viewer.js           # Document viewer scripts
│   │   └── grading.js                   # Grading interface scripts
│   │
│   └── images/
│       ├── icon-128.png                 # Plugin icon
│       ├── icon-256.png
│       └── onboarding/                  # Onboarding images
│
├── blocks/                              # Gutenberg blocks
│   ├── assignment/
│   │   ├── block.json
│   │   ├── index.js
│   │   ├── edit.js
│   │   ├── save.js
│   │   └── style.css
│   │
│   └── my-submissions/
│       ├── block.json
│       ├── index.js
│       ├── edit.js
│       ├── save.js
│       └── style.css
│
├── build/                               # Compiled React/JS (generated)
│   ├── admin/
│   │   └── index.js
│   ├── blocks/
│   └── ...
│
├── src/                                 # React source files
│   ├── admin/                           # Admin React app
│   │   ├── index.js                     # Entry point
│   │   ├── App.js                       # Main app component
│   │   ├── api/                         # API utilities
│   │   │   └── index.js
│   │   ├── components/                  # Shared components
│   │   │   ├── AssignmentForm.js
│   │   │   ├── FileUploader.js
│   │   │   ├── DocumentViewer.js
│   │   │   └── ...
│   │   ├── pages/                       # Page components
│   │   │   ├── Assignments/
│   │   │   ├── Submissions/
│   │   │   ├── Reports/
│   │   │   ├── Settings/
│   │   │   └── Dashboard/
│   │   └── utils/                       # Utilities
│   │
│   ├── grading/                         # Grading interface
│   │   ├── index.js
│   │   ├── GradingInterface.js
│   │   └── components/
│   │
│   └── onboarding/                      # Onboarding wizard
│       ├── index.js
│       └── steps/
│
├── templates/                           # PHP templates
│   ├── assignment/
│   │   ├── single.php                   # Single assignment view
│   │   ├── submission-form.php          # Submission form
│   │   └── submission-status.php        # Status display
│   │
│   ├── dashboard/
│   │   └── my-submissions.php           # Student dashboard
│   │
│   └── emails/
│       ├── submission-received.php      # Confirmation email
│       └── grade-released.php           # Grade notification
│
├── languages/
│   └── pressprimer-assignment.pot       # Translation template
│
├── vendor/                              # Composer dependencies
│   └── ...
│
├── node_modules/                        # npm dependencies (dev only)
│   └── ...
│
└── .wordpress-org/                      # WordPress.org assets
    ├── banner-772x250.png
    ├── banner-1544x500.png
    ├── icon-128x128.png
    ├── icon-256x256.png
    └── screenshot-1.png
```

---

## Key Files Explained

### Main Plugin File

`pressprimer-assignment.php` - Entry point that:
- Defines plugin constants
- Registers autoloader
- Initializes main plugin class
- Registers activation/deactivation hooks

```php
<?php
/**
 * Plugin Name: PressPrimer Assignment
 * Plugin URI: https://pressprimer.com/assignment
 * Description: Document-based assignment submission and grading for WordPress.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: PressPrimer
 * Author URI: https://pressprimer.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pressprimer-assignment
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'PRESSPRIMER_ASSIGNMENT_VERSION', '1.0.0' );
define( 'PRESSPRIMER_ASSIGNMENT_DB_VERSION', '1.0.0' );
define( 'PRESSPRIMER_ASSIGNMENT_FILE', __FILE__ );
define( 'PRESSPRIMER_ASSIGNMENT_PATH', plugin_dir_path( __FILE__ ) );
define( 'PRESSPRIMER_ASSIGNMENT_URL', plugin_dir_url( __FILE__ ) );
define( 'PRESSPRIMER_ASSIGNMENT_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader
require_once PRESSPRIMER_ASSIGNMENT_PATH . 'includes/class-ppa-autoloader.php';
PressPrimer_Assignment_Autoloader::register();

// Activation/Deactivation
register_activation_hook( __FILE__, [ 'PressPrimer_Assignment_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'PressPrimer_Assignment_Deactivator', 'deactivate' ] );

// Initialize plugin
add_action( 'plugins_loaded', function() {
    PressPrimer_Assignment_Plugin::instance();
} );
```

### Autoloader

`includes/class-ppa-autoloader.php` - Maps class names to files:

```php
<?php
class PressPrimer_Assignment_Autoloader {
    
    public static function register() {
        spl_autoload_register( [ __CLASS__, 'autoload' ] );
    }
    
    public static function autoload( $class ) {
        // Only handle our classes
        if ( strpos( $class, 'PressPrimer_Assignment_' ) !== 0 ) {
            return;
        }
        
        // Convert class name to file path
        $class_name = str_replace( 'PressPrimer_Assignment_', '', $class );
        $file_name = 'class-ppa-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
        
        // Check directories
        $directories = [
            PRESSPRIMER_ASSIGNMENT_PATH . 'includes/',
            PRESSPRIMER_ASSIGNMENT_PATH . 'includes/models/',
            PRESSPRIMER_ASSIGNMENT_PATH . 'includes/admin/',
            PRESSPRIMER_ASSIGNMENT_PATH . 'includes/frontend/',
            PRESSPRIMER_ASSIGNMENT_PATH . 'includes/api/',
            PRESSPRIMER_ASSIGNMENT_PATH . 'includes/services/',
            PRESSPRIMER_ASSIGNMENT_PATH . 'includes/integrations/',
            PRESSPRIMER_ASSIGNMENT_PATH . 'includes/database/',
            PRESSPRIMER_ASSIGNMENT_PATH . 'includes/utilities/',
        ];
        
        foreach ( $directories as $dir ) {
            $path = $dir . $file_name;
            if ( file_exists( $path ) ) {
                require_once $path;
                return;
            }
        }
    }
}
```

### Main Plugin Class

`includes/class-ppa-plugin.php` - Orchestrates all components:

```php
<?php
class PressPrimer_Assignment_Plugin {
    
    private static $instance = null;
    
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        // Database
        new PressPrimer_Assignment_Schema();
        new PressPrimer_Assignment_Migrator();
        
        // Core
        new PressPrimer_Assignment_Capabilities();
        
        // Admin
        if ( is_admin() ) {
            new PressPrimer_Assignment_Admin();
        }
        
        // Frontend
        new PressPrimer_Assignment_Frontend();
        new PressPrimer_Assignment_Shortcodes();
        
        // REST API
        new PressPrimer_Assignment_REST_Assignments();
        new PressPrimer_Assignment_REST_Submissions();
        new PressPrimer_Assignment_REST_Files();
        new PressPrimer_Assignment_REST_Categories();
        
        // Integrations
        new PressPrimer_Assignment_LearnDash();
        new PressPrimer_Assignment_TutorLMS();
        new PressPrimer_Assignment_Automator_Loader();
        
        // Services
        new PressPrimer_Assignment_File_Service();
    }
    
    private function init_hooks() {
        add_action( 'init', [ $this, 'load_textdomain' ] );
        add_action( 'init', [ $this, 'register_blocks' ] );
    }
    
    public function load_textdomain() {
        load_plugin_textdomain(
            'pressprimer-assignment',
            false,
            dirname( PRESSPRIMER_ASSIGNMENT_BASENAME ) . '/languages'
        );
    }
    
    public function register_blocks() {
        register_block_type( PRESSPRIMER_ASSIGNMENT_PATH . 'blocks/assignment' );
        register_block_type( PRESSPRIMER_ASSIGNMENT_PATH . 'blocks/my-submissions' );
    }
}
```

---

## Model Pattern

All models extend a base class with common CRUD operations:

```php
<?php
// includes/models/class-ppa-model.php

abstract class PressPrimer_Assignment_Model {
    
    protected static $table_name = '';
    protected static $primary_key = 'id';
    
    protected $data = [];
    
    /**
     * Get table name with prefix
     */
    protected static function get_table() {
        global $wpdb;
        return $wpdb->prefix . static::$table_name;
    }
    
    /**
     * Get single record by ID
     */
    public static function get( $id ) {
        global $wpdb;
        
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . static::get_table() . " WHERE %i = %d",
            static::$primary_key,
            $id
        ) );
        
        if ( ! $row ) {
            return null;
        }
        
        $instance = new static();
        $instance->data = (array) $row;
        return $instance;
    }
    
    /**
     * Get by UUID
     */
    public static function get_by_uuid( $uuid ) {
        global $wpdb;
        
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . static::get_table() . " WHERE uuid = %s",
            $uuid
        ) );
        
        if ( ! $row ) {
            return null;
        }
        
        $instance = new static();
        $instance->data = (array) $row;
        return $instance;
    }
    
    /**
     * Create new record
     */
    public static function create( array $data ) {
        global $wpdb;
        
        // Generate UUID if not provided
        if ( empty( $data['uuid'] ) ) {
            $data['uuid'] = wp_generate_uuid4();
        }
        
        $result = $wpdb->insert( static::get_table(), $data );
        
        if ( false === $result ) {
            return new WP_Error( 'db_error', __( 'Failed to create record.', 'pressprimer-assignment' ) );
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update record
     */
    public function save() {
        global $wpdb;
        
        $id = $this->data[ static::$primary_key ] ?? 0;
        
        if ( ! $id ) {
            return new WP_Error( 'invalid_id', __( 'Cannot save without ID.', 'pressprimer-assignment' ) );
        }
        
        $data = $this->data;
        unset( $data[ static::$primary_key ] );
        
        $result = $wpdb->update(
            static::get_table(),
            $data,
            [ static::$primary_key => $id ]
        );
        
        return false !== $result;
    }
    
    /**
     * Delete record
     */
    public function delete() {
        global $wpdb;
        
        $id = $this->data[ static::$primary_key ] ?? 0;
        
        return $wpdb->delete(
            static::get_table(),
            [ static::$primary_key => $id ]
        );
    }
    
    /**
     * Magic getter
     */
    public function __get( $key ) {
        return $this->data[ $key ] ?? null;
    }
    
    /**
     * Magic setter
     */
    public function __set( $key, $value ) {
        $this->data[ $key ] = $value;
    }
    
    /**
     * Get all data
     */
    public function to_array() {
        return $this->data;
    }
}
```

### Assignment Model Example

```php
<?php
// includes/models/class-ppa-assignment.php

class PressPrimer_Assignment_Assignment extends PressPrimer_Assignment_Model {
    
    protected static $table_name = 'ppa_assignments';
    
    /**
     * Get published assignments
     */
    public static function get_published( $args = [] ) {
        global $wpdb;
        
        $defaults = [
            'limit'   => 20,
            'offset'  => 0,
            'orderby' => 'created_at',
            'order'   => 'DESC',
        ];
        $args = wp_parse_args( $args, $defaults );
        
        // Validate orderby
        $allowed = [ 'id', 'title', 'created_at', 'due_date' ];
        $orderby = in_array( $args['orderby'], $allowed, true ) ? $args['orderby'] : 'created_at';
        
        // Build query with hardcoded ORDER direction
        $is_asc = 'ASC' === strtoupper( $args['order'] );
        
        if ( $is_asc ) {
            $sql = $wpdb->prepare(
                "SELECT * FROM " . self::get_table() . " 
                 WHERE status = 'published' 
                 ORDER BY %i ASC 
                 LIMIT %d OFFSET %d",
                $orderby,
                $args['limit'],
                $args['offset']
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM " . self::get_table() . " 
                 WHERE status = 'published' 
                 ORDER BY %i DESC 
                 LIMIT %d OFFSET %d",
                $orderby,
                $args['limit'],
                $args['offset']
            );
        }
        
        $rows = $wpdb->get_results( $sql );
        
        return array_map( function( $row ) {
            $instance = new self();
            $instance->data = (array) $row;
            return $instance;
        }, $rows );
    }
    
    /**
     * Check if past due
     */
    public function is_past_due() {
        if ( ! $this->due_date ) {
            return false;
        }
        
        $due = new DateTime( $this->due_date, new DateTimeZone( $this->due_date_timezone ?: 'UTC' ) );
        $now = new DateTime( 'now', new DateTimeZone( $this->due_date_timezone ?: 'UTC' ) );
        
        return $now > $due;
    }
    
    /**
     * Get allowed file types as array
     */
    public function get_allowed_file_types() {
        if ( ! $this->allowed_file_types ) {
            return pressprimer_assignment_get_default_file_types();
        }
        
        return json_decode( $this->allowed_file_types, true );
    }
    
    /**
     * Get submissions for this assignment
     */
    public function get_submissions( $args = [] ) {
        $args['assignment_id'] = $this->id;
        return PressPrimer_Assignment_Submission::get_for_assignment( $args );
    }
}
```

---

## Service Pattern

Services encapsulate business logic separate from models:

```php
<?php
// includes/services/class-ppa-grading-service.php

class PressPrimer_Assignment_Grading_Service {
    
    /**
     * Grade a submission
     *
     * @param int    $submission_id Submission ID.
     * @param float  $score         Raw score.
     * @param string $feedback      Feedback text.
     * @return array|WP_Error Result array or error.
     */
    public static function grade( $submission_id, $score, $feedback = '' ) {
        $submission = PressPrimer_Assignment_Submission::get( $submission_id );
        
        if ( ! $submission ) {
            return new WP_Error( 'not_found', __( 'Submission not found.', 'pressprimer-assignment' ) );
        }
        
        $assignment = PressPrimer_Assignment_Assignment::get( $submission->assignment_id );
        
        // Validate score range
        if ( $score < 0 || $score > $assignment->max_points ) {
            return new WP_Error( 'invalid_score', __( 'Score out of range.', 'pressprimer-assignment' ) );
        }
        
        // Fire before action
        do_action( 'pressprimer_assignment_before_grade', $submission_id );
        
        // Calculate late penalty
        $penalty = 0;
        if ( $submission->is_late && 'penalty' === $assignment->late_policy ) {
            $penalty = $score * ( $assignment->late_penalty_percent / 100 );
            $penalty = apply_filters( 'pressprimer_assignment_calculate_late_penalty', $penalty, $submission_id, $score );
        }
        
        // Calculate final score
        $final_score = max( 0, $score - $penalty );
        $final_score = apply_filters( 'pressprimer_assignment_final_score', $final_score, $submission_id, $score, $penalty );
        
        // Determine pass/fail
        $passed = $final_score >= $assignment->passing_score;
        $passed = apply_filters( 'pressprimer_assignment_passed', $passed, $submission_id, $final_score, $assignment->passing_score );
        
        // Update submission
        $submission->score = $score;
        $submission->feedback = $feedback;
        $submission->late_penalty_applied = $penalty;
        $submission->final_score = $final_score;
        $submission->passed = $passed ? 1 : 0;
        $submission->grader_id = get_current_user_id();
        $submission->graded_at = current_time( 'mysql' );
        $submission->status = 'graded';
        
        $submission->save();
        
        // Fire after action
        do_action( 'pressprimer_assignment_after_grade', $submission_id, $score, $feedback );
        do_action( 'pressprimer_assignment_submission_graded', $submission_id, $score, $final_score );
        
        // Fire pass/fail action
        if ( $passed ) {
            do_action( 'pressprimer_assignment_submission_passed', $submission_id, $final_score );
        } else {
            do_action( 'pressprimer_assignment_submission_failed', $submission_id, $final_score );
        }
        
        return [
            'submission_id' => $submission_id,
            'score'         => $score,
            'penalty'       => $penalty,
            'final_score'   => $final_score,
            'passed'        => $passed,
        ];
    }
}
```

---

## REST API Pattern

```php
<?php
// includes/api/class-ppa-rest-assignments.php

class PressPrimer_Assignment_REST_Assignments {
    
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }
    
    public function register_routes() {
        register_rest_route( 'ppa/v1', '/assignments', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_items' ],
                'permission_callback' => [ $this, 'get_items_permissions_check' ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_item' ],
                'permission_callback' => [ $this, 'create_item_permissions_check' ],
            ],
        ] );
        
        register_rest_route( 'ppa/v1', '/assignments/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_item' ],
                'permission_callback' => [ $this, 'get_item_permissions_check' ],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_item' ],
                'permission_callback' => [ $this, 'update_item_permissions_check' ],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_item' ],
                'permission_callback' => [ $this, 'delete_item_permissions_check' ],
            ],
        ] );
    }
    
    public function get_items_permissions_check( $request ) {
        return current_user_can( 'ppa_manage_all' );
    }
    
    public function get_items( $request ) {
        $args = [
            'limit'   => $request->get_param( 'per_page' ) ?: 20,
            'offset'  => ( ( $request->get_param( 'page' ) ?: 1 ) - 1 ) * 20,
            'orderby' => $request->get_param( 'orderby' ) ?: 'created_at',
            'order'   => $request->get_param( 'order' ) ?: 'DESC',
        ];
        
        $assignments = PressPrimer_Assignment_Assignment::get_all( $args );
        
        $data = array_map( function( $assignment ) {
            return $this->prepare_item_for_response( $assignment );
        }, $assignments );
        
        return rest_ensure_response( $data );
    }
    
    // ... other methods
    
    protected function prepare_item_for_response( $assignment ) {
        $data = [
            'id'                 => $assignment->id,
            'uuid'               => $assignment->uuid,
            'title'              => $assignment->title,
            'description'        => $assignment->description,
            'instructions'       => $assignment->instructions,
            'max_points'         => (float) $assignment->max_points,
            'passing_score'      => (float) $assignment->passing_score,
            'due_date'           => $assignment->due_date,
            'status'             => $assignment->status,
            'submission_count'   => (int) $assignment->submission_count,
            'graded_count'       => (int) $assignment->graded_count,
            'created_at'         => $assignment->created_at,
        ];
        
        return apply_filters( 'pressprimer_assignment_rest_response', $data, $assignment, null );
    }
}
```
