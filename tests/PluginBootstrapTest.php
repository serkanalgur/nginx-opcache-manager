<?php
/**
 * Behavioural test of the main plugin file's bootstrap (nginx-opcache-manager.php).
 *
 * Bug guarded: setup_hooks() used to wrap the tracker instantiation in
 * `if ( get_option( 'nom_enable_post_cache_flush', true ) )`, so disabling
 * the post/term/comment auto-flush took the five WooCommerce product
 * handlers down with it and products stopped purging on edit. The option now
 * gates handlers INSIDE the tracker, and the tracker must always load.
 *
 * The main file is required for real here (with the stub WP environment);
 * it only defines classes/constants and registers hooks at load time. The
 * file may only be required once per process, so all assertions live in a
 * single test method.
 */

use PHPUnit\Framework\TestCase;

final class PluginBootstrapTest extends TestCase {

	/**
	 * With nom_enable_post_cache_flush explicitly false the tracker must
	 * still be constructed, and the bootstrap must not consult the option
	 * at all (the tracker reads it per-handler).
	 */
	public function test_tracker_is_instantiated_and_the_option_is_not_gated_in_bootstrap() {
		wp_test_reset_state();
		$GLOBALS['wp_test_state']['options']['nom_enable_post_cache_flush'] = false;

		require dirname( __DIR__ ) . '/nginx-opcache-manager.php';

		// 1. Tracker constructed despite the disabled option.
		$this->assertNotEmpty(
			wp_test_registrations_for( 'save_post' ),
			'Tracker must be instantiated (and register its hooks) even when '
			. 'nom_enable_post_cache_flush is false; the option gates handlers '
			. 'inside the tracker, not the tracker itself'
		);

		$this->assertNotEmpty(
			wp_test_registrations_for( 'woocommerce_update_product' ),
			'WooCommerce handlers must stay reachable when the post flush option is off'
		);

		$this->assertCount(
			1,
			wp_test_registrations_for( 'trashed_post' ),
			'trashed_post hook must be registered by the bootstrap-loaded tracker'
		);

		// 2. The bootstrap must not read the option anywhere. (The old code
		// called get_option('nom_enable_post_cache_flush', true) here; the
		// option is now only consulted inside the tracker's handlers.)
		foreach ( wp_test_get_option_calls() as $call ) {
			$this->assertNotSame(
				'nom_enable_post_cache_flush',
				$call['name'],
				'The main plugin file must not gate on nom_enable_post_cache_flush '
				. 'during bootstrap; the tracker consults it per-handler'
			);
		}
	}
}
