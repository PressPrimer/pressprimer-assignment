<?php
/**
 * Email opt-in service
 *
 * Consent state, eligibility resolution, and the outbound relay for
 * the plugin's single email ask (the free 5-part email course). The
 * hard rules, per the 011 spec:
 *
 * - Nothing leaves the site until a user types an email and clicks
 *   the affirmative button. The relay carries the email address and
 *   the source surface tag ONLY — no segments, no environment data.
 * - One answer, remembered forever: opting in or declining on any
 *   surface permanently silences every surface, including tour
 *   relaunches. Per-surface dismissals are stored separately and are
 *   equally permanent.
 * - Relay failure is silent; the local consent record is still
 *   written and every surface completes identically.
 *
 * @package PressPrimer_Assignment
 * @subpackage Services
 * @since 2.2.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email opt-in service class
 *
 * @since 2.2.0
 */
class PressPrimer_Assignment_Email_Optin_Service {

	/**
	 * User meta key: consent record
	 *
	 * One record per user: [ 'status' => 'opted_in'|'declined',
	 * 'source' => surface, 'timestamp' => unix ]. First answer wins;
	 * it is never overwritten.
	 *
	 * @since 2.2.0
	 * @var string
	 */
	const META_CONSENT = 'pressprimer_assignment_email_optin';

	/**
	 * User meta key: per-surface dismissals
	 *
	 * Map of surface => unix timestamp. Dismissing a surface (closing
	 * its card without answering) hides that surface permanently but
	 * leaves the others available.
	 *
	 * @since 2.2.0
	 * @var string
	 */
	const META_DISMISSALS = 'pressprimer_assignment_email_optin_dismissals';

	/**
	 * Default intake endpoint on pressprimer.com
	 *
	 * A FluentCRM incoming webhook (list: Newsletter, tag:
	 * assignment-free, status: subscribed — all configured on the
	 * webhook itself).
	 *
	 * @since 2.2.0
	 * @var string
	 */
	const DEFAULT_INTAKE_URL = 'https://pressprimer.com/?fluentcrm=1&route=contact&hash=1fe25022-837e-4707-9fb6-db67769f71e5';

	/**
	 * Valid source surfaces
	 *
	 * @since 2.2.0
	 * @var string[]
	 */
	const SOURCES = [ 'wizard', 'whats-new', 'dashboard-card', 'milestone' ];

	/**
	 * Option name: cumulative submissions-received counter
	 *
	 * Incremented on the submission hook from 2.2.0 onward (no
	 * historical backfill — cumulative "received" semantics, so
	 * deleting submissions never un-fires the milestone).
	 *
	 * @since 2.2.0
	 * @var string
	 */
	const SUBMISSION_COUNT_OPTION = 'pressprimer_assignment_submission_count';

	/**
	 * Submissions received before the milestone prompt may fire
	 *
	 * @since 2.2.0
	 * @var int
	 */
	const MILESTONE_THRESHOLD = 10;

	/**
	 * Option name: pending What's New wave marker
	 *
	 * Holds the major line ("2.2", "3.1") of the latest update that
	 * crossed a major boundary — never set on fresh installs. Each
	 * admin sees that wave's panel until they dismiss it; a later
	 * major arms a fresh wave.
	 *
	 * @since 2.2.0
	 * @var string
	 */
	const WHATS_NEW_OPTION = 'pressprimer_assignment_whats_new';

	/**
	 * User meta key: the What's New wave this admin dismissed
	 *
	 * Version-scoped (unlike the ask dismissals): dismissing the 3.1
	 * wave hides 3.1 forever — including later 3.1.x patches — but the
	 * next major's wave shows fresh.
	 *
	 * @since 2.2.0
	 * @var string
	 */
	const META_WHATS_NEW_SEEN = 'pressprimer_assignment_whats_new_seen';

	/**
	 * Get the intake endpoint URL
	 *
	 * Overridable via the PRESSPRIMER_ASSIGNMENT_EMAIL_INTAKE_URL
	 * constant and the filter below. An empty value cleanly disables
	 * every opt-in surface.
	 *
	 * @since 2.2.0
	 *
	 * @return string Intake URL, or '' when disabled.
	 */
	public static function get_intake_url() {
		$url = defined( 'PRESSPRIMER_ASSIGNMENT_EMAIL_INTAKE_URL' )
			? PRESSPRIMER_ASSIGNMENT_EMAIL_INTAKE_URL
			: self::DEFAULT_INTAKE_URL;

		/**
		 * Filters the email opt-in intake endpoint URL.
		 *
		 * Return an empty string (or false) to disable every opt-in
		 * surface without errors.
		 *
		 * @since 2.2.0
		 *
		 * @param string $url Intake endpoint URL.
		 */
		$url = apply_filters( 'pressprimer_assignment_email_intake_url', $url );

		if ( empty( $url ) || ! is_string( $url ) ) {
			return '';
		}

		return esc_url_raw( $url );
	}

	/**
	 * Check whether the opt-in system is enabled at all
	 *
	 * @since 2.2.0
	 *
	 * @return bool True when an intake URL is configured.
	 */
	public static function is_enabled() {
		return '' !== self::get_intake_url();
	}

	/**
	 * Check whether a surface slug is valid
	 *
	 * @since 2.2.0
	 *
	 * @param string $source Surface slug.
	 * @return bool True when known.
	 */
	public static function is_valid_source( $source ) {
		return in_array( $source, self::SOURCES, true );
	}

	/**
	 * Get a user's consent record
	 *
	 * @since 2.2.0
	 *
	 * @param int $user_id User ID.
	 * @return array|null [ status, source, timestamp ] or null when unanswered.
	 */
	public static function get_consent( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return null;
		}

		$consent = get_user_meta( $user_id, self::META_CONSENT, true );

		if (
			! is_array( $consent )
			|| empty( $consent['status'] )
			|| ! in_array( $consent['status'], [ 'opted_in', 'declined' ], true )
		) {
			return null;
		}

		return $consent;
	}

	/**
	 * Check whether the user has answered the ask anywhere
	 *
	 * @since 2.2.0
	 *
	 * @param int $user_id User ID.
	 * @return bool True when opted in or declined.
	 */
	public static function has_answered( $user_id ) {
		return null !== self::get_consent( $user_id );
	}

	/**
	 * Record an opt-in
	 *
	 * First answer wins: if the user has already answered (either
	 * way), nothing is written.
	 *
	 * @since 2.2.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $source  Surface the answer came from.
	 * @return bool True when the record was written.
	 */
	public static function record_opt_in( $user_id, $source ) {
		return self::record_answer( $user_id, 'opted_in', $source );
	}

	/**
	 * Record a decline
	 *
	 * Same permanence as an opt-in: no surface ever asks this user
	 * again, including wizard relaunches.
	 *
	 * @since 2.2.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $source  Surface the answer came from.
	 * @return bool True when the record was written.
	 */
	public static function record_decline( $user_id, $source ) {
		return self::record_answer( $user_id, 'declined', $source );
	}

	/**
	 * Write the consent record (first answer wins)
	 *
	 * @since 2.2.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $status  'opted_in' or 'declined'.
	 * @param string $source  Surface slug.
	 * @return bool True when written.
	 */
	private static function record_answer( $user_id, $status, $source ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! self::is_valid_source( $source ) ) {
			return false;
		}

		if ( self::has_answered( $user_id ) ) {
			return false;
		}

		update_user_meta(
			$user_id,
			self::META_CONSENT,
			[
				'status'    => $status,
				'source'    => $source,
				'timestamp' => time(),
			]
		);

		return true;
	}

	/**
	 * Record a per-surface dismissal
	 *
	 * Most surfaces dismiss permanently. The What's New panel is the
	 * exception: its dismissal is scoped to the pending wave, so the
	 * next major's panel shows fresh.
	 *
	 * @since 2.2.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $surface Surface slug.
	 * @return bool True when written.
	 */
	public static function record_dismissal( $user_id, $surface ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! self::is_valid_source( $surface ) ) {
			return false;
		}

		if ( 'whats-new' === $surface ) {
			update_user_meta( $user_id, self::META_WHATS_NEW_SEEN, self::get_whats_new_version() );
			return true;
		}

		$dismissals = get_user_meta( $user_id, self::META_DISMISSALS, true );
		if ( ! is_array( $dismissals ) ) {
			$dismissals = [];
		}

		$dismissals[ $surface ] = time();
		update_user_meta( $user_id, self::META_DISMISSALS, $dismissals );

		return true;
	}

	/**
	 * Check whether the user dismissed a specific surface
	 *
	 * @since 2.2.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $surface Surface slug.
	 * @return bool True when dismissed.
	 */
	public static function is_dismissed( $user_id, $surface ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		$dismissals = get_user_meta( $user_id, self::META_DISMISSALS, true );

		return is_array( $dismissals ) && isset( $dismissals[ $surface ] );
	}

	/**
	 * Check whether a review prompt is currently due
	 *
	 * The ask-priority rule: one ask per moment — a due review prompt
	 * wins and the course prompt waits. No review-notice engine ships
	 * in the free plugin today, so this resolves through a filter that
	 * a future engine (or an addon) hooks.
	 *
	 * @since 2.2.0
	 *
	 * @return bool True when a review prompt is due.
	 */
	public static function is_review_prompt_due() {
		/**
		 * Filters whether a review prompt is due right now.
		 *
		 * When true, the milestone email ask yields (never stack asks).
		 *
		 * @since 2.2.0
		 *
		 * @param bool $due Whether a review prompt is due. Default false.
		 */
		return (bool) apply_filters( 'pressprimer_assignment_review_prompt_due', false );
	}

	/**
	 * Resolve whether the ask may show on a surface for a user
	 *
	 * Administrators only, on EVERY surface (revised in review, July
	 * 2026): teachers see the guided tour but are never offered the
	 * opt-in — the gate lives here so no surface, current or future,
	 * can leak the ask to them.
	 *
	 * @since 2.2.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $surface Surface slug.
	 * @return bool True when the surface may ask.
	 */
	public static function is_eligible( $user_id, $surface ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! self::is_valid_source( $surface ) ) {
			return false;
		}

		if ( ! user_can( $user_id, 'manage_options' ) ) {
			return false;
		}

		if ( ! self::is_enabled() ) {
			return false;
		}

		if ( self::has_answered( $user_id ) ) {
			return false;
		}

		if ( self::is_dismissed( $user_id, $surface ) ) {
			return false;
		}

		// Ask-priority rule: the milestone prompt is the one surface
		// that pops unprompted, so it yields to a due review prompt.
		if ( 'milestone' === $surface && self::is_review_prompt_due() ) {
			return false;
		}

		return true;
	}

	/**
	 * Increment the submissions-received counter
	 *
	 * Hooked to pressprimer_assignment_submission_submitted (fires for
	 * both file and text submissions).
	 *
	 * @since 2.2.0
	 */
	public static function increment_submission_count() {
		$count = absint( get_option( self::SUBMISSION_COUNT_OPTION, 0 ) );
		update_option( self::SUBMISSION_COUNT_OPTION, $count + 1, false );
	}

	/**
	 * Get the cumulative submissions-received count
	 *
	 * @since 2.2.0
	 *
	 * @return int Count since 2.2.0.
	 */
	public static function get_submission_count() {
		return absint( get_option( self::SUBMISSION_COUNT_OPTION, 0 ) );
	}

	/**
	 * Check whether the submissions milestone has been reached
	 *
	 * @since 2.2.0
	 *
	 * @return bool True at or past the threshold.
	 */
	public static function milestone_reached() {
		return self::get_submission_count() >= self::MILESTONE_THRESHOLD;
	}

	/**
	 * Get the major line ("x.y") of a version string
	 *
	 * @since 2.2.0
	 *
	 * @param string $version Full version, e.g. "3.1.2".
	 * @return string Major line, e.g. "3.1".
	 */
	public static function major_line( $version ) {
		return implode( '.', array_slice( explode( '.', (string) $version ), 0, 2 ) );
	}

	/**
	 * Get the pending What's New wave, if any
	 *
	 * @since 2.2.0
	 *
	 * @return string Major line ("2.2") or '' when no wave is pending.
	 */
	public static function get_whats_new_version() {
		$version = get_option( self::WHATS_NEW_OPTION, '' );
		return is_string( $version ) ? $version : '';
	}

	/**
	 * Arm a What's New wave when an update crosses a major line
	 *
	 * Called from both update-detection paths (the admin_init version
	 * check and reactivation-style updates in the activator). Fresh
	 * installs never qualify: they have no stored version. Patch
	 * updates within the same major line never re-arm — a user who
	 * dismissed the 3.1 wave sees nothing on 3.1.2. Skipped majors
	 * collapse into the latest wave (3.0 → 3.2 arms "3.2" only).
	 *
	 * @since 2.2.0
	 *
	 * @param string|false $stored_version Previously stored plugin version.
	 * @return bool True when a wave was armed.
	 */
	public static function maybe_flag_whats_new_on_update( $stored_version ) {
		if ( empty( $stored_version ) || ! is_string( $stored_version ) ) {
			return false;
		}

		$current_line = self::major_line( PRESSPRIMER_ASSIGNMENT_VERSION );

		if (
			self::major_line( $stored_version ) !== $current_line
			&& version_compare( PRESSPRIMER_ASSIGNMENT_VERSION, $stored_version, '>' )
		) {
			update_option( self::WHATS_NEW_OPTION, $current_line, false );
			return true;
		}

		return false;
	}

	/**
	 * Check whether the What's New panel is visible for a user
	 *
	 * Admins only; requires a pending wave (set only when an update
	 * crosses a major line — never on fresh installs) that this admin
	 * has not dismissed. Dismissal is wave-scoped: the next major's
	 * panel shows fresh. The panel itself shows the release notes
	 * regardless of opt-in state; the ask inside it resolves its own
	 * eligibility separately.
	 *
	 * @since 2.2.0
	 *
	 * @param int $user_id User ID.
	 * @return bool True when the panel should render.
	 */
	public static function whats_new_visible( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! user_can( $user_id, 'manage_options' ) ) {
			return false;
		}

		$pending = self::get_whats_new_version();

		if ( '' === $pending ) {
			return false;
		}

		return get_user_meta( $user_id, self::META_WHATS_NEW_SEEN, true ) !== $pending;
	}

	/**
	 * Relay an opt-in to the pressprimer.com intake
	 *
	 * Non-blocking fire-and-forget: failure is silent by design — the
	 * local consent record is the source of truth. The receiving side
	 * is a FluentCRM incoming webhook (list/tag/status configured on
	 * the webhook itself; subscription starts immediately, every email
	 * carries an unsubscribe link). The payload is the email address
	 * and the source surface tag ONLY.
	 *
	 * @since 2.2.0
	 *
	 * @param string $email  Opted-in email address (already validated).
	 * @param string $source Surface the opt-in came from.
	 */
	public static function relay_opt_in( $email, $source ) {
		$url = self::get_intake_url();

		if ( '' === $url ) {
			return;
		}

		wp_remote_post(
			$url,
			[
				'timeout'  => 2,
				'blocking' => false,
				'body'     => [
					'email'  => $email,
					'source' => $source,
				],
			]
		);
	}
}
