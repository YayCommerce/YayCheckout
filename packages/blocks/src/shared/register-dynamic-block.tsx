import { InnerBlocks } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { decodeEntities } from '@wordpress/html-entities';
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

type NamedAttributes = { metadata?: { name?: string } };

/**
 * An __experimentalLabel for the List View only: the name the merchant gave the block, else $text,
 * entities decoded. Undefined falls back to the block's title.
 */
export function listViewLabel< A >( text: ( attributes: A ) => string ) {
	return ( attributes: A & NamedAttributes, { context }: { context: string } ): string | undefined =>
		context === 'list-view' ? attributes.metadata?.name || decodeEntities( text( attributes ) ) || undefined : undefined;
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
