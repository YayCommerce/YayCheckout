/**
 * Block names an Accordion Step may contain.
 *
 * parent on each block.json decides this. InnerBlocks cannot read those
 * files, so the editor list is derived here.
 */

interface BlockMeta {
	name: string;
	parent?: string[];
}

interface RequireContext {
	keys(): string[];
	( id: string ): BlockMeta | { default: BlockMeta };
}

declare const require: {
	context( path: string, deep?: boolean, filter?: RegExp ): RequireContext;
};

const STEP = 'yaycheckout/accordion-step';
const blocks = require.context( '..', true, /\/block\.json$/ );

export const accordionStepSections: string[] = blocks
	.keys()
	.map( ( key ) => {
		const loaded = blocks( key );

		return 'default' in loaded ? loaded.default : loaded;
	} )
	.filter( ( block ) => ( block.parent ?? [] ).includes( STEP ) )
	.map( ( block ) => block.name );
