import { __experimentalGetGapCSSValue as getGapCSSValue, useSettings } from '@wordpress/block-editor';

export interface BlockGapStyle {
	spacing?: {
		blockGap?: unknown;
	};
}

export type BlockGapStyles = BlockGapStyle & Record< string, BlockGapStyle | undefined >;

export interface ViewportSettings {
	mobile?: string;
	tablet?: string;
}

/**
 * Media queries for the viewport style states. Copies WP_Theme_JSON::get_viewport_media_queries()
 * and core's private JS getResponsiveMediaQueries(), without their breakpoint sanitizing.
 */
function viewportMediaQueries( viewport: ViewportSettings | undefined ): [ string, string ][] {
	const { mobile, tablet } = viewport?.mobile || viewport?.tablet ? viewport : { mobile: '480px', tablet: '782px' };
	const queries: [ string, string ][] = [];

	if ( mobile ) {
		queries.push( [ '@mobile', `@media (width <= ${ mobile })` ] );
	}

	if ( tablet ) {
		queries.push( [ '@tablet', mobile ? `@media (${ mobile } < width <= ${ tablet })` : `@media (width <= ${ tablet })` ] );
	}

	return queries;
}

/** A gap value as CSS, or null. Drops what wp_sanitize_block_gap_value() drops. */
function gapCSS( blockGap: unknown ): string | null {
	if ( typeof blockGap === 'string' && /[\\(&=}]|\/\*/.test( blockGap ) ) {
		return null;
	}

	return getGapCSSValue( blockGap );
}

/**
 * The block's own Block spacing and its Tablet / Mobile values, as BlockGap::add_rules() prints
 * them. One stylesheet, since an inline gap would beat the media queries. The Global Styles gap
 * is storefront-only: the editor keeps it behind a private API.
 */
export function blockGapCSS( selector: string, style: BlockGapStyles | undefined, viewport: ViewportSettings | undefined ): string {
	const baseGap = gapCSS( style?.spacing?.blockGap );

	return [
		baseGap ? `${ selector }{gap:${ baseGap }}` : '',
		...viewportMediaQueries( viewport ).map( ( [ state, query ] ) => {
			const viewportGap = gapCSS( style?.[ state ]?.spacing?.blockGap );
			return viewportGap ? `${ query }{${ selector }{gap:${ viewportGap }}}` : '';
		} ),
	].join( '' );
}

/** blockGapCSS() with the editor's viewport setting, for a block's edit(). */
export function useBlockGapCSS( selector: string, style: BlockGapStyles | undefined ): string {
	const [ viewport ] = useSettings( 'viewport' );

	return blockGapCSS( selector, style, viewport );
}
