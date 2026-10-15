import { useEffect, useState } from '@wordpress/element';

/** Where the step's target is, in the editor window's coordinates. */
export type TargetRect = DOMRect | null;

/** The canvas: blocks render in this iframe, so a block's place is offset by the iframe's. */
const CANVAS_FRAME = 'iframe[name="editor-canvas"]';

/** The first match of the selectors in the document that is drawn, and where it is in the editor window. */
function find( doc: Document, selectors: string[], offset: DOMRect | null ): DOMRect | null {
	for ( const selector of selectors ) {
		for ( const element of Array.from( doc.querySelectorAll( selector ) ) ) {
			const rect = element.getBoundingClientRect();
			if ( rect.width > 0 && rect.height > 0 ) {
				return offset ? new DOMRect( rect.x + offset.x, rect.y + offset.y, rect.width, rect.height ) : rect;
			}
		}
	}

	return null;
}

/** A canvas selector's match, then a match of the selectors in the editor. */
function locate( selectors: string[], canvasSelectors: string[] ): DOMRect | null {
	const frame = document.querySelector< HTMLIFrameElement >( CANVAS_FRAME );
	const canvas = frame?.contentDocument;
	const inCanvas = frame && canvas ? find( canvas, canvasSelectors, frame.getBoundingClientRect() ) : null;

	return inCanvas ?? find( document, selectors, null );
}

function same( a: TargetRect, b: TargetRect ): boolean {
	return a === b || ( !! a && !! b && a.x === b.x && a.y === b.y && a.width === b.width && a.height === b.height );
}

/**
 * Where the step's target is, followed every frame: the target can mount late (a sidebar opening), move (the
 * canvas or sidebar scrolls, the window resizes) or go away, and the editor gives no event for all of these.
 */
export function useTargetRect( selectors: string[] = [], canvasSelectors: string[] = [] ): TargetRect {
	const [ rect, setRect ] = useState< TargetRect >( null );

	useEffect( () => {
		if ( ! selectors.length && ! canvasSelectors.length ) {
			return;
		}

		let frame = 0;
		let last: TargetRect = null;

		const tick = () => {
			const next = locate( selectors, canvasSelectors );

			if ( ! same( last, next ) ) {
				last = next;
				setRect( next );
			}

			frame = window.requestAnimationFrame( tick );
		};

		tick();

		return () => window.cancelAnimationFrame( frame );
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps -- Step is keyed by the step, so the selectors never change.

	return rect;
}
