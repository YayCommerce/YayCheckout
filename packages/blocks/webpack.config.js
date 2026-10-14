/**
 * wp-scripts' config, plus the entries no block.json names: the live value format's and block visibility's
 * editor and storefront scripts. The block.json entries are kept as wp-scripts finds them.
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
	} ),
};
