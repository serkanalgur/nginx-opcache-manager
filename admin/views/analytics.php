<?php
/**
 * Analytics page template
 *
 * @package Nginx_Opcache_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$analytics = new Nginx_Opcache_Manager_Analytics();
$chart_data = $analytics->get_chart_data();
$summary = $analytics->get_summary();
?>

<div class="wrap">
	<div class="nom-header">
		<h1><?php esc_html_e( 'Cache Analytics', 'nginx-opcache-manager' ); ?></h1>
		<p class="description"><?php esc_html_e( 'View detailed analytics for your cache performance', 'nginx-opcache-manager' ); ?></p>
	</div>

	<div class="nom-analytics">
		<!-- Summary Section -->
		<div class="nom-summary-section">
			<h2><?php esc_html_e( 'Current Summary', 'nginx-opcache-manager' ); ?></h2>
			
			<div class="nom-summary-grid">
				<!-- Nginx Summary -->
				<div class="nom-summary-card">
					<h3><?php esc_html_e( 'Nginx Cache', 'nginx-opcache-manager' ); ?></h3>
					<div class="summary-stat">
						<span class="label"><?php esc_html_e( 'Cache Size', 'nginx-opcache-manager' ); ?>:</span>
						<span class="value"><?php echo esc_html( Nginx_Opcache_Manager_Cache::format_bytes( $summary['nginx']['cache_size'] ) ); ?></span>
					</div>
					<div class="summary-stat">
						<span class="label"><?php esc_html_e( 'Files Cached', 'nginx-opcache-manager' ); ?>:</span>
						<span class="value"><?php echo esc_html( $summary['nginx']['cached_files'] ); ?></span>
					</div>
				</div>

				<!-- Opcache Summary -->
				<?php if ( $summary['opcache']['enabled'] ) : ?>
					<div class="nom-summary-card">
						<h3><?php esc_html_e( 'PHP Opcache', 'nginx-opcache-manager' ); ?></h3>
						<div class="summary-stat">
							<span class="label"><?php esc_html_e( 'Hit Rate', 'nginx-opcache-manager' ); ?>:</span>
							<span class="value"><?php echo esc_html( $summary['opcache']['hit_rate'] ); ?>%</span>
						</div>
						<div class="summary-stat">
							<span class="label"><?php esc_html_e( 'Cached Scripts', 'nginx-opcache-manager' ); ?>:</span>
							<span class="value"><?php echo esc_html( $summary['opcache']['cached_scripts'] ); ?></span>
						</div>
						<div class="summary-stat">
							<span class="label"><?php esc_html_e( 'Memory Usage', 'nginx-opcache-manager' ); ?>:</span>
							<span class="value"><?php echo esc_html( $summary['opcache']['memory_usage'] ); ?>%</span>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Charts Section -->
		<?php if ( ! empty( $chart_data['timestamps'] ) ) : ?>
			<div class="nom-charts-section">
				<h2><?php esc_html_e( 'Performance Over Time', 'nginx-opcache-manager' ); ?></h2>
				
				<div id="chartsContainer" class="nom-detailed-charts">
					<?php if ( ! empty( $chart_data['opcache_hits'] ) ) : ?>
						<div class="chart-wrapper full-width">
							<h3><?php esc_html_e( 'Opcache Hits vs Misses', 'nginx-opcache-manager' ); ?></h3>
							<canvas id="nomHitsMissesChart"></canvas>
						</div>
						
						<div class="chart-wrapper full-width">
							<h3><?php esc_html_e( 'Memory Usage Trend', 'nginx-opcache-manager' ); ?></h3>
							<canvas id="nomMemoryTrendChart"></canvas>
						</div>

						<div class="chart-wrapper full-width">
							<h3><?php esc_html_e( 'Nginx Cache Size', 'nginx-opcache-manager' ); ?></h3>
							<canvas id="nomNginxSizeChart"></canvas>
						</div>

						<div class="chart-wrapper half-width">
							<h3><?php esc_html_e( 'Cached Files Count', 'nginx-opcache-manager' ); ?></h3>
							<canvas id="nomCachedFilesChart"></canvas>
						</div>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No statistical data available yet. Data is collected hourly.', 'nginx-opcache-manager' ); ?></p>
					<?php endif; ?>
				</div>

				<!-- Store data for JavaScript -->
				<script type="application/json" id="nomChartData">
					<?php echo wp_json_encode( $chart_data ); ?>
				</script>
			</div>
		<?php endif; ?>

		<!-- Log Table -->
		<div class="nom-logs-section">
			<h2><?php esc_html_e( 'History', 'nginx-opcache-manager' ); ?></h2>
			<div class="nom-log-table-wrapper">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'nginx-opcache-manager' ); ?></th>
							<th><?php esc_html_e( 'Nginx Cache Size', 'nginx-opcache-manager' ); ?></th>
							<th><?php esc_html_e( 'Cached Files', 'nginx-opcache-manager' ); ?></th>
							<th><?php esc_html_e( 'Opcache Hit Rate', 'nginx-opcache-manager' ); ?></th>
							<th><?php esc_html_e( 'Memory Usage', 'nginx-opcache-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$history = ( new Nginx_Opcache_Manager_Stats() )->get_history( 24 );
						if ( ! empty( $history ) ) {
							foreach ( array_reverse( $history ) as $record ) {
								?>
								<tr>
									<td><?php echo esc_html( wp_date( 'Y-m-d H:i', strtotime( $record['timestamp'] ) ) ); ?></td>
									<td><?php echo isset( $record['nginx']['cache_size'] ) ? esc_html( Nginx_Opcache_Manager_Cache::format_bytes( $record['nginx']['cache_size'] ) ) : '—'; ?></td>
									<td><?php echo isset( $record['nginx']['cached_files'] ) ? esc_html( $record['nginx']['cached_files'] ) : '—'; ?></td>
									<td><?php echo isset( $record['opcache']['hit_rate'] ) ? esc_html( $record['opcache']['hit_rate'] ) . '%' : '—'; ?></td>
									<td><?php echo isset( $record['opcache']['memory_usage'] ) ? esc_html( $record['opcache']['memory_usage'] ) . '%' : '—'; ?></td>
								</tr>
								<?php
							}
						} else {
							?>
							<tr>
								<td colspan="5" style="text-align: center;">
									<?php esc_html_e( 'No data available yet', 'nginx-opcache-manager' ); ?>
								</td>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
