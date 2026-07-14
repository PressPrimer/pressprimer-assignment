<?php
/**
 * Premium Touchpoint Registry
 *
 * The single source of truth for every inline premium upsell touchpoint in
 * the plugin. Each touchpoint is one sentence plus one link, rendered where
 * the premium feature would live, and is subject to a uniform double gate:
 * the providing addon is inactive AND the current user is an administrator
 * (`manage_options`). Teachers and students never see upsells — a teacher
 * can neither buy an upgrade nor reach a locked feature, so showing them
 * prompts is pure friction.
 *
 * The gate is resolved server-side: React surfaces receive only the
 * touchpoints the current user is eligible to see (via their localized
 * data), so no client code ever decides visibility.
 *
 * Consolidates the touchpoint set from feature 004 (rubrics, groups, xAPI,
 * white-label) with the 2.2 additions from feature 012 (annotation,
 * detection, anonymous grading). The 004 touchpoint list is superseded by
 * this registry.
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
 * Touchpoint registry class.
 *
 * @since 2.2.0
 */
class PressPrimer_Assignment_Touchpoints {

	/**
	 * The full touchpoint registry.
	 *
	 * Every touchpoint declares:
	 * - key:       Unique identifier (also used in UTM content).
	 * - feature:   The premium feature being advertised (internal label).
	 * - surface:   Which admin surface carries it: 'grading', 'editor',
	 *              or 'settings'. Matches the localized payload it ships in.
	 * - location:  Slot within the surface. The React apps render each
	 *              touchpoint at the slot with this name.
	 * - tier:      Required addon tier: 'educator', 'school', 'enterprise'.
	 * - copy:      The one-sentence prompt.
	 * - link_text: The link label.
	 * - url:       Product page URL (UTM parameters added on output).
	 *
	 * @since 2.2.0
	 *
	 * @return array<int, array<string, string>> Touchpoint definitions.
	 */
	public static function get_registry() {
		return [
			// 1. Rubrics — grading interface sidebar, below the score field.
			[
				'key'       => 'rubrics',
				'feature'   => 'Rubric grading',
				'surface'   => 'grading',
				'location'  => 'rubric-panel',
				'tier'      => 'educator',
				'copy'      => __( 'Add a rubric to this assignment to grade against specific criteria.', 'pressprimer-assignment' ),
				'link_text' => __( 'Upgrade to Educator', 'pressprimer-assignment' ),
				'url'       => 'https://pressprimer.com/pressprimer-assignment-educator/',
			],
			// 2. Groups — assignment editor sidebar, below Publishing/Status.
			[
				'key'       => 'groups',
				'feature'   => 'Student groups',
				'surface'   => 'editor',
				'location'  => 'editor-sidebar',
				'tier'      => 'educator',
				'copy'      => __( 'Assign this to a student group with a custom due date and availability window.', 'pressprimer-assignment' ),
				'link_text' => __( 'Upgrade to Educator', 'pressprimer-assignment' ),
				'url'       => 'https://pressprimer.com/pressprimer-assignment-educator/',
			],
			// 3. xAPI — Settings > Integrations, below the LMS sections.
			[
				'key'       => 'xapi',
				'feature'   => 'xAPI / LRS reporting',
				'surface'   => 'settings',
				'location'  => 'integrations-tab',
				'tier'      => 'school',
				'copy'      => __( 'Report assignment activity to your LRS with xAPI.', 'pressprimer-assignment' ),
				'link_text' => __( 'Upgrade to School', 'pressprimer-assignment' ),
				'url'       => 'https://pressprimer.com/pressprimer-assignment-school/',
			],
			// 4. White-label — Settings > General, below the branding section.
			[
				'key'       => 'white-label',
				'feature'   => 'White-label branding',
				'surface'   => 'settings',
				'location'  => 'general-tab',
				'tier'      => 'enterprise',
				'copy'      => __( 'Remove PressPrimer branding and replace it with your organization\'s name and logo.', 'pressprimer-assignment' ),
				'link_text' => __( 'Upgrade to Enterprise', 'pressprimer-assignment' ),
				'url'       => 'https://pressprimer.com/pressprimer-assignment-enterprise/',
			],
			// 5. Annotation — grading interface, document viewer toolbar area.
			[
				'key'       => 'annotation',
				'feature'   => 'Inline annotation',
				'surface'   => 'grading',
				'location'  => 'viewer-toolbar',
				'tier'      => 'school',
				'copy'      => __( 'Highlight, comment, and mark up submissions right in the viewer.', 'pressprimer-assignment' ),
				'link_text' => __( 'Upgrade to School', 'pressprimer-assignment' ),
				'url'       => 'https://pressprimer.com/pressprimer-assignment-school/',
			],
			// 6. Plagiarism / AI detection — grading interface. The 012 spec
			// says "near the submission file list"; the prompt renders at the
			// slot where Enterprise's real plagiarism panel mounts (grading
			// column) so activation replaces it in place.
			[
				'key'       => 'detection',
				'feature'   => 'Plagiarism / AI detection',
				'surface'   => 'grading',
				'location'  => 'plagiarism-panel',
				'tier'      => 'enterprise',
				'copy'      => __( 'Screen submissions for plagiarism and AI-generated content.', 'pressprimer-assignment' ),
				'link_text' => __( 'Upgrade to Enterprise', 'pressprimer-assignment' ),
				'url'       => 'https://pressprimer.com/pressprimer-assignment-enterprise/',
			],
			// 7. Anonymous grading — assignment editor, grading options section.
			[
				'key'       => 'anonymous-grading',
				'feature'   => 'Anonymous grading',
				'surface'   => 'editor',
				'location'  => 'grading-options',
				'tier'      => 'enterprise',
				'copy'      => __( 'Grade without seeing student names to reduce bias.', 'pressprimer-assignment' ),
				'link_text' => __( 'Upgrade to Enterprise', 'pressprimer-assignment' ),
				'url'       => 'https://pressprimer.com/pressprimer-assignment-enterprise/',
			],
		];
	}

	/**
	 * Get the touchpoints the current user is eligible to see on a surface.
	 *
	 * Applies the double gate server-side, uniformly, for every touchpoint:
	 *
	 * 1. The providing addon tier must be inactive (an active tier replaces
	 *    the prompt with the real feature UI).
	 * 2. The current user must be an administrator (`manage_options`).
	 *
	 * Returns a map of location slot → touchpoint payload, ready to localize.
	 * For non-admins the map is always empty, so the localized data a teacher
	 * receives contains no marketing at all.
	 *
	 * @since 2.2.0
	 *
	 * @param string $surface Surface slug: 'grading', 'editor', or 'settings'.
	 * @return array<string, array<string, string>> Map of location => touchpoint.
	 */
	public static function get_eligible_for_surface( $surface ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return [];
		}

		if ( ! class_exists( 'PressPrimer_Assignment_Upgrade_Page' ) ) {
			return [];
		}

		$eligible = [];

		foreach ( self::get_registry() as $touchpoint ) {
			if ( $surface !== $touchpoint['surface'] ) {
				continue;
			}

			if ( PressPrimer_Assignment_Upgrade_Page::tier_active( $touchpoint['tier'] ) ) {
				continue;
			}

			$eligible[ $touchpoint['location'] ] = [
				'key'      => $touchpoint['key'],
				'copy'     => $touchpoint['copy'],
				'linkText' => $touchpoint['link_text'],
				'url'      => PressPrimer_Assignment_Upgrade_Page::utm_url(
					$touchpoint['url'],
					$touchpoint['key'],
					'touchpoint'
				),
			];
		}

		return $eligible;
	}
}
