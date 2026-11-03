/**
 * wp-scripts' config, plus the entries no block.json names: the live value format's and block visibility's
 * editor and storefront scripts, and Pro's Related products editor and checkout scripts (under pro/, which
 * the Free zip leaves out). The block.json entries are kept as wp-scripts finds them.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: () => ( {
		...defaultConfig.entry(),
		'live-value/index': './src/live-value/index.ts',
		'live-value/view': './src/live-value/view.ts',
		'visibility/index': './src/visibility/index.tsx',
		'visibility/view': './src/visibility/view.ts',
		'pro/related-products/index': './src/pro/related-products/index.ts',
		'pro/related-products/view': './src/pro/related-products/view.ts',
	} ),
};
