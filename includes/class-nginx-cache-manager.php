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
	 */
	public function clear_url_cache( $url ) {
		// Generate cache key from URL
		$cache_key = md5( $url );
		$cache_path = $this->get_cache_path();
		$cache_file = $cache_path . '/' . substr( $cache_key, 0, 1 ) . '/' . substr( $cache_key, 1, 1 ) . '/' . substr( $cache_key, 2 );

		if ( file_exists( $cache_file ) ) {
			return unlink( $cache_file );
		}

		return false;
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
