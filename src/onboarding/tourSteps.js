/**
 * Tour Step Definitions
 *
 * Defines the guided-build tour for PressPrimer Assignment (2.2).
 * Instead of describing the product, the tour walks the user through
 * creating and publishing a real assignment in the real admin UI:
 *
 * 1. Welcome modal — pitch + optional template pick
 * 2. Basics — spotlight the Basic Information card in the real editor
 * 3. Grading — spotlight the Grading card
 * 4. File settings — switch to the File Settings tab, spotlight file types
 * 5. Publish — spotlight the real save controls
 * 6. Page — one-click page creation + the "View your assignment" moment
 * 7. Completion modal
 *
 * Steps 2–6 live on the assignment editor page and match both
 * action=new and action=edit (the URL flips to edit after first save).
 *
 * @package
 * @since 1.0.0
 */

import { createInterpolateElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

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
 * Get the new-assignment editor URL
 *
 * @return {string} Admin URL for the editor.
 */
const getEditorUrl = () =>
	getData().urls?.newAssignment ||
	'admin.php?page=pressprimer-assignment-assignments&action=new';

/**
 * Check whether the given URL params point at the assignment editor
 *
 * Matches both action=new and action=edit: after the user's first save
 * the editor rewrites the URL to action=edit, and the tour must not
 * navigate away from their draft.
 *
 * @param {URLSearchParams} urlParams Current URL search params.
 * @return {boolean} True when on the editor screen.
 */
const isOnEditor = ( urlParams ) =>
	urlParams.get( 'page' ) === 'pressprimer-assignment-assignments' &&
	[ 'new', 'edit' ].includes( urlParams.get( 'action' ) || '' );

/**
 * Build an onEnter handler that activates an editor tab by index
 *
 * The File Settings step lives on the editor's second tab; spotlight
 * targets inside an inactive tab are not in the DOM, so the step
 * switches the real tab before the selector resolves — the same UI
 * action the user would take themselves.
 *
 * @param {number} index Zero-based tab index (0 = Settings, 1 = File Settings).
 * @return {Function} onEnter handler.
 */
const clickEditorTab = ( index ) => () => {
	const tabs = document.querySelectorAll(
		'.ppa-assignment-editor-container .ant-tabs-nav .ant-tabs-tab'
	);
	const tab = tabs[ index ];

	if ( ! tab || tab.classList.contains( 'ant-tabs-tab-active' ) ) {
		return;
	}

	( tab.querySelector( '.ant-tabs-tab-btn' ) || tab ).click();
};

/**
 * Tour steps
 *
 * Each step has:
 *   id               — unique identifier
 *   type             — MODAL or SPOTLIGHT
 *   title            — heading text
 *   content          — body text
 *   selector         — CSS selector for spotlight target (comma-separated for fallbacks)
 *   fallbackSelector — extra fallback if primary selector not found
 *   position         — preferred tooltip position (top, bottom, left, right)
 *   matches          — optional predicate ( URLSearchParams ) => boolean for page matching
 *   page             — legacy exact ?page= match (used when matches is absent)
 *   pageUrl          — URL (or function returning one) to navigate to for this step
 *   onEnter          — optional callback run before the selector resolves
 */
export const TOUR_STEPS = [
	// Step 1: Welcome modal with template pick.
	{
		id: 'welcome',
		type: STEP_TYPE.MODAL,
		title: __(
			"Let's publish your first assignment",
			'pressprimer-assignment'
		),
		content: __(
			"In about five minutes you'll create and publish a real assignment using your actual admin screens. Start from a template or a blank slate — everything can be changed later.",
			'pressprimer-assignment'
		),
		selector: null,
		fallbackSelector: null,
		position: null,
		page: null,
		pageUrl: null,
	},

	// Step 2: Basics — title, description, instructions.
	{
		id: 'create-basics',
		type: STEP_TYPE.SPOTLIGHT,
		title: __(
			'Name it and write the instructions',
			'pressprimer-assignment'
		),
		content: __(
			'Type right into the form: give your assignment a title, a short description, and instructions for students. If you started from a template these are already filled in — tweak anything you like.',
			'pressprimer-assignment'
		),
		selector: '.ppa-editor-card-basic',
		fallbackSelector: '.ppa-assignment-editor-container',
		position: 'bottom',
		matches: isOnEditor,
		page: 'pressprimer-assignment-assignments',
		pageUrl: getEditorUrl,
		onEnter: clickEditorTab( 0 ),
	},

	// Step 3: Grading.
	{
		id: 'create-grading',
		type: STEP_TYPE.SPOTLIGHT,
		title: __( 'Set the points', 'pressprimer-assignment' ),
		content: __(
			'Choose the maximum points and the passing score. Grading guidelines are private notes shown to whoever grades — students never see them.',
			'pressprimer-assignment'
		),
		selector: '.ppa-editor-card-grading',
		fallbackSelector: '.ppa-assignment-editor-container',
		position: 'top',
		matches: isOnEditor,
		page: 'pressprimer-assignment-assignments',
		pageUrl: getEditorUrl,
		onEnter: clickEditorTab( 0 ),
	},

	// Step 4: File settings (second editor tab).
	{
		id: 'create-files',
		type: STEP_TYPE.SPOTLIGHT,
		title: __(
			'Choose what students can upload',
			'pressprimer-assignment'
		),
		content: __(
			"We've switched you to the File Settings tab. Pick the file types students may submit, and set size and count limits below.",
			'pressprimer-assignment'
		),
		selector: '.ppa-editor-card-file-types',
		fallbackSelector: '.ppa-assignment-editor-container',
		position: 'bottom',
		matches: isOnEditor,
		page: 'pressprimer-assignment-assignments',
		pageUrl: getEditorUrl,
		onEnter: clickEditorTab( 1 ),
	},

	// Step 5: Publish with the real controls. The tour advances
	// automatically when it hears the save; Next stays disabled until
	// a published save happens (gated in Onboarding.jsx).
	{
		id: 'publish',
		type: STEP_TYPE.SPOTLIGHT,
		title: __( 'Publish it', 'pressprimer-assignment' ),
		content: createInterpolateElement(
			__(
				'Everything look good? Click <strong>Save Assignment</strong> to publish — Status is already set to Published. Prefer to launch later? Switch Status to Draft in Basic Information first.',
				'pressprimer-assignment'
			),
			{ strong: <strong /> }
		),
		selector: '.ppa-editor-header-actions',
		fallbackSelector: '.ppa-editor-header',
		position: 'bottom',
		matches: isOnEditor,
		page: 'pressprimer-assignment-assignments',
		pageUrl: getEditorUrl,
		onEnter: clickEditorTab( 0 ),
	},

	// Step 6: Put it on a page (custom modal with the create action).
	{
		id: 'page',
		type: STEP_TYPE.MODAL,
		title: __( 'Put it on a page', 'pressprimer-assignment' ),
		content: __(
			"Your assignment is published — nice work! Students submit from a page on your site, and one click creates that page with your assignment's block already on it.",
			'pressprimer-assignment'
		),
		selector: null,
		fallbackSelector: null,
		position: null,
		page: null,
		pageUrl: null,
	},

	// Step 7: Completion modal.
	{
		id: 'complete',
		type: STEP_TYPE.MODAL,
		title: __( "You're all set!", 'pressprimer-assignment' ),
		content: __(
			'You built a real assignment, published it, and put it on a page. You can relaunch this tour anytime from the dashboard.',
			'pressprimer-assignment'
		),
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
	if ( ! step ) {
		return true;
	}

	const urlParams = new URLSearchParams( window.location.search );

	if ( typeof step.matches === 'function' ) {
		return step.matches( urlParams );
	}

	if ( ! step.page ) {
		return true; // Modals don't require a specific page.
	}

	return ( urlParams.get( 'page' ) || '' ) === step.page;
};

/**
 * Get total number of steps
 *
 * @return {number} Total step count.
 */
export const getTotalSteps = () => TOUR_STEPS.length;
