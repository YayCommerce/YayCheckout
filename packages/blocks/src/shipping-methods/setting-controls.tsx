import type { ComponentType } from '@wordpress/element';

export type ShippingSettingKey =
	| 'gap'
	| 'borderRadius'
	| 'backgroundColor'
	| 'backgroundColorSelected'
	| 'backgroundColorHover';

export interface SettingControlProps {
	value: unknown;
	onChange: ( value: unknown ) => void;
}

/**
 * Shared setting controls for Shipping Methods layouts. A layout lists the keys
 * it uses; the Styles tab only renders those. Filled in when the Methods panel lands.
 */
export const SETTING_CONTROLS = {} as Record< ShippingSettingKey, ComponentType< SettingControlProps > >;
