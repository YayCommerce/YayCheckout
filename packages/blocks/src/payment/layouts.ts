import { __ } from '@wordpress/i18n';
import type { PaymentSettingKey } from './setting-controls';

export interface PaymentLayout {
	value: string;
	label: string;
	settings: PaymentSettingKey[];
}

const BACKGROUNDS: PaymentSettingKey[] = [
	'backgroundColor',
	'backgroundColorSelected',
	'backgroundColorHover',
];

/** Mirrors CheckoutFragments::PAYMENT_LAYOUTS in PHP. */
export const PAYMENT_LAYOUTS: PaymentLayout[] = [
	{
		value: 'default',
		label: __( 'Default', 'yaycheckout' ),
		settings: [ 'borderRadius', ...BACKGROUNDS ],
	},
	{
		value: 'check',
		label: __( 'Check', 'yaycheckout' ),
		settings: [ 'gap', 'borderRadius', ...BACKGROUNDS ],
	},
	{
		value: 'boxed',
		label: __( 'Boxed', 'yaycheckout' ),
		settings: [ 'borderRadius', ...BACKGROUNDS ],
	},
	{
		value: 'columns',
		label: __( 'Columns', 'yaycheckout' ),
		settings: [ 'gap', 'borderRadius', ...BACKGROUNDS ],
	},
	{
		value: 'stacked',
		label: __( 'Stacked', 'yaycheckout' ),
		settings: [ 'gap', 'borderRadius', ...BACKGROUNDS ],
	},
	{
		value: 'divided',
		label: __( 'Divided', 'yaycheckout' ),
		settings: [ ...BACKGROUNDS ],
	},
];

/** The layout for a stored value, or Default when missing or unknown. */
export function layoutFor( value: string | undefined ): PaymentLayout {
	return PAYMENT_LAYOUTS.find( ( layout ) => layout.value === value ) ?? PAYMENT_LAYOUTS[ 0 ];
}
