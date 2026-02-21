# Actions and Filters Reference

This document lists all actions and filters in PressPrimer Assignment. Hook names use the `pressprimer_assignment_` prefix for WordPress.org compliance.

---

## Actions

### Assignment Lifecycle

```php
/**
 * Fires after an assignment is created.
 *
 * @since 1.0.0
 * @param int $assignment_id The assignment ID.
 */
do_action( 'pressprimer_assignment_created', $assignment_id );

/**
 * Fires after an assignment is updated.
 *
 * @since 1.0.0
 * @param int $assignment_id The assignment ID.
 */
do_action( 'pressprimer_assignment_updated', $assignment_id );

/**
 * Fires before an assignment is deleted.
 *
 * @since 1.0.0
 * @param int $assignment_id The assignment ID.
 */
do_action( 'pressprimer_assignment_before_delete', $assignment_id );

/**
 * Fires after an assignment is deleted.
 *
 * @since 1.0.0
 * @param int $assignment_id The assignment ID.
 */
do_action( 'pressprimer_assignment_deleted', $assignment_id );

/**
 * Fires when an assignment status changes to published.
 *
 * @since 1.0.0
 * @param int $assignment_id The assignment ID.
 */
do_action( 'pressprimer_assignment_published', $assignment_id );

/**
 * Fires when an assignment status changes to archived.
 *
 * @since 1.0.0
 * @param int $assignment_id The assignment ID.
 */
do_action( 'pressprimer_assignment_archived', $assignment_id );
```

### Submission Lifecycle

```php
/**
 * Fires when a new submission is created (student starts working).
 *
 * @since 1.0.0
 * @param int $submission_id The submission ID.
 */
do_action( 'pressprimer_assignment_submission_created', $submission_id );

/**
 * Fires when a submission is submitted by the student.
 *
 * @since 1.0.0
 * @param int $submission_id The submission ID.
 */
do_action( 'pressprimer_assignment_submission_submitted', $submission_id );

/**
 * Fires when grading begins on a submission.
 *
 * @since 1.0.0
 * @param int $submission_id The submission ID.
 * @param int $grader_id     The user ID of the grader.
 */
do_action( 'pressprimer_assignment_submission_grading_started', $submission_id, $grader_id );

/**
 * Fires when a submission is graded.
 *
 * @since 1.0.0
 * @param int   $submission_id The submission ID.
 * @param float $score         The raw score awarded.
 * @param float $final_score   The final score after any penalties.
 */
do_action( 'pressprimer_assignment_submission_graded', $submission_id, $score, $final_score );

/**
 * Fires when a submission is returned to the student.
 *
 * @since 1.0.0
 * @param int $submission_id The submission ID.
 */
do_action( 'pressprimer_assignment_submission_returned', $submission_id );

/**
 * Fires when a student passes an assignment.
 *
 * @since 1.0.0
 * @param int   $submission_id The submission ID.
 * @param float $final_score   The final score.
 */
do_action( 'pressprimer_assignment_submission_passed', $submission_id, $final_score );

/**
 * Fires when a student fails an assignment.
 *
 * @since 1.0.0
 * @param int   $submission_id The submission ID.
 * @param float $final_score   The final score.
 */
do_action( 'pressprimer_assignment_submission_failed', $submission_id, $final_score );
```

### File Lifecycle

```php
/**
 * Fires after a file is successfully uploaded to a submission.
 *
 * @since 1.0.0
 * @param int $file_id       The file ID.
 * @param int $submission_id The submission ID.
 */
do_action( 'pressprimer_assignment_file_uploaded', $file_id, $submission_id );

/**
 * Fires before a file is deleted.
 *
 * @since 1.0.0
 * @param int $file_id The file ID.
 */
do_action( 'pressprimer_assignment_file_before_delete', $file_id );

/**
 * Fires after a file is deleted.
 *
 * @since 1.0.0
 * @param int $file_id       The file ID.
 * @param int $submission_id The submission ID.
 */
do_action( 'pressprimer_assignment_file_deleted', $file_id, $submission_id );
```

### Grading Actions

```php
/**
 * Fires before grading a submission.
 *
 * @since 1.0.0
 * @param int $submission_id The submission ID.
 */
do_action( 'pressprimer_assignment_before_grade', $submission_id );

/**
 * Fires after grading a submission.
 *
 * @since 1.0.0
 * @param int    $submission_id The submission ID.
 * @param float  $score         The raw score.
 * @param string $feedback      The feedback text.
 */
do_action( 'pressprimer_assignment_after_grade', $submission_id, $score, $feedback );

/**
 * Fires when feedback is updated on a graded submission.
 *
 * @since 1.0.0
 * @param int    $submission_id The submission ID.
 * @param string $feedback      The new feedback text.
 */
do_action( 'pressprimer_assignment_feedback_updated', $submission_id, $feedback );
```

### Display Actions

```php
/**
 * Fires before rendering an assignment on the frontend.
 *
 * @since 1.0.0
 * @param int $assignment_id The assignment ID.
 */
do_action( 'pressprimer_assignment_before_render', $assignment_id );

/**
 * Fires after rendering an assignment on the frontend.
 *
 * @since 1.0.0
 * @param int $assignment_id The assignment ID.
 */
do_action( 'pressprimer_assignment_after_render', $assignment_id );

/**
 * Fires before the submission form is rendered.
 *
 * @since 1.0.0
 * @param int $assignment_id The assignment ID.
 */
do_action( 'pressprimer_assignment_before_submission_form', $assignment_id );

/**
 * Fires after the submission form is rendered.
 *
 * @since 1.0.0
 * @param int $assignment_id The assignment ID.
 */
do_action( 'pressprimer_assignment_after_submission_form', $assignment_id );

/**
 * Fires before the grading interface is rendered.
 *
 * @since 1.0.0
 * @param int $submission_id The submission ID.
 */
do_action( 'pressprimer_assignment_before_grading_interface', $submission_id );

/**
 * Fires after the grading interface is rendered.
 *
 * @since 1.0.0
 * @param int $submission_id The submission ID.
 */
do_action( 'pressprimer_assignment_after_grading_interface', $submission_id );

/**
 * Fires in the grading form, after the feedback field.
 * Useful for addons adding rubric UI or other grading tools.
 *
 * @since 1.0.0
 * @param int $submission_id The submission ID.
 */
do_action( 'pressprimer_assignment_grading_form_after_feedback', $submission_id );
```

### Admin Actions

```php
/**
 * Fires on the admin assignments list page.
 *
 * @since 1.0.0
 */
do_action( 'pressprimer_assignment_admin_assignments_page' );

/**
 * Fires on the admin submissions list page.
 *
 * @since 1.0.0
 * @param int $assignment_id The assignment ID (if filtered).
 */
do_action( 'pressprimer_assignment_admin_submissions_page', $assignment_id );

/**
 * Fires on the admin settings page.
 *
 * @since 1.0.0
 * @param string $tab The current settings tab.
 */
do_action( 'pressprimer_assignment_admin_settings_page', $tab );

/**
 * Fires after settings are saved.
 *
 * @since 1.0.0
 * @param array $settings The saved settings.
 */
do_action( 'pressprimer_assignment_settings_saved', $settings );
```

### LMS Integration Actions

```php
/**
 * Fires when an assignment completion triggers LMS completion.
 *
 * @since 1.0.0
 * @param int    $submission_id The submission ID.
 * @param int    $user_id       The user ID.
 * @param string $lms           The LMS name (learndash, tutorlms).
 */
do_action( 'pressprimer_assignment_lms_completion_triggered', $submission_id, $user_id, $lms );
```

---

## Filters

### Assignment Data Filters

```php
/**
 * Filter assignment data before saving.
 *
 * @since 1.0.0
 * @param array $data          The assignment data.
 * @param int   $assignment_id The assignment ID (0 for new).
 */
$data = apply_filters( 'pressprimer_assignment_data', $data, $assignment_id );

/**
 * Filter assignment settings.
 *
 * @since 1.0.0
 * @param array $settings      The assignment settings.
 * @param int   $assignment_id The assignment ID.
 */
$settings = apply_filters( 'pressprimer_assignment_settings', $settings, $assignment_id );

/**
 * Filter allowed file types for an assignment.
 *
 * @since 1.0.0
 * @param array $types         Array of allowed extensions.
 * @param int   $assignment_id The assignment ID.
 */
$types = apply_filters( 'pressprimer_assignment_allowed_file_types', $types, $assignment_id );

/**
 * Filter maximum file size for an assignment.
 *
 * @since 1.0.0
 * @param int $size           Max size in bytes.
 * @param int $assignment_id  The assignment ID.
 */
$size = apply_filters( 'pressprimer_assignment_max_file_size', $size, $assignment_id );

/**
 * Filter maximum number of files per submission.
 *
 * @since 1.0.0
 * @param int $max_files      Maximum files allowed.
 * @param int $assignment_id  The assignment ID.
 */
$max_files = apply_filters( 'pressprimer_assignment_max_files', $max_files, $assignment_id );
```

### Submission Filters

```php
/**
 * Filter submission data before saving.
 *
 * @since 1.0.0
 * @param array $data          The submission data.
 * @param int   $submission_id The submission ID.
 */
$data = apply_filters( 'pressprimer_assignment_submission_data', $data, $submission_id );

/**
 * Filter whether a user can submit to an assignment.
 *
 * @since 1.0.0
 * @param bool $can_submit     Whether user can submit.
 * @param int  $user_id        The user ID.
 * @param int  $assignment_id  The assignment ID.
 */
$can_submit = apply_filters( 'pressprimer_assignment_can_submit', $can_submit, $user_id, $assignment_id );

/**
 * Filter whether a user can resubmit to an assignment.
 *
 * @since 1.0.0
 * @param bool $can_resubmit   Whether user can resubmit.
 * @param int  $user_id        The user ID.
 * @param int  $assignment_id  The assignment ID.
 * @param int  $current_count  Current submission count.
 */
$can_resubmit = apply_filters( 'pressprimer_assignment_can_resubmit', $can_resubmit, $user_id, $assignment_id, $current_count );

/**
 * Filter whether a submission is considered late.
 *
 * @since 1.0.0
 * @param bool     $is_late       Whether submission is late.
 * @param int      $submission_id The submission ID.
 * @param DateTime $submitted_at  Submission timestamp.
 * @param DateTime $due_date      Due date.
 */
$is_late = apply_filters( 'pressprimer_assignment_is_late', $is_late, $submission_id, $submitted_at, $due_date );
```

### Grading Filters

```php
/**
 * Filter the late penalty calculation.
 *
 * @since 1.0.0
 * @param float $penalty       The calculated penalty amount.
 * @param int   $submission_id The submission ID.
 * @param float $raw_score     The raw score before penalty.
 */
$penalty = apply_filters( 'pressprimer_assignment_calculate_late_penalty', $penalty, $submission_id, $raw_score );

/**
 * Filter the final score after all calculations.
 *
 * @since 1.0.0
 * @param float $final_score   The final score.
 * @param int   $submission_id The submission ID.
 * @param float $raw_score     The raw score.
 * @param float $penalty       Any penalty applied.
 */
$final_score = apply_filters( 'pressprimer_assignment_final_score', $final_score, $submission_id, $raw_score, $penalty );

/**
 * Filter whether a submission is considered passed.
 *
 * @since 1.0.0
 * @param bool  $passed        Whether submission passed.
 * @param int   $submission_id The submission ID.
 * @param float $final_score   The final score.
 * @param float $passing_score The passing threshold.
 */
$passed = apply_filters( 'pressprimer_assignment_passed', $passed, $submission_id, $final_score, $passing_score );

/**
 * Filter grading interface data for addons.
 *
 * @since 1.0.0
 * @param array $data          Grading interface data.
 * @param int   $submission_id The submission ID.
 */
$data = apply_filters( 'pressprimer_assignment_grading_interface_data', $data, $submission_id );
```

### File Filters

```php
/**
 * Filter whether a user can access a file.
 *
 * @since 1.0.0
 * @param bool   $can_access    Whether user can access.
 * @param int    $file_id       The file ID.
 * @param object $submission    The submission object.
 */
$can_access = apply_filters( 'pressprimer_assignment_can_access_file', $can_access, $file_id, $submission );

/**
 * Filter whether to verify file hash on download.
 *
 * @since 1.0.0
 * @param bool $verify Whether to verify hash.
 */
$verify = apply_filters( 'pressprimer_assignment_verify_file_hash', true );

/**
 * Filter the secure upload directory path.
 *
 * @since 1.0.0
 * @param string $path The upload directory path.
 */
$path = apply_filters( 'pressprimer_assignment_upload_dir', $path );
```

### Display Filters

```php
/**
 * Filter the rendered assignment output.
 *
 * @since 1.0.0
 * @param string $html          The rendered HTML.
 * @param int    $assignment_id The assignment ID.
 * @param array  $args          Render arguments.
 */
$html = apply_filters( 'pressprimer_assignment_render_output', $html, $assignment_id, $args );

/**
 * Filter the submission form output.
 *
 * @since 1.0.0
 * @param string $html          The form HTML.
 * @param int    $assignment_id The assignment ID.
 */
$html = apply_filters( 'pressprimer_assignment_submission_form', $html, $assignment_id );

/**
 * Filter the grading interface output.
 *
 * @since 1.0.0
 * @param string $html          The interface HTML.
 * @param int    $submission_id The submission ID.
 */
$html = apply_filters( 'pressprimer_assignment_grading_interface', $html, $submission_id );

/**
 * Filter the document viewer output.
 *
 * @since 1.0.0
 * @param string $html    The viewer HTML.
 * @param int    $file_id The file ID.
 * @param string $type    The file type (pdf, docx, image).
 */
$html = apply_filters( 'pressprimer_assignment_document_viewer', $html, $file_id, $type );

/**
 * Filter the CSS class for assignment container.
 *
 * @since 1.0.0
 * @param array $classes       Array of CSS classes.
 * @param int   $assignment_id The assignment ID.
 */
$classes = apply_filters( 'pressprimer_assignment_container_classes', $classes, $assignment_id );

/**
 * Filter the theme for an assignment.
 *
 * @since 1.0.0
 * @param string $theme         The theme slug.
 * @param int    $assignment_id The assignment ID.
 */
$theme = apply_filters( 'pressprimer_assignment_theme', $theme, $assignment_id );
```

### Report Filters

```php
/**
 * Filter report columns.
 *
 * @since 1.0.0
 * @param array  $columns   The column definitions.
 * @param string $report_id The report ID.
 */
$columns = apply_filters( 'pressprimer_assignment_report_columns', $columns, $report_id );

/**
 * Filter report data.
 *
 * @since 1.0.0
 * @param array  $data      The report data.
 * @param string $report_id The report ID.
 * @param array  $args      Query arguments.
 */
$data = apply_filters( 'pressprimer_assignment_report_data', $data, $report_id, $args );

/**
 * Filter export data before generating CSV.
 *
 * @since 1.0.0
 * @param array  $data      The export data.
 * @param string $report_id The report ID.
 */
$data = apply_filters( 'pressprimer_assignment_export_data', $data, $report_id );
```

### Capability Filters

```php
/**
 * Filter capabilities for the pressprimer_teacher role.
 *
 * @since 1.0.0
 * @param array $caps The capabilities array.
 */
$caps = apply_filters( 'pressprimer_assignment_teacher_capabilities', $caps );

/**
 * Filter whether a user can manage a specific assignment.
 *
 * @since 1.0.0
 * @param bool $can_manage     Whether user can manage.
 * @param int  $assignment_id  The assignment ID.
 * @param int  $user_id        The user ID.
 */
$can_manage = apply_filters( 'pressprimer_assignment_user_can_manage', $can_manage, $assignment_id, $user_id );
```

### REST API Filters

```php
/**
 * Filter REST API response for assignment.
 *
 * @since 1.0.0
 * @param array           $data    The response data.
 * @param object          $assignment The assignment object.
 * @param WP_REST_Request $request The request object.
 */
$data = apply_filters( 'pressprimer_assignment_rest_response', $data, $assignment, $request );

/**
 * Filter REST API response for submission.
 *
 * @since 1.0.0
 * @param array           $data    The response data.
 * @param object          $submission The submission object.
 * @param WP_REST_Request $request The request object.
 */
$data = apply_filters( 'pressprimer_assignment_rest_submission_response', $data, $submission, $request );
```

---

## Usage Examples

### Extending Grading with Rubrics (Educator Addon)

```php
// Add rubric score to final calculation
add_filter( 'pressprimer_assignment_final_score', function( $final_score, $submission_id, $raw_score, $penalty ) {
    $rubric_score = ppa_educator_get_rubric_score( $submission_id );
    if ( $rubric_score !== null ) {
        return $rubric_score - $penalty;
    }
    return $final_score;
}, 10, 4 );

// Add rubric UI to grading form
add_action( 'pressprimer_assignment_grading_form_after_feedback', function( $submission_id ) {
    ppa_educator_render_rubric_interface( $submission_id );
} );
```

### Adding AI Feedback (Educator Addon)

```php
// Inject AI feedback button into grading interface
add_action( 'pressprimer_assignment_before_grading_interface', function( $submission_id ) {
    echo '<button class="ppa-ai-feedback-btn">' . esc_html__( 'Generate AI Feedback', 'pressprimer-assignment-educator' ) . '</button>';
} );
```

### Custom File Type Support

```php
// Allow spreadsheet uploads for specific assignment
add_filter( 'pressprimer_assignment_allowed_file_types', function( $types, $assignment_id ) {
    if ( get_post_meta( $assignment_id, '_allow_spreadsheets', true ) ) {
        $types[] = 'xlsx';
        $types[] = 'csv';
    }
    return $types;
}, 10, 2 );
```

### Integration with External Systems

```php
// Notify external system when assignment is graded
add_action( 'pressprimer_assignment_submission_graded', function( $submission_id, $score, $final_score ) {
    $submission = PressPrimer_Assignment_Submission::get( $submission_id );
    $user = get_userdata( $submission->user_id );
    
    wp_remote_post( 'https://api.example.com/grades', [
        'body' => wp_json_encode( [
            'student_email' => $user->user_email,
            'assignment_id' => $submission->assignment_id,
            'score'         => $final_score,
        ] ),
        'headers' => [ 'Content-Type' => 'application/json' ],
    ] );
}, 10, 3 );
```
