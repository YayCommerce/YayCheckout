/**
 * wp-scripts' config, plus the entries no block.json names: the live value format's editor script and
 * its storefront script. The block.json entries are kept as wp-scripts finds them.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: () => ( {
		...defaultConfig.entry(),
		'live-value/index': './src/live-value/index.ts',
		'live-value/view': './src/live-value/view.ts',
	} ),
};
