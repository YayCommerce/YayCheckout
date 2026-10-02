/**
 * Block names a Step may contain.
 *
 * parent and ancestor on each block.json decide this. InnerBlocks cannot
 * read those files, so the editor list is derived here.
 */

interface BlockMeta {
	name: string;
	parent?: string[];
	ancestor?: string[];
}

interface RequireContext {
	keys(): string[];
	( id: string ): BlockMeta | { default: BlockMeta };
}

declare const require: {
	context( path: string, deep?: boolean, filter?: RegExp ): RequireContext;
};

const STEP = 'yaycheckout/step';
const CHECKOUT = 'yaycheckout/checkout';
const blocks = require.context( '..', true, /\/block\.json$/ );

export const stepSections: string[] = blocks
	.keys()
	.map( ( key ) => {
		const loaded = blocks( key );

		return 'default' in loaded ? loaded.default : loaded;
	} )
	.filter( ( block ) => {
		const parents = block.parent ?? [];
		const ancestors = block.ancestor ?? [];

		return parents.includes( STEP ) || ancestors.includes( STEP ) || ancestors.includes( CHECKOUT );
	} )
	.map( ( block ) => block.name );
