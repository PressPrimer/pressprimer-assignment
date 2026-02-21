# PressPrimer Assignment - Project Vision

## Product Identity

**Name:** PressPrimer Assignment  
**Tagline:** Document-based assessment for WordPress educators.  
**Type:** WordPress plugin for assignment submission and grading  
**Brand:** Part of the PressPrimer plugin suite  

## The Problem We Solve

WordPress LMS platforms provide robust course delivery but lack sophisticated assignment management. Instructors using LearnDash, TutorLMS, or other WordPress LMS plugins face a frustrating gap: they can create lessons and quizzes, but managing document-based assignments—essays, reports, projects—requires cobbling together contact forms, file upload plugins, and manual email workflows.

Meanwhile, dedicated assignment platforms like Turnitin or Canvas assignments are either too expensive, require platform migration, or don't integrate with the WordPress ecosystem instructors have already invested in.

## Our Solution

PressPrimer Assignment brings professional assignment management to WordPress. Instructors can create assignments with detailed instructions, due dates, and scoring criteria. Students submit documents through a clean, drag-and-drop interface. Grading happens in a streamlined interface with in-browser document viewing. The entire workflow lives within WordPress, integrating seamlessly with existing LMS courses.

Combined with PressPrimer Quiz, educators get a complete assessment suite—objective testing AND subjective evaluation—all within WordPress.

## Target Users

**Primary Markets:**
- Individual educators using LearnDash, TutorLMS, or other WordPress LMS platforms
- University departments with 50-500 students
- Corporate training teams managing employee development
- Educational entrepreneurs running course businesses
- Professional associations delivering certification programs

**User Personas:**
1. **Sarah the Professor** - Uses LearnDash for her certificate program. Needs to collect essays, grade them efficiently, and track student progress alongside her quizzes.
2. **Mike the Training Manager** - Runs compliance training for 2,000 employees. Needs document submissions with audit trails and integration with existing LMS workflows.
3. **Lisa the Course Creator** - Sells writing courses online. Needs beautiful submission interfaces and efficient feedback workflows to serve hundreds of students.

## Core Principles

### 1. Document-Focused
Built around the document submission workflow. Clean viewing, efficient grading, clear feedback. Every feature supports the core loop: assign → submit → grade → return.

### 2. WordPress-Native
Deep LMS integration, not bolted-on compatibility. Works seamlessly with LearnDash and TutorLMS. Feels like a natural extension of WordPress.

### 3. PressPrimer Family
Shares design language, settings patterns, role models, and even data (Groups) with PressPrimer Quiz. Consistent experience across the product suite.

### 4. Accessible by Default
Full keyboard navigation, screen reader support, WCAG 2.1 AA compliance. Every learner can submit and every instructor can grade.

### 5. Scale-Ready
Custom database tables, proper indexing, efficient file handling. From 10 students to 10,000 employees.

## Business Model

### Free (WordPress.org)
Full-featured assignment platform: unlimited assignments, file uploads, in-browser document viewing, basic grading, reports, LearnDash and TutorLMS integration. Admin-only management. Not a trial—genuinely useful forever.

### Educator ($149/year - 1 site)
For individual teachers who need more: Teacher role access, rubric builder for structured grading, AI-powered proofreading assistance, import/export, Groups integration for classroom management.

### School ($299/year - 3 sites)
For departments and organizations: Peer review workflows, inline document annotation, xAPI/LRS integration for compliance reporting, multi-teacher coordination.

### Enterprise ($499/year - 5 sites)
For large organizations: White-label branding, comprehensive audit logging, plagiarism detection integration, submission history tracking.

## Document Types (Scope)

We intentionally limit to document-based submissions in v1.0:
- **PDF** - Primary format, in-browser viewing via PDF.js
- **DOCX** - Word documents, converted to HTML for preview via Mammoth.js
- **TXT/RTF** - Plain text formats
- **Images** - JPEG, PNG, GIF for visual submissions

We do NOT support in v1.0: video uploads, audio submissions, code submissions, or presentation files. Focus beats feature sprawl.

## Key Differentiators

**vs. WordPress Form Plugins (WPForms, Gravity Forms, etc.):**
- Purpose-built for assignment workflow, not generic forms
- In-browser document viewing (not just file download links)
- Grading interface with feedback tools
- LMS integration for completion tracking
- Student dashboard with submission history

**vs. Built-in LMS Assignment Features:**
- More sophisticated grading workflows
- Better document viewing experience
- Shared Groups with PressPrimer Quiz
- Stronger reporting capabilities
- Premium features (rubrics, AI feedback, peer review)

**vs. Dedicated Assignment Platforms (Turnitin, Canvas):**
- WordPress-native (no migration)
- 10-100x more affordable
- Self-hosted data ownership
- Integrates with existing WordPress LMS

## Technical Philosophy

1. **Custom tables over CPTs** - Performance at scale, complex queries, precise data structures
2. **React admin, vanilla frontend** - Modern admin experience, fast submission interface
3. **Secure file handling** - Files stored outside webroot, validated uploads, access control
4. **Shared infrastructure** - Groups tables shared with PressPrimer Quiz
5. **WordPress standards** - Coding standards, translation ready, accessibility compliant

## Version Strategy

### v1.0 Free (Initial Release)
Prove product-market fit with genuinely useful free functionality. Target: 500+ active installs within 90 days of WordPress.org approval.

### v2.0 (Premium Launch + LMS Expansion)
Launch all three premium tiers. Add LifterLMS and LearnPress integrations to free plugin. Establish recurring revenue.

### v3.0+ (Feature Expansion)
Additional features based on user feedback: inline annotation, peer review, plagiarism detection, advanced reporting.

## Success Metrics

### Product Success
- Active installations: 500 (90 days), 2,500 (1 year)
- WordPress.org rating: 4.0+ stars
- Support resolution: <48 hours average
- Churn rate: <20% annual

### Business Success
- Paid conversions: 2-5% of active free users
- Cross-sell from Quiz customers: 10%+ conversion
- ARR target: $50K by month 12 (Assignment alone)
- Bundle attach rate: 30%+ buy both products

## Integration with PressPrimer Quiz

PressPrimer Assignment is designed to complement PressPrimer Quiz:

1. **Shared Groups** - Same Groups tables, same membership. Manage a class once, assign both quizzes and assignments.
2. **Shared Teacher Role** - `pressprimer_teacher` works for both plugins.
3. **Unified Design** - Same admin UI patterns, same themes, same settings structure.
4. **Bundle Pricing** - Discounted suite pricing for customers who need both.
5. **Combined Workflows** - Future: "Complete Quiz A before Assignment B unlocks."

## Team & Resources

**Current:** Solo founder with AI-assisted development  
**Near-term:** Contract designers for marketing assets  
**Future:** Support contractor when volume requires  

Development velocity multiplied by Claude Code and comprehensive documentation.
