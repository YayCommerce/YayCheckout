import { InnerBlocks } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { blockIcons } from './block-icons';

type BlockSettings = NonNullable< Parameters< typeof registerBlockType >[ 1 ] >;
type BlockMetadata = { name: string } & Record< string, unknown >;

/**
 * Saves only the inner blocks; render.php still prints the wrapper. A parent whose
 * save() returns null loses its inner blocks on the next load.
 */
export function saveNestedBlocks() {
	return <InnerBlocks.Content />;
}

/**
 * Registers a block whose markup comes from render.php. A parent with inner blocks
 * must pass saveNestedBlocks as save.
 */
export function registerDynamicBlock(
	metadata: BlockMetadata,
	edit: BlockSettings[ 'edit' ],
	save: BlockSettings[ 'save' ] = () => null,
	settings: Omit< BlockSettings, 'edit' | 'save' > = {}
): void {
	registerBlockType( metadata as Parameters< typeof registerBlockType >[ 0 ], {
		icon: blockIcons[ metadata.name ],
		...settings,
		edit,
		save,
	} );
}
