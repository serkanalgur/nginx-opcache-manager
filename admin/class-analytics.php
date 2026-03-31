<?php
/**
 * Analytics class
 *
 * @package Nginx_Opcache_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to handle analytics data and charts
 */
class Nginx_Opcache_Manager_Analytics {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Add any analytics-specific hooks here
	}

	/**
	 * Get analytics data for charts
	 */
	public function get_chart_data() {
		$stats = new Nginx_Opcache_Manager_Stats();
		return $stats->get_dashboard_data();
	}

	/**
	 * Get summary statistics
	 */
	public function get_summary() {
		$nginx = new Nginx_Opcache_Manager_Cache();
		$opcache = new Nginx_Opcache_Manager_Opcache();

		$nginx_stats = $nginx->get_cache_stats();
		$opcache_stats = $opcache->get_opcache_stats();

		return array(
			'nginx'   => $nginx_stats,
			'opcache' => $opcache_stats,
		);
	}

	/**
	 * Format bytes to human readable
	 */
	public static function format_bytes( $bytes ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$bytes = max( $bytes, 0 );
		$pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow = min( $pow, count( $units ) - 1 );
		$bytes /= ( 1 << ( 10 * $pow ) );
		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}

	/**
	 * Get performance metrics
	 */
	public function get_performance_metrics() {
		$opcache = new Nginx_Opcache_Manager_Opcache();
		$opcache_stats = $opcache->get_opcache_stats();

		$metrics = array(
			'opcache_hit_rate'    => isset( $opcache_stats['hit_rate'] ) ? $opcache_stats['hit_rate'] : 0,
			'opcache_memory_used' => isset( $opcache_stats['used_memory'] ) ? self::format_bytes( $opcache_stats['used_memory'] ) : '0 B',
			'cached_scripts'      => isset( $opcache_stats['cached_scripts'] ) ? $opcache_stats['cached_scripts'] : 0,
		);

		return $metrics;
	}
}
