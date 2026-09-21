import { store as blockEditorStore } from '@wordpress/block-editor';
import { select } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { addFilter } from '@wordpress/hooks';

const LAYOUT_POST_TYPE = 'yay_checkout_layout';
const CHECKOUT_BLOCK = 'yaycheckout/checkout';
const FIELD_BLOCK = 'yaycheckout/field';
const CONTACT_BLOCK = 'yaycheckout/contact';
const EXTRA_FIELD_BLOCK = 'yaycheckout/extra-field';
const SLOT_BLOCK = 'yaycheckout/slot';
const ADDRESS_SECTIONS = [ 'yaycheckout/billing', 'yaycheckout/shipping' ];
const UNIQUE_LEAVES = [
	CONTACT_BLOCK,
	...ADDRESS_SECTIONS,
	'yaycheckout/order-review',
	'yaycheckout/shipping-methods',
	'yaycheckout/express-pay',
	'yaycheckout/payment',
	'yaycheckout/order-summary',
	'yaycheckout/place-order',
];
const CHECKOUT_LEAVES = [ ...UNIQUE_LEAVES, EXTRA_FIELD_BLOCK, SLOT_BLOCK ];
const NESTED_YAY = [ ...CHECKOUT_LEAVES, FIELD_BLOCK ];
const WOO_CHECKOUT = 'woocommerce/checkout';

function currentPostType(): string | undefined {
	try {
		return select( editorStore ).getCurrentPostType() || undefined;
	} catch ( e ) {
		return undefined;
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
 * YayCheckout blocks insert only on the layout CPT. Nested leaves only go
 * inside Checkout; Field is Billing/Shipping only. Woo Checkout stays out.
 * Checkout is `multiple: false` — hidden after the first copy, back if deleted.
 */
addFilter(
	'blocks.canInsertBlockType',
	'yaycheckout/layout-insert',
	( canInsert: boolean, blockType: { name: string }, rootClientId?: string ) => {
		const postType = currentPostType();
		const name = blockType.name;

		// getCurrentPostType() is often empty while this filter runs; treating
		// that as "not layout" hid Checkout from its own CPT template.
		if ( ! postType ) {
			return canInsert;
		}

		if ( postType !== LAYOUT_POST_TYPE ) {
			return name.startsWith( 'yaycheckout/' ) ? false : canInsert;
		}

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

		if ( CHECKOUT_LEAVES.includes( name ) ) {
			return parentName === CHECKOUT_BLOCK;
		}

		if ( parentName === CONTACT_BLOCK ) {
			return false;
		}

		return canInsert;
	}
);
