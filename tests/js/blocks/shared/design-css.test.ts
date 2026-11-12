import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { describe, expect, test } from 'vitest';
import { DEFAULT_BASE_SIZE, designCss, isGradient } from '../../../../packages/blocks/src/shared/design-css';
import type { DesignTokens } from '../../../../packages/blocks/src/shared/editor-data';
import { phpSource } from '../../helpers/php-const';

const DESIGNS: Record< string, unknown > = {
	empty: {},
	linkFallsBackToPrimary: { colours: { primary: '#ff0000', link_hover: '#00ff00' } },
	gradientPage: { colours: { background: 'linear-gradient(red, blue)', text: '#111111' } },
	underlineInputs: { inputs: { style: 'underline', size: 'medium', radius: 6 } },
	filledUnderline: { inputs: { style: 'underline', background: '#eeeeee', focus_style: 'border' } },
	serifFontAndButtons: {
		typography: { font_family: 'merriweather', base_size: 16 },
		buttons: { hover_text: '#ffffff', font_weight: 'semibold', radius: 0, text_transform: 'uppercase' },
		checkboxes: { size: 18, radius: 0 },
		notices: { error: '#cc0000' },
	},
	systemFont: { typography: { font_family: 'system' }, help_text: { size: 12 } },
};

type PhpResult = {
	fonts: Parameters< typeof designCss >[ 1 ];
	designs: { tokens: DesignTokens; css: string }[];
};

function php(): PhpResult {
	const script = fileURLToPath( new URL( '../../helpers/design-styles.php', import.meta.url ) );

	return JSON.parse( execFileSync( 'php', [ script ], { input: JSON.stringify( Object.values( DESIGNS ) ) } ).toString() );
}

describe( 'design-css @baseline', () => {
	const result = php();

	test.each( Object.keys( DESIGNS ).map( ( name, index ) => [ name, index ] ) )(
		'prints the same CSS as DesignStyles::css() for %s',
		( _name, index ) => {
			const { tokens, css } = result.designs[ index as number ];

			expect( designCss( tokens, result.fonts ) ).toBe( css );
		}
	);

	test( 'an empty design still sets the default base size on the page', () => {
		const css = result.designs[ 0 ].css;

		expect( css ).toContain( `--yaycheckout-font-size-base: ${ DEFAULT_BASE_SIZE }px;` );
		expect( css ).toContain( 'font-size: var(--yaycheckout-font-size-base);' );
		expect( css ).not.toContain( 'link-color' );
		expect( phpSource( 'includes/Renderer/DesignStyles.php' ) ).toContain( `DEFAULT_BASE_SIZE = ${ DEFAULT_BASE_SIZE };` );
	} );

	test( 'a link colour falls back to the primary colour', () => {
		expect( result.designs[ 1 ].css ).toContain( '--yaycheckout-link-color: #ff0000;' );
	} );

	test( 'isGradient tells a gradient from a colour', () => {
		expect( isGradient( 'linear-gradient(red, blue)' ) ).toBe( true );
		expect( isGradient( 'var(--wp--preset--gradient--vivid)' ) ).toBe( true );
		expect( isGradient( '#ff0000' ) ).toBe( false );
		expect( isGradient( null ) ).toBe( false );
	} );
} );
