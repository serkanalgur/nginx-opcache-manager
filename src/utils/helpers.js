/**
 * Utility functions for Nginx Opcache Manager.
 *
 * @package Nginx_Opcache_Manager
 */

/**
 * Format bytes to human-readable string.
 *
 * @param {number} bytes    - Bytes to format.
 * @param {number} decimals - Number of decimal places.
 * @return {string} Formatted string.
 */
export function formatBytes( bytes, decimals = 2 ) {
	if ( bytes === 0 || bytes === null || bytes === undefined ) {
		return '0 B';
	}

	const k = 1024;
	const dm = decimals < 0 ? 0 : decimals;
	const sizes = [ 'B', 'KB', 'MB', 'GB', 'TB' ];

	const i = Math.floor( Math.log( bytes ) / Math.log( k ) );

	return parseFloat( ( bytes / Math.pow( k, i ) ).toFixed( dm ) ) + ' ' + sizes[ i ];
}

/**
 * Format percentage.
 *
 * @param {number} value    - Value to format.
 * @param {number} decimals - Number of decimal places.
 * @return {string} Formatted percentage string.
 */
export function formatPercent( value, decimals = 1 ) {
	if ( value === null || value === undefined ) {
		return '0%';
	}
	return parseFloat( value ).toFixed( decimals ) + '%';
}

/**
 * Format a timestamp to locale string.
 *
 * @param {string|number} timestamp - ISO date string or Unix timestamp.
 * @return {string} Formatted date string.
 */
export function formatDate( timestamp ) {
	if ( ! timestamp ) {
		return 'N/A';
	}
	const date = new Date( timestamp );
	return date.toLocaleString();
}

/**
 * Get status color based on percentage.
 *
 * @param {number} percent - Percentage value.
 * @return {string} CSS color class.
 */
export function getStatusColor( percent ) {
	if ( percent >= 80 ) {
		return 'nom-status-good';
	}
	if ( percent >= 50 ) {
		return 'nom-status-warning';
	}
	return 'nom-status-critical';
}

/**
 * Format number with locale-specific separators.
 *
 * @param {number} value    - Number to format.
 * @param {number} decimals - Number of decimal places.
 * @return {string} Formatted number string.
 */
export function formatNumber( value, decimals = 0 ) {
	if ( value === null || value === undefined ) {
		return '0';
	}
	return Number( value ).toLocaleString( undefined, {
		minimumFractionDigits: decimals,
		maximumFractionDigits: decimals,
	} );
}
