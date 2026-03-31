<?php
/**
 * Settings page template
 *
 * @package Nginx_Opcache_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<div class="nom-header">
		<h1><?php esc_html_e( 'Cache Manager Settings', 'nginx-opcache-manager' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Configure your cache monitoring preferences', 'nginx-opcache-manager' ); ?></p>
	</div>

	<div class="nom-settings-form">
		<form method="post" action="options.php">
			<?php
			settings_fields( 'nom_settings_group' );
			do_settings_sections( 'nom_settings' );
			submit_button();
			?>
		</form>
	</div>

	<!-- Additional Information -->
	<div class="nom-info-section">
		<h2><?php esc_html_e( 'Information', 'nginx-opcache-manager' ); ?></h2>
		
		<div class="nom-info-box">
			<h3><?php esc_html_e( 'Server Information', 'nginx-opcache-manager' ); ?></h3>
			<ul>
				<li>
					<strong><?php esc_html_e( 'PHP Version', 'nginx-opcache-manager' ); ?>:</strong>
					<?php echo esc_html( phpversion() ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Opcache Enabled', 'nginx-opcache-manager' ); ?>:</strong>
					<?php echo extension_loaded( 'Zend OPcache' ) && ini_get( 'opcache.enable' ) ? '<span style="color: green;">✓ ' . esc_html__( 'Yes', 'nginx-opcache-manager' ) . '</span>' : '<span style="color: red;">✗ ' . esc_html__( 'No', 'nginx-opcache-manager' ) . '</span>'; ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Opcache Memory Limit', 'nginx-opcache-manager' ); ?>:</strong>
					<?php echo esc_html( ini_get( 'opcache.memory_consumption' ) / ( 1024 * 1024 ) ); ?>MB
				</li>
				<li>
					<strong><?php esc_html_e( 'Max Accelerated Files', 'nginx-opcache-manager' ); ?>:</strong>
					<?php echo esc_html( ini_get( 'opcache.max_accelerated_files' ) ); ?>
				</li>
			</ul>
		</div>

		<div class="nom-info-box">
			<h3><?php esc_html_e( 'About This Plugin', 'nginx-opcache-manager' ); ?></h3>
			<p><?php esc_html_e( 'The Nginx Opcache Manager plugin helps you monitor and manage both Nginx cache and PHP Opcache directly from your WordPress dashboard. It provides real-time statistics and analytics to help optimize your server performance.', 'nginx-opcache-manager' ); ?></p>
			
			<h4><?php esc_html_e( 'Features', 'nginx-opcache-manager' ); ?></h4>
			<ul style="list-style: disc; margin-left: 20px;">
				<li><?php esc_html_e( 'Real-time cache statistics', 'nginx-opcache-manager' ); ?></li>
				<li><?php esc_html_e( 'Clear Nginx cache with one click', 'nginx-opcache-manager' ); ?></li>
				<li><?php esc_html_e( 'Reset PHP Opcache', 'nginx-opcache-manager' ); ?></li>
				<li><?php esc_html_e( 'Detailed analytics and charts', 'nginx-opcache-manager' ); ?></li>
				<li><?php esc_html_e( 'Historical data tracking', 'nginx-opcache-manager' ); ?></li>
				<li><?php esc_html_e( 'Dashboard widget', 'nginx-opcache-manager' ); ?></li>
			</ul>
		</div>

		<div class="nom-info-box">
			<h3><?php esc_html_e( 'Support & Documentation', 'nginx-opcache-manager' ); ?></h3>
			<p>
				<?php esc_html_e( 'For more information and support, visit:', 'nginx-opcache-manager' ); ?>
			</p>
			<ul style="list-style: disc; margin-left: 20px;">
				<li><a href="https://wordpress.org/plugins/" target="_blank"><?php esc_html_e( 'WordPress Plugins Directory', 'nginx-opcache-manager' ); ?></a></li>
				<li><a href="https://nginx.org/" target="_blank"><?php esc_html_e( 'Nginx Documentation', 'nginx-opcache-manager' ); ?></a></li>
				<li><a href="https://www.php.net/manual/en/book.opcache.php" target="_blank"><?php esc_html_e( 'PHP Opcache Documentation', 'nginx-opcache-manager' ); ?></a></li>
			</ul>
		</div>
	</div>

	<!-- Danger Zone -->
	<div class="nom-danger-zone">
		<h2><?php esc_html_e( 'Danger Zone', 'nginx-opcache-manager' ); ?></h2>
		<div class="nom-danger-box">
			<h3><?php esc_html_e( 'Clear All Data', 'nginx-opcache-manager' ); ?></h3>
			<p><?php esc_html_e( 'This will permanently delete all stored statistics and analytics data. This action cannot be undone.', 'nginx-opcache-manager' ); ?></p>
			<button class="button button-primary" id="nomClearDataBtn" 
				onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to clear all data?', 'nginx-opcache-manager' ); ?>');">
				<?php esc_html_e( 'Clear All Data', 'nginx-opcache-manager' ); ?>
			</button>
		</div>
	</div>
</div>

<style>
	.nom-info-section {
		margin-top: 40px;
	}

	.nom-info-box {
		background: #fff;
		border: 1px solid #ccc;
		border-radius: 4px;
		padding: 20px;
		margin-bottom: 20px;
	}

	.nom-info-box h3 {
		margin-top: 0;
	}

	.nom-info-box ul {
		margin: 10px 0;
	}

	.nom-danger-zone {
		background: #fff;
		border: 1px solid #cc0000;
		border-radius: 4px;
		padding: 20px;
		margin-top: 40px;
	}

	.nom-danger-zone h2 {
		color: #cc0000;
	}

	.nom-danger-box {
		background: #fafafa;
		border-left: 4px solid #cc0000;
		padding: 15px;
		margin-top: 10px;
	}
</style>
