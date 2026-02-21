# Database Architecture

## Design Philosophy

PressPrimer Assignment uses custom database tables rather than WordPress Custom Post Types. This decision enables:

1. **Performance at scale** - Optimized queries for 10,000+ submissions
2. **Complex queries** - Filtering, reporting, analytics
3. **Precise data structures** - Exact fields we need, no metadata overhead
4. **Secure file handling** - Separate file tracking with integrity checks
5. **Integration with Quiz** - Shared Groups tables for consistent classroom management

## Table Prefix

All Assignment-specific tables use the prefix: `wp_ppa_`

**Exception:** Groups tables use `wp_ppq_` prefix (shared with PressPrimer Quiz).

Full table name examples:
- `wp_ppa_assignments` (Assignment-specific)
- `wp_ppq_groups` (Shared with Quiz)

## Schema Overview

### Assignment-Specific Tables
```
wp_ppa_assignments        # Assignment definitions
wp_ppa_submissions        # Student submissions
wp_ppa_submission_files   # Files within submissions
wp_ppa_categories         # Categories and tags for assignments
wp_ppa_assignment_tax     # Assignment-to-category relationships
```

### Shared Tables (from PressPrimer Quiz)
```
wp_ppq_groups             # User groups (created by Quiz)
wp_ppq_group_members      # Group membership (created by Quiz)
```

**Note:** The Groups tables are created and managed by PressPrimer Quiz. If only Assignment is installed without Quiz, these tables won't exist and Groups functionality will be unavailable until Quiz is installed OR the Educator addon creates them.

---

## Table Definitions

### wp_ppa_assignments

Primary assignment storage.

```sql
CREATE TABLE wp_ppa_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    instructions LONGTEXT DEFAULT NULL,
    grading_guidelines TEXT DEFAULT NULL,
    
    -- Scoring
    max_points DECIMAL(10,2) NOT NULL DEFAULT 100.00,
    passing_score DECIMAL(10,2) NOT NULL DEFAULT 60.00,
    
    -- Due Date & Late Policy
    due_date DATETIME DEFAULT NULL,
    due_date_timezone VARCHAR(50) DEFAULT 'UTC',
    late_policy ENUM('accept', 'reject', 'penalty') NOT NULL DEFAULT 'accept',
    late_penalty_percent DECIMAL(5,2) DEFAULT NULL,
    
    -- Resubmission
    allow_resubmission TINYINT(1) NOT NULL DEFAULT 0,
    max_resubmissions INT UNSIGNED DEFAULT 1,
    
    -- File Settings
    allowed_file_types TEXT DEFAULT NULL,
    max_file_size INT UNSIGNED NOT NULL DEFAULT 10485760,
    max_files INT UNSIGNED NOT NULL DEFAULT 5,
    
    -- Status & Ownership
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    author_id BIGINT UNSIGNED NOT NULL,
    
    -- Statistics (cached)
    submission_count INT UNSIGNED NOT NULL DEFAULT 0,
    graded_count INT UNSIGNED NOT NULL DEFAULT 0,
    
    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY uuid (uuid),
    KEY author_id (author_id),
    KEY status (status),
    KEY due_date (due_date),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Field Notes:**
- `uuid` - For external references (LMS integration, exports)
- `instructions` - Detailed assignment instructions (HTML allowed)
- `grading_guidelines` - Text description of how submissions will be evaluated (visible to students)
- `allowed_file_types` - JSON array like `["pdf","docx","txt"]`. NULL means all allowed types.
- `max_file_size` - In bytes (default 10MB)
- `late_policy` - 'accept' = no penalty, 'reject' = don't accept, 'penalty' = accept with score reduction
- `submission_count` / `graded_count` - Cached counts updated via code

**allowed_file_types JSON Structure:**
```json
["pdf", "docx", "txt", "rtf", "jpg", "png"]
```

---

### wp_ppa_submissions

Student submission records.

```sql
CREATE TABLE wp_ppa_submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    assignment_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    submission_number INT UNSIGNED NOT NULL DEFAULT 1,
    
    -- Status
    status ENUM('draft', 'submitted', 'grading', 'graded', 'returned') NOT NULL DEFAULT 'draft',
    
    -- Timing
    submitted_at DATETIME DEFAULT NULL,
    graded_at DATETIME DEFAULT NULL,
    returned_at DATETIME DEFAULT NULL,
    
    -- Grading
    grader_id BIGINT UNSIGNED DEFAULT NULL,
    score DECIMAL(10,2) DEFAULT NULL,
    feedback LONGTEXT DEFAULT NULL,
    
    -- Late Handling
    is_late TINYINT(1) NOT NULL DEFAULT 0,
    late_penalty_applied DECIMAL(5,2) DEFAULT NULL,
    final_score DECIMAL(10,2) DEFAULT NULL,
    passed TINYINT(1) DEFAULT NULL,
    
    -- File Stats (cached)
    file_count INT UNSIGNED NOT NULL DEFAULT 0,
    total_file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    
    -- Metadata
    meta_json TEXT DEFAULT NULL,
    
    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY uuid (uuid),
    UNIQUE KEY assignment_user_number (assignment_id, user_id, submission_number),
    KEY assignment_id (assignment_id),
    KEY user_id (user_id),
    KEY status (status),
    KEY submitted_at (submitted_at),
    KEY graded_at (graded_at),
    KEY grader_id (grader_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Field Notes:**
- `submission_number` - For resubmissions (1, 2, 3, etc.)
- `status` workflow: draft → submitted → grading → graded → returned
- `score` - Raw score before late penalty
- `final_score` - Score after late penalty applied
- `passed` - Based on final_score >= assignment.passing_score
- `meta_json` - Device info, user agent, etc.

**Status Workflow:**
1. `draft` - Student has started but not submitted
2. `submitted` - Student has submitted, awaiting grading
3. `grading` - Grader has opened the submission (optional, for tracking)
4. `graded` - Grader has assigned a score and feedback
5. `returned` - Feedback has been released to student

---

### wp_ppa_submission_files

Files within a submission.

```sql
CREATE TABLE wp_ppa_submission_files (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id BIGINT UNSIGNED NOT NULL,
    
    -- File Info
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_path TEXT NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_extension VARCHAR(20) NOT NULL,
    
    -- Integrity
    file_hash CHAR(64) NOT NULL,
    
    -- Ordering
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    
    -- Timestamps
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY submission_id (submission_id),
    KEY file_hash (file_hash),
    KEY uploaded_at (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Field Notes:**
- `original_filename` - What the user uploaded (e.g., "My Essay.pdf")
- `stored_filename` - Secure filename on disk (e.g., "abc123_1706789012.pdf")
- `file_path` - Relative path from uploads directory
- `file_hash` - SHA-256 hash for integrity verification
- `sort_order` - For maintaining upload order

**File Storage Location:**
Files are stored in `wp-content/uploads/ppa-submissions/{year}/{month}/` with:
- `.htaccess` protection to prevent direct access
- PHP-based file serving that checks permissions

---

### wp_ppa_categories

Categories and tags for assignments (mirrors Quiz structure).

```sql
CREATE TABLE wp_ppa_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    parent_id BIGINT UNSIGNED DEFAULT NULL,
    taxonomy ENUM('category', 'tag') NOT NULL DEFAULT 'category',
    assignment_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slug_taxonomy (slug, taxonomy),
    KEY parent_id (parent_id),
    KEY taxonomy (taxonomy),
    KEY name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Field Notes:**
- `taxonomy` - Either 'category' (hierarchical) or 'tag' (flat)
- `parent_id` - For hierarchical categories only
- `assignment_count` - Cached count, updated via code

---

### wp_ppa_assignment_tax

Many-to-many relationship between assignments and categories/tags.

```sql
CREATE TABLE wp_ppa_assignment_tax (
    assignment_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (assignment_id, category_id),
    KEY category_id (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Shared Tables Reference (from PressPrimer Quiz)

These tables are created and managed by PressPrimer Quiz. Assignment uses them but does not create or modify their schema.

### wp_ppq_groups

```sql
-- Created by PressPrimer Quiz
CREATE TABLE wp_ppq_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    owner_id BIGINT UNSIGNED NOT NULL,
    settings_json TEXT DEFAULT NULL,
    member_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uuid (uuid),
    KEY owner_id (owner_id),
    KEY name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### wp_ppq_group_members

```sql
-- Created by PressPrimer Quiz
CREATE TABLE wp_ppq_group_members (
    group_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('student', 'teacher') NOT NULL DEFAULT 'student',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id, user_id),
    KEY user_id (user_id),
    KEY role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Usage in Assignment:**
- Groups functionality requires PressPrimer Quiz OR Assignment Educator addon
- Assignment reads from these tables but doesn't write to them in Free version
- Educator addon may create these tables if Quiz isn't installed

---

## Future Tables (Premium Addons)

These tables will be created by premium addons, not the free plugin.

### wp_ppa_rubrics (Educator)

```sql
-- Created by Educator addon
CREATE TABLE wp_ppa_rubrics (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    type ENUM('analytic', 'holistic') NOT NULL DEFAULT 'analytic',
    total_points DECIMAL(10,2) NOT NULL DEFAULT 100.00,
    author_id BIGINT UNSIGNED NOT NULL,
    is_template TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uuid (uuid),
    KEY author_id (author_id),
    KEY is_template (is_template)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### wp_ppa_rubric_criteria (Educator)

```sql
-- Created by Educator addon
CREATE TABLE wp_ppa_rubric_criteria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rubric_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    weight DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    max_points DECIMAL(10,2) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY rubric_id (rubric_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### wp_ppa_rubric_levels (Educator)

```sql
-- Created by Educator addon
CREATE TABLE wp_ppa_rubric_levels (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    criterion_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    points DECIMAL(10,2) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY criterion_id (criterion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### wp_ppa_submission_rubric_scores (Educator)

```sql
-- Created by Educator addon
CREATE TABLE wp_ppa_submission_rubric_scores (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id BIGINT UNSIGNED NOT NULL,
    rubric_id BIGINT UNSIGNED NOT NULL,
    criterion_id BIGINT UNSIGNED NOT NULL,
    level_id BIGINT UNSIGNED DEFAULT NULL,
    points_awarded DECIMAL(10,2) DEFAULT NULL,
    feedback TEXT DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY submission_criterion (submission_id, criterion_id),
    KEY rubric_id (rubric_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Indexes Strategy

All tables include indexes for:
1. Primary keys (automatic)
2. Foreign key relationships
3. Common query patterns (status, date ranges, owner)
4. Search fields (name, slug)

Additional indexes should be added based on actual query patterns after launch.

---

## Migration Strategy

Use a version-based migration system:

```php
function ppa_maybe_run_migrations() {
    $current_version = get_option( 'ppa_db_version', '0' );
    $target_version = PPA_DB_VERSION;
    
    if ( version_compare( $current_version, $target_version, '<' ) ) {
        ppa_run_migrations( $current_version, $target_version );
        update_option( 'ppa_db_version', $target_version );
    }
}

function ppa_run_migrations( $from, $to ) {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    
    // Get schema SQL
    $sql = ppa_get_schema_sql();
    
    // Run dbDelta
    dbDelta( $sql );
    
    // Run any data migrations
    if ( version_compare( $from, '1.1.0', '<' ) ) {
        ppa_migrate_1_1_0();
    }
    // Future migrations here
}
```

---

## Query Patterns

### Get Submissions for Assignment

```php
function ppa_get_assignment_submissions( $assignment_id, $args = [] ) {
    global $wpdb;
    
    $defaults = [
        'status'  => null,
        'orderby' => 'submitted_at',
        'order'   => 'DESC',
        'limit'   => 50,
        'offset'  => 0,
    ];
    $args = wp_parse_args( $args, $defaults );
    
    $where = [ 'assignment_id = %d' ];
    $params = [ $assignment_id ];
    
    if ( $args['status'] ) {
        $where[] = 'status = %s';
        $params[] = $args['status'];
    }
    
    // Validate orderby field
    $allowed_orderby = [ 'submitted_at', 'graded_at', 'final_score', 'user_id' ];
    $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'submitted_at';
    
    // Hardcode ORDER direction
    $is_asc = 'ASC' === strtoupper( $args['order'] );
    
    $where_sql = implode( ' AND ', $where );
    
    if ( $is_asc ) {
        $sql = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppa_submissions 
             WHERE {$where_sql}
             ORDER BY %i ASC
             LIMIT %d OFFSET %d",
            array_merge( $params, [ $orderby, $args['limit'], $args['offset'] ] )
        );
    } else {
        $sql = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppa_submissions 
             WHERE {$where_sql}
             ORDER BY %i DESC
             LIMIT %d OFFSET %d",
            array_merge( $params, [ $orderby, $args['limit'], $args['offset'] ] )
        );
    }
    
    return $wpdb->get_results( $sql );
}
```

### Get Submission with Files

```php
function ppa_get_submission_with_files( $submission_id ) {
    global $wpdb;
    
    $submission = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ppa_submissions WHERE id = %d",
        $submission_id
    ) );
    
    if ( ! $submission ) {
        return null;
    }
    
    $submission->files = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ppa_submission_files 
         WHERE submission_id = %d 
         ORDER BY sort_order ASC",
        $submission_id
    ) );
    
    return $submission;
}
```

### Get User's Submissions Across Assignments

```php
function ppa_get_user_submissions( $user_id, $args = [] ) {
    global $wpdb;
    
    $defaults = [
        'status' => null,
        'limit'  => 20,
    ];
    $args = wp_parse_args( $args, $defaults );
    
    $where = [ 's.user_id = %d' ];
    $params = [ $user_id ];
    
    if ( $args['status'] ) {
        $where[] = 's.status = %s';
        $params[] = $args['status'];
    }
    
    $where_sql = implode( ' AND ', $where );
    $params[] = $args['limit'];
    
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT s.*, a.title as assignment_title, a.due_date, a.max_points
         FROM {$wpdb->prefix}ppa_submissions s
         JOIN {$wpdb->prefix}ppa_assignments a ON s.assignment_id = a.id
         WHERE {$where_sql}
         ORDER BY s.submitted_at DESC
         LIMIT %d",
        $params
    ) );
}
```

### Update Submission Counts

```php
function ppa_update_assignment_counts( $assignment_id ) {
    global $wpdb;
    
    $counts = $wpdb->get_row( $wpdb->prepare(
        "SELECT 
            COUNT(*) as submission_count,
            SUM(CASE WHEN status IN ('graded', 'returned') THEN 1 ELSE 0 END) as graded_count
         FROM {$wpdb->prefix}ppa_submissions
         WHERE assignment_id = %d",
        $assignment_id
    ) );
    
    $wpdb->update(
        $wpdb->prefix . 'ppa_assignments',
        [
            'submission_count' => $counts->submission_count,
            'graded_count'     => $counts->graded_count,
        ],
        [ 'id' => $assignment_id ],
        [ '%d', '%d' ],
        [ '%d' ]
    );
}
```

---

## Data Integrity Notes

1. **Soft deletes** - Not used in v1.0; submissions are permanent
2. **File integrity** - SHA-256 hash stored for each file
3. **Submission snapshots** - Files are immutable once submitted
4. **Foreign keys** - Not enforced by MySQL in WordPress, but documented in schema
5. **Counts** - `submission_count`, `file_count` maintained via code after each operation
6. **Cascading** - When assignment is deleted, related submissions and files should be cleaned up
