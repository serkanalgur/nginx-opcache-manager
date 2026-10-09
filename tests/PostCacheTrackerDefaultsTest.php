<?php
/**
 * Tests for the tracker's flush-enable options: nom_enable_post_cache_flush
 * and nom_enable_woocommerce_flush.
 *
 * Bug guarded: both gates read the option with no default
 * (get_option('nom_...')), so on any install where the option row did not
 * exist yet the check evaluated falsy and every automatic purge — posts,
 * terms, comments AND WooCommerce products — was silently disabled.
 */

use PHPUnit\Framework\TestCase;

final class PostCacheTrackerDefaultsTest extends TestCase {

	protected function setUp(): void {
		wp_test_reset_state();
	}

	/**
	 * Invoke one of the tracker's private enable-gates via reflection.
	 */
	private function invoke_gate( $method ) {
		$tracker = new Nginx_Opcache_Manager_Post_Cache_Tracker();
		$reflection = new ReflectionMethod( $tracker, $method );
		return $reflection->invoke( $tracker );
	}

	/**
	 * The default actually handed to get_option() for one option name.
	 */
	private function get_option_default_for( $option ) {
		$default = null;
		foreach ( wp_test_get_option_calls() as $call ) {
			if ( $option === $call['name'] ) {
				$default = $call['default'];
			}
		}
		return $default;
	}

	/**
	 * Option row missing entirely -> post/term/comment flushing stays ON.
	 */
	public function test_post_flush_defaults_to_enabled_when_option_is_missing() {
		$result = $this->invoke_gate( 'is_post_flush_enabled' );

		$this->assertTrue( $result, 'nom_enable_post_cache_flush missing must default to enabled' );
		$this->assertSame(
			true,
			$this->get_option_default_for( 'nom_enable_post_cache_flush' ),
			'get_option must be called with an explicit true default'
		);
	}

	/**
	 * Option row missing entirely -> WooCommerce flushing stays ON.
	 */
	public function test_woocommerce_flush_defaults_to_enabled_when_option_is_missing() {
		$result = $this->invoke_gate( 'is_woocommerce_flush_enabled' );

		$this->assertTrue( $result, 'nom_enable_woocommerce_flush missing must default to enabled' );
		$this->assertSame(
			true,
			$this->get_option_default_for( 'nom_enable_woocommerce_flush' ),
			'get_option must be called with an explicit true default'
		);
	}

	/**
	 * A stored explicit opt-out must still win over the default.
	 */
	public function test_stored_false_disables_the_gates() {
		$GLOBALS['wp_test_state']['options']['nom_enable_post_cache_flush']    = false;
		$GLOBALS['wp_test_state']['options']['nom_enable_woocommerce_flush'] = false;

		$this->assertFalse( $this->invoke_gate( 'is_post_flush_enabled' ) );
		$this->assertFalse( $this->invoke_gate( 'is_woocommerce_flush_enabled' ) );
	}
}
