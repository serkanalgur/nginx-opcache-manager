<?php
/**
 * REST API endpoints for the React admin panel.
 *
 * @package Nginx_Opcache_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to handle REST API endpoints
 */
class Nginx_Opcache_Manager_REST_API {

	/**
	 * API namespace
	 *
	 * @var string
	 */
	const API_NAMESPACE = 'nom/v1';

	/**
	 * Register REST routes
	 */
	public function register_routes() {
		// Stats endpoint
		register_rest_route(
			self::API_NAMESPACE,
			'/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Nginx cache operations
		register_rest_route(
			self::API_NAMESPACE,
			'/nginx/clear',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'clear_nginx_cache' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Opcache operations
		register_rest_route(
			self::API_NAMESPACE,
			'/opcache/reset',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reset_opcache' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Flush logs
		register_rest_route(
			self::API_NAMESPACE,
			'/logs',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_flush_logs' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Clear activity logs
		register_rest_route(
			self::API_NAMESPACE,
			'/logs/clear',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'clear_activity_logs' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Analytics data
		register_rest_route(
			self::API_NAMESPACE,
			'/analytics',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_analytics' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Analytics summary
		register_rest_route(
			self::API_NAMESPACE,
			'/analytics/summary',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_analytics_summary' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Settings CRUD
		register_rest_route(
			self::API_NAMESPACE,
			'/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_settings' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'body_params' => array(
						'type' => 'object',
					),
				),
			)
		);

		// Server info
		register_rest_route(
			self::API_NAMESPACE,
			'/server-info',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_server_info' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Check user permissions
	 *
	 * @return bool|WP_Error
	 */
	public function check_permissions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to perform this action.', 'nginx-opcache-manager' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Get current statistics
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_stats( $request ) {
		$stats = new Nginx_Opcache_Manager_Stats();
		$nginx_stats   = $stats->get_nginx_stats();
		$opcache_stats = $stats->get_opcache_stats();

		return new WP_REST_Response(
			array(
				'nginx'   => $nginx_stats,
				'opcache' => $opcache_stats,
			),
			200
		);
	}

	/**
	 * Clear Nginx cache
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function clear_nginx_cache( $request ) {
		$cache_manager = new Nginx_Opcache_Manager_Cache();
		$result = $cache_manager->clear_cache();

		if ( $result ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Nginx cache cleared successfully.', 'nginx-opcache-manager' ),
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Failed to clear Nginx cache.', 'nginx-opcache-manager' ),
			),
			500
		);
	}

	/**
	 * Reset PHP Opcache
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reset_opcache( $request ) {
		$opcache_manager = new Nginx_Opcache_Manager_Opcache();
		$result = $opcache_manager->reset_opcache();

		if ( $result ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Opcache reset successfully.', 'nginx-opcache-manager' ),
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Failed to reset Opcache.', 'nginx-opcache-manager' ),
			),
			500
		);
	}

	/**
	 * Get flush logs
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_flush_logs( $request ) {
		$cache_manager = new Nginx_Opcache_Manager_Cache();
		$activities = $cache_manager->get_recent_activities();

		$logs = array();
		foreach ( $activities as $activity ) {
			$logs[] = array(
				'timestamp' => $activity['timestamp'],
				'action'    => $activity['action'],
				'url'       => $activity['url'],
				'file_path' => $activity['file_path'],
				'method'    => $activity['method'],
			);
		}

		return new WP_REST_Response(
			array(
				'logs' => $logs,
			),
			200
		);
	}

	/**
	 * Clear activity logs
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function clear_activity_logs( $request ) {
		$cache_manager = new Nginx_Opcache_Manager_Cache();
		$cache_manager->clear_activity_logs();

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Activity logs cleared successfully.', 'nginx-opcache-manager' ),
			),
			200
		);
	}

	/**
	 * Get analytics data
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_analytics( $request ) {
		$analytics = new Nginx_Opcache_Manager_Analytics();

		$stats = new Nginx_Opcache_Manager_Stats();
		$chart_data = $stats->get_dashboard_data();

		return new WP_REST_Response(
			array(
				'charts'  => $chart_data,
				'summary' => $analytics->get_summary(),
				'metrics' => $analytics->get_performance_metrics(),
			),
			200
		);
	}

	/**
	 * Get analytics summary
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_analytics_summary( $request ) {
		$analytics = new Nginx_Opcache_Manager_Analytics();

		return new WP_REST_Response(
			array(
				'summary' => $analytics->get_summary(),
				'metrics' => $analytics->get_performance_metrics(),
			),
			200
		);
	}

	/**
	 * Get all settings
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_settings( $request ) {
		$settings = array(
			'nginx_cache_enabled'       => (bool) get_option( 'nom_nginx_cache_enabled', false ),
			'nginx_cache_path'          => get_option( 'nom_nginx_cache_path', '/var/run/nginx-cache' ),
			'fastcgi_cache_key_schema'  => get_option( 'nom_fastcgi_cache_key_schema', '$scheme$request_method$host$request_uri' ),
			'enable_notifications'      => (bool) get_option( 'nom_enable_notifications', false ),
			'enable_post_cache_flush'   => (bool) get_option( 'nom_enable_post_cache_flush', true ),
			'enable_woocommerce_flush'  => (bool) get_option( 'nom_enable_woocommerce_flush', true ),
			'schedule_enabled'          => (bool) get_option( 'nom_schedule_enabled', false ),
			'schedule_interval'         => get_option( 'nom_schedule_interval', 'six_hours' ),
			'schedule_targets'          => get_option( 'nom_schedule_targets', 'both' ),
		);

		// Add scheduler info
		if ( class_exists( 'Nginx_Opcache_Manager_Scheduler' ) ) {
			$settings['schedule_next_run'] = Nginx_Opcache_Manager_Scheduler::get_next_run();
			$settings['schedule_last_run'] = Nginx_Opcache_Manager_Scheduler::get_last_run();
			$settings['available_intervals'] = Nginx_Opcache_Manager_Scheduler::get_intervals();
			$settings['available_targets']   = Nginx_Opcache_Manager_Scheduler::get_targets();
		}

		return new WP_REST_Response( $settings, 200 );
	}

	/**
	 * Update settings
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings( $request ) {
		$params = $request->get_json_params();

		$sanitizers = array(
			'nginx_cache_enabled'       => 'rest_sanitize_boolean',
			'nginx_cache_path'          => 'sanitize_text_field',
			'fastcgi_cache_key_schema'  => 'sanitize_text_field',
			'enable_notifications'      => 'rest_sanitize_boolean',
			'enable_post_cache_flush'   => 'rest_sanitize_boolean',
			'enable_woocommerce_flush'  => 'rest_sanitize_boolean',
			'schedule_enabled'          => 'rest_sanitize_boolean',
			'schedule_interval'         => array( 'Nginx_Opcache_Manager_Scheduler', 'sanitize_interval' ),
			'schedule_targets'          => array( 'Nginx_Opcache_Manager_Scheduler', 'sanitize_targets' ),
		);

		$option_map = array(
			'nginx_cache_enabled'       => 'nom_nginx_cache_enabled',
			'nginx_cache_path'          => 'nom_nginx_cache_path',
			'fastcgi_cache_key_schema'  => 'nom_fastcgi_cache_key_schema',
			'enable_notifications'      => 'nom_enable_notifications',
			'enable_post_cache_flush'   => 'nom_enable_post_cache_flush',
			'enable_woocommerce_flush'  => 'nom_enable_woocommerce_flush',
			'schedule_enabled'          => 'nom_schedule_enabled',
			'schedule_interval'         => 'nom_schedule_interval',
			'schedule_targets'          => 'nom_schedule_targets',
		);

		$updated = array();
		foreach ( $params as $key => $value ) {
			if ( isset( $option_map[ $key ] ) ) {
				$option_name = $option_map[ $key ];
				$sanitizer   = isset( $sanitizers[ $key ] ) ? $sanitizers[ $key ] : 'sanitize_text_field';

				if ( is_callable( $sanitizer ) ) {
					$value = call_user_func( $sanitizer, $value );
				}

				update_option( $option_name, $value );
				$updated[ $key ] = $value;
			}
		}

		// Reschedule if schedule settings changed
		if ( isset( $updated['schedule_enabled'] ) || isset( $updated['schedule_interval'] ) || isset( $updated['schedule_targets'] ) ) {
			$scheduler = new Nginx_Opcache_Manager_Scheduler();
			$scheduler->maybe_reschedule();
		}

		return new WP_REST_Response(
			array(
				'success'  => true,
				'message'  => __( 'Settings updated successfully.', 'nginx-opcache-manager' ),
				'settings' => $updated,
			),
			200
		);
	}

	/**
	 * Get server information
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_server_info( $request ) {
		$info = array(
			'php_version'       => PHP_VERSION,
			'wp_version'        => get_bloginfo( 'version' ),
			'server_software'   => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'N/A',
			'opcache_enabled'   => false,
			'opcache_version'   => 'N/A',
		);

		if ( extension_loaded( 'Zend OPcache' ) ) {
			$info['opcache_enabled'] = true;
			$opcache_version         = phpversion( 'Zend OPcache' );
			$info['opcache_version'] = $opcache_version ? $opcache_version : 'N/A';
		}

		return new WP_REST_Response( $info, 200 );
	}
}
