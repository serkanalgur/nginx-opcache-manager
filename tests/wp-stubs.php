<?php
/**
 * Minimal WordPress stubs for the plugin's unit tests.
 *
 * Hand-rolled (instead of a heavier dependency such as Brain Monkey) so the
 * tests run with PHPUnit alone. Only the functions reached by the code paths
 * under test are stubbed. Every stub records what it was called with in
 * $GLOBALS['wp_test_state'] so tests can assert against real interactions.
 */

/**
 * Reset all recorded stub state. Called from every test's setUp().
 */
function wp_test_reset_state() {
	$GLOBALS['wp_test_state'] = array(
		'actions'          => array(),
		'filters'          => array(),
		'options'          => array(),
		'get_option_calls' => array(),
	);
}

/**
 * All add_action() registrations recorded since the last reset.
 *
 * @return array List of arrays with keys hook, callback, priority, accepted_args.
 */
function wp_test_registered_actions() {
	return $GLOBALS['wp_test_state']['actions'];
}

/**
 * Registrations recorded for one hook name.
 *
 * @param string $hook Hook name.
 * @return array Subset of wp_test_registered_actions().
 */
function wp_test_registrations_for( $hook ) {
	$found = array();
	foreach ( wp_test_registered_actions() as $action ) {
		if ( $hook === $action['hook'] ) {
			$found[] = $action;
		}
	}
	return $found;
}

/**
 * get_option() calls recorded since the last reset.
 *
 * @return array List of arrays with keys name, default.
 */
function wp_test_get_option_calls() {
	return $GLOBALS['wp_test_state']['get_option_calls'];
}

function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['wp_test_state']['actions'][] = array(
		'hook'          => $hook_name,
		'callback'      => $callback,
		'priority'      => $priority,
		'accepted_args' => $accepted_args,
	);
	return true;
}

function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['wp_test_state']['filters'][] = array(
		'hook'          => $hook_name,
		'callback'      => $callback,
		'priority'      => $priority,
		'accepted_args' => $accepted_args,
	);
	return true;
}

function get_option( $name, $default = false ) {
	$GLOBALS['wp_test_state']['get_option_calls'][] = array(
		'name'    => $name,
		'default' => $default,
	);
	if ( array_key_exists( $name, $GLOBALS['wp_test_state']['options'] ) ) {
		return $GLOBALS['wp_test_state']['options'][ $name ];
	}
	return $default;
}

function sanitize_text_field( $str ) {
	return is_string( $str ) ? trim( strip_tags( $str ) ) : $str;
}

function __( $text, $domain = 'default' ) { // phpcs:ignore
	return $text;
}

function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}

function is_admin() {
	return false;
}

function plugin_dir_path( $file ) {
	return rtrim( dirname( $file ), '/\\' ) . '/';
}

function plugin_dir_url( $file ) {
	return 'https://example.test/wp-content/plugins/nginx-opcache-manager/';
}

function register_activation_hook( $file, $callback ) {
	return true;
}

function register_deactivation_hook( $file, $callback ) {
	return true;
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

wp_test_reset_state();
