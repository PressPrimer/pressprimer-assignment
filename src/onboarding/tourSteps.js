/**
 * Tour Step Definitions
 *
 * Defines the onboarding tour steps for PressPrimer Assignment.
 * Follows the same pattern as PressPrimer Quiz tour steps —
 * each spotlight step navigates to the actual admin page and
 * highlights the page content container.
 *
 * Steps:
 * 1. Welcome modal — intro to the plugin
 * 2. Menu — spotlight the top-level menu item
 * 3. Dashboard — spotlight the dashboard page content
 * 4. Assignments — spotlight the assignments list page
 * 5. Grading — spotlight the grading interface page
 * 6. Settings — spotlight the settings page
 * 7. Complete modal — success with quick actions
 *
 * @package
 * @since 1.0.0
 */

/**
 * Step type constants
 */
export const STEP_TYPE = {
	MODAL: 'modal',
	SPOTLIGHT: 'spotlight',
};

/**
 * Get onboarding data from PHP
 */
const getData = () => window.pressprimerAssignmentOnboardingData || {};

/**
 * Get the admin URL for a page
 *
 * @param {string} page Page key from the urls object.
 * @return {string} Admin URL.
 */
const getAdminUrl = ( page ) => {
	const baseUrl = getData().urls?.[ page ] || '';
	if ( baseUrl ) {
		return baseUrl;
	}

	// Fallback construction.
	return `admin.php?page=pressprimer-assignment${
		page === 'dashboard' ? '' : '-' + page
	}`;
};

/**
 * Tour steps
 *
 * Each step has:
 *   id              — unique identifier
 *   type            — MODAL or SPOTLIGHT
 *   title           — heading text
 *   content         — body text or JSX
 *   selector        — CSS selector for spotlight target (comma-separated for fallbacks)
 *   fallbackSelector — extra fallback if primary selector not found
 *   position        — preferred tooltip position (top, bottom, left, right)
 *   page            — which admin page this step belongs to (matched against ?page= param)
 *   pageUrl         — URL to navigate to for this step
 */
export const TOUR_STEPS = [
	// Step 1: Welcome Modal.
	{
		id: 'welcome',
		type: STEP_TYPE.MODAL,
		title: 'Welcome to PressPrimer Assignment!',
		content:
			"Let's take a quick tour to help you get started. We'll show you the key features and how to manage assignments.",
		selector: null,
		fallbackSelector: null,
		position: null,
		page: null,
		pageUrl: null,
	},

	// Step 2: Main Menu.
	{
		id: 'menu',
		type: STEP_TYPE.SPOTLIGHT,
		title: 'PressPrimer Assignment Menu',
		content:
			'This is your main menu for PressPrimer Assignment. From here you can access the Dashboard, Assignments, Submissions, Grading, Categories, Reports, and Settings.',
		selector: '#toplevel_page_pressprimer-assignment',
		fallbackSelector: '.toplevel_page_pressprimer-assignment',
		position: 'right',
		page: null,
		pageUrl: null,
	},

	// Step 3: Dashboard.
	{
		id: 'dashboard',
		type: STEP_TYPE.SPOTLIGHT,
		title: 'Dashboard',
		content:
			'The Dashboard gives you a quick overview of your assignment activity — statistics, recent submissions, and quick actions to get things done.',
		selector: '.ppa-dashboard-container',
		fallbackSelector: '#ppa-dashboard-root',
		position: 'bottom',
		page: 'pressprimer-assignment',
		pageUrl: getAdminUrl( 'dashboard' ),
	},

	// Step 4: Assignments.
	{
		id: 'assignments',
		type: STEP_TYPE.SPOTLIGHT,
		title: 'Assignments',
		content:
			'This is where you create and manage your assignments. Each assignment can accept file uploads, text submissions, or both. Set a passing score and configure submission options.',
		selector: '.wrap',
		fallbackSelector: '#wpbody-content',
		position: 'bottom',
		page: 'pressprimer-assignment-assignments',
		pageUrl: getAdminUrl( 'assignments' ),
	},

	// Step 5: Grading Queue.
	{
		id: 'grading',
		type: STEP_TYPE.SPOTLIGHT,
		title: 'Grading Queue',
		content:
			'Review and grade submissions in a side-by-side view. The red badge in the menu shows how many submissions are waiting to be graded.',
		selector: '.ppa-grading-interface',
		fallbackSelector: '#ppa-grading-interface-root',
		position: 'bottom',
		page: 'pressprimer-assignment-grading',
		pageUrl: getAdminUrl( 'grading' ),
	},

	// Step 6: Settings.
	{
		id: 'settings',
		type: STEP_TYPE.SPOTLIGHT,
		title: 'Settings',
		content:
			'Configure your plugin settings here. Start with the General, Appearance and Email tabs, then expand to other areas once you have the basics in place.',
		selector: '.ppa-settings-container',
		fallbackSelector: '#ppa-settings-root',
		position: 'bottom',
		page: 'pressprimer-assignment-settings',
		pageUrl: getAdminUrl( 'settings' ),
	},

	// Step 7: Completion Modal.
	{
		id: 'complete',
		type: STEP_TYPE.MODAL,
		title: "You're All Set!",
		content:
			"You've completed the tour! You now know the basics of PressPrimer Assignment. Start by creating an assignment and sharing it with your students.",
		selector: null,
		fallbackSelector: null,
		position: null,
		page: null,
		pageUrl: null,
	},
];

/**
 * Get a step by index (1-based)
 *
 * @param {number} stepNumber 1-based step number.
 * @return {Object|null} Step object or null.
 */
export const getStep = ( stepNumber ) => {
	const index = stepNumber - 1;
	return TOUR_STEPS[ index ] || null;
};

/**
 * Get the URL for a step (resolving functions)
 *
 * @param {number} stepNumber 1-based step number.
 * @return {string} URL string or empty.
 */
export const getStepUrl = ( stepNumber ) => {
	const step = getStep( stepNumber );
	if ( ! step || ! step.pageUrl ) {
		return '';
	}
	return typeof step.pageUrl === 'function' ? step.pageUrl() : step.pageUrl;
};

/**
 * Check if the current page matches the step's required page
 *
 * @param {number} stepNumber 1-based step number.
 * @return {boolean} True if on the correct page.
 */
export const isOnCorrectPage = ( stepNumber ) => {
	const step = getStep( stepNumber );
	if ( ! step || ! step.page ) {
		return true; // Modals don't require a specific page.
	}

	const urlParams = new URLSearchParams( window.location.search );
	const currentPage = urlParams.get( 'page' ) || '';

	return currentPage === step.page;
};

/**
 * Get total number of steps
 *
 * @return {number} Total step count.
 */
export const getTotalSteps = () => TOUR_STEPS.length;
