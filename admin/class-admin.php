<?php
/**
 * Admin class
 *
 * @package Nginx_Opcache_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to handle admin pages and settings
 */
class Nginx_Opcache_Manager_Admin {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );

		// AJAX handlers
		add_action( 'wp_ajax_nom_get_flush_logs', array( $this, 'get_flush_logs_ajax' ) );
		add_action( 'wp_ajax_nom_clear_cache', array( $this, 'clear_cache_ajax' ) );
		add_action( 'wp_ajax_nom_reset_opcache', array( $this, 'reset_opcache_ajax' ) );
		add_action( 'wp_ajax_nom_clear_activity_logs', array( $this, 'clear_activity_logs_ajax' ) );

		// Schedule background stats recording
		if ( ! wp_next_scheduled( 'nom_record_stats' ) ) {
			wp_schedule_event( time(), 'hourly', 'nom_record_stats' );
		}
		add_action( 'nom_record_stats', array( $this, 'record_background_stats' ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Nginx Opcache Manager', 'nginx-opcache-manager' ),
			__( 'Cache Manager', 'nginx-opcache-manager' ),
			'manage_options',
			'nginx-opcache-manager',
			array( $this, 'admin_page' ),
			'dashicons-shield',
			75
		);

		add_submenu_page(
			'nginx-opcache-manager',
			__( 'Dashboard', 'nginx-opcache-manager' ),
			__( 'Dashboard', 'nginx-opcache-manager' ),
			'manage_options',
			'nginx-opcache-manager',
			array( $this, 'admin_page' )
		);

		add_submenu_page(
			'nginx-opcache-manager',
			__( 'Analytics', 'nginx-opcache-manager' ),
			__( 'Analytics', 'nginx-opcache-manager' ),
			'manage_options',
			'nginx-opcache-manager-analytics',
			array( $this, 'analytics_page' )
		);

		add_submenu_page(
			'nginx-opcache-manager',
			__( 'Settings', 'nginx-opcache-manager' ),
			__( 'Settings', 'nginx-opcache-manager' ),
			'manage_options',
			'nginx-opcache-manager-settings',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting( 'nom_settings_group', 'nom_nginx_cache_enabled' );
		register_setting( 'nom_settings_group', 'nom_nginx_cache_path' );
		register_setting( 'nom_settings_group', 'nom_enable_notifications' );
		register_setting( 'nom_settings_group', 'nom_enable_post_cache_flush' );
		register_setting( 'nom_settings_group', 'nom_fastcgi_cache_key_schema' );

		add_settings_section( 'nom_nginx_settings', __( 'Nginx Cache Settings', 'nginx-opcache-manager' ), array( $this, 'nginx_section_callback' ), 'nom_settings' );
		add_settings_field( 'nom_nginx_cache_enabled', __( 'Enable Nginx Cache Monitoring', 'nginx-opcache-manager' ), array( $this, 'cache_enabled_callback' ), 'nom_settings', 'nom_nginx_settings' );
		add_settings_field( 'nom_nginx_cache_path', __( 'Nginx Cache Path', 'nginx-opcache-manager' ), array( $this, 'cache_path_callback' ), 'nom_settings', 'nom_nginx_settings' );
		add_settings_field( 'nom_fastcgi_cache_key_schema', __( 'Fastcgi Cache Key Schema', 'nginx-opcache-manager' ), array( $this, 'fastcgi_cache_key_schema_callback' ), 'nom_settings', 'nom_nginx_settings' );
		add_settings_field( 'nom_enable_post_cache_flush', __( 'Auto-Flush Cache on Content Changes', 'nginx-opcache-manager' ), array( $this, 'post_cache_flush_callback' ), 'nom_settings', 'nom_nginx_settings' );
		add_settings_field( 'nom_enable_notifications', __( 'Enable Notifications', 'nginx-opcache-manager' ), array( $this, 'notifications_callback' ), 'nom_settings', 'nom_nginx_settings' );
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'nginx-opcache-manager' ) === false ) {
			return;
		}

		wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', array(), '3.9.1', true );
		wp_enqueue_script( 'nom-admin', NGINX_OPCACHE_MANAGER_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'chart-js' ), NGINX_OPCACHE_MANAGER_VERSION, true );
		wp_enqueue_style( 'nom-admin', NGINX_OPCACHE_MANAGER_PLUGIN_URL . 'assets/css/admin.css', array(), NGINX_OPCACHE_MANAGER_VERSION );

		wp_localize_script( 'nom-admin', 'nomData', array(
			'nonce'     => wp_create_nonce( 'nom_nonce' ),
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'pluginUrl' => NGINX_OPCACHE_MANAGER_PLUGIN_URL,
		) );

		// Localize JavaScript strings
		wp_localize_script( 'nom-admin', 'nomLocalize', array(
			'confirmClearCache'   => __( 'Are you sure you want to clear the Nginx cache?', 'nginx-opcache-manager' ),
			'confirmResetOpcache' => __( 'Are you sure you want to reset Opcache?', 'nginx-opcache-manager' ),
		) );
	}

	/**
	 * Main admin page
	 */
	public function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'nginx-opcache-manager' ) );
		}

		include NGINX_OPCACHE_MANAGER_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Analytics page
	 */
	public function analytics_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'nginx-opcache-manager' ) );
		}

		include NGINX_OPCACHE_MANAGER_PLUGIN_DIR . 'admin/views/analytics.php';
	}

	/**
	 * Settings page
	 */
	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'nginx-opcache-manager' ) );
		}

		include NGINX_OPCACHE_MANAGER_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Settings section callback
	 */
	public function nginx_section_callback() {
		echo '<p>' . esc_html__( 'Configure your Nginx cache settings', 'nginx-opcache-manager' ) . '</p>';
	}

	/**
	 * Cache enabled field callback
	 */
	public function cache_enabled_callback() {
		$enabled = get_option( 'nom_nginx_cache_enabled' );
		?>
<input type="checkbox" name="nom_nginx_cache_enabled" value="1" <?php checked( $enabled, 1 ); ?> />
<label><?php esc_html_e( 'Enable Nginx cache monitoring', 'nginx-opcache-manager' ); ?></label>
<?php
	}

	/**
	 * Cache path field callback
	 */
	public function cache_path_callback() {
		$path = get_option( 'nom_nginx_cache_path', '/var/run/nginx-cache' );
		?>
<input type="text" name="nom_nginx_cache_path" value="<?php echo esc_attr( $path ); ?>" style="width: 400px;"
    placeholder="/var/run/nginx-cache" />
<p class="description"><?php esc_html_e( 'Path to your Nginx cache directory', 'nginx-opcache-manager' ); ?></p>
<?php
	}

	/**
	 * Notifications field callback
	 */
	public function notifications_callback() {
		$enabled = get_option( 'nom_enable_notifications' );
		?>
<input type="checkbox" name="nom_enable_notifications" value="1" <?php checked( $enabled, 1 ); ?> />
<label><?php esc_html_e( 'Enable admin notifications', 'nginx-opcache-manager' ); ?></label>
<?php
	}

	/**
	 * Post cache flush field callback
	 */
	public function post_cache_flush_callback() {
		$enabled = get_option( 'nom_enable_post_cache_flush', true );
		?>
<input type="checkbox" name="nom_enable_post_cache_flush" value="1" <?php checked( $enabled, 1 ); ?> />
<label><?php esc_html_e( 'Automatically flush related cache when posts, pages, or comments are created/updated', 'nginx-opcache-manager' ); ?></label>
<p class="description">
    <?php esc_html_e( 'This will clear cache for the modified post, archives, and homepage.', 'nginx-opcache-manager' ); ?>
</p>
<?php
	}

	/**
	 * Fastcgi cache key schema field callback
	 */
	public function fastcgi_cache_key_schema_callback() {
		$schema = get_option( 'nom_fastcgi_cache_key_schema', '$scheme$request_method$host$request_uri' );
		?>
<input type="text" name="nom_fastcgi_cache_key_schema" value="<?php echo esc_attr( $schema ); ?>" style="width: 500px;"
    placeholder="$scheme$request_method$host$request_uri" />
<p class="description">
    <?php esc_html_e( 'Define your Nginx fastcgi_cache_key format. Use tags: $scheme, $request_method, $host, $request_uri, $query_string', 'nginx-opcache-manager' ); ?>
    <br />
    <?php esc_html_e( 'Default: ', 'nginx-opcache-manager' ); ?><code>$scheme$request_method$host$request_uri</code>
    <br />
    <?php esc_html_e( 'Example: ', 'nginx-opcache-manager' ); ?><code>$scheme$host$request_uri</code>
</p>
<?php
	}

	/**
	 * Add dashboard widget
	 */
	public function add_dashboard_widget() {
		wp_add_dashboard_widget( 'nom_dashboard_widget', __( 'Cache Status', 'nginx-opcache-manager' ), array( $this, 'dashboard_widget' ) );
	}

	/**
	 * Dashboard widget output
	 */
	public function dashboard_widget() {
		include NGINX_OPCACHE_MANAGER_PLUGIN_DIR . 'admin/views/dashboard-widget.php';
	}

	/**
	 * Record statistics in background
	 */
	public function record_background_stats() {
		$stats = new Nginx_Opcache_Manager_Stats();
		$stats->record_stats();
	}

	/**
	 * AJAX handler to get recent cache flush logs
	 */
	public function get_flush_logs_ajax() {
		check_ajax_referer( 'nom_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'nginx-opcache-manager' ) );
		}

		$nginx_cache = new Nginx_Opcache_Manager_Cache();
		$activities = $nginx_cache->get_recent_activities();

		// Format activities for display (removed expensive post lookup)
		$logs = array();
		foreach ( $activities as $activity ) {
			$log_item = array(
				'timestamp' => $activity['timestamp'],
				'action'    => $activity['action'],
				'url'       => $activity['url'],
				'file_path' => $activity['file_path'],
				'method'    => $activity['method'],
			);
			
			// Debug: Log what we're returning
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( 'NOM AJAX: Returning action="' . $activity['action'] . '"' );
			}
			
			$logs[] = $log_item;
		}

		wp_send_json_success( array( 'logs' => $logs ) );
	}

	/**
	 * AJAX handler to clear Nginx cache
	 */
	public function clear_cache_ajax() {
		check_ajax_referer( 'nom_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'nginx-opcache-manager' ) );
		}

		$nginx_cache = new Nginx_Opcache_Manager_Cache();
		$result = $nginx_cache->clear_cache();

		if ( $result ) {
			wp_send_json_success( __( 'Cache cleared successfully', 'nginx-opcache-manager' ) );
		} else {
			wp_send_json_error( __( 'Failed to clear cache', 'nginx-opcache-manager' ) );
		}
	}

	/**
	 * AJAX handler to reset Opcache
	 */
	public function reset_opcache_ajax() {
		check_ajax_referer( 'nom_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'nginx-opcache-manager' ) );
		}

		$opcache = new Nginx_Opcache_Manager_Opcache();
		$result = $opcache->reset_opcache();

		if ( $result ) {
			wp_send_json_success( __( 'Opcache reset successfully', 'nginx-opcache-manager' ) );
		} else {
			wp_send_json_error( __( 'Failed to reset Opcache', 'nginx-opcache-manager' ) );
		}
	}

	/**
	 * AJAX handler to clear activity logs
	 */
	public function clear_activity_logs_ajax() {
		check_ajax_referer( 'nom_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'nginx-opcache-manager' ) );
		}

		$nginx_cache = new Nginx_Opcache_Manager_Cache();
		$nginx_cache->clear_activity_logs();

		wp_send_json_success( __( 'Activity logs cleared successfully', 'nginx-opcache-manager' ) );
	}
}
