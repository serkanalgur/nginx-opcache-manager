/**
 * ESLint configuration for the plugin's React sources.
 *
 * wp-scripts only injects its own default config when the project supplies
 * none (see @wordpress/scripts/scripts/lint-js.js), so this file has to extend
 * that default explicitly to keep the full WordPress rule set active.
 *
 * @package Nginx_Opcache_Manager
 */

module.exports = {
	root: true,
	extends: [ require.resolve( '@wordpress/scripts/config/.eslintrc.js' ) ],
	rules: {
		// WordPress plugin convention requires `@package <PluginName>` in every
		// file header. eslint-plugin-jsdoc flags that as an empty tag because in
		// the default (non-closure) mode its `emptyIfNotClosure` set hardcodes
		// 'package' as a tag that must have no content:
		//   node_modules/eslint-plugin-jsdoc/dist/rules/emptyTags.js
		// The rule's `tags` option *adds* tags to that check rather than
		// exempting them, and the only alternative — switching jsdoc to
		// `mode: 'closure'` — changes behaviour across every jsdoc rule.
		// So the rule is off here, and `@package` is kept in all file headers.
		// Trade-off: value-less `@param`/`@return` tags are no longer reported.
		'jsdoc/empty-tags': 'off',
	},
};