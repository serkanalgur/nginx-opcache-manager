<?php
/**
 * Dashboard page template
 *
 * @package Nginx_Opcache_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nginx = new Nginx_Opcache_Manager_Cache();
$opcache = new Nginx_Opcache_Manager_Opcache();
$nginx_stats = $nginx->get_cache_stats();
$opcache_stats = $opcache->get_opcache_stats();

// Build chart data for JavaScript
$chart_data = array(
	'current' => array(
		'opcache' => array(
			'hit_rate'      => floatval( $opcache_stats['hit_rate'] ?? 0 ),
			'memory_usage'  => floatval( $opcache_stats['memory_usage'] ?? 0 ),
			'cached_scripts' => intval( $opcache_stats['cached_scripts'] ?? 0 ),
		),
		'nginx' => array(
			'cache_size'   => intval( $nginx_stats['cache_size'] ?? 0 ),
			'cached_files' => intval( $nginx_stats['cached_files'] ?? 0 ),
		),
	),
	'opcache_hits'    => array(),
	'opcache_misses'  => array(),
	'opcache_memory'  => array(),
	'nginx_sizes'     => array(),
	'nginx_files'     => array(),
	'timestamps'      => array(),
);
?>

<div class="wrap">
	<div class="nom-header">
		<h1><?php esc_html_e( 'Nginx Opcache Manager', 'nginx-opcache-manager' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Monitor and manage your Nginx cache and PHP Opcache', 'nginx-opcache-manager' ); ?></p>
	</div>

	<div class="nom-dashboard">
		<!-- Stats Cards -->
		<div class="nom-stats-grid">
			<!-- Nginx Cache Card -->
			<div class="nom-stat-card nginx-card">
				<div class="stat-header">
					<h3><?php esc_html_e( 'Nginx Cache', 'nginx-opcache-manager' ); ?></h3>
					<?php if ( $nginx_stats['enabled'] ) : ?>
						<span class="nom-badge nom-badge-success"><?php esc_html_e( 'Active', 'nginx-opcache-manager' ); ?></span>
					<?php else : ?>
						<span class="nom-badge nom-badge-warning"><?php esc_html_e( 'Inactive', 'nginx-opcache-manager' ); ?></span>
					<?php endif; ?>
				</div>

				<div class="stat-content">
					<div class="stat-item">
						<label><?php esc_html_e( 'Cache Size', 'nginx-opcache-manager' ); ?></label>
						<div class="stat-value">
							<?php echo esc_html( Nginx_Opcache_Manager_Cache::format_bytes( $nginx_stats['cache_size'] ) ); ?>
						</div>
					</div>

					<div class="stat-item">
						<label><?php esc_html_e( 'Cached Files', 'nginx-opcache-manager' ); ?></label>
						<div class="stat-value">
							<?php echo esc_html( $nginx_stats['cached_files'] ); ?>
						</div>
					</div>

					<div class="stat-item">
						<label><?php esc_html_e( 'Cache Path', 'nginx-opcache-manager' ); ?></label>
						<div class="stat-value small">
							<?php echo esc_html( $nginx_stats['cache_path'] ); ?>
						</div>
					</div>
				</div>

				<div class="stat-actions">
					<button class="button button-primary nom-clear-nginx-btn" data-nonce="<?php echo esc_attr( wp_create_nonce( 'nom_nonce' ) ); ?>">
						<?php esc_html_e( 'Clear Cache', 'nginx-opcache-manager' ); ?>
					</button>
				</div>
			</div>

			<!-- Opcache Card -->
			<div class="nom-stat-card opcache-card">
				<div class="stat-header">
					<h3><?php esc_html_e( 'PHP Opcache', 'nginx-opcache-manager' ); ?></h3>
					<?php if ( $opcache_stats['enabled'] ) : ?>
						<span class="nom-badge nom-badge-success"><?php esc_html_e( 'Active', 'nginx-opcache-manager' ); ?></span>
					<?php else : ?>
						<span class="nom-badge nom-badge-danger"><?php esc_html_e( 'Disabled', 'nginx-opcache-manager' ); ?></span>
					<?php endif; ?>
				</div>

				<div class="stat-content">
					<?php if ( $opcache_stats['enabled'] ) : ?>
						<div class="stat-item">
							<label><?php esc_html_e( 'Hit Rate', 'nginx-opcache-manager' ); ?></label>
							<div class="stat-value">
								<?php echo esc_html( $opcache_stats['hit_rate'] ); ?>%
							</div>
						</div>

						<div class="stat-item">
							<label><?php esc_html_e( 'Memory Usage', 'nginx-opcache-manager' ); ?></label>
							<div class="stat-value">
								<?php echo esc_html( Nginx_Opcache_Manager_Opcache::format_bytes( $opcache_stats['used_memory'] ) ); ?> / 
								<?php echo esc_html( Nginx_Opcache_Manager_Opcache::format_bytes( $opcache_stats['total_memory'] ) ); ?>
							</div>
						</div>

						<div class="stat-item">
							<label><?php esc_html_e( 'Cached Scripts', 'nginx-opcache-manager' ); ?></label>
							<div class="stat-value">
								<?php echo esc_html( $opcache_stats['cached_scripts'] ); ?>
							</div>
						</div>
					<?php else : ?>
						<div class="stat-item">
							<p class="description"><?php esc_html_e( 'Opcache is not enabled on this server', 'nginx-opcache-manager' ); ?></p>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( $opcache_stats['enabled'] ) : ?>
					<div class="stat-actions">
						<button class="button button-primary nom-reset-opcache-btn" data-nonce="<?php echo esc_attr( wp_create_nonce( 'nom_nonce' ) ); ?>">
							<?php esc_html_e( 'Reset Opcache', 'nginx-opcache-manager' ); ?>
						</button>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Live Chart Section -->
		<?php if ( $opcache_stats['enabled'] ) : ?>
			<div class="nom-chart-section">
				<h2><?php esc_html_e( 'Live Performance Metrics', 'nginx-opcache-manager' ); ?></h2>
				<div class="nom-charts-container">
					<div class="nom-chart-wrapper">
						<h3><?php esc_html_e( 'Opcache Hit Rate', 'nginx-opcache-manager' ); ?></h3>
						<div class="nom-gauge-container">
						<canvas id="nomHitRateChart" width="250" height="250"></canvas>
					</div>
					<div class="gauge-value">
						<span id="hitRateValue"><?php echo esc_html( $opcache_stats['hit_rate'] ); ?></span>%
					</div>
				</div>
				<div class="nom-chart-wrapper">
					<h3><?php esc_html_e( 'Memory Usage', 'nginx-opcache-manager' ); ?></h3>
					<div class="nom-gauge-container">
						<canvas id="nomMemoryChart" width="250" height="250"></canvas>
						</div>
						<div class="gauge-value">
							<span id="memoryValue"><?php echo esc_html( $opcache_stats['memory_usage'] ); ?></span>%
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<!-- Cache Flush Activity Section -->
		<div class="nom-activity-section">
			<div class="nom-activity-header">
				<h2><?php esc_html_e( 'Cache Flush Activity', 'nginx-opcache-manager' ); ?></h2>
				<button class="button button-secondary nom-clear-activity-logs-btn" data-nonce="<?php echo esc_attr( wp_create_nonce( 'nom_nonce' ) ); ?>" title="<?php esc_attr_e( 'Clear all activity logs from the database', 'nginx-opcache-manager' ); ?>">
					<?php esc_html_e( 'Clear Logs', 'nginx-opcache-manager' ); ?>
				</button>
			</div>
			<div id="nomFlushLogsContainer" class="nom-flush-logs">
				<p class="description"><?php esc_html_e( 'Loading recent cache flush activity...', 'nginx-opcache-manager' ); ?></p>
			</div>
		</div>

		<!-- Quick Links -->
		<div class="nom-quick-links">
			<h2><?php esc_html_e( 'Quick Links', 'nginx-opcache-manager' ); ?></h2>
			<ul>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=nginx-opcache-manager-analytics' ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'View Analytics', 'nginx-opcache-manager' ); ?>
				</a></li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=nginx-opcache-manager-settings' ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Settings', 'nginx-opcache-manager' ); ?>
				</a></li>
			</ul>
		</div>
	</div>

	<!-- Chart Data -->
	<script type="application/json" id="nomChartData">
		<?php echo wp_json_encode( $chart_data ); ?>
	</script>
</div>
