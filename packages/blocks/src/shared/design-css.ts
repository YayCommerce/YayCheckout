import type { DesignTokens } from './editor-data';

/** Mirrors DesignStyles::FONT_FALLBACK in PHP. */
const FONT_FALLBACK = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';

/** Mirrors DesignStyles::SERIF_FONT_FALLBACK in PHP. */
const SERIF_FONT_FALLBACK = 'Georgia, "Times New Roman", serif';

/** Mirrors DesignStyles::PAGE_SELECTOR in PHP. */
const PAGE_SELECTOR = 'body.yaycheckout-page, .editor-styles-wrapper';

/** Mirrors DesignStyles::HEADING_SELECTOR in PHP. */
const HEADING_SELECTOR =
	'body.yaycheckout-page :is(h1, h2, h3, h4, h5, h6):not([class*="-font-family"]), .editor-styles-wrapper :is(h1, h2, h3, h4, h5, h6):not([class*="-font-family"])';

/** The font lists DesignStyles reads from LayoutDesign in PHP. */
export type DesignFonts = {
	/** Slug => CSS font-family name; '' is the theme's font. */
	fontFamilies: Record< string, string >;
	/** The slugs whose fallback is a serif stack. */
	serifFonts: string[];
};

/** Mirrors DesignStyles::PAGE_PROPERTIES in PHP, in its order. */
const PAGE_PROPERTIES: [ string, string ][] = [
	[ 'background-color', 'color-background' ],
	[ 'background-image', 'background-gradient' ],
	[ 'color', 'color-text' ],
	[ 'font-family', 'font-family' ],
	[ 'font-size', 'font-size-base' ],
];

/**
 * Mirrors LayoutDesign::is_gradient() in PHP for a value that already passed the `fill` rule: a gradient is written
 * out (`linear-gradient(…)` and the like) or a theme gradient preset.
 */
export function isGradient( fill: unknown ): boolean {
	return 'string' === typeof fill && ( fill.includes( '-gradient(' ) || fill.startsWith( 'var(--wp--preset--gradient--' ) );
}

/** Mirrors DesignStyles::DEFAULT_BASE_SIZE in PHP: the page's font size in px while typography.base_size is empty. */
export const DEFAULT_BASE_SIZE = 16;

/** Mirrors DesignStyles::INPUT_SIZES in PHP: the height, then the height with floating labels. */
const INPUT_SIZES: Record< string, [ string, string ] > = {
	small: [ '2.5em', '3em' ],
	medium: [ '2.75em', '3.25em' ],
	large: [ '3em', '3.5em' ],
};

/** Mirrors DesignStyles::vars() in PHP: custom property values by name, for the tokens that aren't empty, and always the font size. */
function vars( tokens: DesignTokens, { fontFamilies, serifFonts }: DesignFonts ): [ string, string ][] {
	const { colours, typography, inputs } = tokens;
	const background = String( colours.background ?? '' );
	const pairs: [ string, string ][] = [
		[ 'color-primary', String( colours.primary ?? '' ) ],
		[ 'color-text', String( colours.text ?? '' ) ],
		[ isGradient( background ) ? 'background-gradient' : 'color-background', background ],
		[ 'color-border', String( colours.border ?? '' ) ],
	];
	const notEmpty = ( [ , value ]: [ string, string ] ) => '' !== value;
	const out = pairs.filter( notEmpty );

	const font = String( typography.font_family ?? '' );

	// PHP never sees a slug outside the list: LayoutDesign::from_meta() empties it.
	if ( '' !== font && fontFamilies[ font ] ) {
		const fallback = serifFonts.includes( font ) ? SERIF_FONT_FALLBACK : FONT_FALLBACK;
		out.push( [ 'font-family', `"${ fontFamilies[ font ] }", ${ fallback }` ] );
	}

	out.push( [ 'font-size-base', `${ typography.base_size ?? DEFAULT_BASE_SIZE }px` ] );

	const isUnderline = 'underline' === inputs.style;

	if ( isUnderline ) {
		out.push( [ 'input-radius', '0px' ] );
	} else if ( null !== inputs.radius ) {
		out.push( [ 'input-radius', `${ inputs.radius }px` ] );
	}

	// The box is the chosen height: no vertical padding for the text to push past it.
	if ( '' !== inputs.size ) {
		const [ height, floatingHeight ] = INPUT_SIZES[ String( inputs.size ) ];
		out.push( [ 'input-height', height ], [ 'input-floating-height', floatingHeight ], [ 'input-padding-block', '0' ] );
	}

	const inputBackground = String( inputs.background ?? '' );

	if ( '' !== inputs.style ) {
		out.push(
			[ 'input-border-width', isUnderline ? '0 0 1px' : '1px' ],
			// An underline input with a background is a filled box, so its text keeps the side padding.
			[ 'input-padding-inline', isUnderline && '' === inputBackground ? '0' : '12px' ]
		);
	}

	const inputColours: [ string, string ][] = [
		[ 'input-background', inputBackground ],
		[ 'input-color', String( inputs.text ?? '' ) ],
		[ 'input-border-color', String( inputs.border ?? '' ) ],
	];

	return [ ...out, ...inputColours.filter( notEmpty ) ];
}

/** Mirrors DesignStyles::css() in PHP, without custom CSS: the same text for the same tokens. */
export function designCss( tokens: DesignTokens, fonts: DesignFonts ): string {
	const values = vars( tokens, fonts );
	const names = values.map( ( [ name ] ) => name );
	const page = PAGE_PROPERTIES.filter( ( [ , name ] ) => names.includes( name ) );
	let css = '';

	if ( values.length ) {
		css = ':root {\n';
		values.forEach( ( [ name, value ] ) => {
			css += `\t--yaycheckout-${ name }: ${ value };\n`;
		} );
		css += '}\n';
	}

	if ( page.length ) {
		css += `${ PAGE_SELECTOR } {\n`;
		page.forEach( ( [ property, name ] ) => {
			css += `\t${ property }: var(--yaycheckout-${ name });\n`;
		} );
		css += '}\n';
	}

	if ( names.includes( 'font-family' ) ) {
		css += `${ HEADING_SELECTOR } {\n\tfont-family: inherit;\n}\n`;
	}

	return css;
}
