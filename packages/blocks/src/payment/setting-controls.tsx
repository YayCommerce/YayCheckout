import { RangeControl } from '@wordpress/components';
import type { ComponentType } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export type PaymentSettingKey =
	| 'gap'
	| 'borderRadius'
	| 'backgroundColor'
	| 'backgroundColorSelected'
	| 'backgroundColorHover';

export const BACKGROUND_SETTING_KEYS: PaymentSettingKey[] = [
	'backgroundColor',
	'backgroundColorSelected',
	'backgroundColorHover',
];

export const BACKGROUND_SETTING_LABELS: Record<
	( typeof BACKGROUND_SETTING_KEYS )[ number ],
	string
> = {
	backgroundColor: __( 'Background', 'yaycheckout' ),
	backgroundColorSelected: __( 'Background (selected)', 'yaycheckout' ),
	backgroundColorHover: __( 'Background (hover)', 'yaycheckout' ),
};

export function isBackgroundSetting( key: PaymentSettingKey ): boolean {
	return ( BACKGROUND_SETTING_KEYS as string[] ).includes( key );
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
 * Range controls for Payment layouts. Background colours use
 * PanelColorSettings in edit.tsx so they match Gutenberg's Styles UI.
 */
export const SETTING_CONTROLS: Partial<
	Record< PaymentSettingKey, ComponentType< SettingControlProps > >
> = {
	gap: GapControl,
	borderRadius: BorderRadiusControl,
};
