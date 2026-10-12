/** Mirrors ButtonArrow::glyph() in PHP (includes/Renderer/ButtonArrow.php), and the blocks' block.json `arrow` enum. */

import { __, sprintf } from '@wordpress/i18n';

export type Arrow = 'none' | 'arrow' | 'chevron';

export type ArrowDirection = 'back' | 'forward';

const GLYPHS: Record< ArrowDirection, Record< Arrow, string > > = {
	back: { none: '', arrow: '←', chevron: '‹' },
	forward: { none: '', arrow: '→', chevron: '›' },
};

/** The glyph for `style` pointing `direction`; empty for `none` or an unknown style. */
export function arrowGlyph( style: Arrow, direction: ArrowDirection ): string {
	return GLYPHS[ direction ][ style ] ?? '';
}

/** Options for an "Icon" select, each labelled with the glyph it prints. */
export function arrowOptions( direction: ArrowDirection ): { label: string; value: Arrow }[] {
	const glyphs = GLYPHS[ direction ];

	return [
		{ label: __( 'None', 'yaycheckout' ), value: 'none' },
		/* translators: %s: the arrow glyph, such as ←. */
		{ label: sprintf( __( 'Arrow (%s)', 'yaycheckout' ), glyphs.arrow ), value: 'arrow' },
		/* translators: %s: the chevron glyph, such as ‹. */
		{ label: sprintf( __( 'Chevron (%s)', 'yaycheckout' ), glyphs.chevron ), value: 'chevron' },
	];
}
