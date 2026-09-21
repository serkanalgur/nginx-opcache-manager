/**
 * WordPress REST API utility for Nginx Opcache Manager.
 *
 * @package Nginx_Opcache_Manager
 */

import apiFetch from '@wordpress/api-fetch';

const API_NAMESPACE = 'nom/v1';

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

	return apiFetch( defaultOptions );
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
