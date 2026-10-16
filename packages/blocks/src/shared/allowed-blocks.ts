import { store as blocksStore } from '@wordpress/blocks';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import backButtonMeta from '../pro/back-button/block.json';
import checkoutMeta from '../checkout/block.json';
import emailMeta from '../email/block.json';
import extraFieldMeta from '../extra-field/block.json';
import stepsProgressMeta from '../pro/steps-progress/block.json';
import stepButtonMeta from '../pro/step-button/block.json';
import wcHookMeta from '../wc-hook/block.json';

interface BlockTypeRules {
	name: string;
	parent?: string[];
	ancestor?: string[];
}

const CHECKOUT = checkoutMeta.name;

// The step buttons find the open step from anywhere in the layout, Email address posts billing_email
// from anywhere in it, Extra Field posts under its Key from anywhere, and a WooCommerce Hook block may
// carry a billing or shipping form hook, so all stay allowed inside an address section.
const ALLOWED_IN_ADDRESS_SECTIONS = new Set( [
	stepButtonMeta.name,
	backButtonMeta.name,
	emailMeta.name,
	extraFieldMeta.name,
	wcHookMeta.name,
] );

/**
 * A block whose ancestor rule names Checkout, other than the step buttons, Email address, Extra Field and WooCommerce Hook,
 * or Steps Progress, which may sit outside Checkout but stays out of address sections.
 */
export function isCheckoutSection( type: BlockTypeRules ): boolean {
	if ( type.name === stepsProgressMeta.name ) {
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
