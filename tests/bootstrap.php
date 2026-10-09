<?php
/**
 * PHPUnit bootstrap.
 *
 * Provides a minimal WordPress environment (tests/wp-stubs.php) and loads the
 * plugin classes under test the same way the plugin's load_dependencies()
 * does, so tests exercise the real production files.
 */

// Never touched by the code under test in these tests; the includes only
// check that it is defined before allowing their class definitions.
define( 'ABSPATH', __DIR__ . '/' );

require __DIR__ . '/wp-stubs.php';

// Classes under test (static helpers + the tracker's hook registration).
require_once dirname( __DIR__ ) . '/includes/class-nginx-cache-manager.php';
require_once dirname( __DIR__ ) . '/includes/class-post-cache-tracker.php';

wp_test_reset_state();
