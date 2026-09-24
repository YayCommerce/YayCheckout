import { InnerBlocks } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { blockIcons } from './block-icons';

type BlockSettings = NonNullable< Parameters< typeof registerBlockType >[ 1 ] >;

/**
 * Persist nested blocks into post_content. save() null on a parent drops
 * InnerBlocks on the next load — Gutenberg stores a self-closing comment
 * with no children.
 *
 * Wrapper HTML still comes from render.php; this only serializes children.
 */
export function saveNestedBlocks() {
	return <InnerBlocks.Content />;
}

/**
 * Dynamic block: default save() is null so leaf markup comes from render.php.
 *
 * Parents with InnerBlocks must pass saveNestedBlocks — see that helper.
 */
export function registerDynamicBlock(
	name: string,
	edit: BlockSettings[ 'edit' ],
	save: BlockSettings[ 'save' ] = () => null,
	settings: Omit< BlockSettings, 'edit' | 'save' > = {}
): void {
	registerBlockType( name, {
		icon: blockIcons[ name ],
		...settings,
		edit,
		save,
	} );
}
