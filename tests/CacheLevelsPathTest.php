<?php
/**
 * Tests for Nginx_Opcache_Manager_Cache::build_cache_file_relative_path().
 *
 * Bug guarded: clear_url_cache() hardcoded the cache file layout as
 * "/<last char>/<2 chars before last>/<md5>" — i.e. nginx fastcgi_cache_path
 * levels=1:2 — regardless of the actual levels directive. On any site whose
 * nginx used different levels (e.g. "2:2") the computed path never existed
 * and every purge failed with "Cache file not found".
 */

use PHPUnit\Framework\TestCase;

final class CacheLevelsPathTest extends TestCase {

	/**
	 * Fixed MD5 hash used in the plugin's own documentation
	 * (levels 1:2 -> /c/29/<hash>).
	 */
	const HASH_1 = 'b7f54b2df7773722d382f4809d65029c';

	/**
	 * Second fixed hash so the assertions are not overfit to one value.
	 */
	const HASH_2 = 'beda56a8736ae8bc335cdd74983649f5';

	/**
	 * At levels=1:2 the helper must be byte-identical to the old hardcoded
	 * formula substr($h,-1).'/'.substr($h,-3,2).'/'.$h, both as the relative
	 * path and as the final path after cache-root concatenation (the helper's
	 * leading "/" replaces the "/" the old concatenation inserted).
	 */
	public function test_levels_1_2_is_byte_identical_to_the_old_hardcoded_formula() {
		foreach ( array( self::HASH_1, self::HASH_2 ) as $hash ) {
			// The exact expression pre-branch clear_url_cache() used.
			$old_formula = substr( $hash, -1 ) . '/' . substr( $hash, -3, 2 ) . '/' . $hash;

			$this->assertSame(
				'/' . $old_formula,
				Nginx_Opcache_Manager_Cache::build_cache_file_relative_path( $hash, '1:2' ),
				"levels=1:2 must reproduce the old formula for hash $hash"
			);

			$cache_root = '/var/run/nginx-cache';
			$this->assertSame(
				$cache_root . '/' . $old_formula,
				$cache_root . Nginx_Opcache_Manager_Cache::build_cache_file_relative_path( $hash, '1:2' ),
				"final purge path must be byte-identical for hash $hash"
			);
		}
	}

	/**
	 * The worked example from the plugin's own docblock must hold exactly.
	 */
	public function test_levels_1_2_matches_the_documented_example_path() {
		$this->assertSame(
			'/c/29/' . self::HASH_1,
			Nginx_Opcache_Manager_Cache::build_cache_file_relative_path( self::HASH_1, '1:2' )
		);
	}

	/**
	 * levels=2:2 takes two digits per level, walking from the END of the
	 * hash in declaration order: /<last 2>/<2 before those>/<hash>.
	 */
	public function test_levels_2_2_takes_two_digits_per_level_from_the_end_of_the_hash() {
		$this->assertSame(
			'/9c/02/' . self::HASH_1,
			Nginx_Opcache_Manager_Cache::build_cache_file_relative_path( self::HASH_1, '2:2' )
		);
		$this->assertSame(
			'/f5/49/' . self::HASH_2,
			Nginx_Opcache_Manager_Cache::build_cache_file_relative_path( self::HASH_2, '2:2' )
		);
	}

	/**
	 * A single-level value ("1" or "2") produces exactly one directory
	 * before the hash — the case the old formula could not express at all.
	 */
	public function test_single_level_values_produce_one_directory() {
		$this->assertSame(
			'/c/' . self::HASH_1,
			Nginx_Opcache_Manager_Cache::build_cache_file_relative_path( self::HASH_1, '1' )
		);
		$this->assertSame(
			'/9c/' . self::HASH_1,
			Nginx_Opcache_Manager_Cache::build_cache_file_relative_path( self::HASH_1, '2' )
		);
	}
}
