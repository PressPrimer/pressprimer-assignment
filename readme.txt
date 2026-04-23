=== PressPrimer Assignment – Homework Submission, Document Viewer & LMS Grading Workflows ===
Contributors: pressprimer
Tags: assignment, grading, education, lms, learndash
Requires at least: 6.4
Tested up to: 6.9
Stable tag: 2.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Collect, review, and grade student assignments in WordPress. File uploads, text submissions, inline feedback, and LMS integration. Free forever.

== Description ==

**PressPrimer Assignment** is a professional assignment submission and grading plugin that gives WordPress educators everything they need to collect student work, provide meaningful feedback, and track grades, all without juggling email attachments, shared drives, or clunky LMS add-ons.

Students get a clean, focused submission experience. Teachers get a centralized grading dashboard. And you keep full control of your data on your own WordPress site.

**This is a genuinely free plugin.** Unlimited assignments, unlimited submissions, full grading tools, LMS integration, and email notifications are all included at no cost. We earn upgrades by building specialized features worth paying for, not by crippling what you get for free.

= Why PressPrimer Assignment? =

Built-in assignment tools in WordPress LMS plugins are often afterthoughts, limited to basic file uploads with no real grading workflow, limited feedback options, and dated interfaces. Dedicated assignment platforms charge monthly fees with per-student pricing that gets expensive fast.

PressPrimer Assignment delivers a focused, polished assignment workflow with the features educators actually need:

* **Flexible Submission Types** – Accept file uploads, rich text submissions, or let students choose. Support for PDF, DOCX, TXT, RTF, ODT, and image files out of the box.
* **Grade Without Leaving WordPress** – A dedicated grading queue with a side-by-side interface: the student's document renders on the left while you score and write feedback on the right. Built-in viewers for PDF, Word documents, images, and text files mean you never have to download, open, and track files on your desktop.
* **Native LMS Integration** – Works with LearnDash, Tutor LMS, LifterLMS, and LearnPress. Assignments appear in lessons, passing grades trigger lesson completion, and instructor roles are mapped automatically.
* **Secure File Handling** – Six-layer file validation and permission-based file serving. Student files are never directly accessible via URL.
* **Customizable Email Notifications** – Automatic emails for submission confirmation, grade release, and new submission alerts. Fully customizable templates with token placeholders.
* **Three Professional Themes** – Default, Modern, and Minimal themes that match the PressPrimer Quiz visual style.

= Features Included Free =

PressPrimer Assignment includes everything you need to manage assignments at any scale:

**Assignment Creation**

* Unlimited assignments with title, description, instructions, and grading guidelines
* Configurable maximum points and passing score
* Three submission types: file upload, text/rich text, or student's choice
* Configurable file types, file size limits, and multiple files per submission
* Resubmission support with configurable attempt limits

**Student Submission Experience**

* Drag-and-drop file upload with progress indicators
* TinyMCE rich text editor with live word count and auto-save drafts
* Student notes field for context or questions
* View submission status, grade, and instructor feedback
* Previous submission history with feedback for each attempt

**Grading & Feedback**

* Centralized grading queue with filter and sort
* Side-by-side grading interface: document viewer on the left, grading panel on the right
* Built-in viewers render PDF, DOCX, images, and text files directly in WordPress; no downloading required
* Automatic text extraction from PDF, DOCX, ODT, RTF, and TXT files with quality scoring
* Grading guidelines reference panel pulled from the assignment

**Email Notifications**

* Submission confirmation, grade notification, and new submission alert emails
* Customizable subject and body templates per email type
* Token placeholders: student name, assignment title, score, feedback URL, and more
* Custom from name, from email, and logo
* Test email feature in settings

**Admin Dashboard & Reports**

* Dashboard with submission statistics, activity chart, and recent submissions
* Reports page with filterable submission data

**Security & Accessibility**

* Six-layer file upload validation (extension whitelist, MIME verification, double-extension blocking, dangerous file rejection)
* Files stored outside webroot with permission-based serving through PHP
* Capability-based access control (teachers see only their own assignments)
* WordPress Privacy API integration (Tools > Export/Erase Personal Data)
* Clean uninstall with optional complete data and file removal
* Keyboard navigation, screen reader support, and reduced motion preferences

= Perfect For =

* **Course creators** using LearnDash, Tutor LMS, LifterLMS, or LearnPress who need better assignments than built-in tools
* **University departments** collecting essays, lab reports, or research papers
* **Corporate trainers** gathering certification documents and project deliverables
* **Standalone WordPress educators** who need assignments without a full LMS
* **Online course entrepreneurs** selling courses with graded assignments

= Built-in Integrations =

PressPrimer Assignment automatically detects and integrates with popular WordPress LMS plugins:

**LearnDash:** Attach assignments to lessons or topics via the editor sidebar. Passing an assignment can automatically mark the lesson or topic complete. LearnDash Group Leaders are granted teacher-level permissions to create assignments and grade their students' submissions. The "Mark Complete" button is hidden until a required assignment is passed.

**Tutor LMS:** Attach assignments to lessons via the course builder. Passing an assignment can auto-complete the lesson and trigger course completion when all lessons are done. Tutor LMS Instructors are granted teacher-level permissions. If both PressPrimer Quiz and PressPrimer Assignment are attached to a lesson, both must be passed before the lesson completes.

**LifterLMS:** Attach assignments to lessons via meta box. Passing an assignment can auto-complete the lesson and course. Works with open/free courses, enrolled students, and instructor roles.

**LearnPress:** Attach assignments to lessons via the lesson settings panel. Link passing an assignment to lesson and course completion. Works with open courses, enrolled students, and instructor roles.

**Uncanny Automator:** Four triggers available—user submits an assignment, user is graded, user passes, user fails. Each trigger includes a full set of tokens (assignment title, score, feedback, student info, grader info, and more) for use in automated workflows.

All integrations are bundled in the free version.

= Premium Features =

Unlock additional premium features at [pressprimer.com](https://pressprimer.com/pressprimer-assignment-pricing/):

* **AI-Assisted Grading** – Generate suggested scores and feedback for submissions using OpenAI or Anthropic. Rubric-aware: returns per-criterion scores when a rubric is attached. Optional auto-grade queues suggestions on submission. Teachers review, edit, and save all suggestions manually.
* **AI Proofreading** – Detect spelling and grammar issues in student submissions with one-click insertion of notes into feedback. Configurable locale for regional spelling variants.
* **Groups & Assignments** – Organize students into groups, distribute assignments with per-group due dates, and track completion progress
* **Rubric Builder** – Create analytic rubrics with criteria and performance levels, attach them to assignments for structured grading with automatic score calculation and per-criterion feedback
* **Teacher Role** – Teachers manage their own groups and grade only their students' submissions, while admins retain full access to all data
* **White-Label Branding** – Remove all PressPrimer branding and customize with your own plugin name, logos, colors, and custom CSS
* **Audit Logging** – Immutable log of every assignment, submission, grading, and settings change with configurable retention, role-based viewer, and export

= Built for Developers =

* Action hooks for assignment creation, submission, grading, and email events
* Filter hooks for submission permissions, file access, email templates, and role mapping
* Full REST API for assignments, submissions, files, categories, and statistics
* Custom database tables with automatic schema migration

= Documentation & Support =

* [Knowledge Base](https://pressprimer.com/knowledge-base/pressprimer-assignment/)

= Source Code & Development =

The full uncompressed source code for all JavaScript and CSS files is available in our public GitHub repository:

* [GitHub Repository](https://github.com/PressPrimer/pressprimer-assignment)

The `/src` directory contains all unminified source files. The plugin uses webpack for building production assets. To rebuild from source:

1. Clone the repository
2. Run `npm install` to install dependencies
3. Run `npm run build` to compile assets

== Installation ==

= Automatic Installation =

1. Go to **Plugins > Add New** in your WordPress admin
2. Search for "PressPrimer Assignment"
3. Click **Install Now** and then **Activate**
4. Navigate to ** Assignments ** in your admin menu to get started

= LMS Integration =

If you have LearnDash, Tutor LMS, LifterLMS, or LearnPress installed, integration features enable automatically. No configuration required—just edit a lesson or topic and you'll see the assignment attachment options.

== Privacy ==

PressPrimer Assignment stores student submission data (files, text, grades, and feedback) in your WordPress database under your full control. No data is transmitted to external servers. All submitted files are stored in a protected directory under `wp-content/uploads/ppa-submissions/` and served via PHP with permission checks.

The plugin integrates with the WordPress Privacy API:

* **Tools > Export Personal Data** — includes all submissions, grades, feedback, and uploaded file metadata for the requested user.
* **Tools > Erase Personal Data** — permanently deletes all submissions, grades, and uploaded files for the requested user.

Administrators can permanently delete all plugin data (database tables, uploaded files, options, and user meta) via Settings > Advanced > "Remove all data on uninstall" before uninstalling the plugin.

== Frequently Asked Questions ==

= Is this really free, or is it a limited trial? =

It's genuinely free and not locked down. PressPrimer Assignment includes unlimited assignments, unlimited submissions, full grading tools, LMS integrations, email notifications, and three professional themes in the free version. We believe in earning upgrades by offering genuinely valuable features, not by crippling the free experience.

= Does this work with LearnDash? =

Yes. PressPrimer Assignment integrates natively with LearnDash. Attach assignments to lessons or topics, and passing grades can automatically trigger lesson completion. LearnDash Group Leaders receive teacher-level permissions to create assignments and grade submissions. The integration activates automatically when LearnDash is detected.

= Does this work with Tutor LMS? =

Yes. Assignments integrate with Tutor LMS lessons via the editor sidebar and course builder. Passing grades can auto-complete lessons, and course completion triggers when all lessons are done. Tutor LMS Instructors receive teacher-level permissions automatically.

= Does this work with LifterLMS or LearnPress? =

Yes. Both LifterLMS and LearnPress are fully supported. Attach assignments to lessons, and passing grades can auto-complete lessons and courses. Instructor roles are mapped automatically. These integrations were added in version 2.0.0.

= Can I use this without an LMS plugin? =

Absolutely. PressPrimer Assignment works as a standalone plugin. Use the `[pressprimer_assignment]` shortcode to embed assignments on any page or post. The LMS integrations are a bonus that enable automatically when an LMS is detected—they don't restrict standalone use.

= Does this work with PressPrimer Quiz? =

Yes. Both plugins are part of the PressPrimer suite and are designed to work together. When both are active, they share the Groups infrastructure and Teacher role for a unified experience. Each plugin also works independently.

= What file types can students upload? =

By default: PDF, DOCX, TXT, RTF, ODT, JPG, JPEG, PNG, and GIF. Administrators can configure allowed file types per assignment. All uploads go through six layers of security validation including MIME type verification and dangerous file blocking.

= Can students submit text instead of files? =

Yes. Each assignment can be configured to accept file uploads, text/rich text submissions, or let the student choose. Text submissions use a TinyMCE editor with live word and character counts, auto-save drafts, and a 50,000-character limit.

= How does the grading interface work? =

The grading interface uses a side-by-side layout. The student's submitted document renders directly in the left panel. PDF, DOCX, images, and text files all display without downloading. The right panel has fields for score, pass/fail status, and rich text feedback, plus a reference panel showing the assignment's grading guidelines.

= Can I customize the email notifications? =

Yes. Each email type (submission confirmation, grade notification, admin alert) has its own customizable subject and body template. Templates support token placeholders for dynamic content like student name, assignment title, score, and feedback. You can also set a custom from name, from email, and upload a logo for the email header.

== Screenshots ==

1. Dashboard showing key stats, quick actions, and assignment activity
2. Upload support for a variety of document formats, including PDF, Word, and images
3. Side-by-side grading interface with embedded document viewer and feedback panel
4. Reports show how each assessment is performing
5. Assignment text editor with autosave and formatting controls

== Changelog ==

= 2.0.0 =
* Added: LifterLMS integration with lesson embedding, completion triggers, and instructor role mapping
* Added: LearnPress integration with lesson embedding, completion triggers, and instructor role mapping
* Added: Multi-format text extraction for DOCX, ODT, RTF, and TXT files with quality scoring
* Added: Unlimited resubmissions option (set max resubmissions to 0)
* Added: Grader name now shown alongside the graded date in student feedback ("Graded by X on Y")
* Added: Premium add-on support with extensibility hooks for Groups, Rubrics, AI Grading, AI Proofreading, White-Label, and Audit Logging
* Added: Status page shows addon versions and database table health for all installed addons
* Added: Reports page supports addon report cards
* Fixed: Assignment edit links in reports and TutorLMS integration now point to the correct admin page
* Fixed: Graded submissions stay visible in the grading queue until explicitly returned to the student
* Fixed: Save validation failures now switch to the tab containing the first error so the message is visible
* Fixed: Duplicate assignments no longer created when a rubric save fails after assignment creation
* Fixed: File upload error messages now show the specific constraint that was exceeded
* Fixed: Download button now triggers a file download instead of opening the file inline in the browser
* Fixed: Resubmitted assignments no longer resurface old submissions in the grading queue
* Fixed: Thin border-radius rendering on the text editor container
* Improved: Inline CSS injection uses wp_strip_all_tags() and wp_kses() sanitization
* Improved: All WP_Error codes use the full pressprimer_assignment_ prefix

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 2.0.0 =
New: LifterLMS and LearnPress integrations, multi-format text extraction, unlimited resubmissions, and premium addon support.

= 1.0.0 =
Initial release of PressPrimer Assignment. Assignment submission, grading, and LMS integration—free forever.
