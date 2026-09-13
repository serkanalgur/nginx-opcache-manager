<?php
/**
 * Post Cache Tracker class
 *
 * @package Nginx_Opcache_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to track and flush cache on content changes
 */
class Nginx_Opcache_Manager_Post_Cache_Tracker {

	/**
	 * Post IDs already flushed during this request (prevents double purge).
	 *
	 * @var array
	 */
	private static $flushed_in_request = array();

	/**
	 * Constructor - register hooks
	 */
	public function __construct() {
		// Post actions
		add_action( 'save_post', array( $this, 'on_post_save' ), 10, 2 );
		add_action( 'delete_post', array( $this, 'on_post_delete' ), 10, 2 );
		add_action( 'publish_post', array( $this, 'on_post_publish' ), 10, 2 );

		// Term actions
		add_action( 'edited_term', array( $this, 'on_term_edit' ), 10, 2 );
		add_action( 'created_term', array( $this, 'on_term_create' ), 10, 2 );

		// Comment actions
		add_action( 'comment_post', array( $this, 'on_comment_post' ), 10, 2 );
		add_action( 'wp_insert_comment', array( $this, 'on_comment_insert' ), 10, 2 );
		add_action( 'delete_comment', array( $this, 'on_comment_delete' ), 10, 2 );

		// WooCommerce product actions (only effective when WooCommerce is active).
		add_action( 'woocommerce_update_product', array( $this, 'on_woocommerce_product_change' ), 10, 1 );
		add_action( 'woocommerce_new_product', array( $this, 'on_woocommerce_product_change' ), 10, 1 );
		add_action( 'woocommerce_product_set_stock', array( $this, 'on_woocommerce_product_stock_change' ), 10, 1 );
		add_action( 'woocommerce_variation_set_stock', array( $this, 'on_woocommerce_variation_stock_change' ), 10, 1 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'on_woocommerce_variation_save' ), 10, 2 );
	}

	/**
	 * Handle post save
	 */
	public function on_post_save( $post_id, $post ) {
		// Don't process auto-saves or revisions
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Skip trashed posts
		if ( get_post_status( $post_id ) === 'trash' ) {
			return;
		}

		$this->flush_post_cache( $post_id );
	}

	/**
	 * Handle post delete
	 */
	public function on_post_delete( $post_id, $post ) {
		$this->flush_post_cache( $post_id );
	}

	/**
	 * Handle post publish
	 */
	public function on_post_publish( $post_id, $post ) {
		// Flush home page and post archives
		$this->flush_cache_for_urls( array(
			home_url( '/' ),
			get_permalink( $post_id ),
			get_post_type_archive_link( $post->post_type ),
		) );

		// Log the change
		$this->log_cache_flush( 'post_publish', $post_id, $post->post_title );
	}

	/**
	 * Handle term edit (category, tag, etc.)
	 */
	public function on_term_edit( $term_id, $taxonomy ) {
		$term = get_term( $term_id, $taxonomy );
		if ( is_wp_error( $term ) || ! isset( $term->term_id ) ) {
			return;
		}

		$this->flush_cache_for_urls( array(
			get_term_link( $term_id, $taxonomy ),
			home_url( '/' ),
		) );

		$this->log_cache_flush( 'term_edit', $term_id, $term->name );
	}

	/**
	 * Handle term creation
	 */
	public function on_term_create( $term_id, $taxonomy ) {
		$this->on_term_edit( $term_id, $taxonomy );
	}

	/**
	 * Handle comment post
	 */
	public function on_comment_post( $comment_id, $comment_object ) {
		if ( isset( $comment_object->comment_post_ID ) ) {
			$this->flush_post_cache( $comment_object->comment_post_ID );
		}
	}

	/**
	 * Handle comment insert (for pending comments)
	 */
	public function on_comment_insert( $comment_id, $comment ) {
		if ( isset( $comment->comment_post_ID ) ) {
			$this->flush_post_cache( $comment->comment_post_ID );
		}
	}

	/**
	 * Handle comment delete
	 */
	public function on_comment_delete( $comment_id, $comment ) {
		if ( isset( $comment->comment_post_ID ) ) {
			$this->flush_post_cache( $comment->comment_post_ID );
		}
	}

	/**
	 * Handle WooCommerce product create/update.
	 *
	 * @param int $product_id Product ID.
	 */
	public function on_woocommerce_product_change( $product_id ) {
		if ( ! $this->is_woocommerce_flush_enabled() ) {
			return;
		}

		$this->flush_product_cache( (int) $product_id, 'product_change' );
	}

	/**
	 * Handle WooCommerce product stock change.
	 *
	 * @param object $product Product object.
	 */
	public function on_woocommerce_product_stock_change( $product ) {
		if ( ! $this->is_woocommerce_flush_enabled() ) {
			return;
		}

		$product_id = is_object( $product ) && isset( $product->id ) ? (int) $product->id : (int) $product;
		if ( method_exists( $product, 'get_id' ) ) {
			$product_id = (int) $product->get_id();
		}

		if ( $product_id > 0 ) {
			$this->flush_product_cache( $product_id, 'product_stock_change' );
		}
	}

	/**
	 * Handle WooCommerce variation stock change (flush parent product).
	 *
	 * @param object $variation Variation object.
	 */
	public function on_woocommerce_variation_stock_change( $variation ) {
		if ( ! $this->is_woocommerce_flush_enabled() ) {
			return;
		}

		$parent_id = 0;
		if ( is_object( $variation ) && method_exists( $variation, 'get_parent_id' ) ) {
			$parent_id = (int) $variation->get_parent_id();
		}

		if ( $parent_id > 0 ) {
			$this->flush_product_cache( $parent_id, 'product_stock_change' );
		}
	}

	/**
	 * Handle WooCommerce variation save (flush parent product).
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop Loop index (unused).
	 */
	public function on_woocommerce_variation_save( $variation_id, $loop ) {
		if ( ! $this->is_woocommerce_flush_enabled() ) {
			return;
		}

		$parent_id = wp_get_post_parent_id( (int) $variation_id );
		if ( $parent_id > 0 ) {
			$this->flush_product_cache( $parent_id, 'product_change' );
		} else {
			$this->flush_product_cache( (int) $variation_id, 'product_change' );
		}
	}

	/**
	 * Check if WooCommerce auto-flush is enabled.
	 *
	 * @return bool
	 */
	private function is_woocommerce_flush_enabled() {
		return (bool) get_option( 'nom_enable_woocommerce_flush', true );
	}

	/**
	 * Flush cache for a WooCommerce product (product + shop + taxonomies).
	 *
	 * @param int    $product_id Product ID.
	 * @param string $action Log action.
	 */
	public function flush_product_cache( $product_id, $action = 'product_change' ) {
		$product_id = (int) $product_id;
		if ( $product_id <= 0 ) {
			return;
		}

		// Dedupe within the same request (save_post + WC hooks often fire together).
		$dedupe_key = 'product:' . $product_id;
		if ( isset( self::$flushed_in_request[ $dedupe_key ] ) ) {
			return;
		}
		self::$flushed_in_request[ $dedupe_key ] = true;

		$post = get_post( $product_id );
		if ( ! $post ) {
			return;
		}

		$urls_to_flush = $this->get_product_related_urls( $product_id, $post );

		$this->flush_cache_for_urls( $urls_to_flush );

		$this->log_cache_flush( $action, $product_id, $post->post_title );
	}

	/**
	 * Flush cache for specific post
	 */
	private function flush_post_cache( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		// WooCommerce products (and variations) get product-specific URL handling.
		if ( in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ) {
			if ( ! $this->is_woocommerce_flush_enabled() ) {
				return;
			}

			if ( 'product_variation' === $post->post_type ) {
				$parent_id = wp_get_post_parent_id( $post_id );
				$this->flush_product_cache( $parent_id > 0 ? $parent_id : $post_id, 'product_change' );
				return;
			}

			$this->flush_product_cache( $post_id, 'post_change' );
			return;
		}

		// Dedupe generic posts within the same request.
		$dedupe_key = 'post_change:' . $post_id;
		if ( isset( self::$flushed_in_request[ $dedupe_key ] ) ) {
			return;
		}
		self::$flushed_in_request[ $dedupe_key ] = true;

		// Get all related URLs
		$urls_to_flush = $this->get_post_related_urls( $post );

		// Flush these URLs
		$this->flush_cache_for_urls( $urls_to_flush );

		// Log the action
		$this->log_cache_flush( 'post_change', $post_id, $post->post_title );
	}

	/**
	 * Get all URLs related to a post
	 */
	private function get_post_related_urls( $post ) {
		// Delegate WooCommerce products to product-specific URL collection.
		if ( in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ) {
			$product_id = 'product_variation' === $post->post_type ? (int) wp_get_post_parent_id( $post->ID ) : (int) $post->ID;
			if ( $product_id <= 0 ) {
				$product_id = (int) $post->ID;
			}
			return $this->get_product_related_urls( $product_id, $post );
		}

		$urls = array();

		// Post permalink
		if ( get_permalink( $post->ID ) ) {
			$urls[] = get_permalink( $post->ID );
		}

		// Archive pages
		if ( $archive_link = get_post_type_archive_link( $post->post_type ) ) {
			$urls[] = $archive_link;
		}

		// Home page
		$urls[] = home_url( '/' );

		// Category pages
		if ( 'post' === $post->post_type ) {
			$cats = get_the_category( $post->ID );
			foreach ( $cats as $cat ) {
				if ( $cat_link = get_category_link( $cat->term_id ) ) {
					$urls[] = $cat_link;
				}
			}

			// Tag pages
			$tags = get_the_tags( $post->ID );
			if ( $tags ) {
				foreach ( $tags as $tag ) {
					if ( $tag_link = get_tag_link( $tag->term_id ) ) {
						$urls[] = $tag_link;
					}
				}
			}
		}

		// Author page
		if ( $author_link = get_author_posts_url( $post->post_author ) ) {
			$urls[] = $author_link;
		}

		// Remove duplicates
		$urls = array_unique( $urls );

		// Filter URLs (allow customization)
		return apply_filters( 'nom_post_cache_urls', $urls, $post );
	}

	/**
	 * Get all URLs related to a WooCommerce product.
	 *
	 * Covers the product page itself, shop page, product categories/tags
	 * and the homepage so price/stock changes are visible immediately.
	 *
	 * @param int      $product_id Product ID.
	 * @param \WP_Post $post Product post object.
	 * @return array
	 */
	private function get_product_related_urls( $product_id, $post ) {
		$urls = array();

		$permalink = get_permalink( $product_id );
		if ( $permalink ) {
			$urls[] = $permalink;
		}

		// Shop page.
		if ( function_exists( 'wc_get_page_id' ) ) {
			$shop_page_id = (int) wc_get_page_id( 'shop' );
			if ( $shop_page_id > 0 ) {
				$shop_url = get_permalink( $shop_page_id );
				if ( $shop_url ) {
					$urls[] = $shop_url;
				}
			}
		}

		// Product categories.
		$cat_terms = get_the_terms( $product_id, 'product_cat' );
		if ( $cat_terms && ! is_wp_error( $cat_terms ) ) {
			foreach ( $cat_terms as $term ) {
				$term_link = get_term_link( $term );
				if ( ! is_wp_error( $term_link ) ) {
					$urls[] = $term_link;
				}
			}
		}

		// Product tags.
		$tag_terms = get_the_terms( $product_id, 'product_tag' );
		if ( $tag_terms && ! is_wp_error( $tag_terms ) ) {
			foreach ( $tag_terms as $term ) {
				$term_link = get_term_link( $term );
				if ( ! is_wp_error( $term_link ) ) {
					$urls[] = $term_link;
				}
			}
		}

		// Product archive (shop post type archive fallback).
		$archive_link = get_post_type_archive_link( 'product' );
		if ( $archive_link ) {
			$urls[] = $archive_link;
		}

		// Home page.
		$urls[] = home_url( '/' );

		$urls = array_unique( array_filter( $urls ) );

		/**
		 * Filter WooCommerce product cache URLs.
		 *
		 * @param array    $urls Product-related URLs.
		 * @param int      $product_id Product ID.
		 * @param \WP_Post $post Product post object.
		 */
		return apply_filters( 'nom_product_cache_urls', $urls, $product_id, $post );
	}

	/**
	 * Flush cache for specific URLs
	 */
	private function flush_cache_for_urls( $urls ) {
		if ( empty( $urls ) ) {
			return;
		}

		$cache_manager = new Nginx_Opcache_Manager_Cache();

		foreach ( $urls as $url ) {
			if ( ! empty( $url ) ) {
				$cache_manager->clear_url_cache( $url );
			}
		}

		// Also flush home page as fallback
		$cache_manager->clear_url_cache( home_url( '/' ) );
	}

	/**
	 * Log cache flush action
	 */
	private function log_cache_flush( $action, $object_id, $object_name = '' ) {
		// Store logs in transient for quick retrieval
		$logs = get_transient( 'nom_cache_flush_logs' );

		if ( false === $logs ) {
			$logs = array();
		}

		$logs[] = array(
			'timestamp' => current_time( 'mysql' ),
			'action'    => $action,
			'object_id' => $object_id,
			'name'      => $object_name,
		);

		// Keep last 50 logs
		if ( count( $logs ) > 50 ) {
			array_shift( $logs );
		}

		set_transient( 'nom_cache_flush_logs', $logs, DAY_IN_SECONDS );

		// Fire action hook for custom logging
		do_action( 'nom_cache_flushed', $action, $object_id, $object_name );
	}

	/**
	 * Get cache flush logs
	 */
	public static function get_flush_logs() {
		return get_transient( 'nom_cache_flush_logs' ) ?: array();
	}

	/**
	 * Clear cache flush logs
	 */
	public static function clear_logs() {
		delete_transient( 'nom_cache_flush_logs' );
	}

	/**
	 * Get cache statistics grouped by post/term type
	 */
	public static function get_cache_stats_by_type() {
		$logs = self::get_flush_logs();
		$stats = array(
			'post_changes'     => 0,
			'product_changes'  => 0,
			'term_changes'     => 0,
			'comment_changes'  => 0,
			'scheduled_purges' => 0,
		);

		foreach ( $logs as $log ) {
			if ( ! isset( $log['action'] ) ) {
				continue;
			}
			if ( 'scheduled_purge' === $log['action'] ) {
				$stats['scheduled_purges']++;
			} elseif ( false !== strpos( $log['action'], 'product' ) ) {
				$stats['product_changes']++;
			} elseif ( false !== strpos( $log['action'], 'post' ) ) {
				$stats['post_changes']++;
			} elseif ( false !== strpos( $log['action'], 'term' ) ) {
				$stats['term_changes']++;
			} elseif ( false !== strpos( $log['action'], 'comment' ) ) {
				$stats['comment_changes']++;
			}
		}

		return $stats;
	}
}
