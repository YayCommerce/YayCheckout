import { __ } from '@wordpress/i18n';
import fieldMeta from '../field/block.json';

/** A block template: [ name, attributes?, inner template? ], nested. */
type TemplateArray = ( string | object | TemplateArray )[];

interface FieldSeed {
	key: string;
	type: string;
	label: string;
	required: boolean;
	placeholder?: string;
	description?: string;
}

function field( prefix: string, { key, ...attrs }: FieldSeed ): TemplateArray {
	return [ fieldMeta.name, { fieldKey: `${ prefix }_${ key }`, ...attrs } ];
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
export function addressTemplate( prefix: 'billing' | 'shipping' ): TemplateArray {
	return [
		row( [
			[ undefined, field( prefix, { key: 'first_name', type: 'text', label: __( 'First name', 'yaycheckout' ), required: true } ) ],
			[ undefined, field( prefix, { key: 'last_name', type: 'text', label: __( 'Last name', 'yaycheckout' ), required: true } ) ],
		] ),
		field( prefix, { key: 'company', type: 'text', label: __( 'Company', 'yaycheckout' ), required: false } ),
		field( prefix, { key: 'country', type: 'country', label: __( 'Country/Region', 'yaycheckout' ), required: true } ),
		field( prefix, {
			key: 'address_1',
			type: 'text',
			label: __( 'Address', 'yaycheckout' ),
			required: true,
			placeholder: __( 'House number and street name', 'yaycheckout' ),
		} ),
		field( prefix, {
			key: 'address_2',
			type: 'text',
			label: __( 'Apartment, suite, unit, etc.', 'yaycheckout' ),
			required: false,
		} ),
		row( [
			[ '50%', field( prefix, { key: 'city', type: 'text', label: __( 'City', 'yaycheckout' ), required: true } ) ],
			[ '25%', field( prefix, { key: 'state', type: 'state', label: __( 'State', 'yaycheckout' ), required: true } ) ],
			[ '25%', field( prefix, { key: 'postcode', type: 'text', label: __( 'ZIP Code', 'yaycheckout' ), required: true } ) ],
			// Bottom-aligned, so the inputs stay in line when a country's longer label wraps.
		], 'bottom' ),
		field( prefix, {
			key: 'phone',
			type: 'tel',
			label: __( 'Phone', 'yaycheckout' ),
			required: false,
			description: __( 'In case we need to contact you about your order', 'yaycheckout' ),
		} ),
	];
}
