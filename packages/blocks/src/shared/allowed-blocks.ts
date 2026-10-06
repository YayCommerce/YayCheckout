import { store as blocksStore } from '@wordpress/blocks';
import { useSelect } from '@wordpress/data';

interface BlockTypeRules {
	name: string;
	parent?: string[];
	ancestor?: string[];
}

const CHECKOUT = 'yaycheckout/checkout';

// The step buttons find the open step from anywhere in the form, so they stay allowed inside an address section.
const STEP_BUTTONS = new Set( [ 'yaycheckout/step-button', 'yaycheckout/back-button' ] );

/** A block whose ancestor rule names Checkout, other than the step buttons. */
export function isCheckoutSection( type: BlockTypeRules ): boolean {
	return ( type.ancestor ?? [] ).includes( CHECKOUT ) && ! STEP_BUTTONS.has( type.name );
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
	return useSelect(
		( select ) =>
			select( blocksStore )
				.getBlockTypes()
				.filter( ( type: BlockTypeRules ) => ! type.parent && ! excluded( type ) )
				.map( ( type: BlockTypeRules ) => type.name ),
		[ excluded ]
	);
}
