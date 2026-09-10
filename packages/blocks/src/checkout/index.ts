import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';

/**
 * Dynamic block: save() returns null so all markup comes from render.php.
 *
 * This is load-bearing for the storage design. Published layouts are stored as
 * block markup and re-rendered on every request, so a renamed attribute is
 * absorbed by a default in render.php. A block with static save output would
 * instead need a deprecation entry for every attribute rename, and existing
 * published layouts would show as invalid in the editor.
 */
registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
