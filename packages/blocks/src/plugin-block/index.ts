import { addFilter } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import metadata from './block.json';
import Edit from './edit';
import { withPluginBlockCard } from './missing';
import { pluginBlocksData } from './plugin-blocks-data';
import { pluginBlockIcon } from '../shared/block-icons';
import { registerDynamicBlock } from '../shared/register-dynamic-block';

// The block itself stays out of the inserter; each catalogue entry is a variation under From Yay plugins.
registerDynamicBlock( metadata, pluginBlockIcon, Edit, () => null, {
	variations: pluginBlocksData.entries.map( ( entry ) => ( {
		name: entry.target.replace( '/', '-' ),
		/* translators: 1: block title, 2: plugin name. */
		title: sprintf( __( '%1$s (%2$s)', 'yaycheckout' ), entry.title, entry.pluginName ),
		description: entry.pitch,
		attributes: { target: entry.target },
		scope: [ 'inserter' ],
		isActive: [ 'target' ],
	} ) ),
} );

// The catalogue is printed in the layout editor only, so other editors keep core's missing-block notice.
if ( pluginBlocksData.entries.length > 0 ) {
	addFilter( 'editor.BlockEdit', 'yaycheckout/plugin-block-missing', withPluginBlockCard );
}
