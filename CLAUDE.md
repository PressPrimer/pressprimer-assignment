# PressPrimer Assignment - Development Guide

## Project Overview

PressPrimer Assignment is a document-based assignment submission and grading plugin for WordPress. It is part of the PressPrimer plugin suite, designed to complement PressPrimer Quiz.

**This plugin follows the same development patterns as PressPrimer Quiz. If you're familiar with Quiz, you'll feel at home here.**

---

## AI Development Workflow (CRITICAL)

These rules govern how AI assistants work on this codebase.

### Branching Strategy

**Work directly on the `develop` branch.** Feature branches are not required for solo development.

- `main` - Release branch, tagged versions, deploys to WordPress.org
- `develop` - Active development branch (DEFAULT)

### Commit Approval Required

**Do NOT commit changes without user approval.** Before committing:
1. Show the user what changes will be committed
2. Wait for explicit approval to commit
3. Only then run `git commit`

### Cross-Plugin Changes Require Approval

**WARN before modifying other plugins.** If working on an addon (Educator, School, Enterprise) and a change to the free plugin is needed:
1. STOP and notify the user
2. Explain what change is needed in the free plugin and why
3. Wait for approval before making changes to the free plugin
4. Coordinate releases - both plugins may need to be released together

**Also applies to PressPrimer Quiz changes.** Since Assignment shares Groups tables and the Teacher role with Quiz, changes affecting shared infrastructure require extra care.

### Prompt-Based Development

**STOP after completing each prompt.** When the user provides numbered prompts (e.g., "Prompt 4.1", "Prompt 4.2"), complete ONE prompt at a time and then STOP to allow the user to:
1. Review and test the implementation
2. Provide feedback or corrections
3. Decide whether to proceed to the next prompt

**DO NOT:**
- Automatically continue to the next prompt
- Batch multiple prompts together
- Assume the user wants you to proceed without confirmation

### Mandatory Code Quality Checks

**Run these checks on ALL code changes before requesting commit approval:**

1. **PHP Syntax Check** - On any new or modified PHP files:
   ```bash
   "/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php" -l path/to/file.php
   ```

2. **PHPCS (WordPress Coding Standards)** - On modified PHP files:
   ```bash
   "/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php" ./vendor/bin/phpcs --standard=phpcs.xml.dist --report=full path/to/file.php
   ```

3. **Security-Specific Checks** - On files handling user input, database queries, or output:
   ```bash
   "/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php" ./vendor/bin/phpcs --standard=WordPress-Extra --sniffs=WordPress.Security.EscapeOutput,WordPress.Security.ValidatedSanitizedInput,WordPress.Security.NonceVerification,WordPress.DB.PreparedSQL --report=full path/to/file.php
   ```

4. **PHP Compatibility (7.4 - 8.4)** - On new PHP files:
   ```bash
   "/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php" ./vendor/bin/phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 7.4-8.4 --extensions=php path/to/file.php
   ```

5. **JavaScript Lint** - If JavaScript was modified:
   ```bash
   npm run lint:js
   ```

6. **Build Check** - If React components were modified:
   ```bash
   npm run build
   ```

**If any check fails, fix the issues before requesting commit approval.**

### Database Migrations

**Database changes require extra care.** When modifying database schema:

1. **Backward compatibility** - Old versions of the plugin should not break with new schema
2. **Migration path** - Include migration code in `class-ppa-migrator.php`
3. **Test both directions** - New plugin with old data, and ensure clean installs work
4. **Document changes** - Note schema changes in commit message and changelog

### Deprecation Warnings

**Features should not be removed without warning.** Before removing a feature:

1. Add deprecation notice in one version (e.g., "Feature X is deprecated and will be removed in version Y")
2. Log deprecation warnings with `_deprecated_function()` or `_deprecated_argument()`
3. Actually remove the feature in the next major version
4. Document the removal in the changelog

### Version Compatibility

**Addons must be compatible with the free plugin version they require.** When making changes:

1. Check if the change affects addon compatibility
2. If addons depend on a function/hook being changed, consider the impact
3. Update `MIN_CORE_VERSION` constants in addons if breaking changes are made
4. Coordinate releases when free plugin changes require addon updates

### Changelog Discipline

**Only certain commit types appear in public changelogs:**

| Prefix | In Changelog? | Description |
|--------|---------------|-------------|
| `feat:` | Yes | New features |
| `fix:` | Yes | Bug fixes |
| `perf:` | Yes | Performance improvements |
| `refactor:` | Yes | Code changes (if user-facing) |
| `docs:` | No | Documentation only |
| `chore:` | No | Build, tooling, maintenance |
| `wip:` | No | Work in progress |
| `test:` | No | Tests only |
| `style:` | No | Code style/formatting |

**Changelog entries need to be copy/paste friendly** for Knowledge Base articles. Write them as user-facing descriptions, not technical implementation notes.

### Testing After Merges

**After merging to main, verify the release works:**

1. Build a fresh zip from main branch
2. Test installation on a clean WordPress site
3. Test upgrade from previous version
4. Verify all features work as expected

### Coordinated Releases

**When changes span multiple plugins:**

1. Make changes to free plugin first
2. Test that addon still works with updated free plugin
3. Release free plugin
4. Then release addon updates
5. Document in both changelogs that versions are coordinated

---

## WordPress.org Coding Standards

These rules were established during the PressPrimer Quiz WordPress.org plugin review process. **All code must follow these standards.**

---

## SQL Security (CRITICAL)

### Use `%i` Placeholder for Field/Column Names

```php
// CORRECT
$query = $wpdb->prepare( "SELECT * FROM {$table} WHERE %i = %s", $field, $value );

// WRONG - Do not use esc_sql() for field names
$query = $wpdb->prepare( "SELECT * FROM {$table} WHERE " . esc_sql( $field ) . " = %s", $value );
```

### Never Interpolate Variables for ORDER Direction

```php
// CORRECT - Hardcode ASC/DESC in separate branches
$is_asc = 'ASC' === strtoupper( $args['order'] );
if ( $is_asc ) {
    $query = $wpdb->prepare( "SELECT * FROM {$table} ORDER BY %i ASC", $field );
} else {
    $query = $wpdb->prepare( "SELECT * FROM {$table} ORDER BY %i DESC", $field );
}

// WRONG - Do not interpolate $order_dir even if validated
$order_dir = in_array( $order, ['ASC', 'DESC'] ) ? $order : 'DESC';
$query = $wpdb->prepare( "SELECT * FROM {$table} ORDER BY %i {$order_dir}", $field );
```

### Always Validate Field Names Against a Whitelist

```php
$queryable_fields = static::get_queryable_fields();
if ( ! in_array( $field, $queryable_fields, true ) ) {
    $field = 'id'; // Default to safe field
}
```

### No String Manipulation on SQL

```php
// WRONG - str_replace on SQL is NEVER safe
$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s", $status );
$count_sql = str_replace( 'SELECT *', 'SELECT COUNT(*)', $sql ); // REJECTED

// CORRECT - Write separate queries
$count_sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status );
```

---

## Input Sanitization (CRITICAL)

### Sanitize Immediately When Receiving Input

```php
// CORRECT
$assignment_id = isset( $_POST['assignment_id'] ) ? absint( $_POST['assignment_id'] ) : 0;

// WRONG - Sanitizing later
$assignment_id = $_POST['assignment_id'];
// ... other code ...
$assignment_id = absint( $assignment_id );
```

### Sanitize Arrays Element by Element

```php
// CORRECT
$file_types = isset( $_POST['file_types'] )
    ? array_map( 'sanitize_text_field', wp_unslash( $_POST['file_types'] ) )
    : [];

// For nested arrays with mixed types
$data_raw = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : [];
$data = [
    'title'       => isset( $data_raw['title'] ) ? sanitize_text_field( $data_raw['title'] ) : '',
    'max_points'  => isset( $data_raw['max_points'] ) ? absint( $data_raw['max_points'] ) : 100,
    'description' => isset( $data_raw['description'] ) ? wp_kses_post( $data_raw['description'] ) : '',
];
```

---

## Output Escaping (CRITICAL)

### Use wp_kses() Instead of phpcs:ignore

```php
// WRONG - Will be rejected by WordPress.org
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo $html;

// CORRECT - Define allowed HTML and use wp_kses()
$allowed_html = wp_kses_allowed_html( 'post' );
$allowed_html['input'] = [
    'type'  => true,
    'name'  => true,
    'value' => true,
    'class' => true,
];
echo wp_kses( $html, $allowed_html );
```

### Use CSS Classes Instead of Inline Styles

```php
// WRONG - style attribute may be stripped
echo '<div style="display: none;">';

// CORRECT - Use CSS classes
echo '<div class="ppa-hidden">';
```

---

## File Upload Security (CRITICAL)

### Validate File Types Thoroughly

```php
// Check extension
$allowed_extensions = [ 'pdf', 'docx', 'txt', 'rtf', 'jpg', 'jpeg', 'png', 'gif' ];
$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
if ( ! in_array( $extension, $allowed_extensions, true ) ) {
    return new WP_Error( 'invalid_extension', __( 'File type not allowed.', 'pressprimer-assignment' ) );
}

// Check MIME type
$allowed_mimes = [
    'pdf'  => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'txt'  => 'text/plain',
    'rtf'  => 'application/rtf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
];
$finfo = finfo_open( FILEINFO_MIME_TYPE );
$mime = finfo_file( $finfo, $tmp_path );
finfo_close( $finfo );

if ( ! in_array( $mime, array_values( $allowed_mimes ), true ) ) {
    return new WP_Error( 'invalid_mime', __( 'File content does not match allowed types.', 'pressprimer-assignment' ) );
}
```

### Store Files Outside Webroot

```php
// Files stored in wp-content/uploads/ppa-submissions/ with .htaccess protection
// Served via PHP script that checks permissions
```

---

## Admin UI Standards (React + Ant Design)

### Component Library

Use Ant Design 5.x for all admin interfaces. This matches PressPrimer Quiz for a consistent experience.

```javascript
import { Button, Table, Form, Input, Select, Modal, message } from 'antd';
```

### API Calls Pattern

```javascript
// Use wp.apiFetch for all REST calls
import apiFetch from '@wordpress/api-fetch';

const saveAssignment = async (data) => {
    try {
        const result = await apiFetch({
            path: '/ppa/v1/assignments',
            method: 'POST',
            data,
        });
        message.success(__('Assignment saved.', 'pressprimer-assignment'));
        return result;
    } catch (error) {
        message.error(error.message || __('Failed to save.', 'pressprimer-assignment'));
        throw error;
    }
};
```

### Form Field Widths

| Field Type | Width |
|------------|-------|
| Short text (title) | `300px` or `style={{ width: 300 }}` |
| Long text (description) | `500px` or `maxWidth: 500` |
| Number inputs | `150px` |
| Select dropdowns | `300px` (match related text inputs) |
| Full width | `style={{ width: '100%' }}` |

---

## File Structure

```
pressprimer-assignment/
├── pressprimer-assignment.php     # Main plugin file
├── uninstall.php                  # Cleanup on uninstall
├── readme.txt                     # WordPress.org readme
├── package.json
├── composer.json
│
├── includes/
│   ├── class-ppa-activator.php
│   ├── class-ppa-deactivator.php
│   ├── class-ppa-autoloader.php
│   ├── class-ppa-plugin.php
│   ├── class-ppa-addon-manager.php
│   │
│   ├── models/
│   │   ├── class-ppa-assignment.php
│   │   ├── class-ppa-submission.php
│   │   ├── class-ppa-submission-file.php
│   │   ├── class-ppa-category.php
│   │   └── class-ppa-model.php
│   │
│   ├── admin/
│   │   ├── class-ppa-admin.php
│   │   ├── class-ppa-admin-assignments.php
│   │   ├── class-ppa-admin-submissions.php
│   │   ├── class-ppa-admin-reports.php
│   │   ├── class-ppa-admin-settings.php
│   │   ├── class-ppa-admin-categories.php
│   │   └── class-ppa-onboarding.php
│   │
│   ├── frontend/
│   │   ├── class-ppa-frontend.php
│   │   ├── class-ppa-shortcodes.php
│   │   ├── class-ppa-assignment-renderer.php
│   │   ├── class-ppa-submission-handler.php
│   │   └── class-ppa-document-viewer.php
│   │
│   ├── api/
│   │   └── class-ppa-rest-controller.php
│   │
│   ├── services/
│   │   ├── class-ppa-grading-service.php
│   │   ├── class-ppa-file-service.php
│   │   ├── class-ppa-email-service.php
│   │   └── class-ppa-statistics-service.php
│   │
│   ├── integrations/
│   │   ├── class-ppa-learndash.php
│   │   ├── class-ppa-tutorlms.php
│   │   └── uncanny-automator/
│   │       ├── class-ppa-automator-loader.php
│   │       ├── class-ppa-automator-integration.php
│   │       └── triggers/
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
│   │   ├── submission.css
│   │   ├── grading.css
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
├── build/                         # Compiled React/JS (generated)
│
├── src/                           # React source files
│   ├── assignment-editor/
│   ├── grading-interface/
│   ├── dashboard/
│   ├── reports/
│   ├── settings-panel/
│   └── onboarding/
│
├── languages/
│   └── pressprimer-assignment.pot
│
├── vendor/                        # Composer dependencies
│   └── pdfparser/                 # For text extraction if needed
│
└── .wordpress-org/                # WordPress.org assets
```

## Database

- Tables use prefix: `{wp_prefix}ppa_`
- **Exception:** Groups tables use `{wp_prefix}ppq_` prefix (shared with PressPrimer Quiz)
- Schema defined in: `includes/database/class-ppa-schema.php`
- Migrations in: `includes/database/class-ppa-migrator.php`
- Current DB version: Check `PRESSPRIMER_ASSIGNMENT_DB_VERSION` constant

---

## Running Code Quality Checks

```bash
# PHP Syntax check
"/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php" -l path/to/file.php

# PHPCS (WordPress coding standards)
"/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php" ./vendor/bin/phpcs --standard=phpcs.xml.dist --report=full path/to/file.php

# Security-specific checks
"/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php" ./vendor/bin/phpcs --standard=WordPress-Extra --sniffs=WordPress.Security.EscapeOutput,WordPress.Security.ValidatedSanitizedInput,WordPress.Security.NonceVerification,WordPress.DB.PreparedSQL --report=full path/to/file.php

# PHP Compatibility (7.4 - 8.4)
"/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php" ./vendor/bin/phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 7.4-8.4 --extensions=php path/to/file.php
```

---

## Building and Releasing

### Build Plugin ZIP
```bash
npm run plugin-zip
```
This creates `dist/pressprimer-assignment.zip`

### Release Process
1. Update version in `pressprimer-assignment.php` and `readme.txt`
2. Commit to `main`
3. Create tag: `git tag v1.0.0 && git push origin v1.0.0`
4. Create GitHub Release from the tag
5. Workflow automatically deploys to WordPress.org

### GitHub Actions Workflow
- Location: `.github/workflows/deploy-to-wordpress-org.yml`
- Triggers on: GitHub Release publish OR manual workflow_dispatch
- Deploys to: WordPress.org SVN (`/trunk/` and `/tags/{version}`)

---

## Branching Strategy

- `main` - Release branch, tagged versions, deploys to WordPress.org
- `develop` - Default branch for active development (work directly here)

**Note:** Feature branches (`feature/*`, `release/*`) are optional and typically not needed for solo development. Work directly on `develop` and merge to `main` when ready to release.

---

## Commit Message Conventions

Use these prefixes for commit messages:

### Changelog-Worthy (User-Facing Changes)

| Prefix | Changelog Shows As | Example |
|--------|-------------------|---------|
| `feat:` | **Added** | `feat: add rubric builder` |
| `fix:` | **Fixed** | `fix: correct file upload validation` |
| `perf:` | **Improved** | `perf: speed up submission loading` |
| `refactor:` | **Changed** | `refactor: simplify grading logic` |

### Non-Changelog (Internal/Maintenance)

| Prefix | Purpose | Example |
|--------|---------|---------|
| `docs:` | Documentation changes | `docs: update readme installation steps` |
| `chore:` | Build, tooling, maintenance | `chore: bump version to 1.0.1` |
| `wip:` | Work in progress | `wip: grading interface - incomplete` |
| `test:` | Test changes only | `test: add unit tests for file upload` |
| `style:` | Code formatting only | `style: fix indentation in submission.js` |

### Writing Good Changelog Entries

Changelog entries will be copied to Knowledge Base articles. Write them as **user-facing descriptions**:

```bash
# GOOD - User understands the benefit
git commit -m "feat: add option to set custom due date messages"
git commit -m "fix: late submissions now correctly apply penalty"

# BAD - Too technical, not user-friendly
git commit -m "feat: add pressprimer_assignment_due_message filter"
git commit -m "fix: apply late_penalty_percent to final_score"
```

---

## Pre-Release Checklist

Before creating a release ZIP:

1. **Prefixes** - Search for `ppa_` in transients, wp_localize_script object names, options
2. **SQL** - No variable interpolation in ORDER BY, use `%i` for field names
3. **Escaping** - No `phpcs:ignore` for EscapeOutput, use `wp_kses()` instead
4. **Inline code** - No `<script>` or `<style>` tags in PHP
5. **External services** - All disclosed in readme.txt
6. **Prohibited files** - No `.git`, `node_modules`, test files in ZIP
7. **Heredoc** - None used anywhere
8. **Array sanitization** - All $_POST arrays sanitized element by element
9. **File uploads** - Extension + MIME type + magic byte validation

---

## PressPrimer Quiz Alignment (CRITICAL)

PressPrimer Assignment must maintain visual and structural consistency with PressPrimer Quiz. Both plugins are part of the same suite and users expect a unified experience.

### CSS Variable Parity

**Every CSS custom property in Quiz must have an equivalent in Assignment.** The variable structure must mirror Quiz exactly, using the `--ppa-` prefix instead of `--ppq-`.

When adding new CSS variables to Assignment:
1. Check if Quiz has the equivalent variable
2. Use the same value (or the Assignment-appropriate equivalent)
3. Use the same naming convention (e.g., `primary-hover` not `primary-dark` for hover states)

**Reference files:**
- Quiz base CSS: `pressprimer-quiz/assets/css/quiz.css` (`:root` section)
- Quiz default theme: `pressprimer-quiz/assets/css/themes/default.css` (theme variables section)
- Assignment base CSS: `assets/css/submission.css` (`:root` section)
- Assignment default theme: `assets/css/themes/default.css` (theme variables section)

### Theme Variable Categories (Must Match Quiz)

Each theme CSS file must define these variable categories:

| Category | Variables | Example |
|----------|-----------|---------|
| Primary Colors | `primary`, `primary-hover`, `primary-dark`, `primary-light`, `primary-rgb` | `--ppa-primary: #0073aa` |
| Secondary Colors | `secondary`, `secondary-hover` | `--ppa-secondary: #50575e` |
| Status Colors | `success`, `success-light`, `success-hover`, `error`, `error-light`, `error-hover`, `warning`, `warning-light`, `info`, `info-light` | `--ppa-success: #00a32a` |
| Background Colors | `background`, `background-gray`, `background-hover`, `background-active` | `--ppa-background: #ffffff` |
| Text Colors | `text`, `text-secondary`, `text-light`, `text-inverse` | `--ppa-text: #1d2327` |
| Border Colors | `border`, `border-light`, `border-focus` | `--ppa-border: #c3c4c7` |
| Spacing | `space-xs` through `space-2xl` | `--ppa-space-md: 1rem` |
| Border Radius | `radius-sm` through `radius-full` | `--ppa-radius-md: 6px` |
| Shadows | `shadow-sm`, `shadow-md`, `shadow-lg`, `shadow-xl`, `shadow-focus` | |
| Typography | `font-sans`, `font-mono`, `font-size-xs` through `font-size-3xl`, `line-height`, `line-height-tight` | |
| Layout | `max-width` | `--ppa-max-width: 800px` |
| Transitions | `transition-fast`, `transition`, `transition-slow` | |

### RTL (Right-to-Left) Support

**All frontend CSS must include RTL overrides.** Use `[dir="rtl"]` selectors matching the Quiz pattern:

- Flip `border-left` to `border-right` on notices/alerts
- Reverse `flex-direction` on horizontal layouts (file info, status headers, submission items)
- Swap `margin-left`/`margin-right` on positioned elements
- Set `direction: rtl` and `text-align: right` on containers

### Print Styles

**All frontend CSS must include `@media print` rules.** Follow the Quiz pattern:

- Hide interactive elements (forms, buttons, upload zones)
- Remove box shadows
- Remove max-width constraints
- Replace backgrounds with `none` and borders with `1px solid #000`
- Use `page-break-inside: avoid` on content blocks

### Theme File Structure

Each theme (default, modern, minimal) must:
1. Define the complete variable set listed above
2. Scope variables to `.ppa-theme-{name}` selector
3. Include scoped selectors for `.ppa-assignment.ppa-theme-{name}` and `.ppa-my-submissions.ppa-theme-{name}`

### Translation / Internationalization

- All user-facing strings must use `__()` or `_e()` with the `pressprimer-assignment` text domain
- Use `esc_html__()` for strings in HTML context
- Use `number_format_i18n()` for numeric display
- Provide translator comments for strings with placeholders: `/* translators: %s: assignment title */`
- Support RTL in both PHP output and CSS (see RTL section above)

### When Creating New Frontend Components

Before writing CSS for any new frontend component:
1. Check if Quiz has an equivalent component
2. Match Quiz's class naming pattern (`ppa-` prefix instead of `ppq-`)
3. Match Quiz's HTML structure where applicable
4. Ensure the component works with all three themes
5. Include RTL overrides if the component has directional layout
6. Include print overrides if the component has interactive elements

---

## Frontend Component Style Guide (CRITICAL)

**NEVER invent new styles from scratch.** Always reuse existing CSS classes and patterns from `assets/css/submission.css`. Before writing any new CSS, search the stylesheet for an existing class that does what you need.

### Rule: Reuse Before You Create

When building any new frontend UI:

1. **Search `submission.css` first** for existing classes that match the need
2. **Check Quiz's equivalent** if Assignment doesn't have it yet
3. **Only create new classes** if no existing pattern covers the use case
4. **New classes must use CSS variables** — never hardcode colors, spacing, fonts, or shadows

### Assignment Component Classes

These classes exist in `assets/css/submission.css`. Use them instead of creating new styles.

#### Buttons

Quiz equivalent: `.ppq-button`, `.ppq-button-primary`, `.ppq-button-secondary` (in `quiz.css` and `themes/default.css`).

| Class | Use For | Notes |
|-------|---------|-------|
| `.ppa-button` | Base class for all buttons | Always combine with a variant |
| `.ppa-button-primary` | Primary actions (submit, save) | `--ppa-primary` background, white text |
| `.ppa-button-secondary` | Secondary actions (cancel, go back) | `--ppa-background` background, border, `--ppa-text` text |
| `.ppa-button-danger` | Destructive actions (delete) | `--ppa-error` background, white text. **Assignment-only** — Quiz does not have this. |
| `.ppa-button-small` | Compact buttons | Smaller padding and font |
| `.ppa-button-large` | Prominent buttons | Larger padding and font |
| `.ppa-button-loading` | Loading state | Adds spinner animation |

**Never style buttons with raw colors.** Always use `.ppa-button` + variant.

#### Modals / Confirmation Dialogs

Quiz equivalent: `.ppq-modal-overlay`, `.ppq-modal`, `.ppq-modal-header`, `.ppq-modal-body`, `.ppq-modal-footer` (in `admin.css`). Assignment uses `ppa-confirm-*` naming instead.

| Class | Use For |
|-------|---------|
| `.ppa-confirm-overlay` | Full-screen backdrop |
| `.ppa-confirm-dialog` | Modal container |
| `.ppa-confirm-header` | Header with title and close button |
| `.ppa-confirm-body` | Content area |
| `.ppa-confirm-footer` | Button row |

**The confirm modal is appended to `<body>`, outside `.ppa-theme-*` wrappers.** Any class used inside a modal MUST work without theme-scoped selectors. Always provide CSS variable fallbacks (e.g., `var(--ppa-text, #1d2327)`).

#### Status & Feedback

Quiz equivalent: `.ppq-notice`, `.ppq-notice-success`, `.ppq-notice-error`, `.ppq-notice-warning`, `.ppq-notice-info` (in `quiz.css`).

| Class | Use For |
|-------|---------|
| `.ppa-upload-error` | Inline error below the upload zone |

For frontend error feedback, prefer inline error messages (`Upload.showError()`) or styled modals (`SubmissionPreview.showConfirm()`) over `window.alert()`.

#### Collapsible / Expandable Sections

Verified from Quiz's `question-builder.js` (`toggleFeedback` method) and `admin.css` (`.ppq-feedback-toggle`).

**Quiz pattern — follow this exactly:**
- Clickable trigger with a `<span class="dashicons">` icon in the HTML (NOT CSS `::before` pseudo-elements)
- `dashicons-arrow-right` (collapsed) / `dashicons-arrow-down` (expanded)
- jQuery `slideDown(200)` / `slideUp(200)` for animation (NOT CSS class toggling)
- Toggle color: `--ppa-primary` (`#0073aa`) with `--ppa-primary-hover` (`#005a87`) on hover

```javascript
// Toggle handler (from Quiz's toggleFeedback)
$toggle.on('click', function() {
    var $icon = $toggle.find('.dashicons');
    if ($content.is(':visible')) {
        $content.slideUp(200);
        $icon.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
    } else {
        $content.slideDown(200);
        $icon.removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');
    }
});
```

#### Typography Colors

Verified from Quiz's `themes/default.css` — Assignment uses the same values with `--ppa-` prefix.

| Variable | Quiz Equivalent | Use For |
|----------|-----------------|---------|
| `--ppa-text` (`#1d2327`) | `--ppq-text` | Primary content, headings |
| `--ppa-text-secondary` (`#50575e`) | `--ppq-text-secondary` | Supporting text, labels |
| `--ppa-text-light` (`#787c82`) | `--ppq-text-light` | Hints, placeholders, meta info |
| `--ppa-primary` (`#0073aa`) | `--ppq-primary` | Links, toggle triggers, primary actions |

#### Spacing

Verified from Quiz's `themes/default.css` — same values with `--ppa-` prefix.

| Variable | Size | Use For |
|----------|------|---------|
| `--ppa-space-xs` | 0.25rem | Tight gaps (icon to text) |
| `--ppa-space-sm` | 0.5rem | Related element spacing |
| `--ppa-space-md` | 1rem | Section spacing, form field gaps, helper text below buttons |
| `--ppa-space-lg` | 1.5rem | Major section separation |
| `--ppa-space-xl` | 2rem | Page-level separation |

### Color Rules

1. **Never hardcode hex colors** in new CSS — always use CSS variables
2. **Hover states:** use the `-hover` variant of the same color family (e.g., `--ppa-primary` → `--ppa-primary-hover`). Never use the same color for default and hover.
3. **Text on colored backgrounds:** always `#ffffff` (white) on primary/danger/dark backgrounds
4. **Interactive elements outside theme wrappers** (modals appended to body): always include fallback values, e.g., `var(--ppa-text, #1d2327)`

---

## Important Reminders

1. **Always run PHPCS before committing** - Pre-commit hook does this automatically
2. **Test with WP_DEBUG enabled** - Catches notices and warnings
3. **Sanitize early, escape late** - Core WordPress security principle
4. **No variable interpolation in SQL** - Use placeholders exclusively
5. **4+ character prefixes** - For all global identifiers
6. **Use CSS classes instead of inline styles** - For elements that need wp_kses
7. **phpcs:ignore for escaping = REJECTION** - Always use wp_kses() instead
8. **File upload security** - Validate extension, MIME type, and magic bytes
9. **Store files outside webroot** - Serve via PHP with permission checks
10. **Reuse existing CSS classes** - Search `submission.css` before creating new styles
