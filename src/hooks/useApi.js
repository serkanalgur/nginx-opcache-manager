/**
 * Custom hook for API data fetching with loading and error states.
 *
 * @package Nginx_Opcache_Manager
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { apiGet } from '../utils/api';

/**
 * Hook for fetching data from the API.
 *
 * @param {string}   endpoint    - API endpoint to fetch.
 * @param {boolean}  autoFetch   - Whether to fetch automatically on mount.
 * @param {number}   refetchInterval - Interval in ms to refetch (0 = no auto-refetch).
 * @return {Object} { data, loading, error, refetch }
 */
export function useApiData( endpoint, autoFetch = true, refetchInterval = 0 ) {
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( autoFetch );
	const [ error, setError ] = useState( null );

	const fetchData = useCallback( async () => {
		setLoading( true );
		setError( null );
		try {
			const result = await apiGet( endpoint );
			setData( result );
		} catch ( err ) {
			setError( err.message || 'An error occurred' );
		} finally {
			setLoading( false );
		}
	}, [ endpoint ] );

	useEffect( () => {
		if ( autoFetch ) {
			fetchData();
		}
	}, [ autoFetch, fetchData ] );

	useEffect( () => {
		if ( refetchInterval > 0 ) {
			const interval = setInterval( fetchData, refetchInterval );
			return () => clearInterval( interval );
		}
	}, [ refetchInterval, fetchData ] );

	return { data, loading, error, refetch: fetchData };
}
