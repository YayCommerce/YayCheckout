import type { DesignTokens } from './editor-data';

/** Mirrors DesignStyles::FONT_FALLBACK in PHP. */
const FONT_FALLBACK = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';

/** Mirrors DesignStyles::PAGE_SELECTOR in PHP. */
const PAGE_SELECTOR = 'body.yaycheckout-page, .editor-styles-wrapper';

/** Mirrors DesignStyles::PAGE_PROPERTIES in PHP, in its order. */
const PAGE_PROPERTIES: [ string, string ][] = [
	[ 'background-color', 'color-background' ],
	[ 'background-image', 'background-gradient' ],
	[ 'color', 'color-text' ],
	[ 'font-family', 'font-family' ],
	[ 'font-size', 'font-size-base' ],
];

/** Mirrors Design::is_gradient() in PHP for a fill that already passed it or the hex rule: a gradient is anything but #rrggbb. */
export function isGradient( fill: unknown ): boolean {
	return 'string' === typeof fill && ! fill.startsWith( '#' );
}

/** Mirrors DesignStyles::INPUT_HEIGHTS in PHP. */
const INPUT_HEIGHTS: Record< string, number > = { small: 32, medium: 40, large: 48 };

/** Mirrors DesignStyles::vars() in PHP: custom property values by name, for the sections that are on. */
function vars( tokens: DesignTokens, fontFamilies: Record< string, string > ): [ string, string ][] {
	const { colours, typography, inputs } = tokens;
	const out: [ string, string ][] = [];

	if ( colours.enabled ) {
		out.push(
			[ 'color-primary', String( colours.primary ) ],
			[ 'color-text', String( colours.text ) ],
			[ isGradient( colours.background ) ? 'background-gradient' : 'color-background', String( colours.background ) ],
			[ 'color-border', String( colours.border ) ]
		);
	}

	if ( typography.enabled ) {
		if ( '' !== typography.font_family ) {
			out.push( [ 'font-family', `"${ fontFamilies[ String( typography.font_family ) ] }", ${ FONT_FALLBACK }` ] );
		}

		out.push( [ 'font-size-base', `${ typography.base_size }px` ] );
	}

	if ( inputs.enabled ) {
		const isUnderline = 'underline' === inputs.style;

		out.push(
			[ 'input-radius', isUnderline ? '0px' : `${ inputs.radius }px` ],
			[ 'input-height', `${ INPUT_HEIGHTS[ String( inputs.size ) ] }px` ],
			[ 'input-border-width', isUnderline ? '0 0 1px' : '1px' ],
			[ 'input-padding-inline', isUnderline ? '0' : '12px' ]
		);
	}

	return out;
}

/** Mirrors DesignStyles::css() in PHP, without custom CSS: the same text for the same tokens. */
export function designCss( tokens: DesignTokens, fontFamilies: Record< string, string > ): string {
	const values = vars( tokens, fontFamilies );
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

	return css;
}
