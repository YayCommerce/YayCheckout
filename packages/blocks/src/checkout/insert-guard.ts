import { store as blockEditorStore } from '@wordpress/block-editor';
import { select } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { addFilter } from '@wordpress/hooks';

const LAYOUT_POST_TYPE = 'yay_checkout_layout';
const CHECKOUT_BLOCK = 'yaycheckout/checkout';
const FIELD_BLOCK = 'yaycheckout/field';
const CONTACT_BLOCK = 'yaycheckout/contact';
const ADDRESS_SECTIONS = [ 'yaycheckout/billing', 'yaycheckout/shipping' ];
const SECTION_BLOCKS = [ CONTACT_BLOCK, ...ADDRESS_SECTIONS ];
const NESTED_YAY = [ ...SECTION_BLOCKS, FIELD_BLOCK ];
const WOO_CHECKOUT = 'woocommerce/checkout';

function isLayoutEditor(): boolean {
	try {
		return select( editorStore ).getCurrentPostType() === LAYOUT_POST_TYPE;
	} catch ( e ) {
		return false;
	}
}

/**
 * Names from the document down to the block that would become the parent.
 */
function insertionChain( rootClientId?: string ): string[] {
	if ( ! rootClientId ) {
		return [];
	}

	const { getBlockParents, getBlockName } = select( blockEditorStore );

	return [ ...getBlockParents( rootClientId ), rootClientId ].map( ( id ) => getBlockName( id ) );
}

function chainHas( chain: string[], names: string[] ): boolean {
	return chain.some( ( name ) => names.includes( name ) );
}

/**
 * Checkout / Contact / Billing / Shipping are `multiple: false` — Gutenberg
 * hides them from the inserter after the first copy. This filter never
 * re-enables a block core already denied. Field is Billing/Shipping only
 * (including nested Rows). Contact is a leaf email block, not a canvas.
 * Woo Checkout Blocks stay out.
 */
addFilter(
	'blocks.canInsertBlockType',
	'yaycheckout/layout-insert',
	( canInsert: boolean, blockType: { name: string }, rootClientId?: string ) => {
		if ( ! isLayoutEditor() ) {
			return canInsert;
		}

		const name = blockType.name;

		if ( name === WOO_CHECKOUT ) {
			return false;
		}

		if ( ! canInsert ) {
			return false;
		}

		if ( ! rootClientId ) {
			if ( name === CHECKOUT_BLOCK ) {
				return true;
			}

			if ( NESTED_YAY.includes( name ) ) {
				return false;
			}

			return canInsert;
		}

		const chain = insertionChain( rootClientId );
		const parentName = chain[ chain.length - 1 ];

		if ( name === CHECKOUT_BLOCK ) {
			return false;
		}

		if ( name === FIELD_BLOCK ) {
			return chainHas( chain, ADDRESS_SECTIONS );
		}

		if ( SECTION_BLOCKS.includes( name ) ) {
			return parentName === CHECKOUT_BLOCK;
		}

		if ( parentName === CONTACT_BLOCK ) {
			return false;
		}

		return canInsert;
	}
);
