import { __ } from '@wordpress/i18n';

export interface AddressFieldDef {
	key: string;
	type: string;
	label: string;
	required: boolean;
}

/**
 * Owns the address-field list the field picker offers. LayoutRepository::address_field_blocks()
 * seeds new layouts with a subset of it; keep the two in step.
 */
function addressFields( prefix: 'billing' | 'shipping' ): AddressFieldDef[] {
	return [
		{
			key: `${ prefix }_first_name`,
			type: 'text',
			label: __( 'First name', 'yaycheckout' ),
			required: true,
		},
		{
			key: `${ prefix }_last_name`,
			type: 'text',
			label: __( 'Last name', 'yaycheckout' ),
			required: true,
		},
		{
			key: `${ prefix }_company`,
			type: 'text',
			label: __( 'Company name', 'yaycheckout' ),
			required: false,
		},
		{
			key: `${ prefix }_country`,
			type: 'country',
			label: __( 'Country / Region', 'yaycheckout' ),
			required: true,
		},
		{
			key: `${ prefix }_address_1`,
			type: 'text',
			label: __( 'Street address', 'yaycheckout' ),
			required: true,
		},
		{
			key: `${ prefix }_address_2`,
			type: 'text',
			label: __( 'Apartment, suite, unit, etc.', 'yaycheckout' ),
			required: false,
		},
		{
			key: `${ prefix }_city`,
			type: 'text',
			label: __( 'Town / City', 'yaycheckout' ),
			required: true,
		},
		{
			key: `${ prefix }_state`,
			type: 'state',
			label: __( 'State / County', 'yaycheckout' ),
			required: true,
		},
		{
			key: `${ prefix }_postcode`,
			type: 'text',
			label: __( 'Postcode / ZIP', 'yaycheckout' ),
			required: true,
		},
		{
			key: `${ prefix }_phone`,
			type: 'tel',
			label: __( 'Phone', 'yaycheckout' ),
			required: false,
		},
	];
}

export function billingAddressFields(): AddressFieldDef[] {
	const fields = addressFields( 'billing' );
	const after = fields.findIndex( ( field ) => field.key === 'billing_last_name' );
	const email: AddressFieldDef = {
		key: 'billing_email',
		type: 'email',
		label: __( 'Email address', 'yaycheckout' ),
		required: true,
	};

	if ( after === -1 ) {
		return [ email, ...fields ];
	}

	return [ ...fields.slice( 0, after + 1 ), email, ...fields.slice( after + 1 ) ];
}

export function shippingAddressFields(): AddressFieldDef[] {
	return addressFields( 'shipping' );
}
