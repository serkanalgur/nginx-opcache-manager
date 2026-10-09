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
	 * Default nginx fastcgi_cache_path levels (nginx default)
	 */
	const DEFAULT_CACHE_LEVELS = '1:2';

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
	 * Get nginx fastcgi_cache_path levels from settings
	 * 
	 * Falls back to the nginx default ("1:2") when the stored value is
	 * missing or not a levels string nginx accepts, so purges still target
	 * the correct path instead of a broken one.
	 * 
	 * @return string Levels string, e.g. "1:2"
	 */
	private function get_cache_levels() {
		$levels = get_option( 'nom_fastcgi_cache_levels', self::DEFAULT_CACHE_LEVELS );
		$levels = sanitize_text_field( $levels );

		if ( ! self::is_valid_cache_levels( $levels ) ) {
			return self::DEFAULT_CACHE_LEVELS;
		}

		return $levels;
	}

	/**
	 * Check whether a levels string is one nginx accepts
	 * 
	 * nginx allows 1 to 3 levels, and each level accepts the value 1 or 2
	 * (e.g. "1", "1:2", "2:2:2"). See ngx_http_fastcgi_module fastcgi_cache_path.
	 * 
	 * @param string $levels Levels string to validate
	 * @return bool Whether the levels string is valid
	 */
	public static function is_valid_cache_levels( $levels ) {
		return (bool) preg_match( '/^[12](:[12]){0,2}$/', $levels );
	}

	/**
	 * Build the cache file path (relative to the cache root) for a key hash
	 * 
	 * Mirrors nginx fastcgi_cache_path "levels" semantics: level directories
	 * are taken from the END of the MD5 hash, in the order the levels are
	 * declared, and the file name is the full hash.
	 * Example: hash=b7f54b2df7773722d382f4809d65029c, levels "1:2" -> /c/29/b7f54b2df7773722d382f4809d65029c
	 * (matches the nginx documentation for levels=1:2)
	 * 
	 * @param string $cache_key_hash Full MD5 hex hash of the cache key
	 * @param string $levels Levels string, e.g. "1:2"
	 * @return string Path relative to the cache root, e.g. "/c/29/<hash>"
	 */
	public static function build_cache_file_relative_path( $cache_key_hash, $levels ) {
		$offset = 0; // Characters already consumed from the end of the hash
		$path = '';

		foreach ( explode( ':', $levels ) as $level ) {
			$length = (int) $level;
			$offset += $length;
			// Each level takes its characters immediately before the previous slice.
			$path .= '/' . substr( $cache_key_hash, -$offset, $length );
		}

		return $path . '/' . $cache_key_hash;
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
	 * Cache directory structure follows the configurable fastcgi_cache_path
	 * levels (option: nom_fastcgi_cache_levels, default "1:2"):
	 * - Level directories are taken from the end of the MD5 hash, in the
	 *   order the levels are declared
	 * - Full MD5 hash as filename
	 * Example with levels "1:2": beda56a8736ae8bc335cdd74983649f5 -> /5/9f/beda56a8736ae8bc335cdd74983649f5
	 * 
	 * @param string $url Full URL (e.g., https://example.com/path)
	 * @param string $method Optional HTTP method (default: GET)
	 * @return array Result array with keys: success (bool), file_path (string), message (string), reason (string)
	 *               Reason is 'invalid_url', 'not_found' or 'delete_failed' when success is false, '' otherwise.
	 */
	public function clear_url_cache( $url, $method = 'GET' ) {
		// Parse URL to extract components
		$url_parts = wp_parse_url( $url );
		
		if ( empty( $url_parts['host'] ) ) {
			return array(
				'success'   => false,
				'file_path' => '',
				'message'   => __( 'Invalid URL provided', 'nginx-opcache-manager' ),
				'reason'    => 'invalid_url',
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
		$levels = $this->get_cache_levels();
		
		// Build cache file path using the configured fastcgi_cache_path levels.
		// Example with levels "1:2": hash=beda56a8736ae8bc335cdd74983649f5 -> /5/9f/beda56a8736ae8bc335cdd74983649f5
		$cache_file = $cache_path . self::build_cache_file_relative_path( $cache_key_hash, $levels );

		if ( file_exists( $cache_file ) ) {
			$deleted = unlink( $cache_file );
			if ( $deleted ) {
				$this->log_cache_activity( 'deleted', $url, $cache_file, $method );
				return array(
					'success'   => true,
					'file_path' => $cache_file,
					'message'   => __( 'Cache file deleted successfully', 'nginx-opcache-manager' ),
					'reason'    => '',
				);
			} else {
				$this->log_cache_activity( 'delete_failed', $url, $cache_file, $method );
				return array(
					'success'   => false,
					'file_path' => $cache_file,
					'message'   => __( 'Failed to delete cache file. Check file permissions.', 'nginx-opcache-manager' ),
					'reason'    => 'delete_failed',
				);
			}
		} else {
			$this->log_cache_activity( 'not_found', $url, $cache_file, $method );
			return array(
				'success'   => false,
				'file_path' => $cache_file,
				'message'   => __( 'Cache file not found at expected location. Verify the cache levels setting matches the fastcgi_cache_path levels in the nginx configuration.', 'nginx-opcache-manager' ),
				'reason'    => 'not_found',
			);
		}
	}

	/**
	 * Verify that the cache directory layout matches the configured levels
	 * 
	 * Read-only sanity check: samples cached files in the cache directory and
	 * confirms each file's directory path matches the path the configured
	 * levels would produce for that file's hash. This catches a mismatch
	 * between nom_fastcgi_cache_levels and the levels=... of the live
	 * nginx fastcgi_cache_path directive, which would make every purge miss.
	 * 
	 * Logs the verdict once per call when the layout does not match
	 * (only when WP_DEBUG_LOG is enabled).
	 * 
	 * @return array Report with keys: status (string: 'match'|'mismatch'|'no_data'),
	 *               configured_levels (string), cache_path (string),
	 *               sampled_files (int), mismatches (array), message (string)
	 */
	public function verify_cache_levels() {
		$levels = $this->get_cache_levels();
		$cache_path = $this->get_cache_path();
		// Levels directories plus the file itself.
		$expected_depth = count( explode( ':', $levels ) ) + 1;
		$max_samples = 100;

		$report = array(
			'status'            => 'no_data',
			'configured_levels' => $levels,
			'cache_path'        => $cache_path,
			'sampled_files'     => 0,
			'mismatches'        => array(),
			'message'           => __( 'No cached files found to verify the levels against.', 'nginx-opcache-manager' ),
		);

		if ( ! is_dir( $cache_path ) ) {
			$report['message'] = __( 'Cache directory does not exist.', 'nginx-opcache-manager' );
			return $report;
		}

		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $cache_path, RecursiveDirectoryIterator::SKIP_DOTS )
		);

		$mismatches = 0;

		foreach ( $it as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			$relative_path = $it->getSubPathname();

			// Only files stored at exactly the configured depth tell us anything.
			if ( count( explode( '/', $relative_path ) ) !== $expected_depth ) {
				continue;
			}

			$hash = basename( $relative_path );

			// Skip anything that is not a full MD5 cache key hash.
			if ( ! preg_match( '/^[0-9a-f]{32}$/', $hash ) ) {
				continue;
			}

			$expected_path = ltrim( self::build_cache_file_relative_path( $hash, $levels ), '/' );
			$report['sampled_files']++;

			if ( $relative_path !== $expected_path ) {
				$mismatches++;

				if ( count( $report['mismatches'] ) < 5 ) {
					$report['mismatches'][] = array(
						'actual'   => $relative_path,
						'expected' => $expected_path,
					);
				}
			}

			if ( $report['sampled_files'] >= $max_samples ) {
				break;
			}
		}

		if ( 0 === $report['sampled_files'] ) {
			return $report;
		}

		if ( $mismatches > 0 ) {
			$report['status'] = 'mismatch';
			$report['message'] = sprintf(
				/* translators: 1: configured levels string, 2: number of mismatched files, 3: number of sampled files */
				__( 'Cache directory layout does not match configured levels %1$s (%2$d of %3$d sampled files). The cache levels setting must match the levels parameter of the nginx fastcgi_cache_path directive.', 'nginx-opcache-manager' ),
				$levels,
				$mismatches,
				$report['sampled_files']
			);

			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log(
					sprintf(
						'[%s] Cache Levels Check - %s',
						current_time( 'mysql' ),
						$report['message']
					)
				);
			}
		} else {
			$report['status'] = 'match';
			$report['message'] = sprintf(
				/* translators: %s: configured levels string */
				__( 'Cache directory layout matches configured levels %s.', 'nginx-opcache-manager' ),
				$levels
			);
		}

		return $report;
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
