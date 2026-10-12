import { RangeControl } from '@wordpress/components';
import type { ComponentType } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export type ShippingSettingKey =
	| 'gap'
	| 'borderRadius'
	| 'backgroundColor'
	| 'backgroundColorSelected'
	| 'backgroundColorHover'
	| 'borderColor'
	| 'borderColorSelected'
	| 'borderColorHover'
	| 'radioColor'
	| 'radioColorSelected';

/** Colour keys in the order the Methods panel lists them. */
export const COLOR_SETTING_KEYS = [
	'backgroundColor',
	'backgroundColorSelected',
	'backgroundColorHover',
	'borderColor',
	'borderColorSelected',
	'borderColorHover',
	'radioColor',
	'radioColorSelected',
] as const satisfies readonly ShippingSettingKey[];

export type ColorSettingKey = ( typeof COLOR_SETTING_KEYS )[ number ];

export const COLOR_SETTING_LABELS: Record< ColorSettingKey, string > = {
	backgroundColor: __( 'Background', 'yaycheckout' ),
	backgroundColorSelected: __( 'Background (selected)', 'yaycheckout' ),
	backgroundColorHover: __( 'Background (hover)', 'yaycheckout' ),
	borderColor: __( 'Border', 'yaycheckout' ),
	borderColorSelected: __( 'Border (selected)', 'yaycheckout' ),
	borderColorHover: __( 'Border (hover)', 'yaycheckout' ),
	radioColor: __( 'Radio', 'yaycheckout' ),
	radioColorSelected: __( 'Radio (selected)', 'yaycheckout' ),
};

export function isColorSetting( key: ShippingSettingKey ): key is ColorSettingKey {
	return ( COLOR_SETTING_KEYS as readonly string[] ).includes( key );
}

export interface SettingControlProps {
	value: unknown;
	onChange: ( value: unknown ) => void;
}

function pxNumber( value: unknown ): number | undefined {
	if ( typeof value === 'number' && Number.isFinite( value ) ) {
		return value;
	}
	if ( typeof value === 'string' ) {
		const parsed = parseInt( value, 10 );
		return Number.isFinite( parsed ) ? parsed : undefined;
	}
	return undefined;
}

function GapControl( { value, onChange }: SettingControlProps ) {
	return (
		<RangeControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			label={ __( 'Gap', 'yaycheckout' ) }
			value={ pxNumber( value ) }
			onChange={ ( next ) => onChange( next === undefined ? undefined : `${ next }px` ) }
			min={ 0 }
			max={ 48 }
			allowReset
		/>
	);
}

function BorderRadiusControl( { value, onChange }: SettingControlProps ) {
	return (
		<RangeControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			label={ __( 'Border radius', 'yaycheckout' ) }
			value={ pxNumber( value ) }
			onChange={ ( next ) => onChange( next === undefined ? undefined : `${ next }px` ) }
			min={ 0 }
			max={ 32 }
			allowReset
		/>
	);
}

/**
 * Range controls for Shipping Methods layouts. Colours use
 * PanelColorSettings in edit.tsx so they match Gutenberg's Styles UI.
 */
export const SETTING_CONTROLS: Partial<
	Record< ShippingSettingKey, ComponentType< SettingControlProps > >
> = {
	gap: GapControl,
	borderRadius: BorderRadiusControl,
};
