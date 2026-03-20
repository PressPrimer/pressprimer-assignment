# PressPrimer Assignment

Collect, review, and grade student assignments in WordPress. File uploads, text submissions, inline feedback, and LMS integration. Free forever.

## Description

PressPrimer Assignment is a professional assignment submission and grading plugin that gives WordPress educators everything they need to collect student work, provide meaningful feedback, and track grades — all without juggling email attachments, shared drives, or clunky LMS add-ons.

Students get a clean, focused submission experience. Teachers get a centralized grading dashboard. And you keep full control of your data on your own WordPress site.

## Features

- **Flexible Submission Types** — Accept file uploads (PDF, DOCX, TXT, RTF, ODT, images), rich text submissions, or let students choose
- **Side-by-Side Grading** — Dedicated grading queue with a document viewer on the left and scoring/feedback panel on the right. PDF, Word, images, and text files render directly in the browser
- **Native LMS Integration** — Works with LearnDash and Tutor LMS. Assignments appear in lessons, passing grades trigger lesson completion, and instructor roles map automatically
- **Uncanny Automator Integration** — Four triggers (submitted, graded, passed, failed) with full token support for automated workflows
- **Secure File Handling** — Six-layer file validation, permission-based file serving, and files stored outside webroot
- **Email Notifications** — Customizable templates for submission confirmation, grade release, and new submission alerts with token placeholders
- **Three Professional Themes** — Default, Modern, and Minimal, with responsive design, RTL support, and print styles
- **Privacy API Integration** — Full support for WordPress Export/Erase Personal Data tools
- **Assignment Categories** — Organize assignments with a category taxonomy
- **Resubmission Support** — Configurable attempt limits with previous submission history
- **Reports Dashboard** — Submission statistics, activity charts, and assignment performance data

## Requirements

- WordPress 6.4 or higher
- PHP 7.4 or higher

## Installation

### From WordPress.org

1. Go to **Plugins > Add New** in your WordPress admin
2. Search for **PressPrimer Assignment**
3. Click **Install Now** and then **Activate**

### Manual Installation

1. Download the latest release ZIP
2. Go to **Plugins > Add New > Upload Plugin**
3. Upload the ZIP file and click **Install Now**
4. Activate the plugin

### LMS Integration

If LearnDash or Tutor LMS is installed, integration features enable automatically. No configuration required.

## Development

### Directory Structure

```
pressprimer-assignment/
├── includes/          # Core PHP classes
│   ├── admin/         # Admin pages and settings
│   ├── api/           # REST API controllers
│   ├── database/      # Schema and migrations
│   ├── frontend/      # Shortcodes and renderers
│   ├── integrations/  # LearnDash, Tutor LMS, Uncanny Automator
│   ├── models/        # Data models (Assignment, Submission, etc.)
│   ├── services/      # Business logic (grading, files, email, stats)
│   └── utilities/     # Capabilities and helpers
├── src/               # React source (assignment editor, grading, dashboard, reports, settings)
├── build/             # Compiled JS/CSS (generated)
├── assets/            # Frontend CSS, JS, and images
├── blocks/            # Gutenberg blocks (assignment, my-submissions)
├── templates/         # Overridable PHP templates
├── languages/         # Translation files
└── pressprimer-assignment.php
```

### Setup Development Environment

```bash
# Install PHP dependencies
composer install

# Install JS dependencies
npm install

# Build production assets
npm run build

# Lint JavaScript
npm run lint:js

# Build plugin ZIP
npm run plugin-zip
```

### REST API

The plugin registers a full REST API under the `ppa/v1` namespace with endpoints for assignments, submissions, files, categories, statistics, and settings.

## Documentation

- [Knowledge Base](https://pressprimer.com/knowledge-base/pressprimer-assignment/)
- [PressPrimer Website](https://pressprimer.com)

## License

GPL v2 or later. See [LICENSE.txt](LICENSE.txt) for details.

## Author

PressPrimer — [https://pressprimer.com](https://pressprimer.com)
