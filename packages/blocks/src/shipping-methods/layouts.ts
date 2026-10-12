import { __ } from '@wordpress/i18n';
import { currentMethodLayout } from '../shared/method-layouts';
import { COLOR_SETTING_KEYS, type ShippingSettingKey } from './setting-controls';

export interface ShippingLayout {
	value: string;
	label: string;
	settings: ShippingSettingKey[];
}

/** Layouts whose rows have their own outline take every colour. */
const ROW_COLORS: ShippingSettingKey[] = [ ...COLOR_SETTING_KEYS ];

/** Single box and Divider lines rows share lines, so they take one border colour. */
const SHARED_LINE_COLORS: ShippingSettingKey[] = ROW_COLORS.filter(
	( key ) => key !== 'borderColorSelected' && key !== 'borderColorHover'
);

/** Mirrors CheckoutFragments::SHIPPING_LAYOUTS in PHP. */
export const SHIPPING_LAYOUTS: ShippingLayout[] = [
	{
		value: 'joined',
		label: __( 'Joined rows', 'yaycheckout' ),
		settings: [ 'borderWidth', 'borderRadius', ...ROW_COLORS ],
	},
	{
		value: 'checkmark',
		label: __( 'Cards with checkmark', 'yaycheckout' ),
		settings: [ 'gap', 'borderWidth', 'borderRadius', ...ROW_COLORS ],
	},
	{
		value: 'box',
		label: __( 'Single box', 'yaycheckout' ),
		settings: [ 'borderWidth', 'borderRadius', ...SHARED_LINE_COLORS ],
	},
	{
		value: 'grid',
		label: __( 'Card grid', 'yaycheckout' ),
		settings: [ 'gap', 'borderWidth', 'borderRadius', ...ROW_COLORS ],
	},
	{
		value: 'cards',
		label: __( 'Separate cards', 'yaycheckout' ),
		settings: [ 'gap', 'borderWidth', 'borderRadius', ...ROW_COLORS ],
	},
	{
		value: 'lines',
		label: __( 'Divider lines', 'yaycheckout' ),
		settings: [ 'borderWidth', ...SHARED_LINE_COLORS ],
	},
];

/** The layout for a stored value (old names included), or Joined rows when missing or unknown. */
export function layoutFor( value: string | undefined ): ShippingLayout {
	const current = currentMethodLayout( value );

	return SHIPPING_LAYOUTS.find( ( layout ) => layout.value === current ) ?? SHIPPING_LAYOUTS[ 0 ];
}
