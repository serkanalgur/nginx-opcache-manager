<?php
/**
 * Scheduled Cache Purge class
 *
 * Handles periodic (6h / 12h / daily / weekly) full cache purges
 * for Nginx cache and PHP Opcache via WP-Cron.
 *
 * @package Nginx_Opcache_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to manage scheduled cache purges
 */
class Nginx_Opcache_Manager_Scheduler {

	/**
	 * Cron hook name
	 */
	const CRON_HOOK = 'nom_scheduled_purge';

	/**
	 * Option names
	 */
	const OPTION_ENABLED  = 'nom_schedule_enabled';
	const OPTION_INTERVAL = 'nom_schedule_interval';
	const OPTION_TARGETS  = 'nom_schedule_targets';
	const OPTION_LAST_RUN = 'nom_schedule_last_run';

	/**
	 * Supported intervals (slug => seconds)
	 *
	 * @return array
	 */
	public static function get_intervals() {
		return array(
			'hourly'       => array(
				'seconds' => HOUR_IN_SECONDS,
				'label'   => __( 'Every hour', 'nginx-opcache-manager' ),
			),
			'six_hours'    => array(
				'seconds' => 6 * HOUR_IN_SECONDS,
				'label'   => __( 'Every 6 hours', 'nginx-opcache-manager' ),
			),
			'twelve_hours' => array(
				'seconds' => 12 * HOUR_IN_SECONDS,
				'label'   => __( 'Every 12 hours', 'nginx-opcache-manager' ),
			),
			'daily'        => array(
				'seconds' => DAY_IN_SECONDS,
				'label'   => __( 'Daily', 'nginx-opcache-manager' ),
			),
			'weekly'       => array(
				'seconds' => WEEK_IN_SECONDS,
				'label'   => __( 'Weekly', 'nginx-opcache-manager' ),
			),
		);
	}

	/**
	 * Supported purge targets
	 *
	 * @return array
	 */
	public static function get_targets() {
		return array(
			'both'    => __( 'Nginx cache + Opcache', 'nginx-opcache-manager' ),
			'nginx'   => __( 'Nginx cache only', 'nginx-opcache-manager' ),
			'opcache' => __( 'Opcache only', 'nginx-opcache-manager' ),
		);
	}

	/**
	 * Register hooks (call on every request, cron needs this)
	 */
	public function init() {
		add_filter( 'cron_schedules', array( $this, 'register_cron_schedules' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_scheduled_purge' ) );

		// Reschedule when settings change.
		add_action( 'update_option_' . self::OPTION_ENABLED, array( $this, 'maybe_reschedule' ), 10, 2 );
		add_action( 'update_option_' . self::OPTION_INTERVAL, array( $this, 'maybe_reschedule' ), 10, 2 );
		add_action( 'update_option_' . self::OPTION_TARGETS, array( $this, 'maybe_reschedule' ), 10, 2 );

		// Ensure correct schedule on every admin load (cheap guard).
		add_action( 'admin_init', array( $this, 'maybe_reschedule' ) );
	}

	/**
	 * Register custom cron schedules (6h / 12h)
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function register_cron_schedules( $schedules ) {
		if ( ! isset( $schedules['six_hours'] ) ) {
			$schedules['six_hours'] = array(
				'interval' => 6 * HOUR_IN_SECONDS,
				/* translators: %s: human readable interval */
				'display'  => __( 'Every 6 hours', 'nginx-opcache-manager' ),
			);
		}

		if ( ! isset( $schedules['twelve_hours'] ) ) {
			$schedules['twelve_hours'] = array(
				'interval' => 12 * HOUR_IN_SECONDS,
				/* translators: %s: human readable interval */
				'display'  => __( 'Every 12 hours', 'nginx-opcache-manager' ),
			);
		}

		return $schedules;
	}

	/**
	 * Is scheduled purge enabled?
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) get_option( self::OPTION_ENABLED, false );
	}

	/**
	 * Get configured interval slug, validated.
	 *
	 * @return string
	 */
	public static function get_interval() {
		$interval  = get_option( self::OPTION_INTERVAL, 'six_hours' );
		$intervals = self::get_intervals();

		if ( ! isset( $intervals[ $interval ] ) ) {
			return 'six_hours';
		}

		return $interval;
	}

	/**
	 * Get configured purge targets, validated.
	 *
	 * @return string
	 */
	public static function get_targets_option() {
		$targets = get_option( self::OPTION_TARGETS, 'both' );
		$allowed = self::get_targets();

		if ( ! isset( $allowed[ $targets ] ) ) {
			return 'both';
		}

		return $targets;
	}

	/**
	 * Schedule the event if enabled and not already scheduled.
	 * Unschedule when disabled or interval changed.
	 */
	public function maybe_reschedule() {
		if ( ! self::is_enabled() ) {
			$this->unschedule();
			return;
		}

		$interval = self::get_interval();
		$next     = wp_next_scheduled( self::CRON_HOOK );

		if ( $next ) {
			$current_recurrence = wp_get_schedule( self::CRON_HOOK );
			if ( $current_recurrence === $interval ) {
				return;
			}
			$this->unschedule();
		}

		wp_schedule_event( time() + 60, $interval, self::CRON_HOOK );
	}

	/**
	 * Unschedule the event.
	 */
	public function unschedule() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Run the scheduled purge (WP-Cron callback).
	 */
	public function run_scheduled_purge() {
		if ( ! self::is_enabled() ) {
			return;
		}

		$targets = self::get_targets_option();
		$results = array();

		if ( 'nginx' === $targets || 'both' === $targets ) {
			$cache_manager       = new Nginx_Opcache_Manager_Cache();
			$results['nginx']    = (bool) $cache_manager->clear_cache();
		}

		if ( 'opcache' === $targets || 'both' === $targets ) {
			$opcache_manager     = new Nginx_Opcache_Manager_Opcache();
			$results['opcache']  = (bool) $opcache_manager->reset_opcache();
		}

		update_option( self::OPTION_LAST_RUN, current_time( 'mysql' ) );

		/**
		 * Fires after a scheduled cache purge runs.
		 *
		 * @param array  $results Purge results keyed by target.
		 * @param string $targets Configured target slug.
		 */
		do_action( 'nom_scheduled_purge_run', $results, $targets );

		$this->log_scheduled_run( $results );
	}

	/**
	 * Log scheduled run into the shared flush-log transient.
	 *
	 * @param array $results Purge results.
	 */
	private function log_scheduled_run( $results ) {
		$logs = get_transient( 'nom_cache_flush_logs' );

		if ( false === $logs || ! is_array( $logs ) ) {
			$logs = array();
		}

		$logs[] = array(
			'timestamp' => current_time( 'mysql' ),
			'action'    => 'scheduled_purge',
			'object_id' => 0,
			'name'      => 'nginx' === self::get_targets_option() ? 'Nginx' : ( 'opcache' === self::get_targets_option() ? 'Opcache' : 'Nginx + Opcache' ),
		);

		if ( count( $logs ) > 50 ) {
			$logs = array_slice( $logs, -50 );
		}

		set_transient( 'nom_cache_flush_logs', $logs, DAY_IN_SECONDS );
	}

	/**
	 * Get next scheduled run timestamp (false when not scheduled).
	 *
	 * @return int|false
	 */
	public static function get_next_run() {
		return wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Get last run mysql datetime or empty string.
	 *
	 * @return string
	 */
	public static function get_last_run() {
		return (string) get_option( self::OPTION_LAST_RUN, '' );
	}

	/**
	 * Sanitize interval value from settings form.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_interval( $value ) {
		$value = sanitize_key( $value );
		if ( ! isset( self::get_intervals()[ $value ] ) ) {
			return 'six_hours';
		}
		return $value;
	}

	/**
	 * Sanitize targets value from settings form.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_targets( $value ) {
		$value = sanitize_key( $value );
		if ( ! isset( self::get_targets()[ $value ] ) ) {
			return 'both';
		}
		return $value;
	}
}
