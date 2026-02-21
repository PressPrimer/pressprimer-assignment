# Claude Code Instructions

This document provides guidance for AI coding assistants (Claude Code, Windsurf, etc.) on how to use the PressPrimer Assignment documentation effectively.

## Current Version Context

**Current Development Focus:** v1.0 Free Plugin  
**Status:** Pre-development  
**Last Updated:** January 2026

Version 1.0 establishes PressPrimer Assignment as a WordPress.org-listed free plugin for document-based assignment submission and grading.

## Before Starting Any Work

Always read these files first:
1. `docs/PROJECT.md` - Understand the vision and principles
2. `docs/architecture/CONVENTIONS.md` - Know the naming conventions
3. `docs/architecture/DATABASE.md` - Understand the data model
4. `docs/versions/v1.0/SCOPE.md` - Know exactly what's in scope

Then read the specific feature file(s) relevant to your task.

## Documentation Structure

```
pressprimer-assignment/
├── CLAUDE.md                        # Development guide (read first)
│
└── docs/
    ├── PROJECT.md                   # Vision, business context
    ├── CLAUDE-INSTRUCTIONS.md       # This file
    │
    ├── architecture/                # Version-agnostic technical docs
    │   ├── DATABASE.md              # Schema, tables, indexes
    │   ├── SECURITY.md              # Security patterns
    │   ├── CONVENTIONS.md           # Naming standards
    │   ├── HOOKS.md                 # Actions and filters
    │   └── rest-api.md              # REST endpoint documentation
    │
    ├── guides/                      # Process documentation
    │   ├── development-workflow.md  # Dev process with Claude Code
    │   ├── release-process.md       # Release steps
    │   └── testing-checklist.md     # QA checklist
    │
    ├── decisions/                   # Architecture Decision Records
    │   └── (to be added as needed)
    │
    ├── versions/
    │   └── v1.0/                    # Current development
    │       ├── SCOPE.md             # v1.0 scope document
    │       └── features/            # Feature specifications
    │           ├── assignment-crud.md
    │           ├── file-upload.md
    │           ├── document-viewer.md
    │           ├── grading-interface.md
    │           ├── student-dashboard.md
    │           ├── learndash-integration.md
    │           ├── tutorlms-integration.md
    │           └── reports.md
    │
    └── addons/                      # Premium tier context (future)
        ├── educator/
        ├── school/
        └── enterprise/
```

## Code Generation Guidelines

### WordPress Standards

Follow WordPress coding standards:
- PHP: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/
- JavaScript: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/
- CSS: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/

Key points:
- Use tabs for indentation (not spaces)
- Yoda conditions: `if ( 'value' === $variable )`
- Space inside parentheses: `function_name( $arg1, $arg2 )`
- Doc blocks for all functions and classes
- Escape all output, sanitize all input
- Use clear, effective documentation throughout the code

### Naming Conventions

**Critical - use these prefixes consistently:**

| Type | Prefix | Example |
|------|--------|---------|
| Database tables | `wp_ppa_` | `wp_ppa_assignments` |
| **Global PHP functions** | `pressprimer_assignment_` | `pressprimer_assignment_init()` |
| **PHP classes** | `PressPrimer_Assignment_` | `class PressPrimer_Assignment_Submission` |
| **Hooks (actions/filters)** | `pressprimer_assignment_` | `do_action( 'pressprimer_assignment_submitted' )` |
| CSS classes | `ppa-` | `.ppa-assignment-container` |
| JavaScript | `PPA` | `PPA.Submission.upload()` |
| Shortcodes | `ppa_` | `[ppa_assignment]` |
| Text domain | `pressprimer-assignment` | `__( 'Submit', 'pressprimer-assignment' )` |
| Options | `ppa_` | `get_option( 'ppa_settings' )` |
| User meta | `ppa_` | `get_user_meta( $id, 'ppa_grading_prefs' )` |
| Transients | `ppa_` | `get_transient( 'ppa_report_cache' )` |
| Capabilities | `ppa_` | `ppa_manage_all` |
| Nonces | `ppa_` | `ppa_save_assignment` |

**Important:** Global namespace identifiers (functions, classes, and hooks) must use the full `pressprimer_assignment_` or `PressPrimer_Assignment_` prefix to meet WordPress.org Plugin Check requirements. The shorter `ppa_` prefix is acceptable for internal identifiers.

### Shared Infrastructure

PressPrimer Assignment shares some infrastructure with PressPrimer Quiz:

| Shared Item | Prefix/Name | Notes |
|-------------|-------------|-------|
| Groups tables | `wp_ppq_groups`, `wp_ppq_group_members` | Created by Quiz, used by Assignment (Educator tier) |
| Teacher role | `pressprimer_teacher` | Shared WordPress role |

### Security Requirements

**Never compromise on these:**

1. **Capability checks** - Check permissions before any action
2. **Nonce verification** - All forms and AJAX calls must verify nonces
3. **Prepared statements** - Use `$wpdb->prepare()` for all SQL queries
4. **Sanitization** - Sanitize all user input immediately
5. **Escaping** - Escape all output
6. **File validation** - Extension + MIME type + magic byte checks
7. **Secure storage** - Files outside webroot, served via PHP

### Internationalization

Every user-facing string must be translatable:

```php
// Simple string
__( 'Assignment Details', 'pressprimer-assignment' )

// String with placeholders
sprintf(
    __( 'File %1$d of %2$d', 'pressprimer-assignment' ),
    $current,
    $total
)

// String that escapes output
esc_html__( 'Submit Assignment', 'pressprimer-assignment' )
```

### Accessibility Requirements

All UI components must be:
- Keyboard navigable (Tab, Enter, Space, Arrow keys)
- Screen reader compatible (ARIA labels, roles, live regions)
- High contrast friendly
- Focus visible
- Error messages announced

## Reading Feature Specifications

Each feature file in `versions/v1.0/features/` should contain:

1. **Overview** - What the feature does
2. **User Stories** - Who needs it and why
3. **Acceptance Criteria** - How we know it's done
4. **Technical Implementation** - How to build it
5. **Database Requirements** - Tables and fields needed
6. **UI/UX Requirements** - Interface specifications
7. **Edge Cases** - What could go wrong
8. **Not In Scope** - What to explicitly exclude

**Important:** Pay attention to "Not In Scope" sections. Don't build features that are listed for future versions or premium tiers.

## When Implementing

### Starting a Feature

1. Read the feature file completely
2. Check DATABASE.md for relevant tables
3. Check SECURITY.md for security patterns
4. Check CONVENTIONS.md for naming
5. Check HOOKS.md for addon compatibility hooks needed
6. Create database tables first (if needed)
7. Build models/classes
8. Build admin interface
9. Build frontend interface
10. Add hooks for extensibility
11. Test manually

### Creating Database Tables

Use the migration pattern from DATABASE.md:
- Version check before running migrations
- Use `dbDelta()` for table creation
- Add proper indexes
- Include foreign key comments

### Building Admin Interfaces

- Use Ant Design components (same as PressPrimer Quiz)
- Add settings pages under "PressPrimer Assignment" menu
- Follow the UX patterns in the feature specs
- Match Quiz admin design for consistency

### Building Frontend Interfaces

- Use shortcodes AND Gutenberg blocks
- Enqueue scripts/styles properly
- Support mobile devices
- Meet accessibility requirements
- Support all three themes (default, modern, minimal)

### Adding Addon Compatibility Hooks

When building free plugin features, add extension points for premium addons:
- Use `pressprimer_assignment_*` hook prefix
- Document hooks in `architecture/HOOKS.md`
- Provide sensible defaults for filters
- Consider what premium tiers might extend

## Testing Expectations

After each feature, manually test:
- Does the feature work as specified?
- Does it work on mobile?
- Can you navigate with keyboard only?
- Are all strings translatable?
- Is the code properly escaped and sanitized?
- Do capability checks work?
- Are files validated and stored securely?

## Questions to Ask Yourself

Before marking any feature complete:

1. Would this code pass WordPress.org plugin review?
2. Can a screen reader user complete this action?
3. Are all user inputs validated and sanitized?
4. Are files validated beyond just extension?
5. Is this translatable to other languages?
6. Is the UI consistent with PressPrimer Quiz?
7. Are appropriate addon hooks in place?

## Common Mistakes to Avoid

1. **Don't build premium features** - If it's listed for Educator/School/Enterprise tier, don't include it in Free
2. **Don't skip security** - Every input needs sanitization, every output needs escaping
3. **Don't hardcode strings** - Everything user-facing needs `__()`
4. **Don't forget mobile** - Test at 375px width
5. **Don't ignore edge cases** - What if there are 0 files? What if due date is passed?
6. **Don't break accessibility** - Tab order, focus states, ARIA labels
7. **Don't trust file uploads** - Validate extension, MIME type, AND magic bytes
8. **Don't store files in webroot** - Use protected directory with PHP delivery
9. **Don't skip addon hooks** - Features should have extension points

## WordPress.org Plugin Review Lessons

These rules were learned during the PressPrimer Quiz v1.0 review process. Violating them will cause rejection.

### SQL Security (Critical)

1. **Use `%i` for dynamic field names** - Never interpolate column names
   ```php
   // CORRECT
   $wpdb->prepare( "SELECT * FROM ... WHERE %i = %s", $field, $value );
   ```

2. **Hardcode ORDER direction** - Never interpolate ASC/DESC
   ```php
   // CORRECT
   if ( 'ASC' === $order ) {
       $wpdb->prepare( "... ORDER BY %i ASC", $field );
   } else {
       $wpdb->prepare( "... ORDER BY %i DESC", $field );
   }
   ```

3. **Always whitelist field names** - Validate against allowed values

### Output Escaping (Critical)

1. **Don't use phpcs:ignore for EscapeOutput** - It will be rejected
2. **Use wp_kses() with custom allowed HTML** - Extend post allowed HTML if needed
3. **Prefer CSS classes over inline styles** - `class="ppa-hidden"` not `style="display:none"`

### Input Sanitization (Critical)

1. **Sanitize immediately on access** - Don't use `$_POST['x']` and sanitize later
2. **Use wp_unslash() before sanitizing**
3. **Sanitize arrays element by element**

### File Upload Security (Critical)

1. **Validate extension** - Against allowed list
2. **Validate MIME type** - Using finfo_file()
3. **Validate magic bytes** - Don't trust headers alone
4. **Prevent double extensions** - Reject `file.php.pdf`
5. **Store outside webroot** - With `.htaccess` protection

### Prefix Requirements

1. **4+ character prefixes for globals** - `pressprimer_assignment_` not `ppa_` for functions, classes, hooks
2. **Short prefix okay for internal** - `ppa_` is fine for options, meta, nonces, CSS, JS

## Updating Documentation

If you discover something that should be documented:
- Note it at the end of the relevant file
- Mark it clearly as "ADDED DURING DEVELOPMENT"
- Include the date

This helps maintain documentation as a living resource.

## Relationship with PressPrimer Quiz

PressPrimer Assignment is designed to work alongside PressPrimer Quiz:

1. **Shared Groups** - Uses the same `wp_ppq_groups` tables
2. **Shared Teacher Role** - Both plugins respect `pressprimer_teacher`
3. **Consistent Design** - Same admin UI patterns, same frontend themes
4. **Same Build Process** - Same webpack config, same deployment workflow

When in doubt about implementation patterns, reference the PressPrimer Quiz codebase for consistency.
