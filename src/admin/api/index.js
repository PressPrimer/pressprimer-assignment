/**
 * API wrapper functions
 *
 * Provides convenient wrappers around @wordpress/api-fetch
 * for all admin REST API endpoints.
 *
 * @package
 * @since 1.0.0
 */

import apiFetch from '@wordpress/api-fetch';

const API_NAMESPACE = '/ppa/v1';

/**
 * Assignments API
 */
export const assignmentsApi = {
	/**
	 * List assignments with optional filters.
	 *
	 * @param {Object} params Query parameters.
	 * @return {Promise} API response.
	 */
	list( params = {} ) {
		const query = new URLSearchParams( params ).toString();
		const path = query
			? `${ API_NAMESPACE }/assignments?${ query }`
			: `${ API_NAMESPACE }/assignments`;

		return apiFetch( { path } );
	},

	/**
	 * Get a single assignment.
	 *
	 * @param {number} id Assignment ID.
	 * @return {Promise} API response.
	 */
	get( id ) {
		return apiFetch( { path: `${ API_NAMESPACE }/assignments/${ id }` } );
	},

	/**
	 * Create a new assignment.
	 *
	 * @param {Object} data Assignment data.
	 * @return {Promise} API response.
	 */
	create( data ) {
		return apiFetch( {
			path: `${ API_NAMESPACE }/assignments`,
			method: 'POST',
			data,
		} );
	},

	/**
	 * Update an existing assignment.
	 *
	 * @param {number} id   Assignment ID.
	 * @param {Object} data Assignment data.
	 * @return {Promise} API response.
	 */
	update( id, data ) {
		return apiFetch( {
			path: `${ API_NAMESPACE }/assignments/${ id }`,
			method: 'PUT',
			data,
		} );
	},

	/**
	 * Delete an assignment.
	 *
	 * @param {number} id Assignment ID.
	 * @return {Promise} API response.
	 */
	delete( id ) {
		return apiFetch( {
			path: `${ API_NAMESPACE }/assignments/${ id }`,
			method: 'DELETE',
		} );
	},
};

/**
 * Submissions API
 */
export const submissionsApi = {
	/**
	 * List submissions with optional filters.
	 *
	 * @param {Object} params Query parameters.
	 * @return {Promise} API response.
	 */
	list( params = {} ) {
		const query = new URLSearchParams( params ).toString();
		const path = query
			? `${ API_NAMESPACE }/submissions?${ query }`
			: `${ API_NAMESPACE }/submissions`;

		return apiFetch( { path } );
	},

	/**
	 * Get a single submission.
	 *
	 * @param {number} id Submission ID.
	 * @return {Promise} API response.
	 */
	get( id ) {
		return apiFetch( {
			path: `${ API_NAMESPACE }/submissions/${ id }`,
		} );
	},

	/**
	 * Update a submission (e.g., grade, return).
	 *
	 * @param {number} id   Submission ID.
	 * @param {Object} data Submission data.
	 * @return {Promise} API response.
	 */
	update( id, data ) {
		return apiFetch( {
			path: `${ API_NAMESPACE }/submissions/${ id }`,
			method: 'PUT',
			data,
		} );
	},
};

/**
 * Categories API
 */
export const categoriesApi = {
	/**
	 * List categories with optional filters.
	 *
	 * @param {Object} params Query parameters.
	 * @return {Promise} API response.
	 */
	list( params = {} ) {
		const query = new URLSearchParams( params ).toString();
		const path = query
			? `${ API_NAMESPACE }/categories?${ query }`
			: `${ API_NAMESPACE }/categories`;

		return apiFetch( { path } );
	},

	/**
	 * Get a single category.
	 *
	 * @param {number} id Category ID.
	 * @return {Promise} API response.
	 */
	get( id ) {
		return apiFetch( {
			path: `${ API_NAMESPACE }/categories/${ id }`,
		} );
	},

	/**
	 * Create a new category.
	 *
	 * @param {Object} data Category data.
	 * @return {Promise} API response.
	 */
	create( data ) {
		return apiFetch( {
			path: `${ API_NAMESPACE }/categories`,
			method: 'POST',
			data,
		} );
	},

	/**
	 * Update an existing category.
	 *
	 * @param {number} id   Category ID.
	 * @param {Object} data Category data.
	 * @return {Promise} API response.
	 */
	update( id, data ) {
		return apiFetch( {
			path: `${ API_NAMESPACE }/categories/${ id }`,
			method: 'PUT',
			data,
		} );
	},

	/**
	 * Delete a category.
	 *
	 * @param {number} id Category ID.
	 * @return {Promise} API response.
	 */
	delete( id ) {
		return apiFetch( {
			path: `${ API_NAMESPACE }/categories/${ id }`,
			method: 'DELETE',
		} );
	},
};
