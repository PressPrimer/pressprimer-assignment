# Security Architecture

## Guiding Principles

1. **Defense in depth** - Multiple layers of validation
2. **Least privilege** - Users can only access what they need
3. **Sanitize early, escape late** - Clean input immediately, escape on output
4. **Never trust the client** - All validation server-side
5. **Secure by default** - Restrictive defaults, explicit permission grants

---

## Authentication & Authorization

### Capability Checks

Every action must check capabilities before executing:

```php
// Admin actions (create/edit/delete assignments)
if ( ! current_user_can( 'ppa_manage_all' ) ) {
    wp_die( esc_html__( 'Permission denied.', 'pressprimer-assignment' ) );
}

// Viewing submissions (check ownership in premium)
$submission = PressPrimer_Assignment_Submission::get( $submission_id );
if ( ! current_user_can( 'ppa_manage_all' ) ) {
    // In premium, also check if user owns this submission
    if ( $submission->user_id !== get_current_user_id() ) {
        wp_die( esc_html__( 'Permission denied.', 'pressprimer-assignment' ) );
    }
}

// REST API capability checks
'permission_callback' => function( $request ) {
    return current_user_can( 'ppa_manage_all' );
}
```

### Nonce Verification

All forms and AJAX requests must use nonces:

```php
// Creating nonce in form
wp_nonce_field( 'ppa_save_assignment', 'ppa_nonce' );

// Creating nonce for AJAX
$nonce = wp_create_nonce( 'ppa_submit_assignment' );
wp_localize_script( 'ppa-submission', 'ppaData', [
    'nonce' => $nonce,
] );

// Verifying form nonce
if ( ! isset( $_POST['ppa_nonce'] ) || 
     ! wp_verify_nonce( sanitize_key( $_POST['ppa_nonce'] ), 'ppa_save_assignment' ) ) {
    wp_die( esc_html__( 'Security check failed.', 'pressprimer-assignment' ) );
}

// Verifying AJAX nonce
check_ajax_referer( 'ppa_submit_assignment', 'nonce' );
```

### Capability Definitions

```php
// v1.0 Free capabilities
$admin_caps = [
    'ppa_manage_all'      => true,  // Full assignment management
    'ppa_manage_settings' => true,  // Settings access
    'ppa_view_reports'    => true,  // Reports access
];

// Future premium capabilities
$teacher_caps = [
    'ppa_manage_own'        => true,  // Manage own assignments
    'ppa_grade_submissions' => true,  // Grade submissions
    'ppa_view_own_reports'  => true,  // View own reports
];
```

---

## File Upload Security

### Multi-Layer Validation

File uploads must pass ALL validation checks:

```php
/**
 * Validate uploaded file
 *
 * @param array $file $_FILES array element.
 * @param int   $assignment_id Assignment ID.
 * @return true|WP_Error True if valid, WP_Error if not.
 */
function ppa_validate_uploaded_file( $file, $assignment_id ) {
    $assignment = PressPrimer_Assignment_Assignment::get( $assignment_id );
    
    // 1. Check for upload errors
    if ( $file['error'] !== UPLOAD_ERR_OK ) {
        return new WP_Error( 'upload_error', __( 'File upload failed.', 'pressprimer-assignment' ) );
    }
    
    // 2. Sanitize filename
    $filename = sanitize_file_name( $file['name'] );
    
    // 3. Check extension
    $extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
    $allowed_types = json_decode( $assignment->allowed_file_types, true ) ?: ppa_get_default_file_types();
    
    if ( ! in_array( $extension, $allowed_types, true ) ) {
        return new WP_Error( 
            'invalid_extension', 
            sprintf( 
                __( 'File type .%s is not allowed.', 'pressprimer-assignment' ),
                esc_html( $extension )
            )
        );
    }
    
    // 4. Prevent double extensions
    $name_parts = explode( '.', $filename );
    if ( count( $name_parts ) > 2 ) {
        // Check if any middle part is an executable extension
        $dangerous = [ 'php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'phar', 'js', 'exe', 'sh' ];
        for ( $i = 1; $i < count( $name_parts ) - 1; $i++ ) {
            if ( in_array( strtolower( $name_parts[ $i ] ), $dangerous, true ) ) {
                return new WP_Error( 'dangerous_filename', __( 'Invalid filename.', 'pressprimer-assignment' ) );
            }
        }
    }
    
    // 5. Check MIME type using finfo
    $finfo = finfo_open( FILEINFO_MIME_TYPE );
    $mime = finfo_file( $finfo, $file['tmp_name'] );
    finfo_close( $finfo );
    
    $allowed_mimes = ppa_get_allowed_mimes();
    $expected_mime = $allowed_mimes[ $extension ] ?? null;
    
    if ( ! $expected_mime || ! ppa_mime_matches( $mime, $expected_mime ) ) {
        return new WP_Error( 
            'invalid_mime', 
            __( 'File content does not match expected type.', 'pressprimer-assignment' )
        );
    }
    
    // 6. Check file size
    $max_size = $assignment->max_file_size ?: 10485760; // 10MB default
    if ( $file['size'] > $max_size ) {
        return new WP_Error(
            'file_too_large',
            sprintf(
                __( 'File exceeds maximum size of %s.', 'pressprimer-assignment' ),
                size_format( $max_size )
            )
        );
    }
    
    return true;
}

/**
 * Check if MIME type matches expected
 * Handles variations like image/jpeg vs image/jpg
 */
function ppa_mime_matches( $actual, $expected ) {
    if ( is_array( $expected ) ) {
        return in_array( $actual, $expected, true );
    }
    return $actual === $expected;
}

/**
 * Get allowed MIME types by extension
 */
function ppa_get_allowed_mimes() {
    return [
        'pdf'  => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt'  => [ 'text/plain', 'text/x-c' ],
        'rtf'  => [ 'application/rtf', 'text/rtf' ],
        'jpg'  => [ 'image/jpeg', 'image/jpg' ],
        'jpeg' => [ 'image/jpeg', 'image/jpg' ],
        'png'  => 'image/png',
        'gif'  => 'image/gif',
    ];
}
```

### Secure File Storage

Files are stored outside the web-accessible directory:

```php
/**
 * Get secure upload directory
 */
function ppa_get_upload_dir() {
    $upload_dir = wp_upload_dir();
    $ppa_dir = $upload_dir['basedir'] . '/ppa-submissions';
    
    // Create directory if needed
    if ( ! file_exists( $ppa_dir ) ) {
        wp_mkdir_p( $ppa_dir );
        
        // Create .htaccess to deny direct access
        $htaccess = $ppa_dir . '/.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            file_put_contents( $htaccess, "Order deny,allow\nDeny from all" );
        }
        
        // Create index.php to prevent directory listing
        $index = $ppa_dir . '/index.php';
        if ( ! file_exists( $index ) ) {
            file_put_contents( $index, '<?php // Silence is golden.' );
        }
    }
    
    return $ppa_dir;
}

/**
 * Generate secure filename
 */
function ppa_generate_secure_filename( $original_name ) {
    $extension = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );
    $hash = wp_generate_password( 16, false );
    $timestamp = time();
    
    return sprintf( '%s_%d.%s', $hash, $timestamp, $extension );
}
```

### File Delivery with Permission Check

Files are served through PHP with permission verification:

```php
/**
 * Handle file download request
 */
function ppa_handle_file_download() {
    if ( ! isset( $_GET['ppa_file'] ) ) {
        return;
    }
    
    $file_id = absint( $_GET['ppa_file'] );
    
    // Verify nonce
    if ( ! isset( $_GET['nonce'] ) || 
         ! wp_verify_nonce( sanitize_key( $_GET['nonce'] ), 'ppa_download_' . $file_id ) ) {
        wp_die( esc_html__( 'Invalid download link.', 'pressprimer-assignment' ) );
    }
    
    // Get file record
    $file = PressPrimer_Assignment_Submission_File::get( $file_id );
    if ( ! $file ) {
        wp_die( esc_html__( 'File not found.', 'pressprimer-assignment' ) );
    }
    
    // Get submission to check permissions
    $submission = PressPrimer_Assignment_Submission::get( $file->submission_id );
    
    // Check access permission
    $can_access = false;
    
    if ( current_user_can( 'ppa_manage_all' ) ) {
        $can_access = true;
    } elseif ( is_user_logged_in() && $submission->user_id === get_current_user_id() ) {
        $can_access = true;
    }
    
    $can_access = apply_filters( 'pressprimer_assignment_can_access_file', $can_access, $file_id, $submission );
    
    if ( ! $can_access ) {
        wp_die( esc_html__( 'Permission denied.', 'pressprimer-assignment' ) );
    }
    
    // Verify file exists and integrity
    $file_path = ppa_get_upload_dir() . '/' . $file->file_path;
    
    if ( ! file_exists( $file_path ) ) {
        wp_die( esc_html__( 'File not found.', 'pressprimer-assignment' ) );
    }
    
    // Verify hash if paranoid mode
    if ( apply_filters( 'pressprimer_assignment_verify_file_hash', true ) ) {
        $current_hash = hash_file( 'sha256', $file_path );
        if ( $current_hash !== $file->file_hash ) {
            // Log potential tampering
            error_log( sprintf( 'PPA: File hash mismatch for file ID %d', $file_id ) );
            wp_die( esc_html__( 'File integrity check failed.', 'pressprimer-assignment' ) );
        }
    }
    
    // Serve file
    header( 'Content-Type: ' . $file->mime_type );
    header( 'Content-Disposition: attachment; filename="' . $file->original_filename . '"' );
    header( 'Content-Length: ' . $file->file_size );
    header( 'Cache-Control: private, no-cache, must-revalidate' );
    header( 'Pragma: no-cache' );
    
    readfile( $file_path );
    exit;
}
add_action( 'init', 'ppa_handle_file_download' );
```

---

## Input Sanitization

### Sanitize Immediately

```php
// CORRECT - Sanitize at point of access
$assignment_id = isset( $_POST['assignment_id'] ) ? absint( $_POST['assignment_id'] ) : 0;
$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
$instructions = isset( $_POST['instructions'] ) ? wp_kses_post( wp_unslash( $_POST['instructions'] ) ) : '';

// WRONG - Accessing then sanitizing later
$assignment_id = $_POST['assignment_id'];
// ... code ...
$assignment_id = absint( $assignment_id ); // Too late!
```

### Sanitization Functions by Type

| Data Type | Function | Notes |
|-----------|----------|-------|
| Plain text | `sanitize_text_field()` | Strips tags, encodes entities |
| Textarea | `sanitize_textarea_field()` | Preserves line breaks |
| HTML content | `wp_kses_post()` | Allows post-level HTML |
| Custom HTML | `wp_kses( $data, $allowed )` | Explicit allowed tags |
| Integer | `absint()` or `intval()` | Forces integer |
| Decimal | `floatval()` | Forces float |
| Email | `sanitize_email()` | Validates email format |
| URL | `esc_url_raw()` | For database storage |
| File name | `sanitize_file_name()` | Safe filename |
| Key/slug | `sanitize_key()` | Lowercase alphanumeric |
| Array of int | `array_map( 'absint', $arr )` | Map over array |
| Array of text | `array_map( 'sanitize_text_field', $arr )` | Map over array |

### Complex Data Sanitization

```php
/**
 * Sanitize assignment data from form
 */
function ppa_sanitize_assignment_data( $raw ) {
    $raw = wp_unslash( $raw );
    
    return [
        'title'                => isset( $raw['title'] ) ? sanitize_text_field( $raw['title'] ) : '',
        'description'          => isset( $raw['description'] ) ? wp_kses_post( $raw['description'] ) : '',
        'instructions'         => isset( $raw['instructions'] ) ? wp_kses_post( $raw['instructions'] ) : '',
        'grading_guidelines'   => isset( $raw['grading_guidelines'] ) ? wp_kses_post( $raw['grading_guidelines'] ) : '',
        'max_points'           => isset( $raw['max_points'] ) ? floatval( $raw['max_points'] ) : 100,
        'passing_score'        => isset( $raw['passing_score'] ) ? floatval( $raw['passing_score'] ) : 60,
        'due_date'             => isset( $raw['due_date'] ) ? sanitize_text_field( $raw['due_date'] ) : null,
        'late_policy'          => isset( $raw['late_policy'] ) && in_array( $raw['late_policy'], [ 'accept', 'reject', 'penalty' ], true ) 
                                  ? $raw['late_policy'] : 'accept',
        'late_penalty_percent' => isset( $raw['late_penalty_percent'] ) ? min( 100, max( 0, floatval( $raw['late_penalty_percent'] ) ) ) : 0,
        'allow_resubmission'   => isset( $raw['allow_resubmission'] ) ? 1 : 0,
        'max_resubmissions'    => isset( $raw['max_resubmissions'] ) ? min( 10, absint( $raw['max_resubmissions'] ) ) : 1,
        'allowed_file_types'   => isset( $raw['allowed_file_types'] ) && is_array( $raw['allowed_file_types'] )
                                  ? wp_json_encode( array_map( 'sanitize_key', $raw['allowed_file_types'] ) )
                                  : null,
        'max_file_size'        => isset( $raw['max_file_size'] ) ? absint( $raw['max_file_size'] ) : 10485760,
        'max_files'            => isset( $raw['max_files'] ) ? min( 10, absint( $raw['max_files'] ) ) : 5,
        'status'               => isset( $raw['status'] ) && in_array( $raw['status'], [ 'draft', 'published', 'archived' ], true )
                                  ? $raw['status'] : 'draft',
    ];
}
```

---

## Output Escaping

### Escape Based on Context

```php
// HTML content
echo esc_html( $title );

// HTML attributes
echo '<input value="' . esc_attr( $value ) . '">';

// URLs
echo '<a href="' . esc_url( $url ) . '">';

// JavaScript data
wp_localize_script( 'ppa-script', 'ppaData', [
    'title' => $title,  // Automatically JSON-encoded
] );

// Direct JSON in HTML
echo '<script>var data = ' . wp_json_encode( $data ) . ';</script>';

// Translated strings
echo esc_html__( 'Submit', 'pressprimer-assignment' );

// With placeholders
printf(
    esc_html__( 'Score: %1$s / %2$s', 'pressprimer-assignment' ),
    esc_html( $score ),
    esc_html( $max )
);
```

### Custom wp_kses for Complex Output

```php
/**
 * Get allowed HTML for assignment display
 */
function ppa_get_assignment_allowed_html() {
    $allowed = wp_kses_allowed_html( 'post' );
    
    // Add form elements for submission interface
    $allowed['form'] = [
        'action' => true,
        'method' => true,
        'class'  => true,
        'id'     => true,
    ];
    $allowed['input'] = [
        'type'        => true,
        'name'        => true,
        'value'       => true,
        'class'       => true,
        'id'          => true,
        'placeholder' => true,
        'required'    => true,
        'disabled'    => true,
        'readonly'    => true,
    ];
    $allowed['button'] = [
        'type'     => true,
        'class'    => true,
        'id'       => true,
        'disabled' => true,
    ];
    $allowed['label'] = [
        'for'   => true,
        'class' => true,
    ];
    
    // Add data attributes
    foreach ( $allowed as $tag => $attrs ) {
        $allowed[ $tag ]['data-*'] = true;
    }
    
    return $allowed;
}

// Usage
$output = ppa_render_submission_form( $assignment_id );
echo wp_kses( $output, ppa_get_assignment_allowed_html() );
```

---

## Database Security

### Prepared Statements

```php
global $wpdb;

// Single value with placeholder
$assignment = $wpdb->get_row( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ppa_assignments WHERE id = %d",
    $assignment_id
) );

// Multiple values
$submissions = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ppa_submissions 
     WHERE assignment_id = %d AND status = %s",
    $assignment_id,
    'submitted'
) );

// Insert with format specifiers
$wpdb->insert(
    $wpdb->prefix . 'ppa_submissions',
    [
        'assignment_id' => $assignment_id,
        'user_id'       => $user_id,
        'status'        => 'submitted',
        'submitted_at'  => current_time( 'mysql' ),
    ],
    [ '%d', '%d', '%s', '%s' ]
);

// Update
$wpdb->update(
    $wpdb->prefix . 'ppa_submissions',
    [ 'score' => $score, 'feedback' => $feedback ],
    [ 'id' => $submission_id ],
    [ '%f', '%s' ],
    [ '%d' ]
);
```

### Dynamic Field Names

```php
// CORRECT - Use %i placeholder for field names
$allowed_fields = [ 'id', 'title', 'status', 'created_at' ];

if ( ! in_array( $orderby, $allowed_fields, true ) ) {
    $orderby = 'created_at';
}

$query = $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ppa_assignments WHERE status = %s ORDER BY %i DESC",
    'published',
    $orderby
);

// WRONG - Never interpolate field names
$query = "SELECT * FROM ... ORDER BY $orderby DESC";  // Rejected!
$query = "SELECT * FROM ... ORDER BY " . esc_sql( $orderby ) . " DESC";  // Also rejected!
```

### ORDER Direction

```php
// CORRECT - Hardcode in conditional branches
$is_asc = 'ASC' === strtoupper( $order );

if ( $is_asc ) {
    $query = $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ppa_assignments ORDER BY %i ASC",
        $orderby
    );
} else {
    $query = $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ppa_assignments ORDER BY %i DESC",
        $orderby
    );
}

// WRONG - Never interpolate direction
$dir = $is_asc ? 'ASC' : 'DESC';
$query = "SELECT * FROM ... ORDER BY ... $dir";  // Rejected!
```

---

## Rate Limiting

### Submission Rate Limiting

```php
/**
 * Check if user can submit (rate limiting)
 */
function ppa_can_user_submit( $user_id, $assignment_id ) {
    // Allow filter to override
    $can_submit = apply_filters( 'pressprimer_assignment_can_submit', true, $user_id, $assignment_id );
    if ( ! $can_submit ) {
        return false;
    }
    
    // Rate limit: max 5 submissions per minute per user
    $transient_key = 'ppa_submit_rate_' . $user_id;
    $submit_count = get_transient( $transient_key );
    
    if ( false === $submit_count ) {
        set_transient( $transient_key, 1, MINUTE_IN_SECONDS );
        return true;
    }
    
    if ( $submit_count >= 5 ) {
        return false;
    }
    
    set_transient( $transient_key, $submit_count + 1, MINUTE_IN_SECONDS );
    return true;
}
```

---

## Error Handling

### Secure Error Messages

```php
// Don't expose internal details
// WRONG
return new WP_Error( 'db_error', $wpdb->last_error );

// CORRECT
if ( false === $result ) {
    // Log detailed error for debugging
    error_log( sprintf( 'PPA: Database error saving assignment: %s', $wpdb->last_error ) );
    
    // Return generic message to user
    return new WP_Error(
        'save_failed',
        __( 'Failed to save assignment. Please try again.', 'pressprimer-assignment' )
    );
}
```

---

## AJAX Security

### Complete AJAX Handler Pattern

```php
/**
 * Handle grade submission AJAX request
 */
function ppa_ajax_grade_submission() {
    // 1. Verify nonce
    check_ajax_referer( 'ppa_grade_submission', 'nonce' );
    
    // 2. Check capabilities
    if ( ! current_user_can( 'ppa_manage_all' ) ) {
        wp_send_json_error( [
            'code'    => 'permission_denied',
            'message' => __( 'Permission denied.', 'pressprimer-assignment' ),
        ], 403 );
    }
    
    // 3. Validate and sanitize input
    $submission_id = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;
    $score = isset( $_POST['score'] ) ? floatval( $_POST['score'] ) : 0;
    $feedback = isset( $_POST['feedback'] ) ? wp_kses_post( wp_unslash( $_POST['feedback'] ) ) : '';
    
    if ( ! $submission_id ) {
        wp_send_json_error( [
            'code'    => 'invalid_submission',
            'message' => __( 'Invalid submission.', 'pressprimer-assignment' ),
        ], 400 );
    }
    
    // 4. Verify submission exists
    $submission = PressPrimer_Assignment_Submission::get( $submission_id );
    if ( ! $submission ) {
        wp_send_json_error( [
            'code'    => 'not_found',
            'message' => __( 'Submission not found.', 'pressprimer-assignment' ),
        ], 404 );
    }
    
    // 5. Validate score range
    $assignment = PressPrimer_Assignment_Assignment::get( $submission->assignment_id );
    if ( $score < 0 || $score > $assignment->max_points ) {
        wp_send_json_error( [
            'code'    => 'invalid_score',
            'message' => sprintf(
                __( 'Score must be between 0 and %s.', 'pressprimer-assignment' ),
                $assignment->max_points
            ),
        ], 400 );
    }
    
    // 6. Perform action
    $result = PressPrimer_Assignment_Grading_Service::grade( $submission_id, $score, $feedback );
    
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [
            'code'    => $result->get_error_code(),
            'message' => $result->get_error_message(),
        ], 400 );
    }
    
    // 7. Return success
    wp_send_json_success( [
        'message' => __( 'Submission graded successfully.', 'pressprimer-assignment' ),
        'data'    => [
            'final_score' => $result['final_score'],
            'passed'      => $result['passed'],
        ],
    ] );
}
add_action( 'wp_ajax_ppa_grade_submission', 'ppa_ajax_grade_submission' );
```
