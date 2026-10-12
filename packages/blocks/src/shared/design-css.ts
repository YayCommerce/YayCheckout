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

/** Mirrors DesignStyles::INPUT_HEIGHTS in PHP. */
const INPUT_HEIGHTS: Record< string, number > = { small: 32, medium: 40, large: 48 };

/** Mirrors DesignStyles::vars() in PHP: custom property values by name, for the tokens that aren't empty. */
function vars( tokens: DesignTokens, { fontFamilies, serifFonts }: DesignFonts ): [ string, string ][] {
	const { colours, typography, inputs } = tokens;
	const background = String( colours.background ?? '' );
	const pairs: [ string, string ][] = [
		[ 'color-primary', String( colours.primary ?? '' ) ],
		[ 'color-text', String( colours.text ?? '' ) ],
		[ isGradient( background ) ? 'background-gradient' : 'color-background', background ],
		[ 'color-border', String( colours.border ?? '' ) ],
	];
	const out = pairs.filter( ( [ , value ] ) => '' !== value );

	const font = String( typography.font_family ?? '' );

	// PHP never sees a slug outside the list: LayoutDesign::from_meta() empties it.
	if ( '' !== font && fontFamilies[ font ] ) {
		const fallback = serifFonts.includes( font ) ? SERIF_FONT_FALLBACK : FONT_FALLBACK;
		out.push( [ 'font-family', `"${ fontFamilies[ font ] }", ${ fallback }` ] );
	}

	if ( null !== typography.base_size ) {
		out.push( [ 'font-size-base', `${ typography.base_size }px` ] );
	}

	const isUnderline = 'underline' === inputs.style;

	if ( isUnderline ) {
		out.push( [ 'input-radius', '0px' ] );
	} else if ( null !== inputs.radius ) {
		out.push( [ 'input-radius', `${ inputs.radius }px` ] );
	}

	if ( '' !== inputs.size ) {
		out.push( [ 'input-height', `${ INPUT_HEIGHTS[ String( inputs.size ) ] }px` ] );
	}

	if ( '' !== inputs.style ) {
		out.push(
			[ 'input-border-width', isUnderline ? '0 0 1px' : '1px' ],
			[ 'input-padding-inline', isUnderline ? '0' : '12px' ]
		);
	}

	return out;
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
