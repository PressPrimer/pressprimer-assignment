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

After merging to main: build fresh zip, test clean install, test upgrade from previous version, verify all features work.

### Coordinated Releases

When changes span multiple plugins: make free plugin changes first → test addon compatibility → release free plugin → release addon → document in both changelogs.

### Cross-Plugin Investigation Required

**Always search ALL 4 plugins** when looking for patterns, implementations, or references. The PressPrimer Assignment ecosystem consists of:

1. `pressprimer-assignment/` — Free plugin (WordPress.org)
2. `pressprimer-assignment-educator/` — Educator addon ($149/yr)
3. `pressprimer-assignment-school/` — School addon ($299/yr)
4. `pressprimer-assignment-enterprise/` — Enterprise addon ($499/yr)

**Also search PressPrimer Quiz** for any pattern involving:
- Groups and group membership (shared tables — Quiz is the reference implementation)
- Teacher role behavior and data isolation
- Admin UI patterns (Quiz's React components are the reference for all admin UI)
- Email notification HTML structure (both plugins use the same template)
- Frontend theme system (CSS variables, RTL, print styles)

**Before building any new feature:**
1. Search all 4 Assignment plugin codebases for similar existing implementations
2. Study how Quiz handles the same concern — it is the reference implementation for shared patterns
3. Match the established patterns exactly — do not reinvent solutions that already exist
4. If a pattern exists in one addon, it is the reference implementation for all addons

**Never conclude something "doesn't exist" after checking only 1-2 plugins.**

### Study Existing Implementations First

**Before building any new page, component, or feature, ALWAYS find and read the closest existing equivalent.** Do not start writing code until you understand the established patterns.

For example:
- Building a new **report page**? Read the existing Reports page in the free plugin first
- Building a new **admin list**? Read an existing list page in the same plugin first
- Building a new **REST endpoint**? Read an existing controller in the same plugin first
- Building a new **settings panel**? Read `src/admin/pages/Settings/` first
- Building a new **grading feature**? Read `src/grading/` first
- Building anything involving **groups**? Read Quiz's Educator addon group implementation first

**What to study in existing implementations:**
1. HTML structure and CSS class names (exact wrapper elements)
2. How data is fetched (API paths, hooks, error handling)
3. How different user roles are handled (admin vs teacher vs student)
4. How the feature integrates with addons (filters, actions, white-label)
5. What libraries and components are used (Ant Design vs native HTML)
6. Loading states, empty states, and error states

### Role-Based Data Access (CRITICAL)

**ALL data output must consider the user's role once the Educator addon is active.** The Educator addon introduces a data isolation system where teachers only see data from students in their groups, while admins see everything.

This applies to every endpoint, report, and list that a teacher could query.

#### How It Works

The free plugin exposes a filter hook that the Educator addon uses to inject user visibility constraints:

```php
// In any service method or REST endpoint that returns submission/student data:
$visible_user_ids = apply_filters(
    'pressprimer_assignment_visible_user_ids',
    null,              // null = no constraint (default for admin, or when Educator not active)
    get_current_user_id()
);

// null    = no constraint — admin sees all data
// array() = empty — no access (should return empty result)
// array(1, 2, 3) = restrict to these user IDs (teacher sees only their students)

if ( null !== $visible_user_ids ) {
    if ( empty( $visible_user_ids ) ) {
        return []; // No access
    }
    // Add WHERE user_id IN (...) to query
}
```

#### Checklist for New Reports/Endpoints

- [ ] Does the endpoint include the `pressprimer_assignment_visible_user_ids` filter?
- [ ] Does the SQL query accept an optional user ID constraint?
- [ ] Are aggregate calculations (averages, counts) designed to accept a user ID scope?
- [ ] Does the response change appropriately for admin vs teacher when Educator is active?
- [ ] Have you tested that a teacher only sees their own students' data?

---

## WordPress.org Coding Standards

These rules were established during the PressPrimer Quiz WordPress.org plugin review process. **All code must follow these standards.**

---

## Prefixing (CRITICAL)

WordPress.org requires **minimum 4 characters** for global namespace identifiers.

```php
// CORRECT - full prefix
define( 'PRESSPRIMER_ASSIGNMENT_VERSION', '1.0.0' );
function pressprimer_assignment_init() {}
set_transient( 'pressprimer_assignment_cache', $data );
wp_localize_script( 'ppa-submission', 'pressprimer_assignment_data', $data );

// WRONG - 3 character prefix (REJECTED)
define( 'PPA_VERSION', '1.0.0' );
function ppa_init() {}
set_transient( 'ppa_cache', $data );
wp_localize_script( 'ppa-submission', 'ppaData', $data );
```

**Items that require the full `pressprimer_assignment_` prefix:**
- All `define()` constants
- All global functions
- All class names
- All hook names (do_action, apply_filters)
- All AJAX action names
- All option names
- **All transient names** (commonly missed!)
- All user meta and post meta keys
- All shortcode names
- **All wp_localize_script object names** (commonly missed!)
- **All capability names** (commonly missed — they are global identifiers checked via `current_user_can()` from anywhere on the site, including from addons; using a short prefix produces orphan caps that addons end up checking against the wrong name)

**Short `ppa_` prefix is acceptable for:** CSS classes, JavaScript namespace (`PPA`), REST API namespace (`ppa/v1`), database table names (`wp_ppa_`), nonces — these are not globally registered PHP identifiers.

**The free plugin's actual capabilities** (defined in `class-ppa-capabilities.php`):

| Capability | Holder | Purpose |
|---|---|---|
| `pressprimer_assignment_manage_all` | Admin | Full data access; bypasses ownership checks |
| `pressprimer_assignment_manage_own` | Admin + Teacher | Baseline access — Dashboard, Assignments, Submissions, Grading, Categories, Tags |
| `pressprimer_assignment_view_reports` | Admin + Teacher | View Reports page |
| `pressprimer_assignment_manage_settings` | Admin only | Settings page |

Addons should reuse these caps for free-plugin pages and REST endpoints. Addons that introduce their own pages (e.g., Groups, Rubrics) may define addon-scoped caps using their own full prefix (e.g., `pressprimer_assignment_educator_manage_own_groups`) — never short prefixes.

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

### json_decode() Is NOT Sanitization

```php
// BAD - json_decode doesn't sanitize
$data = json_decode( wp_unslash( $_POST['data'] ), true );
// Using $data directly - NOT SAFE

// GOOD - Sanitize each field after decoding
$raw = json_decode( wp_unslash( $_POST['data'] ), true );
if ( ! is_array( $raw ) ) {
    $raw = [];
}
$data = [
    'title'    => isset( $raw['title'] ) ? sanitize_text_field( $raw['title'] ) : '',
    'feedback' => isset( $raw['feedback'] ) ? wp_kses_post( $raw['feedback'] ) : '',
    'score'    => isset( $raw['score'] ) ? floatval( $raw['score'] ) : 0.0,
];
```

### Never Iterate Over Entire Superglobals

```php
// WRONG - Processing ALL parameters
foreach ( $_POST as $key => $value ) { ... }

// CORRECT - Only process the expected parameters
$assignment_id = isset( $_POST['assignment_id'] ) ? absint( $_POST['assignment_id'] ) : 0;
$score         = isset( $_POST['score'] ) ? floatval( $_POST['score'] ) : 0.0;
$feedback      = isset( $_POST['feedback'] ) ? wp_kses_post( wp_unslash( $_POST['feedback'] ) ) : '';
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
// WRONG - style attribute may be stripped by wp_kses
echo '<div style="display: none;">';

// CORRECT - Use CSS classes
echo '<div class="ppa-hidden">';
// With CSS: .ppa-hidden { display: none !important; }
```

### wp_add_inline_style() Needs CSS Sanitization

```php
// WRONG - Unsanitized CSS
$css = $this->get_theme_css();
wp_add_inline_style( 'ppa-submission', $css ); // REJECTED

// CORRECT - Build CSS only from individually validated values
$primary   = sanitize_hex_color( $settings['primary_color'] ) ?: '#0073aa';
$max_width = absint( $settings['max_width'] ) ?: 800;
$css = sprintf(
    '.ppa-assignment { --ppa-primary: %s; --ppa-max-width: %dpx; }',
    $primary,
    $max_width
);
wp_add_inline_style( 'ppa-submission', $css );
```

---

## File Upload Security (CRITICAL)

### Always Validate Before Processing

Never pass `$_FILES` directly to processing functions. Validate upload error, extension, and MIME type first:

```php
// 1. Check upload succeeded
if ( ! isset( $_FILES['submission_file'] ) || $_FILES['submission_file']['error'] !== UPLOAD_ERR_OK ) {
    wp_send_json_error( [ 'message' => __( 'Upload failed.', 'pressprimer-assignment' ) ] );
}

// 2. Check extension against whitelist
$allowed_extensions = [ 'pdf', 'docx', 'txt', 'rtf', 'jpg', 'jpeg', 'png', 'gif' ];
$extension = strtolower( pathinfo( $_FILES['submission_file']['name'], PATHINFO_EXTENSION ) );
if ( ! in_array( $extension, $allowed_extensions, true ) ) {
    wp_send_json_error( [ 'message' => __( 'File type not allowed.', 'pressprimer-assignment' ) ] );
}

// 3. Verify actual MIME type via finfo (magic bytes)
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
$mime = finfo_file( $finfo, $_FILES['submission_file']['tmp_name'] );
finfo_close( $finfo );

if ( ! in_array( $mime, array_values( $allowed_mimes ), true ) ) {
    wp_send_json_error( [ 'message' => __( 'File content does not match allowed types.', 'pressprimer-assignment' ) ] );
}
```

### Store Files Outside Webroot

```php
// Files stored in wp-content/uploads/ppa-submissions/ with .htaccess protection
// Served via PHP script that checks permissions
```

---

## Prohibited Code Patterns

### Never Use These

```php
eval()            // Security risk - REJECTED
create_function() // Deprecated - REJECTED
extract()         // Security risk - REJECTED
goto              // REJECTED
```

### Heredoc/Nowdoc Syntax Is Prohibited

```php
// WRONG - Heredoc not allowed
$html = <<<HTML
<div class="my-class">Content</div>
HTML;

// CORRECT - Use string concatenation or sprintf
$html = '<div class="' . esc_attr( $class ) . '">' . esc_html( $content ) . '</div>';
```

### No Inline Script/Style Tags in PHP

```php
// WRONG - Inline tags rejected
?>
<script>var data = <?php echo wp_json_encode( $data ); ?>;</script>
<?php

// CORRECT - Use WordPress functions
wp_localize_script( 'ppa-submission', 'pressprimer_assignment_data', $data );
wp_add_inline_script( 'ppa-submission', 'console.log("loaded");' );
```

---

## Admin UI Standards (React + Ant Design)

**All WordPress admin pages MUST use React with Ant Design for consistency.** Do not use plain PHP forms or vanilla JavaScript for admin interfaces.

### Component Library

Use Ant Design 5.x for all admin interfaces. This matches PressPrimer Quiz for a consistent experience.

```javascript
import { Button, Table, Form, Input, Select, Modal, message } from 'antd';
```

### API Calls Pattern

```javascript
// Use @wordpress/api-fetch for all REST calls
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

### apiFetch Path Convention (CRITICAL)

**Always use relative paths with `apiFetch`.** The `@wordpress/api-fetch` package automatically prepends the site's REST root URL.

```javascript
// CORRECT — relative path
apiFetch({ path: '/ppa/v1/assignments' });
apiFetch({ path: `/ppa/v1/submissions/${submissionId}` });

// WRONG — full URL (apiFetch will double-prepend the root)
apiFetch({ path: restUrl + 'assignments' });       // Results in /wp-json/wp-json/...
apiFetch({ path: window.pressprimer_assignment_data.restUrl }); // Same problem
```

**REST namespace paths by plugin:**
- Free plugin: `/ppa/v1/*`
- Educator addon: `/ppae/v1/*`
- School addon: `/ppas/v1/*`
- Enterprise addon: `/ppaent/v1/*`

**Do NOT pass `rest_url()` output to React components as an API base URL.** If localized data includes a `restUrl` field, it is for reference/display only, never as an `apiFetch` path.

### Form Field Widths

| Field Type | Width |
|------------|-------|
| Short text (title) | `300px` or `style={{ width: 300 }}` |
| Long text (description) | `500px` or `maxWidth: 500` |
| Number inputs | `150px` |
| Select dropdowns | `300px` (match related text inputs) |
| Full width | `style={{ width: '100%' }}` |

### UI Component Patterns

These patterns are established in the Quiz codebase and must be matched for visual consistency.

**Color palette:**

| Use Case | Color |
|----------|-------|
| Primary/Active border | `#1890ff` |
| Primary/Active background | `#e6f7ff` |
| Default border | `#d9d9d9` |
| Success | `#52c41a` |
| Warning | `#faad14` |
| Error/Danger | `#ff4d4f` |
| Secondary text | `#8c8c8c` |
| WordPress admin blue | `#2271b1` |

**Button patterns:**

```jsx
// Primary action
<Button type="primary" icon={<SaveOutlined />}>
    {__('Save', 'pressprimer-assignment')}
</Button>

// Danger action
<Button danger onClick={handleDelete}>
    {__('Delete', 'pressprimer-assignment')}
</Button>

// Loading state
<Button type="primary" loading={saving}>
    {saving ? __('Saving...', 'pressprimer-assignment') : __('Save', 'pressprimer-assignment')}
</Button>
```

**Empty states:**

```jsx
<Empty description={__('No submissions yet.', 'pressprimer-assignment')} />

// With action
<Empty description={__('No assignments yet.', 'pressprimer-assignment')}>
    <Button type="primary">{__('Create Assignment', 'pressprimer-assignment')}</Button>
</Empty>
```

---

## Required in Distribution

### External Services Disclosure

If the plugin connects to external services (OpenAI, Anthropic, etc.), readme.txt MUST include:

```
== External Services ==

This plugin connects to [Service Name] for [purpose].
- When: [When data is sent]
- What data: [What is transmitted]
- Terms of Service: [URL]
- Privacy Policy: [URL]
```

### Files That Must NOT Be in Release ZIP

- `.git`, `.gitignore`, `.gitattributes`
- `node_modules`, `package-lock.json`
- `.wordpress-org` folder
- Test directories (`tests/`, `spec/`)
- Config files (`phpunit.xml`, `phpcs.xml.dist`, `webpack.config.js`)
- IDE folders (`.idea`, `.vscode`)
- `.env` files

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

## Commit Message Conventions

Use the prefixes from the **Changelog Discipline** table above (`feat:`, `fix:`, `perf:`, `refactor:`, `docs:`, `chore:`, `wip:`, `test:`, `style:`). Write changelog-worthy entries as **user-facing descriptions** for Knowledge Base articles:

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
