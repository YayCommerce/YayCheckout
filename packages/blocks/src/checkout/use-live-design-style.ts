import { useEffect } from '@wordpress/element';
import { designVars } from '../shared/design-vars';
import type { DesignTokens } from '../shared/editor-data';

const STYLE_ID = 'yaycheckout-layout-design-live';

/** Mirrors DesignStyles::PAGE_SELECTOR and PAGE_PROPERTIES in PHP. */
const PAGE_SELECTOR = 'body.yaycheckout-page, .editor-styles-wrapper';
const PAGE_PROPERTIES: Record< string, string > = {
	'background-color': 'color-background',
	color: 'color-text',
	'font-family': 'font-family',
	'font-size': 'font-size-base',
};

const CANVAS = 'iframe[name="editor-canvas"]';

/** The iframed canvas, or the editor's own document when the canvas is not iframed; null before either exists. */
function canvasDocument(): Document | null {
	const iframe = document.querySelector< HTMLIFrameElement >( CANVAS );

	if ( iframe ) {
		return iframe.contentDocument?.head ? iframe.contentDocument : null;
	}

	return document.querySelector( '.editor-styles-wrapper' ) ? document : null;
}

function css( tokens: DesignTokens ): string {
	const vars = designVars( tokens );
	const root = Object.entries( vars )
		.map( ( [ name, value ] ) => `--yaycheckout-${ name }: ${ value };` )
		.join( '' );
	const page = Object.entries( PAGE_PROPERTIES )
		.filter( ( [ , name ] ) => vars[ name ] !== 'initial' )
		.map( ( [ property, name ] ) => `${ property }: var(--yaycheckout-${ name });` )
		.join( '' );

	return `:root{${ root }}` + ( page ? `${ PAGE_SELECTOR }{${ page }}` : '' );
}

/**
 * Shows the unsaved effective tokens on the canvas: a `<style>` after the saved design's,
 * naming every variable, so it wins over what CheckoutBaseStyles printed. Removed on unmount.
 */
export function useLiveDesignStyle( tokens: DesignTokens | null ): void {
	useEffect( () => {
		if ( ! tokens ) {
			return;
		}

		const text = css( tokens );
		const write = () => {
			const doc = canvasDocument();
			if ( ! doc ) {
				return;
			}
			const style = doc.getElementById( STYLE_ID ) ?? doc.head.appendChild( doc.createElement( 'style' ) );
			style.id = STYLE_ID;
			style.textContent = text;
		};

		// The canvas iframe loads after the editor and reloads when the device preview changes; load does not bubble, so capture it.
		const onLoad = ( event: Event ) => ( event.target as Element ).matches?.( CANVAS ) && write();

		write();
		document.addEventListener( 'load', onLoad, true );

		return () => {
			document.removeEventListener( 'load', onLoad, true );
			canvasDocument()?.getElementById( STYLE_ID )?.remove();
		};
	}, [ tokens ] );
}
