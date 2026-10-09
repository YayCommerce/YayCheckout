import { __ } from '@wordpress/i18n';
import type { ShippingSettingKey } from './setting-controls';

export interface ShippingLayout {
	value: string;
	label: string;
	settings: ShippingSettingKey[];
}

const BACKGROUNDS: ShippingSettingKey[] = [
	'backgroundColor',
	'backgroundColorSelected',
	'backgroundColorHover',
];

/** Mirrors CheckoutFragments::SHIPPING_LAYOUTS in PHP. */
export const SHIPPING_LAYOUTS: ShippingLayout[] = [
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
export function layoutFor( value: string | undefined ): ShippingLayout {
	return SHIPPING_LAYOUTS.find( ( layout ) => layout.value === value ) ?? SHIPPING_LAYOUTS[ 0 ];
}
