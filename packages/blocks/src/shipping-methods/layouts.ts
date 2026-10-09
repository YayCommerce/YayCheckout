import { __ } from '@wordpress/i18n';
import type { ShippingSettingKey } from './setting-controls';

export interface ShippingLayout {
	value: string;
	label: string;
	settings: ShippingSettingKey[];
}

/** Mirrors CheckoutFragments::SHIPPING_LAYOUTS in PHP. */
export const SHIPPING_LAYOUTS: ShippingLayout[] = [
	{ value: 'default', label: __( 'Default', 'yaycheckout' ), settings: [] },
];

/** The layout for a stored value, or Default when missing or unknown. */
export function layoutFor( value: string | undefined ): ShippingLayout {
	return SHIPPING_LAYOUTS.find( ( layout ) => layout.value === value ) ?? SHIPPING_LAYOUTS[ 0 ];
}
