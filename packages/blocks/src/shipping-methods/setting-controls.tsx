import { ColorPalette, RangeControl } from '@wordpress/components';
import type { ComponentType } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

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

function ColorControl( {
	label,
	value,
	onChange,
}: SettingControlProps & { label: string } ) {
	const color = typeof value === 'string' ? value : undefined;

	return (
		<div className="yaycheckout-shipping-methods__color-control">
			<p className="yaycheckout-shipping-methods__color-label">{ label }</p>
			<ColorPalette
				value={ color }
				onChange={ ( next ) => onChange( next || undefined ) }
				clearable
			/>
		</div>
	);
}

function BackgroundColorControl( props: SettingControlProps ) {
	return <ColorControl { ...props } label={ __( 'Background', 'yaycheckout' ) } />;
}

function BackgroundColorSelectedControl( props: SettingControlProps ) {
	return <ColorControl { ...props } label={ __( 'Background (selected)', 'yaycheckout' ) } />;
}

function BackgroundColorHoverControl( props: SettingControlProps ) {
	return <ColorControl { ...props } label={ __( 'Background (hover)', 'yaycheckout' ) } />;
}

/**
 * Shared setting controls for Shipping Methods layouts. A layout lists the keys
 * it uses; the Styles tab only renders those.
 */
export const SETTING_CONTROLS: Record< ShippingSettingKey, ComponentType< SettingControlProps > > = {
	gap: GapControl,
	borderRadius: BorderRadiusControl,
	backgroundColor: BackgroundColorControl,
	backgroundColorSelected: BackgroundColorSelectedControl,
	backgroundColorHover: BackgroundColorHoverControl,
};
