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

/** Mirrors DesignStyles::LINK_SELECTOR in PHP. */
const LINK_SELECTOR =
	'body.yaycheckout-page :is(p, h1, h2, h3, h4, h5, h6, .wp-block-yaycheckout-terms) a:not(.has-link-color a, .wp-element-button), .editor-styles-wrapper :is(p, h1, h2, h3, h4, h5, h6, .wp-block-yaycheckout-terms) a:not(.has-link-color a, .wp-element-button)';

/** Mirrors DesignStyles::LINK_HOVER_SELECTOR in PHP. */
const LINK_HOVER_SELECTOR =
	'body.yaycheckout-page :is(p, h1, h2, h3, h4, h5, h6, .wp-block-yaycheckout-terms) a:not(.has-link-color a, .wp-element-button):is(:hover, :focus), .editor-styles-wrapper :is(p, h1, h2, h3, h4, h5, h6, .wp-block-yaycheckout-terms) a:not(.has-link-color a, .wp-element-button):is(:hover, :focus)';

/** The font lists DesignStyles reads from LayoutDesign in PHP. */
export type DesignFonts = {
	/** Slug => CSS font-family name; '' is the theme's font. */
	fontFamilies: Record< string, string >;
	/** The slugs whose fallback is a serif stack. */
	serifFonts: string[];
	/** The slugs the device already has => the stack they print, with no fallback added. */
	fontStacks: Record< string, string >;
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
export const DEFAULT_BASE_SIZE = 13;

/** Mirrors DesignStyles::INPUT_SIZES in PHP: the height, then the height with floating labels. */
const INPUT_SIZES: Record< string, [ string, string ] > = {
	small: [ '2.5em', '3em' ],
	medium: [ '2.75em', '3.25em' ],
	large: [ '3em', '3.5em' ],
};

/** Mirrors DesignStyles::BUTTON_FONT_WEIGHTS in PHP. */
const BUTTON_FONT_WEIGHTS: Record< string, string > = {
	normal: '400',
	medium: '500',
	semibold: '600',
	bold: '700',
};

/** Mirrors DesignStyles::vars() in PHP: custom property values by name, for the tokens that aren't empty, and always the font size. */
function vars( tokens: DesignTokens, { fontFamilies, serifFonts, fontStacks }: DesignFonts ): [ string, string ][] {
	const { colours, typography, inputs, checkboxes, radios, buttons } = tokens;
	const background = String( colours.background ?? '' );
	const pairs: [ string, string ][] = [
		[ 'color-primary', String( colours.primary ?? '' ) ],
		[ 'color-text', String( colours.text ?? '' ) ],
		[ isGradient( background ) ? 'background-gradient' : 'color-background', background ],
		[ 'color-border', String( colours.border ?? '' ) ],
		[ 'color-muted', String( colours.muted ?? '' ) ],
	];
	const notEmpty = ( [ , value ]: [ string, string ] ) => '' !== value;
	const out = pairs.filter( notEmpty );

	const font = String( typography.font_family ?? '' );

	// PHP never sees a slug outside the list: LayoutDesign::from_meta() empties it.
	if ( fontStacks[ font ] ) {
		out.push( [ 'font-family', fontStacks[ font ] ] );
	} else if ( '' !== font && fontFamilies[ font ] ) {
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
	const inputFocusBackground = String( inputs.focus_background ?? '' );

	if ( '' !== inputs.style ) {
		// An underline input with a background, or one on focus, is a filled box, so its text keeps the side padding.
		const isFilled = '' !== inputBackground || '' !== inputFocusBackground;

		out.push(
			[ 'input-border-width', isUnderline ? '0 0 1px' : '1px' ],
			[ 'input-padding-inline', isUnderline && ! isFilled ? '0' : '12px' ]
		);
	}

	const inputColours: [ string, string ][] = [
		[ 'input-background', inputBackground ],
		[ 'input-color', String( inputs.text_color ?? '' ) ],
		[ 'input-label-color', String( inputs.label_color ?? '' ) ],
		[ 'input-border-color', String( inputs.border ?? '' ) ],
		[ 'input-focus-color', String( inputs.focus_border ?? '' ) ],
		[ 'input-focus-background', inputFocusBackground ],
	];

	out.push( ...inputColours.filter( notEmpty ) );

	// Thin border drops the flush ring, so focus just recolours the border.
	if ( 'border' === inputs.focus_style ) {
		out.push( [ 'input-focus-ring-width', '0' ] );
	}

	// A radius of 0 is a square box, not an empty token.
	if ( null !== checkboxes.size ) {
		out.push( [ 'checkbox-size', `${ checkboxes.size }px` ] );
	}

	if ( null !== checkboxes.radius ) {
		out.push( [ 'checkbox-radius', `${ checkboxes.radius }px` ] );
	}

	if ( null !== checkboxes.border_width ) {
		out.push( [ 'checkbox-border-width', `${ checkboxes.border_width }px` ] );
	}

	const checkboxColours: [ string, string ][] = [
		[ 'checkbox-border-color', String( checkboxes.border ?? '' ) ],
		[ 'checkbox-background', String( checkboxes.background ?? '' ) ],
		[ 'checkbox-checked-color', String( checkboxes.checked ?? '' ) ],
	];

	out.push( ...checkboxColours.filter( notEmpty ) );

	if ( null !== radios.size ) {
		out.push( [ 'radio-size', `${ radios.size }px` ] );
	}

	if ( null !== radios.border_width ) {
		out.push( [ 'radio-border-width', `${ radios.border_width }px` ] );
	}

	const radioColours: [ string, string ][] = [
		[ 'radio-border-color', String( radios.border ?? '' ) ],
		[ 'radio-background', String( radios.background ?? '' ) ],
		[ 'radio-checked-color', String( radios.checked ?? '' ) ],
	];

	out.push( ...radioColours.filter( notEmpty ) );

	const linkColours: [ string, string ][] = [
		[ 'link-color', String( colours.link ?? '' ) ],
		[ 'link-hover-color', String( colours.link_hover ?? '' ) ],
	];

	out.push( ...linkColours.filter( notEmpty ) );

	if ( null !== tokens.help_text.size ) {
		out.push( [ 'help-text-size', `${ tokens.help_text.size }px` ] );
	}

	const buttonHoverBackground = String( buttons.hover_background ?? '' );
	const buttonHoverText = String( buttons.hover_text ?? '' );
	const buttonColours: [ string, string ][] = [
		[ 'button-background', String( buttons.background ?? '' ) ],
		[ 'button-color', String( buttons.text ?? '' ) ],
		[ 'button-hover-background', buttonHoverBackground ],
		[ 'button-hover-color', buttonHoverText ],
	];

	out.push( ...buttonColours.filter( notEmpty ) );

	// A hover colour of its own replaces the darkened default.
	if ( '' !== buttonHoverBackground || '' !== buttonHoverText ) {
		out.push( [ 'button-hover-filter', 'none' ] );
	}

	if ( null !== buttons.radius ) {
		out.push( [ 'button-radius', `${ buttons.radius }px` ] );
	}

	if ( '' !== buttons.font_weight ) {
		out.push( [ 'button-font-weight', BUTTON_FONT_WEIGHTS[ String( buttons.font_weight ) ] ] );
	}

	if ( '' !== buttons.text_transform ) {
		out.push( [ 'button-text-transform', String( buttons.text_transform ) ] );
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

	if ( names.includes( 'link-color' ) ) {
		css += `${ LINK_SELECTOR } {\n\tcolor: var(--yaycheckout-link-color);\n}\n`;
	}

	// An empty hover colour keeps the link colour, so the theme's hover colour doesn't show either.
	if ( names.includes( 'link-color' ) || names.includes( 'link-hover-color' ) ) {
		css += `${ LINK_HOVER_SELECTOR } {\n\tcolor: var(--yaycheckout-link-hover-color, var(--yaycheckout-link-color));\n}\n`;
	}

	return css;
}
