import { __, sprintf } from '@wordpress/i18n';
import metadata from './block.json';
import Edit from './edit';
import { pluginBlocksData } from './plugin-blocks-data';
import { pluginBlockIcon } from '../shared/block-icons';
import { registerDynamicBlock } from '../shared/register-dynamic-block';

// Each catalogue entry is a variation under From Yay plugins. Core drops a block's variations along with the
// block when its inserter support is off, so the block stays insertable while it has entries, and the first
// variation's isDefault keeps the bare, targetless block out of the inserter. A variation starts with its entry's
// style, the look the templates give the spot.
registerDynamicBlock( metadata, pluginBlockIcon, Edit, () => null, {
	supports: { ...metadata.supports, inserter: pluginBlocksData.entries.length > 0 },
	variations: pluginBlocksData.entries.map( ( entry, index ) => ( {
		isDefault: index === 0,
		name: entry.target.replace( '/', '-' ),
		/* translators: 1: block title, 2: plugin name. */
		title: sprintf( __( '%1$s (%2$s)', 'yaycheckout' ), entry.title, entry.pluginName ),
		description: entry.pitch,
		attributes: { target: entry.target, ...( Object.keys( entry.style ).length > 0 ? { style: entry.style } : {} ) },
		scope: [ 'inserter' ],
		isActive: [ 'target' ],
	} ) ),
} );
