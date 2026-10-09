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
	 * Term archive URLs captured before a post update, keyed by post ID.
	 *
	 * @var array
	 */
	private $previous_term_urls = array();

	/**
	 * Constructor - register hooks
	 */
	public function __construct() {
		// Post actions
		add_action( 'save_post', array( $this, 'on_post_save' ), 10, 2 );
		add_action( 'delete_post', array( $this, 'on_post_delete' ), 10, 2 );
		add_action( 'publish_post', array( $this, 'on_post_publish' ), 10, 2 );
		add_action( 'trashed_post', array( $this, 'on_post_trash' ), 10, 1 );
		add_action( 'untrashed_post', array( $this, 'on_post_untrash' ), 10, 1 );
		add_action( 'post_updated', array( $this, 'on_post_updated' ), 10, 3 );
		// Fires before the update is written, i.e. while the OLD terms are
		// still in the DB and before clean_post_cache() runs.
		add_action( 'pre_post_update', array( $this, 'on_pre_post_update' ), 10, 2 );

		// Term actions
		add_action( 'edited_term', array( $this, 'on_term_edit' ), 10, 3 );
		add_action( 'created_term', array( $this, 'on_term_create' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'on_term_delete' ), 10, 5 );

		// Comment actions
		add_action( 'comment_post', array( $this, 'on_comment_post' ), 10, 3 );
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
		if ( ! $this->is_post_flush_enabled() ) {
			return;
		}

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
		if ( ! $this->is_post_flush_enabled() ) {
			return;
		}

		$this->flush_post_cache( $post_id );
	}

	/**
	 * Handle post publish
	 */
	public function on_post_publish( $post_id, $post ) {
		if ( ! $this->is_post_flush_enabled() ) {
			return;
		}

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
	 * Handle post moved to trash.
	 *
	 * The post still exists at this point, so its permalink is purged before
	 * the trash redirect URL replaces it in any cache.
	 *
	 * @param int $post_id Post ID.
	 */
	public function on_post_trash( $post_id ) {
		if ( ! $this->is_post_flush_enabled() ) {
			return;
		}

		// Don't process auto-saves or revisions
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$this->flush_post_cache( $post_id );
	}

	/**
	 * Handle post restored from trash.
	 *
	 * Content may have changed while the post was trashed, so the cache is
	 * flushed again even though the post is back at its previous permalink.
	 *
	 * @param int $post_id Post ID.
	 */
	public function on_post_untrash( $post_id ) {
		if ( ! $this->is_post_flush_enabled() ) {
			return;
		}

		// Don't process auto-saves or revisions
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$this->flush_post_cache( $post_id );
	}

	/**
	 * Handle post updated (purges the old permalink when the slug changed).
	 *
	 * save_post fires after the update and only sees the new permalink, so
	 * the pre-update slug is reconstructed from $post_before here.
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  Post object after the update.
	 * @param WP_Post $post_before Post object before the update.
	 */
	public function on_post_updated( $post_id, $post_after, $post_before ) {
		if ( ! $this->is_post_flush_enabled() ) {
			return;
		}

		// Don't process auto-saves or revisions
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! is_object( $post_after ) || ! is_object( $post_before ) ) {
			return;
		}

		// Slug unchanged: the new-permalink flush on save_post already covers it.
		if ( $post_after->post_name === $post_before->post_name ) {
			return;
		}

		// Dedupe within the same request (post_updated + save_post often fire together).
		$dedupe_key = 'post_slug_change:' . $post_id;
		if ( isset( self::$flushed_in_request[ $dedupe_key ] ) ) {
			return;
		}
		self::$flushed_in_request[ $dedupe_key ] = true;

		$old_permalink = $this->get_permalink_from_post_before( $post_before );
		if ( $old_permalink ) {
			$this->flush_cache_for_urls( array( $old_permalink ) );
			$this->log_cache_flush( 'post_slug_change', $post_id, $post_after->post_title );
		}
	}

	/**
	 * Handle the moment just before a post update is written.
	 *
	 * In wp_update_post() the new terms are set (wp_set_post_categories /
	 * wp_set_post_tags) and the post cache is cleaned BEFORE post_updated
	 * fires, so on_post_updated only ever sees the NEW terms. pre_post_update
	 * fires while the OLD terms are still in the database and before
	 * clean_post_cache(), so the previous term archives are read and stashed
	 * here and merged into the next flush for this post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    Unslashed data for the post being updated.
	 */
	public function on_pre_post_update( $post_id, $data ) {
		if ( ! $this->is_post_flush_enabled() ) {
			return;
		}

		// Don't process auto-saves or revisions
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// A post created for the first time reaches pre_post_update as an
		// auto-draft with no previous terms; purging is covered by the
		// save_post flush once it is published.
		$status = get_post_status( $post_id );
		if ( false === $status || 'auto-draft' === $status ) {
			return;
		}

		$this->stash_previous_term_urls( get_post( $post_id ) );
	}

	/**
	 * Reconstruct a post's pre-update permalink from its pre-update data.
	 *
	 * get_permalink() builds the URL from the passed object's fields, so the
	 * pre-update slug and date in $post_before yield the old URL.
	 *
	 * @param WP_Post $post_before Post object before the update.
	 * @return string|false Old permalink, or false when it cannot be built.
	 */
	private function get_permalink_from_post_before( $post_before ) {
		if ( 'attachment' === $post_before->post_type ) {
			return false;
		}

		$old_permalink = get_permalink( $post_before );

		return $old_permalink ? $old_permalink : false;
	}

	/**
	 * Capture the term archives a post belongs to before an update is written.
	 *
	 * Called from pre_post_update, before wp_update_post() assigns the new
	 * taxonomies, so the relationships read here are still the previous ones.
	 * The collected URLs are merged into the next flush for the same post so
	 * a category/tag move purges the OLD archives too, not just the new ones.
	 *
	 * @param WP_Post|null $post Post object in its pre-update state.
	 */
	private function stash_previous_term_urls( $post ) {
		if ( ! is_object( $post ) || 'post' !== $post->post_type ) {
			return;
		}

		$urls = array();

		$cats = get_the_category( $post->ID );
		foreach ( $cats as $cat ) {
			if ( $cat_link = get_category_link( $cat->term_id ) ) {
				$urls[] = $cat_link;
			}
		}

		// get_the_tags() returns false (not an empty array) when no tags exist.
		$tags = get_the_tags( $post->ID );
		if ( $tags && ! is_wp_error( $tags ) ) {
			foreach ( $tags as $tag ) {
				if ( $tag_link = get_tag_link( $tag->term_id ) ) {
					$urls[] = $tag_link;
				}
			}
		}

		if ( ! empty( $urls ) ) {
			$this->previous_term_urls[ $post->ID ] = array_unique( $urls );
		}
	}

	/**
	 * Handle term edit (category, tag, etc.)
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public function on_term_edit( $term_id, $tt_id, $taxonomy ) {
		if ( ! $this->is_post_flush_enabled() ) {
			return;
		}

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
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public function on_term_create( $term_id, $tt_id, $taxonomy ) {
		$this->on_term_edit( $term_id, $tt_id, $taxonomy );
	}

	/**
	 * Handle term deletion (purge its archive and the home page).
	 *
	 * @param int     $term_id      Term ID.
	 * @param int     $tt_id        Term taxonomy ID.
	 * @param string  $taxonomy     Taxonomy slug.
	 * @param WP_Term $deleted_term Copy of the already-deleted term.
	 * @param array   $object_ids   Object IDs previously assigned to the term.
	 */
	public function on_term_delete( $term_id, $tt_id, $taxonomy, $deleted_term, $object_ids ) {
		if ( ! $this->is_post_flush_enabled() ) {
			return;
		}

		$urls = array( home_url( '/' ) );

		$term_link = $this->get_deleted_term_link( $deleted_term, $taxonomy );
		if ( $term_link ) {
			$urls[] = $term_link;
		}

		$this->flush_cache_for_urls( $urls );

		$this->log_cache_flush(
			'term_delete',
			$term_id,
			is_object( $deleted_term ) && isset( $deleted_term->name ) ? $deleted_term->name : ''
		);
	}

	/**
	 * Rebuild a deleted term's archive URL from its saved data.
	 *
	 * get_term_link() cannot be used here: 'delete_term' fires after the term
	 * row is deleted and its cache cleaned, so get_term_link() re-queries the
	 * database, finds nothing and returns a WP_Error. The URL is instead
	 * reconstructed from the $deleted_term copy core passes to the hook,
	 * mirroring how get_term_link() builds it.
	 *
	 * @param WP_Term|null $deleted_term Copy of the already-deleted term.
	 * @param string       $taxonomy     Taxonomy slug.
	 * @return string|false Term archive URL, or false when it cannot be built.
	 */
	private function get_deleted_term_link( $deleted_term, $taxonomy ) {
		if ( ! is_object( $deleted_term ) || ! isset( $deleted_term->slug ) || '' === $deleted_term->slug ) {
			return false;
		}

		// Rebuild the ancestor path (kept for hierarchical taxonomies such as
		// categories); the parents themselves are still in the database.
		$slug    = $deleted_term->slug;
		$parent  = isset( $deleted_term->parent ) ? (int) $deleted_term->parent : 0;
		while ( $parent > 0 ) {
			$parent_term = get_term( $parent, $taxonomy );
			if ( ! $parent_term || is_wp_error( $parent_term ) ) {
				break;
			}
			$slug   = $parent_term->slug . '/' . $slug;
			$parent = (int) $parent_term->parent;
		}

		global $wp_rewrite;

		$termlink = $wp_rewrite->get_extra_permastruct( $taxonomy );

		if ( empty( $termlink ) ) {
			return home_url( "?taxonomy={$taxonomy}&term={$slug}" );
		}

		$termlink = str_replace( "%{$taxonomy}%", $slug, $termlink );

		return home_url( user_trailingslashit( $termlink, 'category' ) );
	}

	/**
	 * Handle comment post
	 *
	 * @param int   $comment_id       Comment ID.
	 * @param int   $comment_approved Comment approval status.
	 * @param array $commentdata     Comment data.
	 */
	public function on_comment_post( $comment_id, $comment_approved, $commentdata ) {
		if ( ! $this->is_post_flush_enabled() ) {
			return;
		}

		if ( isset( $commentdata['comment_post_ID'] ) ) {
			$this->flush_post_cache( (int) $commentdata['comment_post_ID'] );
		}
	}

	/**
	 * Handle comment insert (for pending comments)
	 */
	public function on_comment_insert( $comment_id, $comment ) {
		if ( ! $this->is_post_flush_enabled() ) {
			return;
		}

		if ( isset( $comment->comment_post_ID ) ) {
			$this->flush_post_cache( $comment->comment_post_ID );
		}
	}

	/**
	 * Handle comment delete
	 */
	public function on_comment_delete( $comment_id, $comment ) {
		if ( ! $this->is_post_flush_enabled() ) {
			return;
		}

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
	 * Check if post/term/comment auto-flush is enabled.
	 *
	 * @return bool
	 */
	private function is_post_flush_enabled() {
		return (bool) get_option( 'nom_enable_post_cache_flush', true );
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
			// Static posts page (Settings > Reading): the blog index lives
			// there instead of the home URL. Only queued when it differs
			// from the home URL already added above.
			$posts_page_id = (int) get_option( 'page_for_posts' );
			if ( $posts_page_id > 0 ) {
				$posts_page = get_post( $posts_page_id );
				if ( $posts_page && 'page' === $posts_page->post_type && 'publish' === $posts_page->post_status ) {
					$posts_page_url = get_permalink( $posts_page );
					if ( $posts_page_url && $posts_page_url !== home_url( '/' ) ) {
						$urls[] = $posts_page_url;
					}
				}
			}

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

			// Previous term archives (stashed by on_pre_post_update) so a
			// category/tag move purges the terms the post was removed from.
			if ( ! empty( $this->previous_term_urls[ $post->ID ] ) ) {
				$urls = array_merge( $urls, $this->previous_term_urls[ $post->ID ] );
				unset( $this->previous_term_urls[ $post->ID ] );
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
	 * 
	 * Counts purge failures per reason so a broken cache layout (e.g. the
	 * configured cache levels not matching nginx's fastcgi_cache_path levels)
	 * is observable instead of being silently discarded.
	 * 
	 * @return array Result array with keys: attempted (int), failed (int),
	 *               not_found (int), delete_failed (int)
	 */
	private function flush_cache_for_urls( $urls ) {
		$counts = array(
			'attempted'      => 0,
			'failed'         => 0,
			'not_found'      => 0,
			'delete_failed'  => 0,
		);

		if ( empty( $urls ) ) {
			return $counts;
		}

		$cache_manager = new Nginx_Opcache_Manager_Cache();

		foreach ( $urls as $url ) {
			if ( ! empty( $url ) ) {
				$this->count_flush_result( $counts, $cache_manager->clear_url_cache( $url ) );
			}
		}

		// Also flush home page as fallback
		$this->count_flush_result( $counts, $cache_manager->clear_url_cache( home_url( '/' ) ) );

		if ( $counts['failed'] > 0 && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf(
				'[%s] Cache Purge - %1$d of %2$d URLs failed (%3$d not found, %4$d delete failed). If "not found" dominates, check that the cache levels setting matches the fastcgi_cache_path levels in nginx.',
				current_time( 'mysql' ),
				$counts['failed'],
				$counts['attempted'],
				$counts['not_found'],
				$counts['delete_failed']
			) );
		}

		return $counts;
	}

	/**
	 * Tally one clear_url_cache() result into the flush counts
	 * 
	 * @param array $counts Counts array, modified by reference (attempted, failed, not_found, delete_failed)
	 * @param array $result Result array returned by Nginx_Opcache_Manager_Cache::clear_url_cache()
	 */
	private function count_flush_result( &$counts, $result ) {
		$counts['attempted']++;

		if ( empty( $result['success'] ) ) {
			$counts['failed']++;

			if ( isset( $result['reason'] ) && isset( $counts[ $result['reason'] ] ) ) {
				$counts[ $result['reason'] ]++;
			}
		}
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
