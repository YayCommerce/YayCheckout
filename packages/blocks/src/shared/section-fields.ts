import billingMeta from '../billing/block.json';
import fieldMeta from '../field/block.json';
import shippingMeta from '../shipping/block.json';

export type AddressSection = 'billing' | 'shipping';

/** The Billing and Shipping block names, by the prefix of the field keys they hold. Billing first. */
export const ADDRESS_SECTIONS: Record< AddressSection, string > = {
	billing: billingMeta.name,
	shipping: shippingMeta.name,
};

/** The address section a block name is, or null. */
export function addressSectionOf( blockName: string | null ): AddressSection | null {
	return ( Object.keys( ADDRESS_SECTIONS ) as AddressSection[] ).find( ( section ) => ADDRESS_SECTIONS[ section ] === blockName ) ?? null;
}

/** The block-editor selectors the section readers use. */
export interface BlockEditorSelectors {
	getBlocksByName: ( name: string ) => string[];
	getClientIdsOfDescendants: ( clientIds: string[] ) => string[];
	getBlockName: ( clientId: string ) => string | null;
	getBlockAttributes: ( clientId: string ) => Record< string, unknown > | null;
}

export interface SectionField {
	key: string;
	attributes: Record< string, unknown >;
}

/** The Field blocks inside each section named sectionBlockName, in document order, that have a Key. */
export function sectionFields( editor: BlockEditorSelectors, sectionBlockName: string ): SectionField[] {
	return editor.getBlocksByName( sectionBlockName ).flatMap( ( sectionId ) =>
		editor
			.getClientIdsOfDescendants( [ sectionId ] )
			.filter( ( clientId ) => editor.getBlockName( clientId ) === fieldMeta.name )
			.map( ( clientId ) => {
				const attributes = editor.getBlockAttributes( clientId ) ?? {};

				return { key: String( attributes.fieldKey ?? '' ).trim(), attributes };
			} )
			.filter( ( field ) => field.key !== '' )
	);
}
