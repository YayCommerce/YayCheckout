import { store as blocksStore } from '@wordpress/blocks';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import backButtonMeta from '../back-button/block.json';
import checkoutMeta from '../checkout/block.json';
import emailMeta from '../email/block.json';
import progressMeta from '../progress/block.json';
import stepButtonMeta from '../step-button/block.json';

interface BlockTypeRules {
	name: string;
	parent?: string[];
	ancestor?: string[];
}

const CHECKOUT = checkoutMeta.name;

// The step buttons find the open step from anywhere in the layout, and Email address posts billing_email
// from anywhere in it, so both stay allowed inside an address section.
const ALLOWED_IN_ADDRESS_SECTIONS = new Set( [ stepButtonMeta.name, backButtonMeta.name, emailMeta.name ] );

/**
 * A block whose ancestor rule names Checkout, other than the step buttons and Email address,
 * or Progress, which may sit outside Checkout but stays out of address sections.
 */
export function isCheckoutSection( type: BlockTypeRules ): boolean {
	if ( type.name === progressMeta.name ) {
		return true;
	}

	return ( type.ancestor ?? [] ).includes( CHECKOUT ) && ! ALLOWED_IN_ADDRESS_SECTIONS.has( type.name );
}

/**
 * Every registered block except those `excluded` matches, as a container's
 * allowedBlocks. Core rules only allow, so this is how a container keeps a
 * block out. It binds direct children only: inside a Group the excluded
 * blocks are offered again.
 *
 * Blocks with a parent rule are left out: core lets a block in when either
 * the container lists it or its own parent rule names the container, so
 * listing one would override its parent rule.
 */
export function useBlocksAllowedExcept( excluded: ( type: BlockTypeRules ) => boolean ): string[] {
	const types: BlockTypeRules[] = useSelect( ( select ) => select( blocksStore ).getBlockTypes(), [] );

	return useMemo(
		() => types.filter( ( type ) => ! type.parent && ! excluded( type ) ).map( ( type ) => type.name ),
		[ types, excluded ]
	);
}
