# PressPrimer Assignment v1.0 - Scope Document

## Version Overview

**Version:** 1.0.0  
**Theme:** "Solid Foundation"  
**Target:** WordPress.org initial submission  
**Timeline:** 3-4 months development  

v1.0 establishes PressPrimer Assignment as a genuinely useful free plugin for document-based assignment submission and grading. The focus is on core functionality that works reliably, with a clear upgrade path to premium features.

---

## Target Users

**Primary:** WordPress site administrators using LearnDash or TutorLMS who need to collect and grade document submissions.

**Use Cases:**
- University professor collecting essay submissions through LearnDash
- Corporate trainer gathering project reports via TutorLMS
- Course creator needing student work samples beyond quizzes
- Any WordPress educator who needs document-based assessment

---

## Core Features

### 1. Assignment Management (Admin Only)

#### 1.1 Create Assignments
- Title (required)
- Description (optional, rich text)
- Instructions (required, rich text with full formatting)
- Grading Guidelines (optional, rich text - visible to students)
- Categories (optional, multiple)
- Tags (optional, multiple)
- Featured image (optional)

#### 1.2 Assignment Settings
- **Due Date:** Date and time picker with timezone support
- **Late Policy:** Accept / Reject / Accept with Penalty
- **Late Penalty:** Percentage reduction (0-100%)
- **Resubmission:** Enable/disable
- **Max Resubmissions:** Number allowed (1-10)

#### 1.3 Scoring Settings
- **Max Points:** Configurable (default 100)
- **Passing Score:** Configurable (default 60%)

#### 1.4 File Settings
- **Allowed File Types:** Checkboxes for PDF, DOCX, TXT, RTF, JPG, PNG, GIF
- **Max File Size:** Dropdown (1MB, 5MB, 10MB, 25MB, 50MB)
- **Max Files:** Dropdown (1, 2, 3, 5, 10)

#### 1.5 Status Management
- Draft / Published / Archived
- Duplicate assignment
- Delete assignment (with confirmation)

#### 1.6 Assignment Templates
- Save current assignment as template
- Create new assignment from template
- Manage templates (list, rename, delete)

### 2. File Submission System

#### 2.1 Upload Interface
- Drag-and-drop zone
- Click to browse option
- Multi-file upload support
- Upload progress indicator per file
- Cancel upload option

#### 2.2 File Validation
- Extension check (against allowed types)
- MIME type verification
- Magic byte validation (security)
- File size check
- Total files check

#### 2.3 File Security
- Files stored outside webroot: `wp-content/uploads/ppa-submissions/`
- `.htaccess` protection to prevent direct access
- Unique filenames with hash prefix
- SHA-256 integrity hash stored

#### 2.4 Submission Flow
1. Student views assignment
2. Student uploads file(s)
3. System validates files
4. Student confirms submission
5. System records timestamp
6. Confirmation displayed with submission ID

#### 2.5 Resubmission
- When enabled, student can submit again
- Previous submissions preserved
- Submission number incremented
- Due date still applies

### 3. Document Viewing

#### 3.1 PDF Viewing
- In-browser rendering via PDF.js 4.x
- Page navigation
- Zoom controls
- Download original option
- Mobile-responsive

#### 3.2 DOCX Viewing
- Converted to HTML via Mammoth.js
- Preserves headings, lists, tables
- Basic formatting retained
- Download original option

#### 3.3 Image Viewing
- Direct display for JPG, PNG, GIF
- Lightbox for larger view
- Download original option

#### 3.4 Other Files (TXT, RTF)
- Text content display
- Download original option

### 4. Grading Interface (Admin Only)

#### 4.1 Submissions List View
- Table of all submissions for an assignment
- Columns: Student, Status, Submitted, Score, Actions
- Sortable by any column
- Filterable by status
- Search by student name/email
- Bulk status update (mark as grading)

#### 4.2 Single Submission View
- Split panel: Document viewer (left), Grading form (right)
- Resizable divider
- Document viewer (see Section 3)

#### 4.3 Grading Form
- **Score:** Number input (0 to max_points)
- **Feedback:** Rich text editor
- **Status:** Dropdown (Grading → Graded → Returned)
- Save button
- Auto-save indicator

#### 4.4 Grading Workflow
- Keyboard shortcuts:
  - `J` / `K` - Next / Previous submission
  - `1-9` - Quick score (×10, so 7 = 70)
  - `Ctrl+S` - Save
- Navigation between submissions
- "Mark all viewed as grading" bulk action

#### 4.5 Late Penalty Handling
- System shows if submission was late
- Shows original score vs. final score after penalty
- Penalty calculation: `final = raw - (raw × penalty%)`

### 5. Student Experience

#### 5.1 My Submissions Dashboard
- Gutenberg block: `pressprimer-assignment/my-submissions`
- Shortcode: `[ppa_my_submissions]`
- Shows all user's submissions
- Columns: Assignment, Status, Submitted, Score, Actions

#### 5.2 Assignment View
- Gutenberg block: `pressprimer-assignment/assignment`
- Shortcode: `[ppa_assignment id="123"]`
- Shows assignment details
- Shows grading guidelines
- Shows due date with countdown if approaching
- Upload interface (when not submitted or resubmission allowed)
- View previous submissions

#### 5.3 Submission Status Display
- **Not Submitted** - Upload interface shown
- **Draft** - Files uploaded but not submitted (rare in v1.0)
- **Submitted** - Awaiting grading
- **Grading** - Being reviewed (optional status)
- **Graded** - Score assigned but not released
- **Returned** - Student can view score and feedback

#### 5.4 View Feedback
- See score and points
- See pass/fail status
- Read instructor feedback
- Download original files

### 6. LMS Integration

#### 6.1 LearnDash Integration
- Meta box on LearnDash lessons/topics
- Select assignment to attach
- Completion trigger: submission graded with passing score
- Assignment appears within lesson content
- Respects LearnDash enrollment/access rules

#### 6.2 TutorLMS Integration
- Meta box on TutorLMS lessons
- Select assignment to attach
- Completion trigger: submission graded with passing score
- Assignment appears within lesson content
- Respects TutorLMS enrollment/access

**Note:** LifterLMS and LearnPress integrations are planned for v2.0.

### 7. Uncanny Automator Integration

#### 7.1 Triggers
- **Assignment Submitted** - When student submits
  - Tokens: assignment_title, student_name, student_email, submission_id
- **Assignment Graded** - When instructor grades (any score)
  - Tokens: assignment_title, student_name, score, max_points, passed
- **Assignment Passed** - When student passes (score >= passing)
  - Tokens: assignment_title, student_name, score, max_points
- **Assignment Failed** - When student fails (score < passing)
  - Tokens: assignment_title, student_name, score, max_points

### 8. Reports

#### 8.1 Report 1: Assignments Overview
- List of all assignments
- Columns: Title, Status, Submissions, Graded, Avg Score, Created
- Sortable
- Click row to see submissions

#### 8.2 Report 2: Submissions by Assignment
- Select assignment from dropdown
- List of all submissions for that assignment
- Columns: Student, Status, Submitted, Graded, Score, Late
- Export to CSV

### 9. Categories & Tags

#### 9.1 Categories
- Hierarchical (parent/child)
- Name, slug, description
- Assignment count displayed
- Bulk actions (delete, merge)

#### 9.2 Tags
- Flat (non-hierarchical)
- Name, slug
- Assignment count displayed

#### 9.3 Filtering
- Filter assignments by category/tag in admin
- Filter in frontend blocks/shortcodes (future)

### 10. Settings

#### 10.1 General Tab
- Default late policy
- Default max points
- Default passing score
- Default theme

#### 10.2 File Upload Tab
- Default allowed file types
- Default max file size
- Default max files per submission
- File storage location (info display)

#### 10.3 LMS Tab
- LearnDash integration enable/disable
- TutorLMS integration enable/disable
- Default completion behavior

#### 10.4 Advanced Tab
- Role capabilities settings
- Delete data on uninstall (opt-in, default OFF)
- Debug mode

### 11. Onboarding

#### 11.1 Welcome Flow
1. **Welcome Screen** - Introduction to PressPrimer Assignment
2. **Create First Assignment** - Guided creation
3. **Embed in Content** - Show block/shortcode options
4. **Complete** - Link to documentation, prompt for review

#### 11.2 Dashboard Widget
- Assignments overview (total, published)
- Recent submissions
- Quick links (create assignment, view reports)

### 12. Themes

#### 12.1 Default Theme
- Clean, professional appearance
- Blue accent colors
- Clear typography

#### 12.2 Modern Theme
- Card-based layout
- Rounded corners
- Subtle shadows

#### 12.3 Minimal Theme
- Maximum simplicity
- Reduced visual elements
- Focus on content

Each theme supports condensed mode for tighter layouts.

### 13. Accessibility

#### 13.1 Requirements (WCAG 2.1 AA)
- Full keyboard navigation
- Screen reader compatibility
- ARIA labels and roles
- Focus visible indicators
- Color contrast compliance
- Error messages announced

#### 13.2 Specific Features
- Skip links in admin
- Focus trap in modals
- Live regions for status updates
- Alt text for UI elements

---

## What's NOT in v1.0

### Premium Features (Future Addons)

| Feature | Tier |
|---------|------|
| Rubric builder | Educator |
| Teacher role management | Educator |
| AI-powered proofreading | Educator |
| Import/export assignments | Educator |
| Groups and group assignments | Educator |
| Peer review | School |
| Inline document annotation | School |
| xAPI/LRS integration | School |
| Plagiarism detection | Enterprise |
| AI content detection | Enterprise |
| White-label branding | Enterprise |
| Audit logging | Enterprise |

### Future Free Features (v2.0+)

- LifterLMS integration
- LearnPress integration
- Enhanced email notifications
- More report types
- Public assignment URLs (no login required)

### Out of Scope

- Video/audio submissions
- Code submissions with syntax highlighting
- Presentation file preview (.pptx)
- Real-time collaborative editing
- Discussion/comments on submissions
- Plagiarism detection in free version
- DOC (legacy) file preview (DOCX only)

---

## Technical Requirements

### WordPress Compatibility
- Minimum: WordPress 6.0
- Tested up to: WordPress 6.7
- PHP: 7.4 - 8.4

### LMS Compatibility (v1.0)
- LearnDash: 4.0+
- TutorLMS: 2.0+

### LMS Compatibility (Planned for v2.0)
- LifterLMS: 7.0+
- LearnPress: 4.0+

### Browser Support
- Chrome (last 2 versions)
- Firefox (last 2 versions)
- Safari (last 2 versions)
- Edge (last 2 versions)
- Mobile browsers (iOS Safari, Chrome Android)

### Dependencies
- PDF.js 4.x (bundled)
- Mammoth.js (bundled)
- Plupload (WordPress bundled)
- React 18.x (for admin UI)
- Ant Design 5.x (for admin UI)

---

## Database Tables

Created by v1.0:
- `wp_ppa_assignments`
- `wp_ppa_submissions`
- `wp_ppa_submission_files`
- `wp_ppa_categories`
- `wp_ppa_assignment_tax`

See `architecture/DATABASE.md` for full schema.

---

## Capabilities

### Administrator
- `ppa_manage_all` - Full access to all assignments and submissions
- `ppa_manage_settings` - Access to settings page
- `ppa_view_reports` - Access to reports

### Future (Educator Addon)
- `ppa_manage_own` - Manage own assignments and grade submissions
- `ppa_view_own_reports` - View reports for own assignments

---

## REST API Endpoints

### Assignments
- `GET /ppa/v1/assignments` - List assignments
- `GET /ppa/v1/assignments/{id}` - Get assignment
- `POST /ppa/v1/assignments` - Create assignment
- `PUT /ppa/v1/assignments/{id}` - Update assignment
- `DELETE /ppa/v1/assignments/{id}` - Delete assignment

### Submissions
- `GET /ppa/v1/submissions` - List submissions
- `GET /ppa/v1/submissions/{id}` - Get submission with files
- `POST /ppa/v1/submissions` - Create submission
- `PUT /ppa/v1/submissions/{id}` - Update submission (grading)
- `POST /ppa/v1/submissions/{id}/files` - Upload file to submission

### Categories
- `GET /ppa/v1/categories` - List categories
- `POST /ppa/v1/categories` - Create category
- `PUT /ppa/v1/categories/{id}` - Update category
- `DELETE /ppa/v1/categories/{id}` - Delete category

### Reports
- `GET /ppa/v1/reports/overview` - Assignments overview data
- `GET /ppa/v1/reports/submissions` - Submissions data for assignment

---

## Hooks for Addon Compatibility

### Actions
```php
// Assignment lifecycle
do_action( 'pressprimer_assignment_created', $assignment_id );
do_action( 'pressprimer_assignment_updated', $assignment_id );
do_action( 'pressprimer_assignment_deleted', $assignment_id );
do_action( 'pressprimer_assignment_published', $assignment_id );

// Submission lifecycle
do_action( 'pressprimer_assignment_submission_created', $submission_id );
do_action( 'pressprimer_assignment_submission_submitted', $submission_id );
do_action( 'pressprimer_assignment_submission_graded', $submission_id, $score );
do_action( 'pressprimer_assignment_submission_returned', $submission_id );

// File lifecycle
do_action( 'pressprimer_assignment_file_uploaded', $file_id, $submission_id );
do_action( 'pressprimer_assignment_file_deleted', $file_id );

// Grading
do_action( 'pressprimer_assignment_before_grade', $submission_id );
do_action( 'pressprimer_assignment_after_grade', $submission_id, $score, $feedback );

// Display
do_action( 'pressprimer_assignment_before_render', $assignment_id );
do_action( 'pressprimer_assignment_after_render', $assignment_id );
do_action( 'pressprimer_assignment_before_submission_form', $assignment_id );
do_action( 'pressprimer_assignment_after_submission_form', $assignment_id );
```

### Filters
```php
// Assignment data
apply_filters( 'pressprimer_assignment_data', $data, $assignment_id );
apply_filters( 'pressprimer_assignment_settings', $settings, $assignment_id );
apply_filters( 'pressprimer_assignment_allowed_file_types', $types, $assignment_id );
apply_filters( 'pressprimer_assignment_max_file_size', $size, $assignment_id );

// Submission data
apply_filters( 'pressprimer_assignment_submission_data', $data, $submission_id );
apply_filters( 'pressprimer_assignment_can_submit', $can_submit, $user_id, $assignment_id );
apply_filters( 'pressprimer_assignment_can_resubmit', $can_resubmit, $user_id, $assignment_id );

// Grading
apply_filters( 'pressprimer_assignment_calculate_late_penalty', $penalty, $submission_id );
apply_filters( 'pressprimer_assignment_final_score', $score, $submission_id );
apply_filters( 'pressprimer_assignment_passed', $passed, $submission_id );

// Display
apply_filters( 'pressprimer_assignment_render_output', $html, $assignment_id );
apply_filters( 'pressprimer_assignment_grading_interface', $html, $submission_id );

// Reports
apply_filters( 'pressprimer_assignment_report_columns', $columns, $report_id );
apply_filters( 'pressprimer_assignment_report_data', $data, $report_id );
```

---

## Success Criteria

### WordPress.org Approval
- [ ] Pass Plugin Check validation
- [ ] No security issues flagged in review
- [ ] Documentation complete (readme.txt)
- [ ] Screenshots provided
- [ ] FAQ section populated

### Quality Gates
- [ ] All PHPCS checks pass
- [ ] PHP 7.4-8.4 compatibility verified
- [ ] WCAG 2.1 AA compliance verified
- [ ] Mobile responsive on all views
- [ ] LearnDash integration tested
- [ ] TutorLMS integration tested

### Performance
- [ ] Assignment load < 500ms
- [ ] File upload handles 50MB without timeout
- [ ] Grading interface responsive with 100+ submissions
- [ ] Document viewer loads within 3 seconds

---

## Development Phases

### Phase 1: Foundation (Weeks 1-4)
- Plugin scaffold
- Database schema
- Basic models (Assignment, Submission, File)
- Admin menu structure
- Settings page

### Phase 2: Core Admin (Weeks 5-8)
- Assignment CRUD
- Categories/Tags
- React admin UI
- File upload system
- Document viewer

### Phase 3: Grading & Frontend (Weeks 9-12)
- Grading interface
- Student submission flow
- Blocks and shortcodes
- Themes
- Reports

### Phase 4: Integration & Polish (Weeks 13-16)
- LearnDash integration
- TutorLMS integration
- Uncanny Automator integration
- Onboarding flow
- Testing and bug fixes
- WordPress.org submission

---

## Related Documents

- `PROJECT.md` - Vision and business context
- `CLAUDE.md` - Development guide
- `architecture/DATABASE.md` - Database schema
- `architecture/CONVENTIONS.md` - Naming conventions
- `architecture/SECURITY.md` - Security patterns
- `architecture/HOOKS.md` - Actions and filters reference
