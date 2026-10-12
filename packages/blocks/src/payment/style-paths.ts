import type { PaymentSettingKey } from './setting-controls';

type StyleObject = Record< string, unknown >;

const PATHS: Record< PaymentSettingKey, string[] > = {
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
export function getStyleSetting( style: StyleObject | undefined, key: PaymentSettingKey ): unknown {
	return getAt( style, PATHS[ key ] );
}

/** Merge a Methods setting into `style` without wiping sibling keys. */
export function setStyleSetting(
	style: StyleObject | undefined,
	key: PaymentSettingKey,
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
 * Inline styles for the payment wrapper: Methods CSS variables plus
 * text colour from color.text / textColor (skipSerialization keeps core from
 * painting the wrapper; we apply colour here so it still shows).
 */
export function paymentStyleVars(
	style: StyleObject | undefined,
	textColor?: string
): Record< string, string > {
	const vars: Record< string, string > = {};
	const gap = getStyleSetting( style, 'gap' );
	const radius = getStyleSetting( style, 'borderRadius' );
	const bg = getStyleSetting( style, 'backgroundColor' );
	const bgSelected = getStyleSetting( style, 'backgroundColorSelected' );
	const bgHover = getStyleSetting( style, 'backgroundColorHover' );
	const text = cssColor( getAt( style, [ 'color', 'text' ] ), textColor );

	if ( typeof gap === 'string' && gap !== '' ) {
		vars[ '--yaycheckout-payment-gap' ] = gap;
	}
	if ( typeof radius === 'string' && radius !== '' ) {
		vars[ '--yaycheckout-payment-radius' ] = radius;
	} else if ( typeof radius === 'number' && Number.isFinite( radius ) ) {
		vars[ '--yaycheckout-payment-radius' ] = `${ radius }px`;
	}
	if ( typeof bg === 'string' && bg !== '' ) {
		vars[ '--yaycheckout-payment-bg' ] = bg;
	}
	if ( typeof bgSelected === 'string' && bgSelected !== '' ) {
		vars[ '--yaycheckout-payment-bg-selected' ] = bgSelected;
	}
	if ( typeof bgHover === 'string' && bgHover !== '' ) {
		vars[ '--yaycheckout-payment-bg-hover' ] = bgHover;
	}
	if ( text ) {
		vars.color = text;
	}

	return vars;
}
