<?php
/**
 * Tests for the hooks Nginx_Opcache_Manager_Post_Cache_Tracker registers.
 *
 * Bug guarded: several hooks were registered with fewer accepted_args than
 * WordPress actually passes, so handlers received truncated arguments —
 * edited_term/created_term declared 2 of 3 ($taxonomy landed in $tt_id's
 * position and the taxonomy was read from the wrong slot), comment_post
 * declared 2 of 3 and expected an object where WP passes the $commentdata
 * array. Whole hooks were missing too: trash/untrash left the trashed URL
 * cached, post_updated left the OLD permalink cached after a slug change,
 * the term-delete hook was registered under a name WordPress never fires so
 * deleting a category/tag never purged its archive, previous term archives
 * were stashed after the update had already written the new terms, and the
 * five WooCommerce product hooks were absent.
 */

use PHPUnit\Framework\TestCase;

final class PostCacheTrackerHookRegistrationTest extends TestCase {

	protected function setUp(): void {
		wp_test_reset_state();
		new Nginx_Opcache_Manager_Post_Cache_Tracker();
	}

	/**
	 * Hook name => accepted_args WordPress passes (and the tracker must claim).
	 */
	public function hookProvider() {
		return array(
			// New post-lifecycle hooks on this branch.
			'trashed_post'                       => array( 'trashed_post', 1 ),
			'untrashed_post'                     => array( 'untrashed_post', 1 ),
			'post_updated'                       => array( 'post_updated', 3 ),
			// Fires before the update is written, while the OLD terms are
			// still in the DB (post_updated sees only the new ones).
			'pre_post_update'                    => array( 'pre_post_update', 2 ),
			// Term hooks widened from 2 args / missing entirely.
			'edited_term'                        => array( 'edited_term', 3 ),
			'created_term'                       => array( 'created_term', 3 ),
			'delete_term'                        => array( 'delete_term', 5 ),
			// comment_post widened from 2 args (object) to WP's 3 (array).
			'comment_post'                       => array( 'comment_post', 3 ),
			// The five WooCommerce product hooks.
			'woocommerce_update_product'         => array( 'woocommerce_update_product', 1 ),
			'woocommerce_new_product'            => array( 'woocommerce_new_product', 1 ),
			'woocommerce_product_set_stock'      => array( 'woocommerce_product_set_stock', 1 ),
			'woocommerce_variation_set_stock'    => array( 'woocommerce_variation_set_stock', 1 ),
			'woocommerce_save_product_variation' => array( 'woocommerce_save_product_variation', 2 ),
			// Pre-existing hooks that must stay registered.
			'save_post'                          => array( 'save_post', 2 ),
			'delete_post'                        => array( 'delete_post', 2 ),
			'publish_post'                       => array( 'publish_post', 2 ),
			'wp_insert_comment'                  => array( 'wp_insert_comment', 2 ),
			'delete_comment'                     => array( 'delete_comment', 2 ),
		);
	}

	/**
	 * @dataProvider hookProvider
	 */
	public function test_hook_is_registered_with_the_accepted_args_wordpress_passes( $hook, $accepted_args ) {
		$registrations = wp_test_registrations_for( $hook );

		$this->assertCount(
			1,
			$registrations,
			"Tracker must register exactly one listener on '$hook'"
		);

		$registration = $registrations[0];

		$this->assertSame(
			$accepted_args,
			$registration['accepted_args'],
			"Tracker must claim all $accepted_args argument(s) WP passes to '$hook'; "
			. 'claiming fewer truncates the handler arguments'
		);

		$this->assertTrue(
			is_callable( $registration['callback'] ),
			"'$hook' callback must be a real tracker method"
		);

		$this->assertSame( 10, $registration['priority'], "'$hook' must register at priority 10" );
	}
}
