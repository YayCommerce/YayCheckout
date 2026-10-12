import { __ } from '@wordpress/i18n';
import fieldMeta from '../field/block.json';
import { billingAddressFields, shippingAddressFields } from './address-fields';

/** A block template: [ name, attributes?, inner template? ], nested. */
export type TemplateArray = ( string | object | TemplateArray )[];

type Prefix = 'billing' | 'shipping';

interface FieldSeed {
	key: string;
	label: string;
	placeholder?: string;
	description?: string;
}

/** A Field block for one address field; its type and required flag come from shared/address-fields.ts. */
function field( prefix: Prefix, { key, label, ...attrs }: FieldSeed ): TemplateArray {
	const fieldKey = `${ prefix }_${ key }`;
	const def = ( prefix === 'billing' ? billingAddressFields() : shippingAddressFields() ).find( ( f ) => f.key === fieldKey );

	if ( ! def ) {
		throw new Error( `No address field ${ fieldKey }.` );
	}

	return [ fieldMeta.name, { fieldKey, type: def.type, label, required: def.required, ...attrs } ];
}

// Matches the horizontal spacing between side-by-side fields in
// templates/layouts/classic-two-column/markup.php.
const COLUMN_GAP = { style: { spacing: { blockGap: { left: '24px' } } } };

/** Core aligns each Column, not the row, so a row's verticalAlignment is copied onto its columns as the editor does. */
function row(
	columns: [ width: string | undefined, cell: TemplateArray ][],
	verticalAlignment?: 'bottom'
): TemplateArray {
	const align = verticalAlignment ? { verticalAlignment } : {};

	return [
		'core/columns',
		{ ...align, ...COLUMN_GAP },
		columns.map( ( [ width, cell ] ) => [ 'core/column', { ...align, ...( width ? { width } : {} ) }, [ cell ] ] ),
	];
}

/**
 * What a new Billing or Shipping section holds, whether it comes with a new Checkout or on its own.
 * templates/layouts/classic-two-column/markup.php seeds new layouts with the same structure;
 * change both together.
 * Keys come from shared/address-fields.ts; the apartment line starts behind its Disclosure link.
 */
export function addressTemplate( prefix: Prefix ): TemplateArray {
	return [
		row( [
			[ undefined, field( prefix, { key: 'first_name', label: __( 'First name', 'yaycheckout' ) } ) ],
			[ undefined, field( prefix, { key: 'last_name', label: __( 'Last name', 'yaycheckout' ) } ) ],
		] ),
		field( prefix, { key: 'company', label: __( 'Company', 'yaycheckout' ) } ),
		field( prefix, { key: 'country', label: __( 'Country/Region', 'yaycheckout' ) } ),
		field( prefix, {
			key: 'address_1',
			label: __( 'Address', 'yaycheckout' ),
			placeholder: __( 'House number and street name', 'yaycheckout' ),
		} ),
		field( prefix, {
			key: 'address_2',
			label: __( 'Apartment, suite, unit, etc.', 'yaycheckout' ),
		} ),
		row( [
			[ '50%', field( prefix, { key: 'city', label: __( 'City', 'yaycheckout' ) } ) ],
			[ '25%', field( prefix, { key: 'state', label: __( 'State', 'yaycheckout' ) } ) ],
			[ '25%', field( prefix, { key: 'postcode', label: __( 'ZIP Code', 'yaycheckout' ) } ) ],
			// Bottom-aligned, so the inputs stay in line when a country's longer label wraps.
		], 'bottom' ),
		field( prefix, {
			key: 'phone',
			label: __( 'Phone', 'yaycheckout' ),
			description: __( 'In case we need to contact you about your order', 'yaycheckout' ),
		} ),
	];
}
