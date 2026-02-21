# Coding Conventions

## Terminology

Assignments in the back end should be called "PPA Assignment", "PPA Assignments", or "PressPrimer Assignments" in the UI to prevent confusion with assignments in other plugins. In the front end only, the terms "Assignment" and "Assignments" can be used.

## Naming Conventions

### Global Prefixes

| Type | Prefix | Example |
|------|--------|---------|
| Plugin slug | `pressprimer-assignment` | `pressprimer-assignment/pressprimer-assignment.php` |
| Text domain | `pressprimer-assignment` | `__( 'Assignment', 'pressprimer-assignment' )` |
| Database tables | `wp_ppa_` | `wp_ppa_assignments` |
| **Global PHP functions** | `pressprimer_assignment_` | `pressprimer_assignment_init()` |
| **PHP classes** | `PressPrimer_Assignment_` | `class PressPrimer_Assignment_Submission` |
| **Hooks (actions/filters)** | `pressprimer_assignment_` | `do_action( 'pressprimer_assignment_submitted' )` |
| CSS classes | `ppa-` | `.ppa-assignment-container` |
| JavaScript namespace | `PPA` | `PPA.Submission.upload()` |
| Shortcodes | `ppa_` | `[ppa_assignment]` |
| REST API namespace | `ppa/v1` | `/wp-json/ppa/v1/assignments` |
| Options | `ppa_` | `get_option( 'ppa_settings' )` |
| User meta | `ppa_` | `get_user_meta( $id, 'ppa_grading_prefs' )` |
| Post meta | `ppa_` | `get_post_meta( $id, 'ppa_assignment_id' )` |
| Transients | `ppa_` | `get_transient( 'ppa_report_1' )` |
| Capabilities | `ppa_` | `ppa_manage_all` |
| Nonces | `ppa_` | `wp_nonce_field( 'ppa_save_assignment' )` |
| AJAX actions | `ppa_` | `add_action( 'wp_ajax_ppa_save_assignment' )` |
| Block names | `pressprimer-assignment/` | `pressprimer-assignment/assignment` |

**Note:** Global namespace identifiers (functions, classes, and hooks) use the full `pressprimer_assignment_` or `PressPrimer_Assignment_` prefix to meet WordPress.org Plugin Check requirements. Internal identifiers (options, meta keys, nonces, CSS) use the shorter `ppa_` prefix.

### Shared Infrastructure (PressPrimer Suite)

These identifiers are shared with PressPrimer Quiz:

| Type | Prefix | Example | Notes |
|------|--------|---------|-------|
| Groups tables | `wp_ppq_` | `wp_ppq_groups` | Created by Quiz, used by Assignment |
| Teacher role | `pressprimer_teacher` | `pressprimer_teacher` | Shared capability role |
| Teacher capabilities | `ppq_` + `ppa_` | `ppq_manage_own`, `ppa_manage_own` | Each plugin adds its own caps |

---

### Class Naming

**Models:**
```php
class PressPrimer_Assignment_Assignment { }
class PressPrimer_Assignment_Submission { }
class PressPrimer_Assignment_Submission_File { }
class PressPrimer_Assignment_Category { }
```

**Controllers/Handlers:**
```php
class PressPrimer_Assignment_Admin_Controller { }
class PressPrimer_Assignment_AJAX_Handler { }
class PressPrimer_Assignment_REST_Controller { }
class PressPrimer_Assignment_Submission_Handler { }
```

**Services:**
```php
class PressPrimer_Assignment_Grading_Service { }
class PressPrimer_Assignment_File_Service { }
class PressPrimer_Assignment_Email_Service { }
class PressPrimer_Assignment_Statistics_Service { }
```

**Admin Pages:**
```php
class PressPrimer_Assignment_Admin_Assignments_Page { }
class PressPrimer_Assignment_Admin_Submissions_Page { }
class PressPrimer_Assignment_Admin_Settings_Page { }
```

### Function Naming

**Note:** Global functions should use the `pressprimer_assignment_` prefix. However, most functionality is implemented via class methods rather than global functions. The examples below show the naming pattern if global functions are needed.

**Getters:**
```php
pressprimer_assignment_get_assignment( $id )
pressprimer_assignment_get_submission( $id )
pressprimer_assignment_get_user_submissions( $user_id )
```

**Boolean checks:**
```php
pressprimer_assignment_is_assignment_published( $assignment_id )
pressprimer_assignment_has_user_submitted( $user_id, $assignment_id )
pressprimer_assignment_can_user_resubmit( $user_id, $assignment_id )
pressprimer_assignment_is_past_due( $assignment_id )
```

**Actions:**
```php
pressprimer_assignment_create_assignment( $data )
pressprimer_assignment_submit_files( $submission_id, $files )
pressprimer_assignment_grade_submission( $submission_id, $score, $feedback )
```

**Rendering:**
```php
pressprimer_assignment_render_assignment( $assignment_id, $args )
pressprimer_assignment_render_submission_form( $assignment_id )
pressprimer_assignment_render_grading_interface( $submission_id )
```

### Database Field Naming

- Use `snake_case` for all fields
- Use `_id` suffix for foreign keys: `user_id`, `assignment_id`, `submission_id`
- Use `_at` suffix for timestamps: `created_at`, `updated_at`, `submitted_at`, `graded_at`
- Use `_json` suffix for JSON fields: `settings_json`, `files_json`
- Use `_count` suffix for counts: `submission_count`, `file_count`
- Boolean fields: `is_late`, `allow_resubmission`, `is_graded`

### CSS Class Naming

Use BEM-lite methodology:

```css
/* Block */
.ppa-assignment { }
.ppa-submission { }
.ppa-grading { }

/* Element (double underscore) */
.ppa-assignment__header { }
.ppa-assignment__instructions { }
.ppa-assignment__upload-zone { }
.ppa-submission__file-list { }

/* Modifier (double dash) */
.ppa-assignment--past-due { }
.ppa-assignment--graded { }
.ppa-submission__status--pending { }
.ppa-submission__status--graded { }

/* State (is- prefix) */
.ppa-assignment.is-loading { }
.ppa-upload-zone.is-dragging { }
.ppa-file.is-uploading { }
.ppa-file.is-error { }
```

### JavaScript Naming

```javascript
// Namespace
const PPA = window.PPA || {};

// Modules
PPA.Assignment = { };
PPA.Submission = { };
PPA.Upload = { };
PPA.Grading = { };
PPA.DocumentViewer = { };

// Public methods use camelCase
PPA.Submission.submit();
PPA.Upload.start();
PPA.DocumentViewer.load();

// Private methods prefixed with underscore
PPA.Upload._validateFile();
PPA.Grading._calculateScore();

// Events use colon-separated names
'ppa:submission:started'
'ppa:file:uploaded'
'ppa:grading:saved'
'ppa:document:loaded'
```

---

## WordPress Coding Standards

### PHP Standards

Follow WordPress PHP Coding Standards: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/

**Key Requirements:**

1. **Indentation:** Use tabs, not spaces

2. **Brace Style:**
```php
// Correct
if ( condition ) {
    action();
}

// Not this
if ( condition )
{
    action();
}
```

3. **Space Inside Parentheses:**
```php
// Correct
if ( $condition ) {
    function_call( $arg1, $arg2 );
}

// Not this
if ($condition) {
    function_call($arg1, $arg2);
}
```

4. **Yoda Conditions:**
```php
// Correct
if ( 'published' === $status ) { }
if ( true === $is_valid ) { }

// Not this
if ( $status === 'published' ) { }
```

5. **Array Syntax:**
```php
// Short syntax preferred
$array = [
    'key1' => 'value1',
    'key2' => 'value2',
];

// Not this
$array = array(
    'key1' => 'value1',
    'key2' => 'value2',
);
```


### Documentation Standards

**File Headers:**
```php
<?php
/**
 * Submission model
 *
 * @package PressPrimer_Assignment
 * @subpackage Models
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

**Class Documentation:**
```php
/**
 * Submission model class
 *
 * Handles submission data, files, and grading.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Submission {
```

**Method Documentation:**
```php
/**
 * Get submission by ID
 *
 * @since 1.0.0
 *
 * @param int $id Submission ID.
 * @return PressPrimer_Assignment_Submission|null Submission object or null if not found.
 */
public function get( int $id ): ?PressPrimer_Assignment_Submission {
```

**Inline Comments:**
```php
// Calculate late penalty if submission is past due date.
$is_late = $submitted_at > $due_date;
$late_penalty = 0;

if ( $is_late && 'penalty' === $assignment->late_policy ) {
    // Formula: score * (penalty_percent / 100)
    $late_penalty = $raw_score * ( $assignment->late_penalty_percent / 100 );
}

$final_score = max( 0, $raw_score - $late_penalty );
```

---

## Security Standards

### Input Sanitization

Always sanitize based on expected data type:

```php
// Text fields
$title = sanitize_text_field( $_POST['title'] );

// Textarea (preserves newlines)
$feedback = sanitize_textarea_field( $_POST['feedback'] );

// HTML content (use wp_kses for specific allowed tags)
$instructions = wp_kses_post( $_POST['instructions'] );

// Integer
$assignment_id = absint( $_POST['assignment_id'] );

// Decimal
$score = floatval( $_POST['score'] );

// Email
$email = sanitize_email( $_POST['email'] );

// URL
$url = esc_url_raw( $_POST['url'] );

// Array of integers
$ids = array_map( 'absint', (array) $_POST['ids'] );

// File names
$filename = sanitize_file_name( $_FILES['file']['name'] );
```

### Output Escaping

Always escape output based on context:

```php
// HTML context
echo esc_html( $title );

// HTML attributes
echo '<input value="' . esc_attr( $value ) . '">';

// URLs
echo '<a href="' . esc_url( $url ) . '">';

// JavaScript in HTML
echo '<script>var data = ' . wp_json_encode( $data ) . ';</script>';

// Textarea content
echo '<textarea>' . esc_textarea( $content ) . '</textarea>';

// Translated strings
echo esc_html__( 'Submit', 'pressprimer-assignment' );

// With placeholders
printf(
    esc_html__( 'Submission %d of %d', 'pressprimer-assignment' ),
    absint( $current ),
    absint( $max )
);
```

### Database Queries

Always use prepared statements:

```php
global $wpdb;

// Single value
$assignment = $wpdb->get_row( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ppa_assignments WHERE id = %d",
    $assignment_id
) );

// Multiple values
$submissions = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ppa_submissions WHERE assignment_id = %d AND status = %s",
    $assignment_id,
    'submitted'
) );

// Insert
$wpdb->insert(
    $wpdb->prefix . 'ppa_submissions',
    [
        'assignment_id' => $assignment_id,
        'user_id'       => $user_id,
        'status'        => 'submitted',
    ],
    [ '%d', '%d', '%s' ]
);
```

Use the `%i` placeholder for dynamic field/column names. **Never interpolate field names directly:**

```php
// WRONG - Will be rejected by WordPress.org
$query = "SELECT * FROM {$wpdb->prefix}ppa_assignments WHERE $field = %s";
$query = "SELECT * FROM ... WHERE " . esc_sql( $field ) . " = %s";

// CORRECT - Use %i placeholder (WordPress 6.2+)
$query = $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ppa_assignments WHERE %i = %s",
    $field,
    $value
);
```

### Nonce Verification

```php
// In form
wp_nonce_field( 'ppa_save_assignment', 'ppa_nonce' );

// Verification
if ( ! isset( $_POST['ppa_nonce'] ) || 
     ! wp_verify_nonce( $_POST['ppa_nonce'], 'ppa_save_assignment' ) ) {
    wp_die( __( 'Security check failed.', 'pressprimer-assignment' ) );
}

// AJAX verification
check_ajax_referer( 'ppa_submit_assignment', 'nonce' );
```

### Capability Checks

```php
// Before any admin action
if ( ! current_user_can( 'ppa_manage_all' ) ) {
    wp_die( __( 'Permission denied.', 'pressprimer-assignment' ) );
}

// For owned content
$assignment = ppa_get_assignment( $assignment_id );
if ( ! current_user_can( 'ppa_manage_all' ) && 
     $assignment->author_id !== get_current_user_id() ) {
    wp_die( __( 'Permission denied.', 'pressprimer-assignment' ) );
}
```

---

## Internationalization Standards

### Translatable Strings

```php
// Simple string
__( 'Assignment Details', 'pressprimer-assignment' )

// Echo directly
_e( 'Submit Assignment', 'pressprimer-assignment' );

// With escape
esc_html__( 'Grade Submission', 'pressprimer-assignment' )
esc_html_e( 'Continue', 'pressprimer-assignment' );
esc_attr__( 'Enter feedback', 'pressprimer-assignment' )

// With placeholders (use sprintf)
sprintf(
    __( 'Submission %1$d of %2$d', 'pressprimer-assignment' ),
    $current,
    $total
)

// Plural forms
sprintf(
    _n(
        '%d file',
        '%d files',
        $count,
        'pressprimer-assignment'
    ),
    $count
)

// Context for translators
_x( 'Draft', 'assignment status', 'pressprimer-assignment' )
_x( 'Draft', 'submission status', 'pressprimer-assignment' )
```

### Translator Comments

```php
// translators: %s is the assignment title
printf( __( 'Submit: %s', 'pressprimer-assignment' ), $assignment->title );

// translators: 1: score points, 2: max points
printf(
    __( 'Score: %1$s / %2$s points', 'pressprimer-assignment' ),
    $score,
    $max_points
);
```

### JavaScript Translations

```php
// Register script translations
wp_set_script_translations( 
    'ppa-submission-script', 
    'pressprimer-assignment',
    PPA_PLUGIN_PATH . 'languages'
);
```

```javascript
// In JavaScript
const { __ } = wp.i18n;

const message = __( 'Submission uploaded!', 'pressprimer-assignment' );
```

---

## File Organization

### Directory Structure

```
pressprimer-assignment/
├── pressprimer-assignment.php      # Main plugin file
├── uninstall.php                   # Cleanup on uninstall
├── readme.txt                      # WordPress.org readme
│
├── includes/
│   ├── class-ppa-activator.php
│   ├── class-ppa-deactivator.php
│   ├── class-ppa-autoloader.php
│   │
│   ├── models/
│   │   ├── class-ppa-assignment.php
│   │   ├── class-ppa-submission.php
│   │   ├── class-ppa-submission-file.php
│   │   └── class-ppa-category.php
│   │
│   ├── admin/
│   │   ├── class-ppa-admin.php
│   │   ├── class-ppa-admin-assignments.php
│   │   ├── class-ppa-admin-submissions.php
│   │   ├── class-ppa-admin-reports.php
│   │   └── class-ppa-admin-settings.php
│   │
│   ├── frontend/
│   │   ├── class-ppa-frontend.php
│   │   ├── class-ppa-shortcodes.php
│   │   ├── class-ppa-assignment-renderer.php
│   │   └── class-ppa-document-viewer.php
│   │
│   ├── services/
│   │   ├── class-ppa-grading-service.php
│   │   ├── class-ppa-file-service.php
│   │   └── class-ppa-email-service.php
│   │
│   ├── integrations/
│   │   ├── class-ppa-learndash.php
│   │   ├── class-ppa-tutorlms.php
│   │   └── class-ppa-automator.php
│   │
│   ├── database/
│   │   ├── class-ppa-schema.php
│   │   └── class-ppa-migrator.php
│   │
│   └── utilities/
│       ├── class-ppa-helpers.php
│       └── class-ppa-capabilities.php
│
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   ├── frontend.css
│   │   └── themes/
│   │       ├── default.css
│   │       ├── modern.css
│   │       └── minimal.css
│   │
│   ├── js/
│   │   ├── admin.js
│   │   ├── submission.js
│   │   ├── document-viewer.js
│   │   └── grading.js
│   │
│   └── images/
│
├── blocks/
│   ├── assignment/
│   └── my-submissions/
│
├── languages/
│   └── pressprimer-assignment.pot
│
└── vendor/
```

### Autoloading

Use WordPress-style autoloading:

```php
// In main plugin file
spl_autoload_register( function( $class ) {
    // Only handle our classes
    if ( strpos( $class, 'PressPrimer_Assignment_' ) !== 0 ) {
        return;
    }

    // Convert class name to file name
    // PressPrimer_Assignment_Submission -> class-ppa-submission.php
    $class_without_prefix = substr( $class, strlen( 'PressPrimer_Assignment_' ) );
    $file = 'class-ppa-' . strtolower( str_replace( '_', '-', $class_without_prefix ) ) . '.php';

    // Check in includes directory
    $path = PPA_PLUGIN_PATH . 'includes/' . $file;
    if ( file_exists( $path ) ) {
        require_once $path;
        return;
    }

    // Check in subdirectories
    $subdirs = [ 'models', 'admin', 'frontend', 'services', 'integrations', 'database', 'utilities' ];
    foreach ( $subdirs as $subdir ) {
        $path = PPA_PLUGIN_PATH . 'includes/' . $subdir . '/' . $file;
        if ( file_exists( $path ) ) {
            require_once $path;
            return;
        }
    }
} );
```

---

## Error Handling

### WP_Error Usage

```php
function ppa_create_assignment( array $data ): int|WP_Error {
    // Validation
    if ( empty( $data['title'] ) ) {
        return new WP_Error(
            'ppa_missing_title',
            __( 'Assignment title is required.', 'pressprimer-assignment' )
        );
    }
    
    // Database operation
    $result = $wpdb->insert( /* ... */ );
    
    if ( false === $result ) {
        return new WP_Error(
            'ppa_db_error',
            __( 'Failed to create assignment.', 'pressprimer-assignment' ),
            [ 'db_error' => $wpdb->last_error ]
        );
    }
    
    return $wpdb->insert_id;
}

// Usage
$result = ppa_create_assignment( $data );
if ( is_wp_error( $result ) ) {
    // Handle error
    $error_message = $result->get_error_message();
}
```

### AJAX Error Responses

```php
function ppa_ajax_save_assignment() {
    check_ajax_referer( 'ppa_save_assignment', 'nonce' );
    
    if ( ! current_user_can( 'ppa_manage_all' ) ) {
        wp_send_json_error( [
            'code' => 'permission_denied',
            'message' => __( 'Permission denied.', 'pressprimer-assignment' )
        ], 403 );
    }
    
    $result = ppa_create_assignment( $_POST['data'] );
    
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [
            'code' => $result->get_error_code(),
            'message' => $result->get_error_message()
        ], 400 );
    }
    
    wp_send_json_success( [
        'assignment_id' => $result,
        'message' => __( 'Assignment saved.', 'pressprimer-assignment' )
    ] );
}
```
