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
	 * Flush cache for specific post
	 */
	private function flush_post_cache( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

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
			'post_changes'  => 0,
			'term_changes'  => 0,
			'comment_changes' => 0,
		);

		foreach ( $logs as $log ) {
			if ( strpos( $log['action'], 'post' ) !== false ) {
				$stats['post_changes']++;
			} elseif ( strpos( $log['action'], 'term' ) !== false ) {
				$stats['term_changes']++;
			} elseif ( strpos( $log['action'], 'comment' ) !== false ) {
				$stats['comment_changes']++;
			}
		}

		return $stats;
	}
}
