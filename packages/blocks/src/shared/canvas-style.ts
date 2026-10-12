/**
 * A style the editor writes into the canvas as the merchant edits: the Layout design sidebar's design and
 * fonts, and the live value format's missing-field warning.
 */

import { useEffect, useRef } from '@wordpress/element';

/** The canvas iframe's documents. */
function canvasFrames(): Document[] {
	return Array.from( document.querySelectorAll< HTMLIFrameElement >( 'iframe[name="editor-canvas"]' ) )
		.map( ( frame ) => frame.contentDocument )
		.filter( ( doc ): doc is Document => !! doc );
}

/**
 * Keeps the style `styleId` in the canvas equal to `css`. The canvas iframe is rebuilt on a device
 * preview switch or after the code editor, and comes back without the edited style, so it is watched.
 * Null leaves the printed style as it is.
 */
export function useCanvasStyle( css: string | null, styleId: string ): void {
	const latest = useRef( css );
	latest.current = css;

	const apply = () => {
		if ( null === latest.current ) {
			return;
		}

		const frames = canvasFrames();

		// The editor's own document holds the canvas when it isn't iframed. A canvas without the style (WordPress
		// prints none for an empty design) gets its own, after the stylesheets already there.
		( frames.length ? frames : [ document ] ).forEach( ( doc ) => {
			if ( doc.head && ! doc.getElementById( styleId ) ) {
				const created = doc.createElement( 'style' );
				created.id = styleId;
				doc.head.appendChild( created );
			}
		} );

		[ document, ...frames ].forEach( ( doc ) => {
			const style = doc.getElementById( styleId );
			if ( style && style.textContent !== latest.current ) {
				style.textContent = latest.current;
			}
		} );
	};

	useEffect( apply, [ css ] );

	useEffect( () => {
		// Only each canvas head, where the style is printed, so editing blocks doesn't wake it.
		const observer = new MutationObserver( apply );
		const watched = new WeakSet< Document >();

		const watchFrames = () => {
			canvasFrames().forEach( ( doc ) => {
				if ( doc.head && ! watched.has( doc ) ) {
					watched.add( doc );
					observer.observe( doc.head, { childList: true } );
				}
			} );
			apply();
		};

		// A rebuilt canvas iframe loads a new document; load doesn't bubble, so it is caught on the way down.
		document.addEventListener( 'load', watchFrames, true );
		watchFrames();

		return () => {
			observer.disconnect();
			document.removeEventListener( 'load', watchFrames, true );
		};
	}, [] );
}
