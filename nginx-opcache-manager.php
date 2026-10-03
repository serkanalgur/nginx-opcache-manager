<?php
/**
 * Plugin Name: Nginx Opcache Manager
 * Description: Manage and monitor Nginx cache and PHP Opcache directly from WordPress dashboard with analytics
 * Version: 1.3.6
 * Author: Serkan Algur
 * Author URI: https://github.com/serkanalgur
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nginx-opcache-manager
 * Domain Path: /languages
 * Requires: 6.2
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
define( 'NGINX_OPCACHE_MANAGER_VERSION', '1.3.6' );
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
		require_once NGINX_OPCACHE_MANAGER_PLUGIN_DIR . 'includes/class-scheduler.php';
		require_once NGINX_OPCACHE_MANAGER_PLUGIN_DIR . 'includes/class-post-cache-tracker.php';
		require_once NGINX_OPCACHE_MANAGER_PLUGIN_DIR . 'includes/class-rest-api.php';

		// Admin classes
		if ( is_admin() ) {
			require_once NGINX_OPCACHE_MANAGER_PLUGIN_DIR . 'admin/class-admin.php';
		}

		// Analytics class needed by REST API too
		require_once NGINX_OPCACHE_MANAGER_PLUGIN_DIR . 'admin/class-analytics.php';
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

		// Scheduled purges (must run on every request so WP-Cron fires).
		$scheduler = new Nginx_Opcache_Manager_Scheduler();
		$scheduler->init();

		// Initialize post cache tracker
		if ( get_option( 'nom_enable_post_cache_flush', true ) ) {
			new Nginx_Opcache_Manager_Post_Cache_Tracker();
		}

		// Register REST API routes
		add_action( 'rest_api_init', array( new Nginx_Opcache_Manager_REST_API(), 'register_routes' ) );

		// Admin setup
		if ( is_admin() ) {
			add_action( 'plugins_loaded', array( $this, 'init_admin' ) );
		}
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

		// Set defaults for new automation options (do not override existing values).
		add_option( 'nom_enable_woocommerce_flush', true );
		add_option( 'nom_schedule_enabled', false );
		add_option( 'nom_schedule_interval', 'six_hours' );
		add_option( 'nom_schedule_targets', 'both' );

		// Schedule purge if already enabled.
		$scheduler = new Nginx_Opcache_Manager_Scheduler();
		$scheduler->maybe_reschedule();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Deactivate plugin
	 */
	public function deactivate() {
		$scheduler = new Nginx_Opcache_Manager_Scheduler();
		$scheduler->unschedule();

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
}

/**
 * Initialize plugin
 */
function nginx_opcache_manager_init() {
	return Nginx_Opcache_Manager::get_instance();
}

// Initialize the plugin
nginx_opcache_manager_init();
