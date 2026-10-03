/**
 * Babel configuration.
 *
 * This file exists solely to switch JSX from the automatic runtime to the
 * classic one.
 *
 * `@wordpress/babel-preset-default` hardcodes `runtime: 'automatic'`, which
 * makes the emitted bundle import `react/jsx-runtime` and adds the
 * `react-jsx-runtime` WordPress script handle to `build/index.asset.php`.
 * WordPress only registers that handle from 6.6 onward; on anything older the
 * handle is silently dropped, `window.ReactJSXRuntime` is undefined and the
 * bundle throws at module scope, leaving the admin panel completely blank.
 *
 * This plugin declares support for WordPress 6.2+, so the classic runtime —
 * which only needs the `react` handle WordPress has always registered — is the
 * correct choice. Every JSX module therefore imports React explicitly.
 *
 * @package Nginx_Opcache_Manager
 */

const wpBabelPreset = require( '@wordpress/babel-preset-default' );
const jsxTransform = require.resolve( '@babel/plugin-transform-react-jsx' );

module.exports = ( api ) => {
	const preset = wpBabelPreset( api );

	// Drop the preset's automatic-runtime JSX plugin and substitute the classic
	// transform, keeping the rest of the WordPress preset (preset-env targets,
	// TypeScript, polyfill/runtime handling) untouched.
	preset.plugins = [
		...preset.plugins.filter(
			( plugin ) =>
				! Array.isArray( plugin ) || plugin[ 0 ] !== jsxTransform
		),
		[ jsxTransform, { runtime: 'classic' } ],
	];

	return preset;
};