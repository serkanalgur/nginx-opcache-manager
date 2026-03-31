<?php
/**
 * Dashboard widget template
 *
 * @package Nginx_Opcache_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stats = new Nginx_Opcache_Manager_Stats();
$current_stats = $stats->get_current_stats();
?>

<div class="nom-dashboard-widget">
	<div class="nom-widget-section">
		<h4><?php esc_html_e( 'Nginx Cache', 'nginx-opcache-manager' ); ?></h4>
		<ul class="nom-widget-list">
			<li>
				<span class="label"><?php esc_html_e( 'Cache Size:', 'nginx-opcache-manager' ); ?></span>
				<span class="value">
					<?php 
					if ( isset( $current_stats['nginx']['cache_size'] ) ) {
						echo esc_html( Nginx_Opcache_Manager_Cache::format_bytes( $current_stats['nginx']['cache_size'] ) );
					} else {
						echo '—';
					}
					?>
				</span>
			</li>
			<li>
				<span class="label"><?php esc_html_e( 'Files:', 'nginx-opcache-manager' ); ?></span>
				<span class="value">
					<?php 
					if ( isset( $current_stats['nginx']['cached_files'] ) ) {
						echo esc_html( $current_stats['nginx']['cached_files'] );
					} else {
						echo '—';
					}
					?>
				</span>
			</li>
		</ul>
	</div>

	<?php if ( $current_stats['opcache']['enabled'] ) : ?>
		<div class="nom-widget-section">
			<h4><?php esc_html_e( 'PHP Opcache', 'nginx-opcache-manager' ); ?></h4>
			<ul class="nom-widget-list">
				<li>
					<span class="label"><?php esc_html_e( 'Hit Rate:', 'nginx-opcache-manager' ); ?></span>
					<span class="value">
						<?php echo esc_html( $current_stats['opcache']['hit_rate'] ); ?>%
					</span>
				</li>
				<li>
					<span class="label"><?php esc_html_e( 'Scripts:', 'nginx-opcache-manager' ); ?></span>
					<span class="value">
						<?php echo esc_html( $current_stats['opcache']['cached_scripts'] ); ?>
					</span>
				</li>
				<li>
					<span class="label"><?php esc_html_e( 'Memory:', 'nginx-opcache-manager' ); ?></span>
					<span class="value">
						<?php 
						if ( isset( $current_stats['opcache']['used_memory'] ) && isset( $current_stats['opcache']['total_memory'] ) ) {
							echo esc_html( Nginx_Opcache_Manager_Opcache::format_bytes( $current_stats['opcache']['used_memory'] ) );
							echo ' / ';
							echo esc_html( Nginx_Opcache_Manager_Opcache::format_bytes( $current_stats['opcache']['total_memory'] ) );
						} else {
							echo '—';
						}
						?>
					</span>
				</li>
			</ul>
		</div>
	<?php endif; ?>

	<div class="nom-widget-actions">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nginx-opcache-manager' ) ); ?>" class="button button-primary">
			<?php esc_html_e( 'View Dashboard', 'nginx-opcache-manager' ); ?>
		</a>
	</div>
</div>

<style>
	.nom-dashboard-widget {
		margin-bottom: 20px;
	}

	.nom-widget-section {
		margin-bottom: 20px;
	}

	.nom-widget-section h4 {
		margin: 0 0 10px 0;
		font-size: 13px;
		font-weight: 600;
		color: #23282d;
	}

	.nom-widget-list {
		list-style: none;
		margin: 0;
		padding: 0;
	}

	.nom-widget-list li {
		padding: 5px 0;
		line-height: 1.6;
		border-bottom: 1px solid #eee;
	}

	.nom-widget-list li:last-child {
		border-bottom: none;
	}

	.nom-widget-list .label {
		display: inline-block;
		width: 80px;
		color: #666;
		font-size: 12px;
	}

	.nom-widget-list .value {
		font-weight: 600;
		color: #0073aa;
	}

	.nom-widget-actions {
		margin-top: 15px;
		padding-top: 15px;
		border-top: 1px solid #eee;
	}

	.nom-widget-actions .button {
		width: 100%;
		text-align: center;
		padding: 8px;
	}
</style>
