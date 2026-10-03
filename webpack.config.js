const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );
const {
	defaultRequestToExternal,
	defaultRequestToHandle,
} = require( '@wordpress/dependency-extraction-webpack-plugin/lib/util' );
const path = require( 'path' );

/**
 * `react/jsx-runtime` is deliberately left out of the WordPress script
 * dependencies and bundled into `index.js` instead.
 *
 * WordPress only registers the `react-jsx-runtime` script handle from 6.6
 * onward. On older versions the handle is silently dropped, leaving
 * `window.ReactJSXRuntime` undefined and the whole admin panel blank. This
 * plugin declares support for WordPress 6.2+, so the subpath must not become a
 * runtime requirement.
 *
 * The remaining `react` external is unaffected: React 18's `jsx-runtime` module
 * requires `react`, which still resolves to the `window.React` global.
 *
 * `useDefaults` is off so that returning `undefined` is a final answer rather
 * than falling back to the default mapping and re-externalizing the subpath.
 *
 * REACT VERSION COUPLING — do not bump the `react` devDependency without
 * re-reading this.
 *
 * Bundling `react/jsx-runtime` means this bundle carries a *compiled-in copy*
 * of the automatic runtime, and it is the version resolved at build time — not
 * the `window.React` global. That pins two things at once:
 *
 *  1. `react-chartjs-2@5.x` is itself pre-compiled against the automatic
 *     runtime (its `dist/index.js` does `require('react/jsx-runtime')`). Its
 *     output has to be run by a `jsx-runtime` whose shape matches what React
 *     shipped when that release was cut — React 18 here. Bumping `react` to 19
 *     would hand react-chartjs-2 a runtime from a different major.
 *  2. React 19 *removed* `ReactCurrentOwner`, which React 18's
 *     `react/jsx-runtime` dereferences (9 references in
 *     `react@18.3.1/cjs/react-jsx-runtime.development.js`). Inlining the
 *     react@18 runtime therefore keeps react-chartjs-2 working; pointing the
 *     subpath back at the host's React 19 `jsx-runtime` would not necessarily
 *     — which is the opposite trade from what the WP 6.6 handle constraint
 *     above pushes you toward.
 *
 * The two constraints pull in opposite directions, so neither can be relaxed
 * alone. Lifting the WordPress 6.6 floor is the clean way to re-externalize the
 * subpath and unpin React; bumping React to 19 is not, because react-chartjs-2
 * would have to be replaced at the same time.
 *
 * `src/__tests__/build-asset.test.js` guards this: it fails if
 * `react-jsx-runtime` reappears in build/index.asset.php, and it fails if
 * babel.config.js — the other half of this fix — goes missing.
 *
 * @param {string} request Module request.
 * @return {string|undefined} External identifier, or undefined to bundle.
 */
const requestToExternal = ( request ) =>
	request === 'react/jsx-runtime' ? undefined : defaultRequestToExternal( request );

/**
 * Keep the asset manifest in step with the externals above.
 *
 * @param {string} request Module request.
 * @return {string|undefined} Script handle, or undefined to omit.
 */
const requestToHandle = ( request ) =>
	request === 'react/jsx-runtime' ? undefined : defaultRequestToHandle( request );

module.exports = {
	...defaultConfig,
	entry: {
		index: path.resolve( __dirname, 'src/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
	optimization: {
		...defaultConfig.optimization,
		splitChunks: false,
	},
	plugins: defaultConfig.plugins
		// Swap the stock dependency-extraction plugin for one that bundles
		// `react/jsx-runtime`. Everything else keeps the upstream behaviour.
		.map( ( plugin ) =>
			plugin instanceof DependencyExtractionWebpackPlugin
				? new DependencyExtractionWebpackPlugin( {
						useDefaults: false,
						requestToExternal,
						requestToHandle,
				  } )
				: plugin
		),
};
