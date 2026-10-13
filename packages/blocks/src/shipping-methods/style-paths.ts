import type { ColorSettingKey, ShippingSettingKey } from './setting-controls';

import { getAt, setAt, type StyleObject } from '../shared/style-path';

/** Row radius and background sit under `yaycheckout`, because core's border and background style the wrapper. */
const PATHS: Record< ShippingSettingKey, string[] > = {
	gap: [ 'spacing', 'blockGap' ],
	borderWidth: [ 'yaycheckout', 'borderWidth' ],
	borderRadius: [ 'yaycheckout', 'radius' ],
	backgroundColor: [ 'yaycheckout', 'background' ],
	backgroundColorSelected: [ 'yaycheckout', 'backgroundSelected' ],
	backgroundColorHover: [ 'yaycheckout', 'backgroundHover' ],
	borderColor: [ 'yaycheckout', 'border' ],
	borderColorSelected: [ 'yaycheckout', 'borderSelected' ],
	borderColorHover: [ 'yaycheckout', 'borderHover' ],
	radioColor: [ 'yaycheckout', 'radio' ],
	radioColorSelected: [ 'yaycheckout', 'radioSelected' ],
};

/** Length setting => the variable it sets. Mirrors CheckoutFragments::SHIPPING_LENGTH_VARS. */
const LENGTH_VARS: Partial< Record< ShippingSettingKey, string > > = {
	borderWidth: '--yaycheckout-shipping-border-width',
	borderRadius: '--yaycheckout-shipping-radius',
};

/** Colour setting => the variable it sets, in the order PHP prints them. Mirrors CheckoutFragments::SHIPPING_COLOR_VARS. */
const COLOR_VARS: Record< ColorSettingKey, string > = {
	backgroundColor: '--yaycheckout-shipping-bg',
	backgroundColorSelected: '--yaycheckout-shipping-bg-selected',
	backgroundColorHover: '--yaycheckout-shipping-bg-hover',
	radioColor: '--yaycheckout-shipping-radio',
	radioColorSelected: '--yaycheckout-shipping-radio-selected',
	borderColor: '--yaycheckout-shipping-border',
	borderColorSelected: '--yaycheckout-shipping-border-selected',
	borderColorHover: '--yaycheckout-shipping-border-hover',
};

/** Read a Methods setting from the block's `style` attribute. */
export function getStyleSetting( style: StyleObject | undefined, key: ShippingSettingKey ): unknown {
	return getAt( style, PATHS[ key ] );
}

/** Merge a Methods setting into `style` without wiping sibling keys. */
export function setStyleSetting(
	style: StyleObject | undefined,
	key: ShippingSettingKey,
	value: unknown
): StyleObject | undefined {
	return setAt( style, PATHS[ key ], value );
}

/** Resolve a colour value or theme preset slug to a CSS colour. */
function cssColor( value: unknown, presetSlug?: string ): string | undefined {
	if ( typeof value === 'string' && value !== '' ) {
		if ( value.startsWith( 'var:preset|color|' ) ) {
			const slug = value.slice( 'var:preset|color|'.length );
			return slug ? `var(--wp--preset--color--${ slug })` : undefined;
		}
		return value;
	}
	if ( typeof presetSlug === 'string' && presetSlug !== '' ) {
		return `var(--wp--preset--color--${ presetSlug })`;
	}
	return undefined;
}

/**
 * Inline styles for the shipping-methods wrapper: Methods CSS variables plus
 * text colour from color.text / textColor (skipSerialization keeps core from
 * painting the wrapper; we apply colour here so it still shows).
 */
export function shippingStyleVars(
	style: StyleObject | undefined,
	textColor?: string
): Record< string, string > {
	const vars: Record< string, string > = {};
	const gap = getStyleSetting( style, 'gap' );
	const text = cssColor( getAt( style, [ 'color', 'text' ] ), textColor );

	if ( typeof gap === 'string' && gap !== '' ) {
		vars[ '--yaycheckout-shipping-gap' ] = gap;
	}
	for ( const [ key, name ] of Object.entries( LENGTH_VARS ) as [ ShippingSettingKey, string ][] ) {
		const length = getStyleSetting( style, key );
		if ( typeof length === 'string' && length !== '' ) {
			vars[ name ] = length;
		} else if ( typeof length === 'number' && Number.isFinite( length ) ) {
			vars[ name ] = `${ length }px`;
		}
	}
	for ( const [ key, name ] of Object.entries( COLOR_VARS ) as [ ColorSettingKey, string ][] ) {
		const value = cssColor( getStyleSetting( style, key ) );
		if ( value ) {
			vars[ name ] = value;
		}
	}
	if ( text ) {
		vars.color = text;
	}

	return vars;
}
