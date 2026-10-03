/**
 * WordPress REST API utility for Nginx Opcache Manager.
 *
 * @package Nginx_Opcache_Manager
 */

import apiFetch, { createNonceMiddleware } from '@wordpress/api-fetch';

const API_NAMESPACE = 'nom/v1';

/**
 * Resolve the REST nonce that admin/class-admin.php localizes as `nomData.nonce`,
 * falling back to the core `wpApiSettings.nonce` used by other WP admin screens.
 *
 * Without this header WordPress rejects cookie-authenticated REST calls with
 * `rest_cookie_invalid_nonce` (403), because it cannot prove the request comes
 * from a logged-in admin session.
 *
 * @return {string|undefined} REST nonce.
 */
function getRestNonce() {
	if ( typeof window === 'undefined' ) {
		return undefined;
	}

	if ( window.nomData && window.nomData.nonce ) {
		return window.nomData.nonce;
	}

	if ( window.wpApiSettings && window.wpApiSettings.nonce ) {
		return window.wpApiSettings.nonce;
	}

	return undefined;
}

const restNonce = getRestNonce();

if ( restNonce ) {
	apiFetch.use( createNonceMiddleware( restNonce ) );
}

/**
 * Turn an apiFetch failure into an error carrying the server's real reason.
 *
 * apiFetch only reports "The server responded with a status of 403 (Forbidden)";
 * the actionable WP REST error (`code`/`message`) lives on `error.data`.
 *
 * @param {Object} error Raw apiFetch error.
 * @return {Error} Error with `code` and `status` properties.
 */
function toApiError( error ) {
	const data = error && error.data;
	const apiError = new Error(
		( data && data.message ) ||
			( error && error.message ) ||
			'Unknown error'
	);

	apiError.code = ( data && data.code ) || 'unknown';
	apiError.status = ( error && error.status ) || 0;

	return apiError;
}

/**
 * Generic API request helper.
 *
 * @param {string} endpoint - API endpoint path.
 * @param {Object} options  - Additional fetch options.
 * @return {Promise<Object>} Parsed JSON response.
 */
export async function apiRequest( endpoint, options = {} ) {
	const defaultOptions = {
		path: `/${ API_NAMESPACE }/${ endpoint }`,
		headers: {
			'Content-Type': 'application/json',
		},
		...options,
	};

	try {
		return await apiFetch( defaultOptions );
	} catch ( error ) {
		throw toApiError( error );
	}
}

/**
 * GET request helper.
 *
 * @param {string} endpoint - API endpoint path.
 * @return {Promise<Object>} Parsed JSON response.
 */
export function apiGet( endpoint ) {
	return apiRequest( endpoint, { method: 'GET' } );
}

/**
 * POST request helper.
 *
 * @param {string} endpoint - API endpoint path.
 * @param {Object} data     - Request body data.
 * @return {Promise<Object>} Parsed JSON response.
 */
export function apiPost( endpoint, data = {} ) {
	return apiRequest( endpoint, {
		method: 'POST',
		body: JSON.stringify( data ),
	} );
}

/**
 * API endpoints.
 */
export const endpoints = {
	stats: 'stats',
	nginxClear: 'nginx/clear',
	opcacheReset: 'opcache/reset',
	logs: 'logs',
	logsClear: 'logs/clear',
	analytics: 'analytics',
	analyticsSummary: 'analytics/summary',
	settings: 'settings',
	serverInfo: 'server-info',
};

// Named exports for convenience
export const fetchStats = () => apiGet( endpoints.stats );
export const clearNginxCache = () => apiPost( endpoints.nginxClear );
export const resetOpcache = () => apiPost( endpoints.opcacheReset );
export const fetchLogs = () => apiGet( endpoints.logs );
export const clearLogs = () => apiPost( endpoints.logsClear );
export const fetchAnalytics = () => apiGet( endpoints.analytics );
export const fetchSettings = () => apiGet( endpoints.settings );
export const updateSettings = ( data ) => apiPost( endpoints.settings, data );
export const fetchServerInfo = () => apiGet( endpoints.serverInfo );
