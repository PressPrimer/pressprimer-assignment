<?php
/**
 * Upgrade Page Controller
 *
 * Registers and renders the "Upgrade" submenu shown to administrators on
 * sites without the Enterprise addon. The page surfaces tier cards and a
 * feature comparison table for the three premium addons (Educator, School,
 * Enterprise). The menu, highlight styles, and page are all skipped when
 * the Enterprise addon is active — at that point the user already has the
 * full feature set.
 *
 * Ported from PressPrimer Quiz 3.0's upgrade page pattern
 * (class-ppq-upgrade-page.php) with Assignment naming and content.
 *
 * @package PressPrimer_Assignment
 * @subpackage Admin
 * @since 2.2.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Upgrade page class.
 *
 * @since 2.2.0
 */
class PressPrimer_Assignment_Upgrade_Page {

	/**
	 * Menu slug for the Upgrade page.
	 *
	 * @since 2.2.0
	 * @var string
	 */
	const MENU_SLUG = 'pressprimer-assignment-upgrade';

	/**
	 * Pricing page URL on pressprimer.com (verified slug, no fragment).
	 *
	 * @since 2.2.0
	 * @var string
	 */
	const PRICING_URL = 'https://pressprimer.com/pressprimer-assignment-pricing/';

	/**
	 * Initialize hooks.
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public function init() {
		// Register the menu after the core PPA menu (priority 99 so it lands
		// after Dashboard / Assignments / Settings, but before WP's default 100).
		add_action( 'admin_menu', [ $this, 'register_menu' ], 99 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_menu_styles' ] );
	}

	/**
	 * Register the Upgrade submenu, conditional on Enterprise not being active.
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public function register_menu() {
		if ( $this->enterprise_addon_active() ) {
			return;
		}

		add_submenu_page(
			'pressprimer-assignment',
			esc_html__( 'Upgrade PressPrimer Assignment', 'pressprimer-assignment' ),
			esc_html__( 'Upgrade', 'pressprimer-assignment' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Check whether the Enterprise addon is currently active.
	 *
	 * The Upgrade page hides itself only at the top tier — users on Educator
	 * or School still see "Upgrade" because there are higher tiers available
	 * to them.
	 *
	 * The version constant is checked first: Enterprise defines
	 * PRESSPRIMER_ASSIGNMENT_ENTERPRISE_VERSION on load and (as shipped in
	 * 2.1) never registers with the addon manager, so the constant is the
	 * authoritative signal. The addon-manager tier lookup is a fallback in
	 * case a future Enterprise build registers with the manager without
	 * defining the constant.
	 *
	 * @since 2.2.0
	 *
	 * @return bool True when the Enterprise addon is active.
	 */
	public function enterprise_addon_active() {
		return self::tier_active( 'enterprise' );
	}

	/**
	 * Check whether a premium tier's addon is currently active.
	 *
	 * The tier version constants are checked first: each addon defines its
	 * constant on load, and (as shipped in 2.1) Enterprise never registers
	 * with the addon manager, so the constant is the authoritative signal.
	 * The addon-manager tier lookup is a fallback in case a future addon
	 * build registers with the manager without defining its constant.
	 *
	 * @since 2.2.0
	 *
	 * @param string $tier Tier slug: 'educator', 'school', or 'enterprise'.
	 * @return bool True when the tier's addon is active.
	 */
	public static function tier_active( $tier ) {
		if ( ! class_exists( 'PressPrimer_Assignment_Addon_Manager' ) ) {
			// Defensive — without the manager no tier can be detected.
			return false;
		}

		$by_constant = [
			'educator'   => PressPrimer_Assignment_Addon_Manager::is_educator_active(),
			'school'     => PressPrimer_Assignment_Addon_Manager::is_school_active(),
			'enterprise' => PressPrimer_Assignment_Addon_Manager::is_enterprise_active(),
		];

		if ( ! empty( $by_constant[ $tier ] ) ) {
			return true;
		}

		$manager = PressPrimer_Assignment_Addon_Manager::get_instance();

		foreach ( array_keys( $manager->get_by_tier( $tier ) ) as $slug ) {
			if ( $manager->is_active( $slug ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Enqueue the admin menu highlight styles for the Upgrade item.
	 *
	 * Targets the submenu link by its slug-anchor href so unrelated submenu
	 * items are not painted. Uses wp_add_inline_style() on a dedicated
	 * registered handle (no raw <style> tags) and must load on every admin
	 * page — the flyout submenu is visible site-wide in wp-admin. Skipped
	 * entirely when Enterprise is active (the menu item isn't registered).
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public function enqueue_menu_styles() {
		if ( $this->enterprise_addon_active() ) {
			return;
		}

		// CSS is static apart from the class-constant slug — no user input.
		$css = sprintf(
			'#adminmenu .wp-submenu a[href$="%1$s"],
			#adminmenu .wp-submenu li.current a[href$="%1$s"] {
				background-color: #1f7a3a;
				color: #fff !important;
				font-weight: 700;
			}
			#adminmenu .wp-submenu a[href$="%1$s"]:hover,
			#adminmenu .wp-submenu a[href$="%1$s"]:focus {
				background-color: #186730;
				color: #fff !important;
			}',
			self::MENU_SLUG
		);

		wp_register_style( 'ppa-upgrade-menu', false, [], PRESSPRIMER_ASSIGNMENT_VERSION );
		wp_enqueue_style( 'ppa-upgrade-menu' );
		wp_add_inline_style( 'ppa-upgrade-menu', $css );
	}

	/**
	 * Render the upgrade page.
	 *
	 * Loads the view file with the prepared data in scope. The view file
	 * uses esc_url(), esc_html(), etc. on every dynamic value.
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'pressprimer-assignment' ),
				esc_html__( 'Permission Denied', 'pressprimer-assignment' ),
				[ 'response' => 403 ]
			);
		}

		// Defensive — if Enterprise was activated after the menu item was
		// registered (e.g., admin loaded the page in one tab, then activated
		// Enterprise in another), the direct-URL access should fail closed.
		if ( $this->enterprise_addon_active() ) {
			wp_die(
				esc_html__( 'You already have the full PressPrimer Assignment feature set.', 'pressprimer-assignment' ),
				esc_html__( 'Not Available', 'pressprimer-assignment' ),
				[ 'response' => 403 ]
			);
		}

		$features = self::get_comparison_features();
		$tiers    = self::get_tiers();

		// UTM-tag every outbound link with the page and its position.
		foreach ( $tiers as $tier_slug => $tier ) {
			$tiers[ $tier_slug ]['url'] = self::utm_url( $tier['url'], 'tier-card-' . $tier_slug );
		}

		$pricing_hero_url   = self::get_pricing_url( 'hero-cta' );
		$pricing_footer_url = self::get_pricing_url( 'footer-cta' );
		$pricing_sticky_url = self::get_pricing_url( 'sticky-bar' );

		$logo_url          = PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/images/PressPrimer-Logo-White.svg';
		$hero_mascot_url   = PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/images/reports-mascot.png';
		$footer_mascot_url = PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'assets/images/construction-mascot.png';

		// Make $this available to the view so render_cell_value() is callable.
		$upgrade_page = $this;

		include PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'includes/admin/views/upgrade-page.php';
	}

	/**
	 * Append UTM parameters identifying the page and position to a URL.
	 *
	 * Shared by every marketing surface in the plugin so outbound links
	 * carry a consistent, analyzable tag set:
	 * - utm_source:   the plugin
	 * - utm_medium:   in-product placement
	 * - utm_campaign: the surface (page) the link lives on
	 * - utm_content:  the position within that surface
	 *
	 * @since 2.2.0
	 *
	 * @param string $url      Base pressprimer.com URL.
	 * @param string $position Position slug within the surface (e.g. 'hero-cta').
	 * @param string $campaign Optional. Surface slug. Default 'upgrade-page'.
	 * @return string URL with UTM parameters appended.
	 */
	public static function utm_url( $url, $position, $campaign = 'upgrade-page' ) {
		return add_query_arg(
			[
				'utm_source'   => 'pressprimer-assignment',
				'utm_medium'   => 'plugin',
				'utm_campaign' => $campaign,
				'utm_content'  => $position,
			],
			$url
		);
	}

	/**
	 * Get the UTM-tagged pricing page URL for a given position.
	 *
	 * The #pricing fragment (verified to exist on the pricing page) is
	 * appended after the query string so the browser lands on the plans.
	 *
	 * @since 2.2.0
	 *
	 * @param string $position Position slug within the surface.
	 * @param string $campaign Optional. Surface slug. Default 'upgrade-page'.
	 * @return string UTM-tagged pricing URL.
	 */
	public static function get_pricing_url( $position, $campaign = 'upgrade-page' ) {
		return self::utm_url( self::PRICING_URL, $position, $campaign ) . '#pricing';
	}

	/**
	 * Render a single comparison-table cell value.
	 *
	 * Converts the raw row value into display HTML:
	 *   - true   → checkmark
	 *   - false  → em dash
	 *   - string → escaped text (allows row-specific notes like "1 site")
	 *
	 * Output contains only static spans and escaped text; the view runs it
	 * through wp_kses() with a matching allowlist.
	 *
	 * @since 2.2.0
	 *
	 * @param mixed $value Raw value from a feature row's tier column.
	 * @return string HTML to render inside the cell.
	 */
	public function render_cell_value( $value ) {
		if ( true === $value ) {
			return '<span class="ppa-upgrade-cell-yes" aria-label="' . esc_attr__( 'Included', 'pressprimer-assignment' ) . '">&#10003;</span>';
		}

		if ( false === $value ) {
			return '<span class="ppa-upgrade-cell-no" aria-label="' . esc_attr__( 'Not included', 'pressprimer-assignment' ) . '">&mdash;</span>';
		}

		return '<span class="ppa-upgrade-cell-note">' . esc_html( (string) $value ) . '</span>';
	}

	/**
	 * Curated comparison table contents.
	 *
	 * Every row is verified against shipped addon code (not marketing copy)
	 * as of Free 2.2 / Educator 2.1 / School 2.1 / Enterprise 2.1. Rows are
	 * added or updated by code change as part of each release — when a tier
	 * boundary changes for any feature, update this array in the same
	 * release that ships the change.
	 *
	 * Order is meaningful: it is the display order on the page. Category
	 * headers are emitted by the view whenever the `category` value changes
	 * between consecutive rows.
	 *
	 * Each row's tier value is:
	 *   - true   : feature included in that tier
	 *   - false  : not included
	 *   - string : included with a caveat (e.g., "Unlimited", "2 sites")
	 *
	 * Tiers are cumulative (School requires Educator; Enterprise requires
	 * both), so a feature available at a tier is true for every higher tier.
	 *
	 * @since 2.2.0
	 *
	 * @return array<int, array<string, mixed>> Array of feature row arrays.
	 */
	public static function get_comparison_features() {
		$core       = __( 'Core Assignment Features', 'pressprimer-assignment' );
		$groups     = __( 'Groups & Teachers', 'pressprimer-assignment' );
		$grading    = __( 'Rubrics & Grading', 'pressprimer-assignment' );
		$ai         = __( 'AI Grading & Annotation', 'pressprimer-assignment' );
		$reporting  = __( 'Reporting & Analytics', 'pressprimer-assignment' );
		$lms        = __( 'Integrations', 'pressprimer-assignment' );
		$compliance = __( 'Compliance & Branding', 'pressprimer-assignment' );

		return [
			// Core Assignment Features.
			[
				'category'   => $core,
				'feature'    => __( 'Unlimited assignments and submissions', 'pressprimer-assignment' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $core,
				'feature'    => __( 'File upload, rich text, or student\'s-choice submissions', 'pressprimer-assignment' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $core,
				'feature'    => __( 'PDF, Word, PowerPoint, text, RTF, ODT, and image files', 'pressprimer-assignment' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $core,
				'feature'    => __( 'Side-by-side grading queue with in-browser document and slide viewers', 'pressprimer-assignment' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $core,
				'feature'    => __( 'Rich text feedback with formatting shown to students', 'pressprimer-assignment' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $core,
				'feature'    => __( 'Graduated late penalty schedules with optional cutoff', 'pressprimer-assignment' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $core,
				'feature'    => __( 'Customizable email notifications', 'pressprimer-assignment' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $core,
				'feature'    => __( '3 built-in themes and appearance settings', 'pressprimer-assignment' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $core,
				'feature'    => __( 'Site licenses included', 'pressprimer-assignment' ),
				'free'       => __( 'Unlimited', 'pressprimer-assignment' ),
				'educator'   => __( '1 site', 'pressprimer-assignment' ),
				'school'     => __( '2 sites', 'pressprimer-assignment' ),
				'enterprise' => __( '5 sites', 'pressprimer-assignment' ),
			],

			// Groups & Teachers.
			[
				'category'   => $groups,
				'feature'    => __( 'Teacher role with per-teacher data isolation', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $groups,
				'feature'    => __( 'Student groups and member management', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $groups,
				'feature'    => __( 'Per-group scheduling (open and due dates)', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $groups,
				'feature'    => __( 'Student "assigned assignments" page and block', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $groups,
				'feature'    => __( 'Data cleanup tools with scheduled runs', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],

			// Rubrics & Grading.
			[
				'category'   => $grading,
				'feature'    => __( 'Rubric builder with criteria and performance levels', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $grading,
				'feature'    => __( 'Click-to-grade rubric scoring', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $grading,
				'feature'    => __( 'Student-facing rubric and grading breakdown', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $grading,
				'feature'    => __( 'Anonymous grading (per assignment)', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => false,
				'school'     => false,
				'enterprise' => true,
			],

			// AI Grading & Annotation.
			[
				'category'   => $ai,
				'feature'    => __( 'AI-assisted grading with teacher review (OpenAI & Anthropic, your keys)', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => false,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $ai,
				'feature'    => __( 'Rubric-aware AI score suggestions', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => false,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $ai,
				'feature'    => __( 'AI proofreading (spelling and grammar)', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => false,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $ai,
				'feature'    => __( 'Inline document annotation (highlights, drawings, notes)', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => false,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $ai,
				'feature'    => __( 'AI content & plagiarism detection (Winston AI, Originality.ai, GPTZero)', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => false,
				'school'     => false,
				'enterprise' => true,
			],

			// Reporting & Analytics.
			[
				'category'   => $reporting,
				'feature'    => __( 'Dashboard statistics and submission reports', 'pressprimer-assignment' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $reporting,
				'feature'    => __( 'Group completion reports', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $reporting,
				'feature'    => __( 'Per-criteria grade breakdown', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $reporting,
				'feature'    => __( 'xAPI / LRS reporting', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => false,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $reporting,
				'feature'    => __( 'Plagiarism report across submissions', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => false,
				'school'     => false,
				'enterprise' => true,
			],

			// Integrations.
			[
				'category'   => $lms,
				'feature'    => __( 'LearnDash, Tutor LMS, LifterLMS, LearnPress integration', 'pressprimer-assignment' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],
			[
				'category'   => $lms,
				'feature'    => __( 'Uncanny Automator integration', 'pressprimer-assignment' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],

			// Compliance & Branding.
			[
				'category'   => $compliance,
				'feature'    => __( 'Audit logging with retention controls', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => false,
				'school'     => false,
				'enterprise' => true,
			],
			[
				'category'   => $compliance,
				'feature'    => __( 'White-label branding (remove PressPrimer references)', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => false,
				'school'     => false,
				'enterprise' => true,
			],
			[
				'category'   => $compliance,
				'feature'    => __( 'Priority support', 'pressprimer-assignment' ),
				'free'       => false,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			],
		];
	}

	/**
	 * Tier metadata for the cards section.
	 *
	 * Highlights array drives the bulleted "what you get" list under each
	 * tier's tagline — keep these short and benefit-focused, not a feature
	 * inventory (the comparison table is for that). Content reflects what
	 * has shipped: Educator 2.1, School 2.1, Enterprise 2.1.
	 *
	 * @since 2.2.0
	 *
	 * @return array<string, array<string, mixed>> Map of slug → tier data.
	 */
	public static function get_tiers() {
		return [
			'educator'   => [
				'name'        => __( 'Educator', 'pressprimer-assignment' ),
				'tagline'     => __( 'For individual instructors and small teams', 'pressprimer-assignment' ),
				'description' => __( 'Add student groups, per-group scheduling, rubric grading, and group-level reports.', 'pressprimer-assignment' ),
				'highlights'  => [
					__( 'Student groups & per-group due dates', 'pressprimer-assignment' ),
					__( 'Rubric builder with click-to-grade scoring', 'pressprimer-assignment' ),
					__( 'Teacher role with data isolation', 'pressprimer-assignment' ),
					__( 'Group completion & per-criteria reports', 'pressprimer-assignment' ),
					__( 'Data cleanup tools', 'pressprimer-assignment' ),
				],
				'url'         => 'https://pressprimer.com/pressprimer-assignment-educator/',
			],
			'school'     => [
				'name'        => __( 'School', 'pressprimer-assignment' ),
				'tagline'     => __( 'For multi-instructor programs and institutions', 'pressprimer-assignment' ),
				'description' => __( 'Everything in Educator plus AI-assisted grading, proofreading, inline annotation, and xAPI/LRS reporting.', 'pressprimer-assignment' ),
				'highlights'  => [
					__( 'Everything in Educator', 'pressprimer-assignment' ),
					__( 'AI-assisted grading with teacher review', 'pressprimer-assignment' ),
					__( 'AI proofreading — OpenAI & Anthropic, your keys', 'pressprimer-assignment' ),
					__( 'Inline document annotation', 'pressprimer-assignment' ),
					__( 'xAPI / LRS reporting', 'pressprimer-assignment' ),
				],
				'url'         => 'https://pressprimer.com/pressprimer-assignment-school/',
				'featured'    => true,
			],
			'enterprise' => [
				'name'        => __( 'Enterprise', 'pressprimer-assignment' ),
				'tagline'     => __( 'For organizations with compliance requirements', 'pressprimer-assignment' ),
				'description' => __( 'Everything in School plus plagiarism and AI detection, anonymous grading, audit logging, and white-label branding.', 'pressprimer-assignment' ),
				'highlights'  => [
					__( 'Everything in School', 'pressprimer-assignment' ),
					__( 'AI & plagiarism detection — Winston AI (default), Originality.ai, GPTZero', 'pressprimer-assignment' ),
					__( 'Anonymous grading', 'pressprimer-assignment' ),
					__( 'Audit logging with retention controls', 'pressprimer-assignment' ),
					__( 'White-label branding', 'pressprimer-assignment' ),
				],
				'url'         => 'https://pressprimer.com/pressprimer-assignment-enterprise/',
			],
		];
	}

	/**
	 * Ordered catalog of premium report cards shown on the Reports page.
	 *
	 * The free plugin's own knowledge of the reports each premium tier adds,
	 * so the Reports page can advertise them (locked) even when the providing
	 * addon is not installed. Order is meaningful — it is the display order,
	 * grouped by tier (Educator, then School, then Enterprise) — and MUST
	 * stay stable whether a report is locked or available, so the grid does
	 * not reflow when an addon is activated.
	 *
	 * Keys, titles, descriptions, icon types, and colors mirror the cards
	 * each addon registers via `pressprimer_assignment_reports_addon_reports`
	 * (verified against Educator 2.1 and Enterprise 2.1), so a locked card
	 * sits exactly where its real card appears once the addon is active.
	 * The `group-performance` entry fronts a report School has not shipped
	 * yet (School 2.2) — when it ships, School must register it under this
	 * same key so the locked card resolves in place.
	 *
	 * @since 2.2.0
	 *
	 * @return array<int, array<string, string>> Ordered premium report catalog.
	 */
	public static function get_premium_report_catalog() {
		return [
			[
				'key'         => 'group-completion',
				'tier'        => 'educator',
				'title'       => __( 'Group Completion Reports', 'pressprimer-assignment' ),
				'description' => __( 'See per-group submission status, score distribution, and completion grids. Drill down into individual member submission histories.', 'pressprimer-assignment' ),
				'iconType'    => 'PieChartOutlined',
				'color'       => '#14b8a6',
			],
			[
				'key'         => 'per-criteria-breakdown',
				'tier'        => 'educator',
				'title'       => __( 'Per-Criteria Grade Breakdown', 'pressprimer-assignment' ),
				'description' => __( 'See how every student performs on each rubric criterion. Identify class-wide weak spots and drill into the level-by-level distribution per criterion. Filter by group when you need to compare cohorts.', 'pressprimer-assignment' ),
				'iconType'    => 'BarChartOutlined',
				'color'       => '#8b5cf6',
			],
			[
				'key'         => 'group-performance',
				'tier'        => 'school',
				'title'       => __( 'Group Performance Comparison', 'pressprimer-assignment' ),
				'description' => __( 'Compare submission rates, average scores, and grading turnaround across your groups side by side.', 'pressprimer-assignment' ),
				'iconType'    => 'TeamOutlined',
				'color'       => '#f59e0b',
			],
			[
				'key'         => 'audit-trail',
				'tier'        => 'enterprise',
				'title'       => __( 'Audit Trail', 'pressprimer-assignment' ),
				'description' => __( 'View a complete audit log of all assignment, submission, and grading activity for compliance and troubleshooting.', 'pressprimer-assignment' ),
				'iconType'    => 'AuditOutlined',
				'color'       => '#722ed1',
			],
		];
	}

	/**
	 * Premium report cards merged with the addon-registered real cards.
	 *
	 * Walks the catalog in order and resolves each entry against reality:
	 *
	 * - Tier active and the addon registered a card with the same key →
	 *   the real card, in the catalog position.
	 * - Tier active but no matching card (the user lacks the capability the
	 *   addon gates the card behind, or the installed addon version does not
	 *   ship the report yet) → skipped silently. Never show an owner of a
	 *   tier an upgrade prompt for that tier.
	 * - Tier inactive → a locked card (lock flag, tier label, pricing URL),
	 *   built for administrators only. For non-admins locked entries are
	 *   skipped, so teachers receive only reports from active tiers.
	 *
	 * Registered cards the catalog does not know about (e.g. Enterprise's
	 * Plagiarism report, future addon reports) are appended after the catalog
	 * entries in their registration order. The catalog order never changes
	 * with which tiers are active, so the grid does not reflow when an addon
	 * is enabled or disabled.
	 *
	 * @since 2.2.0
	 *
	 * @param array $registered_cards Cards from the
	 *                                pressprimer_assignment_reports_addon_reports filter.
	 * @return array<int, array<string, mixed>> Ordered report cards for the Reports app.
	 */
	public static function get_premium_report_cards( $registered_cards ) {
		if ( ! is_array( $registered_cards ) ) {
			$registered_cards = [];
		}

		$by_key = [];
		foreach ( $registered_cards as $card ) {
			if ( is_array( $card ) && ! empty( $card['key'] ) ) {
				$by_key[ (string) $card['key'] ] = $card;
			}
		}

		$tiers    = self::get_tiers();
		$is_admin = current_user_can( 'manage_options' );

		$cards        = [];
		$catalog_keys = [];

		foreach ( self::get_premium_report_catalog() as $entry ) {
			$key                  = $entry['key'];
			$tier                 = $entry['tier'];
			$catalog_keys[ $key ] = true;

			if ( self::tier_active( $tier ) ) {
				if ( isset( $by_key[ $key ] ) ) {
					$cards[] = $by_key[ $key ];
				}
				continue;
			}

			// Locked (upsell) cards are for administrators only — a teacher
			// can neither buy an upgrade nor reach a locked report.
			if ( ! $is_admin ) {
				continue;
			}

			$cards[] = [
				'key'         => $key,
				'title'       => $entry['title'],
				'description' => $entry['description'],
				'iconType'    => $entry['iconType'],
				'color'       => $entry['color'],
				'tier'        => $tier,
				'tierName'    => isset( $tiers[ $tier ]['name'] ) ? $tiers[ $tier ]['name'] : ucfirst( $tier ),
				'locked'      => true,
				'available'   => false,
				'upgradeUrl'  => self::get_pricing_url( 'locked-card-' . $key, 'reports' ),
			];
		}

		// Forward-compat: registered cards outside the catalog keep working.
		foreach ( $registered_cards as $card ) {
			if ( is_array( $card ) && ! empty( $card['key'] ) && ! isset( $catalog_keys[ (string) $card['key'] ] ) ) {
				$cards[] = $card;
			}
		}

		return $cards;
	}
}
