<?php
/**
 * Plugin Name: Nginx Opcache Manager
 * Description: Manage and monitor Nginx cache and PHP Opcache directly from WordPress dashboard with analytics
 * Version: 1.1.0
 * Author: Serkan Algur
 * Author URI: https://github.com/serkanalgur
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nginx-opcache-manager
 * Domain Path: /languages
 * Requires: 4.7
 * Requires PHP: 7.2
 *
 * @package Nginx_Opcache_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Define plugin constants
 */
define( 'NGINX_OPCACHE_MANAGER_VERSION', '1.1.0' );
define( 'NGINX_OPCACHE_MANAGER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NGINX_OPCACHE_MANAGER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NGINX_OPCACHE_MANAGER_PLUGIN_FILE', __FILE__ );

/**
 * Main plugin class
 */
class Nginx_Opcache_Manager {

	/**
	 * Instance of this class
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->setup_hooks();
	}

	/**
	 * Load required files
	 */
	private function load_dependencies() {
		// Core classes
		require_once NGINX_OPCACHE_MANAGER_PLUGIN_DIR . 'includes/class-nginx-cache-manager.php';
		require_once NGINX_OPCACHE_MANAGER_PLUGIN_DIR . 'includes/class-opcache-manager.php';
		require_once NGINX_OPCACHE_MANAGER_PLUGIN_DIR . 'includes/class-cache-stats.php';
		require_once NGINX_OPCACHE_MANAGER_PLUGIN_DIR . 'includes/class-post-cache-tracker.php';

		// Admin classes
		if ( is_admin() ) {
			require_once NGINX_OPCACHE_MANAGER_PLUGIN_DIR . 'admin/class-admin.php';
			require_once NGINX_OPCACHE_MANAGER_PLUGIN_DIR . 'admin/class-analytics.php';
		}
	}

	/**
	 * Setup plugin hooks
	 */
	private function setup_hooks() {
		// Activation/Deactivation
		register_activation_hook( NGINX_OPCACHE_MANAGER_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( NGINX_OPCACHE_MANAGER_PLUGIN_FILE, array( $this, 'deactivate' ) );

		// Internationalization
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Initialize post cache tracker
		if ( get_option( 'nom_enable_post_cache_flush', true ) ) {
			new Nginx_Opcache_Manager_Post_Cache_Tracker();
		}

		// Admin setup
		if ( is_admin() ) {
			add_action( 'plugins_loaded', array( $this, 'init_admin' ) );
		}

		// Register AJAX handlers
		add_action( 'wp_ajax_nom_get_stats', array( $this, 'ajax_get_stats' ) );
		add_action( 'wp_ajax_nom_clear_cache', array( $this, 'ajax_clear_cache' ) );
		add_action( 'wp_ajax_nom_reset_opcache', array( $this, 'ajax_reset_opcache' ) );
		add_action( 'wp_ajax_nom_get_flush_logs', array( $this, 'ajax_get_flush_logs' ) );
	}

	/**
	 * Activate plugin
	 */
	public function activate() {
		// Create database tables
		$cache_manager = new Nginx_Opcache_Manager_Cache();
		$cache_manager->initialize();

		$stats_manager = new Nginx_Opcache_Manager_Stats();
		$stats_manager->initialize();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Deactivate plugin
	 */
	public function deactivate() {
		// Cleanup if needed
		flush_rewrite_rules();
	}

	/**
	 * Load plugin textdomain
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'nginx-opcache-manager',
			false,
			dirname( plugin_basename( NGINX_OPCACHE_MANAGER_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Initialize admin
	 */
	public function init_admin() {
		// Only initialize if user has manage_options capability
		if ( current_user_can( 'manage_options' ) ) {
			$admin = new Nginx_Opcache_Manager_Admin();
			$analytics = new Nginx_Opcache_Manager_Analytics();
		}
	}

	/**
	 * AJAX handler for getting statistics
	 */
	public function ajax_get_stats() {
		$this->check_nonce( 'nom_nonce' );

		$stats = new Nginx_Opcache_Manager_Stats();
		$nginx_stats = $stats->get_nginx_stats();
		$opcache_stats = $stats->get_opcache_stats();

		wp_send_json_success( array(
			'nginx' => $nginx_stats,
			'opcache' => $opcache_stats,
		) );
	}

	/**
	 * AJAX handler for clearing nginx cache
	 */
	public function ajax_clear_cache() {
		$this->check_nonce( 'nom_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$nginx_manager = new Nginx_Opcache_Manager_Cache();
		$result = $nginx_manager->clear_cache();

		if ( $result ) {
			wp_send_json_success( 'Nginx cache cleared successfully' );
		} else {
			wp_send_json_error( 'Failed to clear nginx cache' );
		}
	}

	/**
	 * AJAX handler for resetting opcache
	 */
	public function ajax_reset_opcache() {
		$this->check_nonce( 'nom_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$opcache_manager = new Nginx_Opcache_Manager_Opcache();
		$result = $opcache_manager->reset_opcache();

		if ( $result ) {
			wp_send_json_success( 'Opcache reset successfully' );
		} else {
			wp_send_json_error( 'Failed to reset opcache' );
		}
	}

	/**
	 * AJAX handler for getting flush logs
	 */
	public function ajax_get_flush_logs() {
		$this->check_nonce( 'nom_nonce' );

		$logs = Nginx_Opcache_Manager_Post_Cache_Tracker::get_flush_logs();
		$stats = Nginx_Opcache_Manager_Post_Cache_Tracker::get_cache_stats_by_type();

		wp_send_json_success( array(
			'logs'  => array_reverse( $logs ),
			'stats' => $stats,
		) );
	}

	/**
	 * Check AJAX nonce
	 */
	private function check_nonce( $nonce_name ) {
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), $nonce_name ) ) {
			wp_send_json_error( 'Security check failed' );
			exit;
		}
	}
}

/**
 * Initialize plugin
 */
function nginx_opcache_manager_init() {
	return Nginx_Opcache_Manager::get_instance();
}

// Initialize the plugin
nginx_opcache_manager_init();
