<?php
/**
 * Nginx Cache Manager class
 *
 * @package Nginx_Opcache_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to manage Nginx cache
 */
class Nginx_Opcache_Manager_Cache {

	/**
	 * Default nginx cache path
	 */
	const DEFAULT_CACHE_PATH = '/var/run/nginx-cache';

	/**
	 * Initialize database table for cache activity logs
	 */
	public function initialize() {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'nom_cache_activities';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			timestamp datetime DEFAULT CURRENT_TIMESTAMP,
			action varchar(50) NOT NULL,
			url varchar(2083) NOT NULL,
			file_path varchar(2083) NOT NULL,
			method varchar(10) NOT NULL DEFAULT 'GET',
			KEY timestamp (timestamp),
			KEY action (action)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	/**
	 * Clear all cache activity logs
	 */
	public function clear_activity_logs() {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'nom_cache_activities';
		$wpdb->query( "TRUNCATE TABLE $table_name" );
		
		return true;
	}

	/**
	 * Get nginx cache path from settings
	 */
	private function get_cache_path() {
		$path = get_option( 'nom_nginx_cache_path', self::DEFAULT_CACHE_PATH );
		return sanitize_text_field( $path );
	}

	/**
	 * Check if nginx cache is enabled
	 */
	public function is_enabled() {
		return get_option( 'nom_nginx_cache_enabled', false );
	}

	/**
	 * Get cache directory size
	 */
	public function get_cache_size() {
		$cache_path = $this->get_cache_path();

		if ( ! is_dir( $cache_path ) ) {
			return 0;
		}

		return $this->get_directory_size( $cache_path );
	}

	/**
	 * Get number of cached files
	 */
	public function get_cached_files_count() {
		$cache_path = $this->get_cache_path();

		if ( ! is_dir( $cache_path ) ) {
			return 0;
		}

		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $cache_path, RecursiveDirectoryIterator::SKIP_DOTS )
		);

		return iterator_count( $it );
	}

	/**
	 * Clear nginx cache
	 */
	public function clear_cache() {
		$cache_path = $this->get_cache_path();

		if ( ! is_dir( $cache_path ) ) {
			return false;
		}

		return $this->remove_directory_contents( $cache_path );
	}

	/**
	 * Clear specific cache entry
	 * 
	 * Uses customizable fastcgi_cache_key schema from settings.
	 * Default format: "$scheme$request_method$host$request_uri"
	 * Cache directory structure:
	 * - Last character of MD5 as first folder
	 * - 2 characters before last (positions -3 to -2) as second folder
	 * - Full MD5 hash as filename
	 * Example: beda56a8736ae8bc335cdd74983649f5 -> /5/9f/beda56a8736ae8bc335cdd74983649f5
	 * 
	 * @param string $url Full URL (e.g., https://example.com/path)
	 * @param string $method Optional HTTP method (default: GET)
	 * @return array Result array with keys: success (bool), file_path (string), message (string)
	 */
	public function clear_url_cache( $url, $method = 'GET' ) {
		// Parse URL to extract components
		$url_parts = wp_parse_url( $url );
		
		if ( empty( $url_parts['host'] ) ) {
			return array(
				'success'   => false,
				'file_path' => '',
				'message'   => __( 'Invalid URL provided', 'nginx-opcache-manager' ),
			);
		}

		// Extract URL components
		$scheme = isset( $url_parts['scheme'] ) ? $url_parts['scheme'] : 'https';
		$host = $url_parts['host'];
		$path = isset( $url_parts['path'] ) ? $url_parts['path'] : '/';
		$query_string = isset( $url_parts['query'] ) ? $url_parts['query'] : '';
		
		// Get custom cache key schema from settings
		$schema = get_option( 'nom_fastcgi_cache_key_schema', '$scheme$request_method$host$request_uri' );
		
		// Build the cache key based on schema
		$cache_key_string = $this->build_cache_key_from_schema( $schema, $scheme, $method, $host, $path, $query_string );
		$cache_key_hash = md5( $cache_key_string );
		
		$cache_path = $this->get_cache_path();
		
		// Build cache file path: /last_char/2_chars_before_last/full_md5
		// Example: hash=beda56a8736ae8bc335cdd74983649f5 -> /5/9f/beda56a8736ae8bc335cdd74983649f5
		$cache_file = $cache_path . '/' . substr( $cache_key_hash, -1 ) . '/' . substr( $cache_key_hash, -3, 2 ) . '/' . $cache_key_hash;

		if ( file_exists( $cache_file ) ) {
			$deleted = unlink( $cache_file );
			if ( $deleted ) {
				$this->log_cache_activity( 'deleted', $url, $cache_file, $method );
				return array(
					'success'   => true,
					'file_path' => $cache_file,
					'message'   => __( 'Cache file deleted successfully', 'nginx-opcache-manager' ),
				);
			} else {
				$this->log_cache_activity( 'delete_failed', $url, $cache_file, $method );
				return array(
					'success'   => false,
					'file_path' => $cache_file,
					'message'   => __( 'Failed to delete cache file. Check file permissions.', 'nginx-opcache-manager' ),
				);
			}
		} else {
			$this->log_cache_activity( 'not_found', $url, $cache_file, $method );
			return array(
				'success'   => false,
				'file_path' => $cache_file,
				'message'   => __( 'Cache file not found at expected location', 'nginx-opcache-manager' ),
			);
		}
	}

	/**
	 * Build cache key string from schema template
	 * 
	 * Replaces variables like $scheme, $request_method, $host, $request_uri, $query_string
	 * 
	 * @param string $schema Cache key schema template
	 * @param string $scheme HTTP scheme (http/https)
	 * @param string $method HTTP method (GET, POST, etc)
	 * @param string $host Request host
	 * @param string $path Request path/URI
	 * @param string $query_string Query string
	 * @return string Constructed cache key string
	 */
	private function build_cache_key_from_schema( $schema, $scheme, $method, $host, $path, $query_string ) {
		$request_uri = $path;
		if ( ! empty( $query_string ) ) {
			$request_uri .= '?' . $query_string;
		}

		// Define replacements
		$replacements = array(
			'$scheme'          => $scheme,
			'$request_method'  => $method,
			'$host'            => $host,
			'$request_uri'     => $request_uri,
			'$query_string'    => $query_string,
		);

		// Replace variables in schema
		return str_replace( array_keys( $replacements ), array_values( $replacements ), $schema );
	}

	/**
	 * Log cache flush activity
	 * 
	 * Logs cache deletion attempts to help track what files are being targeted
	 * 
	 * @param string $action Action performed (deleted, delete_failed, not_found)
	 * @param string $url Original URL
	 * @param string $file_path Full path to cache file
	 * @param string $method HTTP method
	 */
	private function log_cache_activity( $action, $url, $file_path, $method = 'GET' ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'nom_cache_activities';
		$timestamp = current_time( 'mysql' );
		$log_entry = sprintf(
			'[%s] Cache Activity - Action: %s | URL: %s | Method: %s | File: %s',
			$timestamp,
			strtoupper( $action ),
			esc_url( $url ),
			$method,
			$file_path
		);

		// Log to WordPress error log if WP_DEBUG_LOG is enabled
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( $log_entry );
		}

		// Debug: Log what we're inserting
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'NOM: Inserting cache activity - action="' . $action . '" (type: ' . gettype($action) . ')' );
		}

		// Insert into database table
		$wpdb->insert(
			$table_name,
			array(
				'timestamp' => $timestamp,
				'action'    => $action,
				'url'       => $url,
				'file_path' => $file_path,
				'method'    => $method,
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		// Clean up old entries (keep only last 500 entries)
		$wpdb->query(
			"DELETE FROM $table_name WHERE id NOT IN (
				SELECT id FROM (
					SELECT id FROM $table_name ORDER BY timestamp DESC LIMIT 500
				) AS t
			)"
		);
	}

	/**
	 * Get recent cache activities from database
	 * 
	 * @param int $limit Number of recent activities to retrieve
	 * @return array Array of recent cache activities
	 */
	public function get_recent_activities( $limit = 20 ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'nom_cache_activities';
		
		$activities = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT timestamp, action, url, file_path, method FROM $table_name ORDER BY timestamp DESC LIMIT %d",
				$limit
			)
		);

		if ( ! $activities ) {
			return array();
		}

		// Debug: Log what we retrieved
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'NOM: Retrieved ' . count( $activities ) . ' activities from database' );
			foreach ( $activities as $activity ) {
				error_log( 'NOM: Activity - action="' . $activity->action . '" (type: ' . gettype($activity->action) . ')' );
			}
		}

		// Convert stdClass to array
		return json_decode( json_encode( $activities ), true );
	}

	/**
	 * Get cache statistics
	 */
	public function get_cache_stats() {
		return array(
			'enabled'       => $this->is_enabled(),
			'cache_size'    => $this->get_cache_size(),
			'cached_files'  => $this->get_cached_files_count(),
			'cache_path'    => $this->get_cache_path(),
		);
	}

	/**
	 * Calculate directory size recursively
	 */
	private function get_directory_size( $path ) {
		$size = 0;

		if ( ! is_dir( $path ) ) {
			return filesize( $path );
		}

		$files = array_diff( scandir( $path ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$file_path = $path . '/' . $file;

			if ( is_file( $file_path ) ) {
				$size += filesize( $file_path );
			} elseif ( is_dir( $file_path ) ) {
				$size += $this->get_directory_size( $file_path );
			}
		}

		return $size;
	}

	/**
	 * Remove directory contents recursively
	 */
	private function remove_directory_contents( $path ) {
		if ( ! is_dir( $path ) ) {
			return false;
		}

		$files = array_diff( scandir( $path ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$file_path = $path . '/' . $file;

			if ( is_file( $file_path ) ) {
				unlink( $file_path );
			} elseif ( is_dir( $file_path ) ) {
				$this->remove_directory_contents( $file_path );
				@rmdir( $file_path );
			}
		}

		return true;
	}

	/**
	 * Format bytes to human readable format
	 */
	public static function format_bytes( $bytes, $precision = 2 ) {
		$units = array( 'B', 'KB', 'MB', 'GB' );

		$bytes = max( $bytes, 0 );
		$pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow = min( $pow, count( $units ) - 1 );
		$bytes /= ( 1 << ( 10 * $pow ) );

		return round( $bytes, $precision ) . ' ' . $units[ $pow ];
	}
}
