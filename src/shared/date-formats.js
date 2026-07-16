/**
 * Admin date/time display standard.
 *
 * One format for every admin-side picker and date display: "Jul 15, 2026
 * 12:00 AM". Change it here, and every admin surface follows. The PHP-side
 * equivalent (used for server-rendered admin strings like the Scheduling
 * tab's current-site-time line) is 'M j, Y g:i A'.
 *
 * Student-facing PHP surfaces intentionally do NOT use this — they follow
 * the site's WordPress date/time settings so site owners and translators
 * stay in control.
 *
 * @package
 * @since 2.2.0
 */

export const ADMIN_DATETIME_FORMAT = 'MMM D, YYYY h:mm A';

// Matching time-column config so every DatePicker popup looks the same.
export const ADMIN_SHOWTIME = {
	use12Hours: true,
	format: 'h:mm A',
	minuteStep: 15,
};

// Date-only variant for range pickers and compact report displays.
export const ADMIN_DATE_FORMAT = 'MMM D, YYYY';
