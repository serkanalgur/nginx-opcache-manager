/**
 * Build guard: the bundle must not depend on the `react-jsx-runtime` WordPress
 * script handle (FIX 5).
 *
 * WordPress only registers that handle from 6.6 onward, while the plugin header
 * declares 6.2. On an older install the handle is silently dropped,
 * `window.ReactJSXRuntime` is undefined and the admin panel renders nothing at
 * all. The handle only reached the manifest because
 * `@wordpress/babel-preset-default` hardcodes `runtime: 'automatic'`, which
 * compiles JSX to an import of `react/jsx-runtime`.
 *
 * The fix spans three pieces of BUILD CONFIGURATION:
 *   - babel.config.js      — forces the classic JSX runtime;
 *   - webpack.config.js    — refuses to externalize `react/jsx-runtime`, so the
 *                            subpath is bundled instead of becoming a handle;
 *   - build/index.asset.php — the generated manifest, i.e. the result.
 *
 * Reading the committed manifest alone would only prove the artifact is
 * correct, not that the configuration still *produces* it: delete
 * babel.config.js and the stale artifact keeps passing. So the assertions below
 * exercise the configuration directly — Babel really is asked to transform JSX
 * through the real config file, and webpack's request mappers really are
 * called — and read the manifest only as a final end-to-end check.
 *
 * @package Nginx_Opcache_Manager
 */

const fs = require( 'fs' );
const path = require( 'path' );

const babel = require( '@babel/core' );
const wpBabelPreset = require( '@wordpress/babel-preset-default' );

const ROOT = path.resolve( __dirname, '../..' );
const BABEL_CONFIG = path.join( ROOT, 'babel.config.js' );
const WEBPACK_CONFIG = path.join( ROOT, 'webpack.config.js' );
const ASSET_MANIFEST = path.join( ROOT, 'build/index.asset.php' );

/**
 * Read the `dependencies` handle list out of the webpack asset manifest.
 *
 * The manifest is machine-generated `<?php return array( 'dependencies' =>
 * array( 'react', 'wp-i18n' ), 'version' => '<hash>' );`. It is PHP, not JS, so
 * it is read textually rather than evaluated: evaluating it would need a PHP
 * `array()` shim, and `array( 'x' => $y )` is not even parseable as a JS call.
 *
 * @return {Array<string>} Declared script handles.
 */
function readDependencies() {
	const manifest = fs.readFileSync( ASSET_MANIFEST, 'utf8' );
	const block = manifest.match( /'dependencies'\s*=>\s*array\(([^)]*)\)/ );

	if ( ! block ) {
		throw new Error( `No dependencies array found in ${ ASSET_MANIFEST }` );
	}

	return ( block[ 1 ].match( /'([^']*)'/g ) || [] ).map( ( quoted ) =>
		quoted.slice( 1, -1 )
	);
}

describe( 'build config: classic JSX runtime', () => {
	it( 'keeps babel.config.js, which is what forces the classic runtime', () => {
		// The manifest assertion below would still pass with a stale artifact if
		// this file were deleted, so its existence is asserted explicitly.
		expect( fs.existsSync( BABEL_CONFIG ) ).toBe( true );
	} );

	it( 'compiles JSX to React.createElement, with no react/jsx-runtime import', () => {
		// Resolve babel.config.js by path so the assertion covers the file on
		// disk, not jest's own babel-jest transform of this test file.
		const { code } = babel.transformSync( 'const el = <div />;', {
			cwd: ROOT,
			root: ROOT,
			babelrc: false,
			configFile: BABEL_CONFIG,
			filename: path.join( ROOT, 'src/build-probe.js' ),
		} );

		// Deleting babel.config.js leaves the JSX untransformed (no plugin at
		// all) or lets @wordpress/babel-preset-default emit
		// `from "react/jsx-runtime"` (automatic). Both fail here.
		expect( code ).toContain( 'React.createElement' );
		expect( code ).not.toMatch( /jsx-runtime/ );
	} );

	it( 'emits an automatic runtime without babel.config.js, proving the assertion above has teeth', () => {
		// Control case. If this ever stops producing react/jsx-runtime, the
		// assertion in the previous test is vacuous and needs rethinking.
		const { code } = babel.transformSync( 'const el = <div />;', {
			cwd: ROOT,
			root: ROOT,
			babelrc: false,
			configFile: false,
			filename: path.join( ROOT, 'src/build-probe.js' ),
			// Array form so Babel actually invokes the preset function.
			presets: [ [ wpBabelPreset, {} ] ],
		} );

		expect( code ).toMatch( /jsx-runtime/ );
	} );
} );

describe( 'build config: webpack external mapping', () => {
	let requestToExternal;
	let requestToHandle;

	beforeAll( () => {
		// eslint-disable-next-line @typescript-eslint/no-var-requires
		const webpackConfig = require( WEBPACK_CONFIG );
		const plugin = webpackConfig.plugins.find(
			( candidate ) =>
				candidate.constructor.name ===
				'DependencyExtractionWebpackPlugin'
		);

		if ( ! plugin ) {
			throw new Error(
				'No DependencyExtractionWebpackPlugin in webpack.config.js; the react/jsx-runtime override is gone.'
			);
		}

		( { requestToExternal, requestToHandle } = plugin.options );
	} );

	it( 'bundles react/jsx-runtime instead of externalizing it', () => {
		// undefined from both mappers is what makes the subpath a bundled
		// module rather than a WordPress script handle.
		expect( requestToExternal( 'react/jsx-runtime' ) ).toBeUndefined();
		expect( requestToHandle( 'react/jsx-runtime' ) ).toBeUndefined();
	} );

	it( 'still externalizes react, which the bundled jsx-runtime requires', () => {
		// If `react` were bundled too, the bundle would carry its own copy of
		// React alongside the host's window.React — two Reacts, broken hooks.
		// react maps to the `React` global and, unlike every other dependency,
		// carries no script handle of its own.
		expect( requestToExternal( 'react' ) ).toBe( 'React' );
		expect( requestToHandle( 'react' ) ).toBeUndefined();
	} );

	it( 'leaves every other WordPress dependency on the default mapping', () => {
		expect( requestToExternal( '@wordpress/api-fetch' ) ).toEqual( [
			'wp',
			'apiFetch',
		] );
		expect( requestToHandle( '@wordpress/api-fetch' ) ).toBe(
			'wp-api-fetch'
		);
	} );
} );

describe( 'build/index.asset.php script dependencies', () => {
	let dependencies;

	beforeAll( () => {
		dependencies = readDependencies();
	} );

	it( 'declares a non-empty array of script handles', () => {
		expect( Array.isArray( dependencies ) ).toBe( true );
		expect( dependencies.length ).toBeGreaterThan( 0 );
		expect( dependencies ).toContain( 'wp-components' );
	} );

	it( 'does not declare react-jsx-runtime, the one handle this plugin must not need', () => {
		/*
		 * Deliberately a single handle, not a list of "everything added in
		 * WP 6.6". An earlier version of this file asserted against
		 * `react-jsx-runtime-jsx`, `interactivity` and `wp-interactivity` as
		 * well — handles that are only load-bearing when the Interactivity API
		 * or the `jsx-runtime` *bundle* is actually used, neither of which this
		 * plugin does. That list is noise: it makes the guard look broader than
		 * it is and would one day fail on a dependency that is perfectly safe.
		 *
		 * The invariant is narrow and it is the one that matters: this plugin
		 * supports WordPress 6.2, and `react-jsx-runtime` is the single handle
		 * in this manifest that 6.2 does not register.
		 */
		expect( dependencies ).not.toContain( 'react-jsx-runtime' );
	} );
} );
