import type { ComponentType } from '@wordpress/element';

export interface SettingControlProps {
	value: unknown;
	onChange: ( value: unknown ) => void;
}

/**
 * Shared setting controls for Shipping Methods layouts. A layout lists the keys
 * it uses; the inspector only renders those. Empty until a layout needs one.
 */
export const SETTING_CONTROLS = {} satisfies Record< string, ComponentType< SettingControlProps > >;

export type ShippingSettingKey = keyof typeof SETTING_CONTROLS;
