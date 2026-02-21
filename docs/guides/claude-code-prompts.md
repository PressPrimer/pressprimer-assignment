# Claude Code Development Prompts

This document contains sequential prompts for building PressPrimer Assignment using Claude Code. Execute these prompts one at a time, reviewing and testing after each step.

---

## Prerequisites

Before starting, ensure:
1. Claude Code has access to the PressPrimer folder containing PressPrimer Quiz
2. Node.js and npm are available
3. Composer is available
4. Local WordPress development environment is running

---

## Phase 1: Project Setup

### Prompt 1.1: Create Plugin Scaffold

```
Create the PressPrimer Assignment plugin scaffold in the PressPrimer folder.

Read these documentation files first:
- pressprimer-assignment/docs/architecture/CODE-STRUCTURE.md
- pressprimer-assignment/docs/architecture/CONVENTIONS.md

Reference PressPrimer Quiz for file patterns:
- pressprimer-quiz/pressprimer-quiz.php (for main file structure)
- pressprimer-quiz/includes/class-ppq-autoloader.php (for autoloader pattern)

Create these files:
1. pressprimer-assignment/pressprimer-assignment.php (main plugin file with constants, autoloader registration, activation hooks)
2. pressprimer-assignment/includes/class-ppa-autoloader.php (class autoloader)
3. pressprimer-assignment/includes/class-ppa-activator.php (activation logic - empty for now)
4. pressprimer-assignment/includes/class-ppa-deactivator.php (deactivation logic - empty for now)
5. pressprimer-assignment/includes/class-ppa-plugin.php (main plugin class - minimal for now)
6. pressprimer-assignment/uninstall.php (uninstall cleanup - checks option before deleting data)

Use these prefixes:
- Constants: PRESSPRIMER_ASSIGNMENT_
- Classes: PressPrimer_Assignment_
- Text domain: pressprimer-assignment

Do NOT implement any functionality yet - just the skeleton structure.
Run PHP syntax check on all files before finishing.
```

### Prompt 1.2: Create Configuration Files

```
Create build and configuration files for PressPrimer Assignment.

Reference PressPrimer Quiz for patterns:
- pressprimer-quiz/package.json
- pressprimer-quiz/composer.json
- pressprimer-quiz/webpack.config.js
- pressprimer-quiz/phpcs.xml.dist
- pressprimer-quiz/.gitignore
- pressprimer-quiz/.distignore

Create:
1. pressprimer-assignment/package.json (copy from Quiz, update names/descriptions)
2. pressprimer-assignment/composer.json (copy from Quiz, update names)
3. pressprimer-assignment/webpack.config.js (copy from Quiz, update paths)
4. pressprimer-assignment/phpcs.xml.dist (copy from Quiz, update text domain)
5. pressprimer-assignment/.gitignore
6. pressprimer-assignment/.distignore
7. pressprimer-assignment/LICENSE (GPL v2)
8. pressprimer-assignment/CHANGELOG.md (empty template)

After creating files, run:
- npm install
- composer install

Verify no errors.
```

### Prompt 1.3: Create Database Schema

```
Create the database schema for PressPrimer Assignment.

Read: pressprimer-assignment/docs/architecture/DATABASE.md

Reference PressPrimer Quiz:
- pressprimer-quiz/includes/database/class-ppq-schema.php
- pressprimer-quiz/includes/database/class-ppq-migrator.php

Create:
1. pressprimer-assignment/includes/database/class-ppa-schema.php
   - Define all v1.0 tables: ppa_assignments, ppa_submissions, ppa_submission_files, ppa_categories, ppa_assignment_tax
   - Use dbDelta() for table creation
   - Include all indexes

2. pressprimer-assignment/includes/database/class-ppa-migrator.php
   - Version-based migration system
   - Check ppa_db_version option
   - Run migrations on admin_init

3. Update class-ppa-activator.php to call schema creation on activation

Run PHPCS on both files.
Test by activating the plugin and verifying tables are created.
```

### Prompt 1.4: Create Base Model Class

```
Create the base model class and Assignment model.

Read: pressprimer-assignment/docs/architecture/CODE-STRUCTURE.md (Model Pattern section)

Reference PressPrimer Quiz:
- pressprimer-quiz/includes/models/class-ppq-question.php

Create:
1. pressprimer-assignment/includes/models/class-ppa-model.php
   - Abstract base class
   - Static get(), get_by_uuid(), create() methods
   - Instance save(), delete() methods
   - Magic __get/__set for data access
   - to_array() method

2. pressprimer-assignment/includes/models/class-ppa-assignment.php
   - Extends base model
   - $table_name = 'ppa_assignments'
   - get_published() static method
   - is_past_due() method
   - get_allowed_file_types() method
   - get_submissions() method

Use %i placeholder for dynamic field names.
Hardcode ORDER direction (never interpolate).
Run PHPCS and security checks.
```

---

## Phase 2: Core Models and Services

### Prompt 2.1: Create Remaining Models

```
Create the Submission, Submission_File, and Category models.

Reference the Assignment model pattern just created.

Create:
1. pressprimer-assignment/includes/models/class-ppa-submission.php
   - Fields: id, uuid, assignment_id, user_id, submission_number, status, etc.
   - get_for_assignment($assignment_id, $args) static method
   - get_for_user($user_id, $args) static method
   - get_files() method
   - is_late() calculation
   - Status constants: DRAFT, SUBMITTED, GRADING, GRADED, RETURNED

2. pressprimer-assignment/includes/models/class-ppa-submission-file.php
   - Fields: id, submission_id, original_filename, stored_filename, file_path, etc.
   - get_for_submission($submission_id) static method
   - get_download_url() method
   - verify_integrity() method

3. pressprimer-assignment/includes/models/class-ppa-category.php
   - Fields: id, name, slug, description, parent_id, taxonomy
   - get_categories() static method
   - get_tags() static method
   - get_assignments() method

Run PHPCS on all model files.
```

### Prompt 2.2: Create File Service

```
Create the file handling service.

Read: pressprimer-assignment/docs/versions/v1.0/features/file-upload.md
Read: pressprimer-assignment/docs/architecture/SECURITY.md

Create pressprimer-assignment/includes/services/class-ppa-file-service.php:

1. validate_file($file, $assignment) method
   - Check upload error code
   - Sanitize filename
   - Validate extension against allowed types
   - Check for double extensions
   - Verify MIME type using finfo
   - Check file size

2. store_file($file, $submission_id) method
   - Get secure upload directory
   - Generate secure filename (hash + timestamp)
   - Create date-based subdirectory
   - Move uploaded file
   - Calculate SHA-256 hash
   - Create database record
   - Update submission stats

3. get_upload_directory() method
   - Create ppa-submissions directory
   - Add .htaccess to deny direct access
   - Add index.php silence file

4. delete_file($file_id) method
   - Remove physical file
   - Remove database record
   - Update submission stats

5. serve_file($file_id) method (for download handler)
   - Check user permissions
   - Verify file integrity (optional)
   - Set headers and output file

Run PHPCS and security-specific checks.
```

### Prompt 2.3: Create Grading Service

```
Create the grading calculation service.

Read: pressprimer-assignment/docs/architecture/HOOKS.md (grading section)

Create pressprimer-assignment/includes/services/class-ppa-grading-service.php:

1. grade($submission_id, $score, $feedback) method
   - Validate submission exists
   - Validate score range (0 to max_points)
   - Fire pressprimer_assignment_before_grade action
   - Calculate late penalty if applicable
   - Calculate final_score with filter
   - Determine pass/fail with filter
   - Update submission record
   - Fire pressprimer_assignment_after_grade action
   - Fire passed or failed action
   - Return result array

2. calculate_late_penalty($submission_id, $raw_score) method
   - Check if submission is late
   - Check assignment late_policy
   - Apply percentage reduction
   - Return penalty amount with filter

3. return_submission($submission_id) method
   - Update status to 'returned'
   - Fire pressprimer_assignment_submission_returned action
   - (For future: trigger email notification)

Run PHPCS checks.
```

### Prompt 2.4: Create Capabilities System

```
Create the role and capability system.

Reference PressPrimer Quiz:
- pressprimer-quiz/includes/utilities/class-ppq-capabilities.php

Create pressprimer-assignment/includes/utilities/class-ppa-capabilities.php:

1. Define capability constants:
   - PPA_CAP_MANAGE_ALL = 'ppa_manage_all'
   - PPA_CAP_MANAGE_SETTINGS = 'ppa_manage_settings'
   - PPA_CAP_VIEW_REPORTS = 'ppa_view_reports'

2. register() method (called on activation)
   - Add capabilities to administrator role
   - Check for pressprimer_teacher role (shared with Quiz)
   - If teacher role exists, note that premium caps will be added by addons

3. unregister() method (called on uninstall)
   - Remove ppa_ capabilities from all roles

4. current_user_can_manage_assignment($assignment_id) helper
   - Check ppa_manage_all
   - (Future: check ownership for teachers)

5. Filter for pressprimer_assignment_teacher_capabilities

Update class-ppa-activator.php to call Capabilities::register()
Update uninstall.php to call Capabilities::unregister() (conditionally)

Run PHPCS checks.
```

---

## Phase 3: Admin Interface

### Prompt 3.1: Create Admin Menu and Pages Structure

```
Create the admin menu structure for PressPrimer Assignment.

Reference PressPrimer Quiz:
- pressprimer-quiz/includes/admin/class-ppq-admin.php

Create:
1. pressprimer-assignment/includes/admin/class-ppa-admin.php
   - Register admin menu: "PPA Assignments" with dashicons-media-document
   - Submenus: Assignments, Submissions, Categories, Reports, Settings
   - Enqueue admin scripts and styles
   - Register REST API endpoints for admin

2. Create placeholder admin page classes:
   - class-ppa-admin-assignments.php
   - class-ppa-admin-submissions.php  
   - class-ppa-admin-categories.php
   - class-ppa-admin-reports.php
   - class-ppa-admin-settings.php

Each should render a simple container div for React to mount into.

3. Update class-ppa-plugin.php to initialize Admin when is_admin()

Test: Activate plugin and verify menu appears with all submenus.
```

### Prompt 3.2: Create React Admin Entry Point

```
Create the React admin application entry point.

Reference PressPrimer Quiz:
- pressprimer-quiz/src/admin/index.js
- pressprimer-quiz/src/admin/App.js

Create:
1. pressprimer-assignment/src/admin/index.js
   - Import React and render
   - Mount App to #ppa-admin-root

2. pressprimer-assignment/src/admin/App.js
   - Import Ant Design ConfigProvider
   - Import React Router (HashRouter)
   - Define routes: /assignments, /submissions, /categories, /reports, /settings
   - Placeholder components for each route

3. pressprimer-assignment/src/admin/api/index.js
   - apiFetch wrapper functions
   - assignmentsApi: list, get, create, update, delete
   - submissionsApi: list, get, update
   - categoriesApi: list, get, create, update, delete

4. Update webpack.config.js if needed

5. Run npm run build

Test: Navigate to Assignments page, verify React app loads.
```

### Prompt 3.3: Create Assignment List and Form Components

```
Create the Assignments admin interface.

Reference PressPrimer Quiz:
- pressprimer-quiz/src/admin/pages/Quizzes/

Create:
1. pressprimer-assignment/src/admin/pages/Assignments/index.js
   - List view with Ant Design Table
   - Columns: Title, Status, Due Date, Submissions, Actions
   - Create New button
   - Bulk actions (delete)

2. pressprimer-assignment/src/admin/pages/Assignments/AssignmentForm.js
   - Form with all assignment fields
   - Tabs: Basic Info, Scoring, Due Date, File Settings
   - Save as Draft / Publish buttons
   - Uses Ant Design Form, Input, Select, DatePicker, etc.

3. pressprimer-assignment/src/admin/pages/Assignments/AssignmentEdit.js
   - Loads existing assignment
   - Uses AssignmentForm component
   - Handles update API call

4. pressprimer-assignment/src/admin/components/RichTextEditor.js
   - Simple rich text editor for instructions
   - (Can use @wordpress/rich-text or a simple textarea for v1.0)

Run npm run build and test the interface.
```

### Prompt 3.4: Create REST API for Assignments

```
Create the REST API endpoints for assignments.

Read: pressprimer-assignment/docs/architecture/CODE-STRUCTURE.md (REST API section)
Reference PressPrimer Quiz:
- pressprimer-quiz/includes/api/class-ppq-rest-quizzes.php

Create pressprimer-assignment/includes/api/class-ppa-rest-assignments.php:

1. Register routes on rest_api_init:
   - GET /ppa/v1/assignments (list)
   - POST /ppa/v1/assignments (create)
   - GET /ppa/v1/assignments/{id} (get single)
   - PUT /ppa/v1/assignments/{id} (update)
   - DELETE /ppa/v1/assignments/{id} (delete)

2. Permission callbacks checking ppa_manage_all

3. Schema validation for create/update

4. Sanitize all inputs
   - title: sanitize_text_field
   - description, instructions, grading_guidelines: wp_kses_post
   - numeric fields: absint or floatval
   - enum fields: validate against allowed values

5. Fire appropriate actions (pressprimer_assignment_created, etc.)

6. Return properly formatted responses

Run PHPCS and test endpoints with Postman or browser.
```

---

## Phase 4: Frontend Submission

### Prompt 4.1: Create Frontend Base and Shortcodes

```
Create the frontend initialization and shortcodes.

Reference PressPrimer Quiz:
- pressprimer-quiz/includes/frontend/class-ppq-frontend.php
- pressprimer-quiz/includes/frontend/class-ppq-shortcodes.php

Create:
1. pressprimer-assignment/includes/frontend/class-ppa-frontend.php
   - Enqueue frontend styles
   - Enqueue frontend scripts (submission.js, document-viewer.js)
   - Localize script with nonces and i18n strings
   - Register file download handler (init action)

2. pressprimer-assignment/includes/frontend/class-ppa-shortcodes.php
   - [ppa_assignment id="123"] - Render single assignment
   - [ppa_my_submissions] - Render user's submissions dashboard
   - Both shortcodes call renderer classes

3. Create CSS files:
   - pressprimer-assignment/assets/css/submission.css
   - pressprimer-assignment/assets/css/themes/default.css

Run PHPCS checks.
```

### Prompt 4.2: Create Assignment Renderer

```
Create the assignment frontend renderer.

Reference PressPrimer Quiz:
- pressprimer-quiz/includes/frontend/class-ppq-quiz-renderer.php

Create pressprimer-assignment/includes/frontend/class-ppa-assignment-renderer.php:

1. render($assignment_id, $args) method
   - Get assignment
   - Check if published
   - Check user access
   - Determine display mode (view, submit, view_submission)
   - Build HTML using templates
   - Apply filters (pressprimer_assignment_render_output)

2. render_assignment_info($assignment) method
   - Title, description, instructions
   - Grading guidelines
   - Due date with countdown if approaching
   - Max points and passing score

3. render_submission_form($assignment) method
   - Check if user can submit
   - Include upload zone
   - Submit button
   - Uses template file

4. render_user_submission($submission) method
   - Show submission status
   - List files
   - Show grade if returned

Create template files:
- pressprimer-assignment/templates/assignment/single.php
- pressprimer-assignment/templates/assignment/submission-form.php
- pressprimer-assignment/templates/assignment/submission-status.php

Use wp_kses() for all output.
Run PHPCS checks.
```

### Prompt 4.3: Create File Upload JavaScript

```
Create the frontend file upload functionality.

Read: pressprimer-assignment/docs/versions/v1.0/features/file-upload.md

Create pressprimer-assignment/assets/js/submission.js:

1. PPA.Upload module:
   - init(container) - Initialize upload zone
   - bindEvents() - Drag/drop, click, keyboard
   - handleDragOver/Leave/Drop
   - processFiles(files)
   - validateFile(file) - Client-side validation
   - uploadFile(file) - XHR upload with progress
   - createFileItem(file) - DOM element for file
   - removeFile(item) - Remove uploaded file
   - formatSize(bytes) - Human readable
   - escapeHtml(text) - Security

2. Accessibility:
   - Upload zone is keyboard accessible
   - Progress announced via aria-live
   - Error messages use role="alert"

3. Progressive enhancement:
   - Works with basic form fallback if JS fails

4. Create AJAX handlers in class-ppa-submission-handler.php:
   - ppa_upload_file action
   - ppa_remove_file action
   - ppa_submit_assignment action

Run the upload handler through PHPCS security checks.
Test file upload end-to-end.
```

### Prompt 4.4: Create Submission Handler

```
Create the submission processing handler.

Create pressprimer-assignment/includes/frontend/class-ppa-submission-handler.php:

1. AJAX handlers:
   - handle_upload() - Process single file upload
   - handle_remove() - Remove uploaded file
   - handle_submit() - Finalize submission

2. handle_submit() logic:
   - Verify nonce
   - Check user can submit (filter)
   - Get draft submission with files
   - Validate at least one file
   - Update status to 'submitted'
   - Set submitted_at timestamp
   - Check if late
   - Fire pressprimer_assignment_submission_submitted action
   - Return success with submission details

3. Helper methods:
   - get_or_create_draft($user_id, $assignment_id)
   - check_resubmission_allowed($user_id, $assignment_id)
   - count_user_submissions($user_id, $assignment_id)

All handlers must:
- Verify nonces
- Check capabilities
- Sanitize all input
- Return proper JSON responses

Run PHPCS and security checks.
Test complete submission flow.
```

---

## Phase 5: Document Viewing and Grading

### Prompt 5.1: Create Document Viewer

```
Create the document viewing component.

Read technical feasibility doc for PDF.js and Mammoth.js guidance.

Create:
1. pressprimer-assignment/includes/frontend/class-ppa-document-viewer.php
   - render($file_id, $context) method
   - Determine viewer type based on extension
   - render_pdf_viewer($file) - PDF.js embed
   - render_docx_viewer($file) - Mammoth.js conversion
   - render_image_viewer($file) - Simple img tag
   - render_text_viewer($file) - Pre-formatted text
   - Download link for all types

2. pressprimer-assignment/assets/js/document-viewer.js
   - PPA.DocumentViewer module
   - initPdfViewer(container, url) - PDF.js setup
   - initDocxViewer(container, url) - Mammoth.js fetch and convert
   - Page navigation for PDFs
   - Zoom controls

3. Include PDF.js from CDN or bundle:
   - Option A: CDN link to Mozilla's PDF.js
   - Option B: Bundle pdfjs-dist via npm

4. Include Mammoth.js:
   - npm install mammoth
   - Bundle with webpack

Ensure document URLs go through permission-checked PHP handler.
Test viewing PDF, DOCX, TXT, and image files.
```

### Prompt 5.2: Create Grading Interface Backend

```
Create the grading REST API and admin page.

Create pressprimer-assignment/includes/api/class-ppa-rest-submissions.php:

1. Routes:
   - GET /ppa/v1/submissions (list, filterable by assignment)
   - GET /ppa/v1/submissions/{id} (get with files)
   - PUT /ppa/v1/submissions/{id} (update grade, feedback, status)

2. GET single submission response includes:
   - Submission data
   - Assignment data (title, max_points, grading_guidelines)
   - Files array with download URLs
   - User info (name, email)

3. PUT validation:
   - Score must be 0 to max_points
   - Status must be valid transition
   - Feedback sanitized with wp_kses_post

4. Use PressPrimer_Assignment_Grading_Service::grade() for actual grading

Update class-ppa-admin-submissions.php to:
- Render container for React grading interface
- Pass submission ID from URL parameter

Run PHPCS checks.
```

### Prompt 5.3: Create Grading Interface Frontend

```
Create the React grading interface.

Reference PressPrimer Quiz grading patterns.

Create:
1. pressprimer-assignment/src/grading/index.js
   - Entry point for grading interface
   - Mount to #ppa-grading-root

2. pressprimer-assignment/src/grading/GradingInterface.js
   - Split panel layout (resizable)
   - Left: Document viewer
   - Right: Grading form
   - Submission navigation (prev/next)
   - Keyboard shortcuts (J/K, 1-9, Ctrl+S)

3. pressprimer-assignment/src/grading/components/DocumentPanel.js
   - File list
   - Document viewer embed
   - File switcher tabs

4. pressprimer-assignment/src/grading/components/GradingForm.js
   - Score input (number, 0 to max)
   - Score quick buttons (×10)
   - Feedback textarea (rich text optional)
   - Late penalty display (if applicable)
   - Final score display
   - Status dropdown
   - Save button with loading state
   - Auto-save indicator

5. pressprimer-assignment/src/grading/components/SubmissionNav.js
   - Previous/Next buttons
   - Submission count indicator
   - Filter by status

Update webpack to build grading bundle separately.
Test grading workflow end-to-end.
```

---

## Phase 6: LMS Integration and Reports

### Prompt 6.1: Create LearnDash Integration

```
Create LearnDash integration.

Reference PressPrimer Quiz:
- pressprimer-quiz/includes/integrations/class-ppq-learndash.php

Create pressprimer-assignment/includes/integrations/class-ppa-learndash.php:

1. Check if LearnDash is active before initializing

2. Add meta box to LearnDash lessons and topics:
   - "PPA Assignment" meta box
   - Dropdown to select assignment
   - Saved as post meta: ppa_assignment_id

3. Display assignment within lesson content:
   - Filter learndash_content or appropriate hook
   - Insert assignment shortcode output

4. Completion trigger:
   - Hook into pressprimer_assignment_submission_passed
   - Mark LearnDash lesson/topic complete
   - Use learndash_process_mark_complete()

5. Settings integration:
   - Add LearnDash tab to settings
   - Enable/disable integration option
   - Default behavior setting

Test with LearnDash:
- Add assignment to lesson
- Submit and pass assignment
- Verify lesson marked complete
```

### Prompt 6.2: Create TutorLMS Integration

```
Create TutorLMS integration.

Reference PressPrimer Quiz TutorLMS integration.

Create pressprimer-assignment/includes/integrations/class-ppa-tutorlms.php:

1. Check if TutorLMS is active

2. Add meta box to TutorLMS lessons:
   - "PPA Assignment" meta box
   - Select assignment dropdown
   - Save as post meta

3. Display in lesson:
   - Hook into tutor_lesson/content or appropriate filter
   - Render assignment

4. Completion:
   - Hook into pressprimer_assignment_submission_passed
   - Use tutor_utils()->mark_lesson_complete()

5. Settings:
   - Enable/disable in settings
   - Default behavior

Test with TutorLMS if available.
```

### Prompt 6.3: Create Reports

```
Create the reports functionality.

Reference PressPrimer Quiz:
- pressprimer-quiz/includes/admin/class-ppq-admin-reports.php

Create pressprimer-assignment/includes/admin/class-ppa-admin-reports.php:

1. Register reports:
   - Report 1: Assignments Overview
   - Report 2: Submissions by Assignment

2. Assignments Overview:
   - Table: Title, Status, Submissions, Graded, Avg Score, Completion Rate
   - Sortable columns
   - Date range filter

3. Submissions by Assignment:
   - Assignment selector dropdown
   - Table: Student, Status, Submitted, Graded, Score, Late
   - Filter by status
   - Export to CSV

Create pressprimer-assignment/includes/api/class-ppa-rest-reports.php:
- GET /ppa/v1/reports/overview
- GET /ppa/v1/reports/submissions?assignment_id=X

Create pressprimer-assignment/includes/services/class-ppa-statistics-service.php:
- get_assignment_stats($assignment_id)
- get_overview_stats($args)
- Calculate averages, completion rates

Create React components:
- pressprimer-assignment/src/admin/pages/Reports/index.js
- pressprimer-assignment/src/admin/pages/Reports/OverviewReport.js
- pressprimer-assignment/src/admin/pages/Reports/SubmissionsReport.js

Test reports with sample data.
```

### Prompt 6.4: Create Uncanny Automator Integration

```
Create Uncanny Automator integration.

Reference PressPrimer Quiz Automator integration.

Create pressprimer-assignment/includes/integrations/uncanny-automator/:

1. class-ppa-automator-loader.php
   - Check if Automator is active
   - Load integration files

2. class-ppa-automator-integration.php
   - Register "PressPrimer Assignment" integration
   - Define icon, name

3. triggers/class-ppa-trigger-submitted.php
   - Trigger: "User submits an assignment"
   - Tokens: assignment_title, student_name, student_email, submission_id

4. triggers/class-ppa-trigger-graded.php
   - Trigger: "Assignment is graded"
   - Tokens: assignment_title, student_name, score, max_points, passed

5. triggers/class-ppa-trigger-passed.php
   - Trigger: "User passes an assignment"
   - Tokens: assignment_title, student_name, score

6. triggers/class-ppa-trigger-failed.php
   - Trigger: "User fails an assignment"
   - Tokens: assignment_title, student_name, score

Hook each trigger to appropriate pressprimer_assignment_* actions.
Test with Automator if available.
```

---

## Phase 7: Settings, Onboarding, and Polish

### Prompt 7.1: Create Settings Page

```
Create the settings page.

Reference PressPrimer Quiz:
- pressprimer-quiz/includes/admin/class-ppq-admin-settings.php

Create pressprimer-assignment/includes/admin/class-ppa-admin-settings.php:

1. Settings tabs:
   - General
   - File Upload
   - LMS
   - Advanced

2. General settings:
   - Default late policy (accept/reject/penalty)
   - Default max points
   - Default passing score
   - Default theme

3. File Upload settings:
   - Default allowed file types (checkboxes)
   - Default max file size (dropdown)
   - Default max files

4. LMS settings:
   - LearnDash enable/disable
   - TutorLMS enable/disable
   - Default completion behavior

5. Advanced settings:
   - Delete data on uninstall (checkbox, default OFF)
   - Debug mode

Create React settings components:
- pressprimer-assignment/src/admin/pages/Settings/index.js
- Tab components for each section

Store settings in ppa_settings option (JSON).

Create REST endpoint for settings:
- GET/PUT /ppa/v1/settings

Test saving and loading settings.
```

### Prompt 7.2: Create Onboarding Flow

```
Create the first-run onboarding experience.

Reference PressPrimer Quiz:
- pressprimer-quiz/includes/admin/class-ppq-onboarding.php

Create pressprimer-assignment/includes/admin/class-ppa-onboarding.php:

1. Check if onboarding needed (ppa_onboarding_complete option)

2. Redirect to onboarding on first admin visit

3. Onboarding steps:
   - Step 1: Welcome - Introduction to PressPrimer Assignment
   - Step 2: Create First Assignment - Guided creation form
   - Step 3: Embed - Show block and shortcode options
   - Step 4: Complete - Links to docs, prompt for review

4. Track progress in transient

5. Skip option always available

6. Mark complete at end (set option)

Create React onboarding components:
- pressprimer-assignment/src/onboarding/index.js
- Step components with illustrations

Test onboarding flow on fresh install.
```

### Prompt 7.3: Create Gutenberg Blocks

```
Create the Gutenberg blocks.

Reference PressPrimer Quiz blocks.

Create pressprimer-assignment/blocks/assignment/:
1. block.json
   - name: pressprimer-assignment/assignment
   - attributes: assignmentId
   - supports: align

2. index.js - Register block

3. edit.js
   - Assignment selector (dropdown of published assignments)
   - Preview in editor

4. save.js
   - Return null (dynamic block)

5. PHP render callback in class-ppa-shortcodes.php

Create pressprimer-assignment/blocks/my-submissions/:
1. block.json
   - name: pressprimer-assignment/my-submissions
   - attributes: limit, showStatus

2. edit.js - Configuration options

3. save.js - Return null

4. PHP render callback

Register blocks in class-ppa-plugin.php.
Test blocks in Gutenberg editor.
```

### Prompt 7.4: Final Polish and Testing

```
Final polish and testing checklist.

1. Run full PHPCS check on all PHP files:
   - Fix any remaining issues
   - Ensure no phpcs:ignore for security rules

2. Run PHP compatibility check (7.4-8.4)

3. Verify all strings are translatable:
   - Generate POT file: wp i18n make-pot . languages/pressprimer-assignment.pot

4. Test accessibility:
   - Keyboard navigation through all interfaces
   - Screen reader testing on key flows
   - Color contrast check

5. Test mobile responsiveness:
   - Admin pages
   - Frontend submission form
   - Document viewer

6. Create readme.txt:
   - Plugin description
   - Installation instructions
   - FAQ
   - Screenshots list
   - Changelog

7. Create screenshot files for WordPress.org

8. Test clean install:
   - Fresh WordPress site
   - Activate plugin
   - Verify tables created
   - Complete onboarding
   - Create assignment
   - Submit as student
   - Grade submission
   - Check reports

9. Test upgrade path (for future):
   - Document current schema version

10. Final build:
    - npm run build
    - npm run plugin-zip
```

---

## Additional Prompts (As Needed)

### Prompt A: Add Email Notifications

```
Add basic email notifications.

Create pressprimer-assignment/includes/services/class-ppa-email-service.php:

1. send_submission_confirmation($submission_id)
   - To: Student
   - Subject: "Submission Received: {assignment_title}"
   - Body: Confirmation with submission details

2. send_grade_notification($submission_id)
   - To: Student
   - Subject: "Your assignment has been graded"
   - Body: Score, feedback preview, link to view

Create email templates:
- pressprimer-assignment/templates/emails/submission-received.php
- pressprimer-assignment/templates/emails/grade-released.php

Hook into appropriate actions.
Add settings to enable/disable emails.
```

### Prompt B: Add Dashboard Widget

```
Create admin dashboard widget.

Create pressprimer-assignment/includes/admin/class-ppa-dashboard-widget.php:

1. Register widget on wp_dashboard_setup

2. Widget content:
   - Total assignments (published)
   - Pending submissions (needs grading)
   - Recent activity
   - Quick links

Style to match WordPress admin dashboard.
```

### Prompt C: Performance Optimization

```
Optimize performance for scale.

1. Add caching:
   - Cache assignment lists in transients
   - Cache statistics
   - Clear cache on updates

2. Optimize queries:
   - Review all queries for N+1 issues
   - Add missing indexes if needed
   - Use appropriate LIMIT clauses

3. Lazy load document viewer:
   - Don't load PDF.js until needed
   - Don't convert DOCX until viewed

4. Asset optimization:
   - Ensure CSS/JS only loaded when needed
   - Minify production builds
```

---

## Notes

- Always run PHPCS before committing
- Test each feature in isolation before integration
- Reference PressPrimer Quiz for consistent patterns
- Follow WordPress.org guidelines throughout
- Document any deviations from the plan
