import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';

/**
 * save() must stay null: with static save output every attribute rename
 * would need a deprecation entry and invalidate published layouts.
 */
registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
