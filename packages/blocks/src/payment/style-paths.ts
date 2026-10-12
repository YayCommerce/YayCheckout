import type { ColorSettingKey, PaymentSettingKey } from './setting-controls';

type StyleObject = Record< string, unknown >;

/**
 * Gap and idle background sit under core style keys; radius under core's border.
 * The rest sit under `yaycheckout`, because Payment does not enable core background or border.
 */
const PATHS: Record< PaymentSettingKey, string[] > = {
	gap: [ 'spacing', 'blockGap' ],
	borderWidth: [ 'yaycheckout', 'borderWidth' ],
	borderRadius: [ 'border', 'radius' ],
	backgroundColor: [ 'color', 'background' ],
	backgroundColorSelected: [ 'yaycheckout', 'backgroundSelected' ],
	backgroundColorHover: [ 'yaycheckout', 'backgroundHover' ],
	borderColor: [ 'yaycheckout', 'border' ],
	borderColorSelected: [ 'yaycheckout', 'borderSelected' ],
	borderColorHover: [ 'yaycheckout', 'borderHover' ],
	radioColor: [ 'yaycheckout', 'radio' ],
	radioColorSelected: [ 'yaycheckout', 'radioSelected' ],
};

/** Length setting => the variable it sets. Mirrors CheckoutFragments length printing (radius plus PAYMENT_LENGTH_VARS). */
const LENGTH_VARS: Partial< Record< PaymentSettingKey, string > > = {
	borderWidth: '--yaycheckout-payment-border-width',
	borderRadius: '--yaycheckout-payment-radius',
};

/** Colour setting => the variable it sets, in the order PHP prints them. Mirrors CheckoutFragments colour printing. */
const COLOR_VARS: Record< ColorSettingKey, string > = {
	backgroundColor: '--yaycheckout-payment-bg',
	backgroundColorSelected: '--yaycheckout-payment-bg-selected',
	backgroundColorHover: '--yaycheckout-payment-bg-hover',
	radioColor: '--yaycheckout-payment-radio',
	radioColorSelected: '--yaycheckout-payment-radio-selected',
	borderColor: '--yaycheckout-payment-border',
	borderColorSelected: '--yaycheckout-payment-border-selected',
	borderColorHover: '--yaycheckout-payment-border-hover',
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
	const text = cssColor( getAt( style, [ 'color', 'text' ] ), textColor );

	if ( typeof gap === 'string' && gap !== '' ) {
		vars[ '--yaycheckout-payment-gap' ] = gap;
	}
	for ( const [ key, name ] of Object.entries( LENGTH_VARS ) as [ PaymentSettingKey, string ][] ) {
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
