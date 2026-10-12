import { __ } from '@wordpress/i18n';
import { COLOR_SETTING_KEYS, type ShippingSettingKey } from './setting-controls';

export interface ShippingLayout {
	value: string;
	label: string;
	settings: ShippingSettingKey[];
}

/** Layouts whose rows have their own outline take every colour. */
const ROW_COLORS: ShippingSettingKey[] = [ ...COLOR_SETTING_KEYS ];

/** Divided and Boxed rows share lines, so they take one border colour. */
const SHARED_LINE_COLORS: ShippingSettingKey[] = ROW_COLORS.filter(
	( key ) => key !== 'borderColorSelected' && key !== 'borderColorHover'
);

/** Mirrors CheckoutFragments::SHIPPING_LAYOUTS in PHP. */
export const SHIPPING_LAYOUTS: ShippingLayout[] = [
	{
		value: 'default',
		label: __( 'Default', 'yaycheckout' ),
		settings: [ 'borderRadius', ...ROW_COLORS ],
	},
	{
		value: 'check',
		label: __( 'Check', 'yaycheckout' ),
		settings: [ 'gap', 'borderRadius', ...ROW_COLORS ],
	},
	{
		value: 'boxed',
		label: __( 'Boxed', 'yaycheckout' ),
		settings: [ 'borderRadius', ...SHARED_LINE_COLORS ],
	},
	{
		value: 'columns',
		label: __( 'Columns', 'yaycheckout' ),
		settings: [ 'gap', 'borderRadius', ...ROW_COLORS ],
	},
	{
		value: 'stacked',
		label: __( 'Stacked', 'yaycheckout' ),
		settings: [ 'gap', 'borderRadius', ...ROW_COLORS ],
	},
	{
		value: 'divided',
		label: __( 'Divided', 'yaycheckout' ),
		settings: [ ...SHARED_LINE_COLORS ],
	},
];

/** The layout for a stored value, or Default when missing or unknown. */
export function layoutFor( value: string | undefined ): ShippingLayout {
	return SHIPPING_LAYOUTS.find( ( layout ) => layout.value === value ) ?? SHIPPING_LAYOUTS[ 0 ];
}
