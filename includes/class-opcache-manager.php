<?php
/**
 * Opcache Manager class
 *
 * @package Nginx_Opcache_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to manage PHP Opcache
 */
class Nginx_Opcache_Manager_Opcache {

	/**
	 * Check if opcache is enabled
	 */
	public function is_enabled() {
		return extension_loaded( 'Zend OPcache' ) && ini_get( 'opcache.enable' );
	}

	/**
	 * Get opcache status
	 */
	public function get_status() {
		if ( ! $this->is_enabled() ) {
			return array(
				'enabled' => false,
				'error'   => 'Opcache is not enabled',
			);
		}

		$status = opcache_get_status( false );
		return $status ? $status : array();
	}

	/**
	 * Get opcache configuration
	 */
	public function get_config() {
		if ( ! $this->is_enabled() ) {
			return array();
		}

		return opcache_get_configuration();
	}

	/**
	 * Get opcache statistics
	 */
	public function get_opcache_stats() {
		if ( ! $this->is_enabled() ) {
			return array(
				'enabled'        => false,
				'used_memory'    => 0,
				'free_memory'    => 0,
				'wasted_memory'  => 0,
				'cached_scripts' => 0,
				'hit_rate'       => 0,
				'memory_usage'   => 0,
			);
		}

		$status = $this->get_status();
		$config = $this->get_config();

		if ( empty( $status ) ) {
			return array(
				'enabled' => true,
				'error'   => 'Unable to retrieve opcache status',
			);
		}

		$memory_usage = isset( $status['memory_usage'] ) ? $status['memory_usage'] : array();
		$stats = isset( $status['opcache_statistics'] ) ? $status['opcache_statistics'] : array();

		$used_memory = isset( $memory_usage['used_memory'] ) ? $memory_usage['used_memory'] : 0;
		$free_memory = isset( $memory_usage['free_memory'] ) ? $memory_usage['free_memory'] : 0;
		$wasted_memory = isset( $memory_usage['wasted_memory'] ) ? $memory_usage['wasted_memory'] : 0;

		$num_cached_scripts = isset( $stats['num_cached_scripts'] ) ? $stats['num_cached_scripts'] : 0;
		$num_hits = isset( $stats['hits'] ) ? $stats['hits'] : 0;
		$num_misses = isset( $stats['misses'] ) ? $stats['misses'] : 0;
		$total_requests = $num_hits + $num_misses;

		$hit_rate = $total_requests > 0 ? ( $num_hits / $total_requests ) * 100 : 0;

		$memory_usage_percent = ( $used_memory > 0 && ( $used_memory + $free_memory ) > 0 ) 
			? ( $used_memory / ( $used_memory + $free_memory ) ) * 100 
			: 0;

		return array(
			'enabled'          => true,
			'used_memory'      => $used_memory,
			'free_memory'      => $free_memory,
			'wasted_memory'    => $wasted_memory,
			'cached_scripts'   => $num_cached_scripts,
			'hits'             => $num_hits,
			'misses'           => $num_misses,
			'hit_rate'         => round( $hit_rate, 2 ),
			'memory_usage'     => round( $memory_usage_percent, 2 ),
			'total_memory'     => $used_memory + $free_memory + $wasted_memory,
			'status'           => $status,
		);
	}

	/**
	 * Reset opcache
	 */
	public function reset_opcache() {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		return opcache_reset();
	}

	/**
	 * Invalidate caches for a specific file
	 */
	public function invalidate_file( $file_path ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		return opcache_invalidate( $file_path, true );
	}

	/**
	 * Get cached files list
	 */
	public function get_cached_files() {
		if ( ! $this->is_enabled() ) {
			return array();
		}

		$status = $this->get_status();

		if ( empty( $status ) || ! isset( $status['scripts'] ) ) {
			return array();
		}

		return $status['scripts'];
	}

	/**
	 * Get memory percentage
	 */
	public function get_memory_percentage() {
		$stats = $this->get_opcache_stats();
		return isset( $stats['memory_usage'] ) ? $stats['memory_usage'] : 0;
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
