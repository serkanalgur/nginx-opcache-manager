<?php
/**
 * Tests for Nginx_Opcache_Manager_Cache::is_valid_cache_levels().
 *
 * Bug guarded: the nom_fastcgi_cache_levels option is stored by the settings
 * screen as free text. Without validation a typo ("", "abc", "1:3", "0:2")
 * flowed straight into build_cache_file_relative_path() and produced a
 * nonsensical purge path, so purges silently missed. The validator pins the
 * grammar nginx accepts: 1–3 levels, each "1" or "2".
 */

use PHPUnit\Framework\TestCase;

final class CacheLevelsValidationTest extends TestCase {

	/**
	 * Strings nginx accepts as fastcgi_cache_path levels.
	 */
	public function validLevelsProvider() {
		return array(
			'default nginx levels' => array( '1:2' ),
			'two double levels'    => array( '2:2' ),
			'first-level only 1'   => array( '1' ),
			'first-level only 2'   => array( '2' ),
			'three levels'         => array( '2:2:2' ),
		);
	}

	/**
	 * @dataProvider validLevelsProvider
	 */
	public function test_accepts_valid_levels( $levels ) {
		$this->assertTrue(
			Nginx_Opcache_Manager_Cache::is_valid_cache_levels( $levels ),
			"'$levels' is a levels string nginx accepts and must validate"
		);
	}

	/**
	 * Strings that must fall back to the default instead of reaching the
	 * path builder: empty, non-numeric, per-level values outside 1..2,
	 * and more than three levels.
	 */
	public function invalidLevelsProvider() {
		return array(
			'empty string'          => array( '' ),
			'non-numeric'           => array( 'abc' ),
			'level value 3'         => array( '1:3' ),
			'level value 0'         => array( '0:2' ),
			'four levels'           => array( '1:2:3:2' ),
			'single level 3'        => array( '3' ),
			'second level 3'        => array( '2:3' ),
			'trailing colon'        => array( '2:' ),
			'leading colon'         => array( ':2' ),
			'double digit level'    => array( '1:22' ),
			'spaces'                => array( ' 1:2 ' ),
		);
	}

	/**
	 * @dataProvider invalidLevelsProvider
	 */
	public function test_rejects_invalid_levels( $levels ) {
		$this->assertFalse(
			Nginx_Opcache_Manager_Cache::is_valid_cache_levels( $levels ),
			"'$levels' is not a levels string nginx accepts and must be rejected"
		);
	}
}
