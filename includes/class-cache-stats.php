<?php
/**
 * Cache Statistics Manager class
 *
 * @package Nginx_Opcache_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to manage cache statistics and history
 */
class Nginx_Opcache_Manager_Stats {

	const STATS_TABLE = 'nom_cache_stats';
	const STATS_OPTION = 'nom_cache_stats';

	/**
	 * Initialize statistics storage
	 */
	public function initialize() {
		// We'll use options table for simplicity, but can switch to custom table if needed
		// Create initial stats record
		$initial_stats = array(
			'timestamp' => current_time( 'mysql' ),
			'nginx' => array(),
			'opcache' => array(),
		);

		if ( ! get_option( self::STATS_OPTION ) ) {
			add_option( self::STATS_OPTION, array( $initial_stats ) );
		}
	}

	/**
	 * Get nginx cache statistics
	 */
	public function get_nginx_stats() {
		$nginx_manager = new Nginx_Opcache_Manager_Cache();
		return $nginx_manager->get_cache_stats();
	}

	/**
	 * Get opcache statistics
	 */
	public function get_opcache_stats() {
		$opcache_manager = new Nginx_Opcache_Manager_Opcache();
		return $opcache_manager->get_opcache_stats();
	}

	/**
	 * Record statistics to history
	 */
	public function record_stats() {
		$stats_history = get_option( self::STATS_OPTION, array() );

		$new_record = array(
			'timestamp' => current_time( 'mysql' ),
			'nginx'     => $this->get_nginx_stats(),
			'opcache'   => $this->get_opcache_stats(),
		);

		// Keep only last 100 records (24+ hours approximately)
		if ( count( $stats_history ) >= 100 ) {
			array_shift( $stats_history );
		}

		$stats_history[] = $new_record;
		update_option( self::STATS_OPTION, $stats_history );

		return $new_record;
	}

	/**
	 * Get statistics history
	 */
	public function get_history( $limit = 24 ) {
		$all_stats = get_option( self::STATS_OPTION, array() );
		return array_slice( $all_stats, -$limit );
	}

	/**
	 * Get current statistics
	 */
	public function get_current_stats() {
		return array(
			'nginx'   => $this->get_nginx_stats(),
			'opcache' => $this->get_opcache_stats(),
		);
	}

	/**
	 * Get statistics for dashboard widget
	 */
	public function get_dashboard_data() {
		$history = $this->get_history( 24 );

		$nginx_sizes = array();
		$nginx_files = array();
		$opcache_hits = array();
		$opcache_misses = array();
		$opcache_memory = array();
		$timestamps = array();

		foreach ( $history as $record ) {
			$timestamps[] = mysql2date( 'H:i', $record['timestamp'] );

			if ( isset( $record['nginx']['cache_size'] ) ) {
				$nginx_sizes[] = $record['nginx']['cache_size'] / ( 1024 * 1024 ); // Convert to MB
				$nginx_files[] = $record['nginx']['cached_files'];
			}

			if ( isset( $record['opcache']['hits'] ) ) {
				$opcache_hits[] = $record['opcache']['hits'];
				$opcache_misses[] = $record['opcache']['misses'];
				$opcache_memory[] = $record['opcache']['memory_usage'];
			}
		}

		return array(
			'timestamps'      => $timestamps,
			'nginx_sizes'     => $nginx_sizes,
			'nginx_files'     => $nginx_files,
			'opcache_hits'    => $opcache_hits,
			'opcache_misses'  => $opcache_misses,
			'opcache_memory'  => $opcache_memory,
			'current'         => $this->get_current_stats(),
		);
	}

	/**
	 * Clear all statistics history
	 */
	public function clear_history() {
		$initial_stats = array(
			'timestamp' => current_time( 'mysql' ),
			'nginx'     => array(),
			'opcache'   => array(),
		);

		return update_option( self::STATS_OPTION, array( $initial_stats ) );
	}
}
