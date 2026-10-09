import type { ShippingSettingKey } from './setting-controls';

type StyleObject = Record< string, unknown >;

const PATHS: Record< ShippingSettingKey, string[] > = {
	gap: [ 'spacing', 'blockGap' ],
	borderRadius: [ 'border', 'radius' ],
	backgroundColor: [ 'color', 'background' ],
	backgroundColorSelected: [ 'yaycheckout', 'backgroundSelected' ],
	backgroundColorHover: [ 'yaycheckout', 'backgroundHover' ],
};

function isPlainObject( value: unknown ): value is StyleObject {
	return typeof value === 'object' && value !== null && ! Array.isArray( value );
}

function getAt( style: StyleObject | undefined, path: string[] ): unknown {
	let cursor: unknown = style;

	for ( const key of path ) {
		if ( ! isPlainObject( cursor ) ) {
			return undefined;
		}
		cursor = cursor[ key ];
	}

	return cursor;
}

/** Drop empty nested objects so Copy/Paste styles stays tidy. */
function setAt( style: StyleObject | undefined, path: string[], value: unknown ): StyleObject | undefined {
	if ( path.length === 0 ) {
		return style;
	}

	const [ head, ...rest ] = path;
	const current = isPlainObject( style ) ? { ...style } : {};

	if ( rest.length === 0 ) {
		if ( value === undefined || value === null || value === '' ) {
			delete current[ head ];
		} else {
			current[ head ] = value;
		}
	} else {
		const nested = setAt( isPlainObject( current[ head ] ) ? ( current[ head ] as StyleObject ) : undefined, rest, value );
		if ( nested === undefined || Object.keys( nested ).length === 0 ) {
			delete current[ head ];
		} else {
			current[ head ] = nested;
		}
	}

	return Object.keys( current ).length === 0 ? undefined : current;
}

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

/** CSS custom properties for the shipping-methods wrapper from `style`. */
export function shippingStyleVars( style: StyleObject | undefined ): Record< string, string > {
	const vars: Record< string, string > = {};
	const gap = getStyleSetting( style, 'gap' );
	const radius = getStyleSetting( style, 'borderRadius' );
	const bg = getStyleSetting( style, 'backgroundColor' );
	const bgSelected = getStyleSetting( style, 'backgroundColorSelected' );
	const bgHover = getStyleSetting( style, 'backgroundColorHover' );

	if ( typeof gap === 'string' && gap !== '' ) {
		vars[ '--yaycheckout-shipping-gap' ] = gap;
	}
	if ( typeof radius === 'string' && radius !== '' ) {
		vars[ '--yaycheckout-shipping-radius' ] = radius;
	} else if ( typeof radius === 'number' && Number.isFinite( radius ) ) {
		vars[ '--yaycheckout-shipping-radius' ] = `${ radius }px`;
	}
	if ( typeof bg === 'string' && bg !== '' ) {
		vars[ '--yaycheckout-shipping-bg' ] = bg;
	}
	if ( typeof bgSelected === 'string' && bgSelected !== '' ) {
		vars[ '--yaycheckout-shipping-bg-selected' ] = bgSelected;
	}
	if ( typeof bgHover === 'string' && bgHover !== '' ) {
		vars[ '--yaycheckout-shipping-bg-hover' ] = bgHover;
	}

	return vars;
}
