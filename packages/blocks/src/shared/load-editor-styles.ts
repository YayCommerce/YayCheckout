/** The JSON object that starts at `start` in `text`, read up to its matching brace; null when there is none. */
export function jsonObjectAt( text: string, start: number ): Record< string, unknown > | null {
	let depth = 0;
	let inString = false;

	for ( let i = start; i < text.length; i++ ) {
		const char = text[ i ];

		if ( inString ) {
			if ( char === '\\' ) {
				i++;
			} else if ( char === '"' ) {
				inString = false;
			}
		} else if ( char === '"' ) {
			inString = true;
		} else if ( char === '{' ) {
			depth++;
		} else if ( char === '}' && --depth === 0 ) {
			try {
				return JSON.parse( text.slice( start, i + 1 ) );
			} catch {
				return null;
			}
		}
	}

	return null;
}

/** Waits for an appended stylesheet to load or fail. */
function settle( element: HTMLLinkElement ): Promise< void > {
	return new Promise( ( resolve ) => {
		element.addEventListener( 'load', () => resolve(), { once: true } );
		element.addEventListener( 'error', () => resolve(), { once: true } );
	} );
}

/** A copy of a stylesheet parsed by DOMParser, to append to a live document. */
function liveCopy( element: Element ): HTMLLinkElement | HTMLStyleElement {
	const copy = document.createElement( element.tagName.toLowerCase() ) as HTMLLinkElement | HTMLStyleElement;

	for ( const { name, value } of Array.from( element.attributes ) ) {
		copy.setAttribute( name, value );
	}

	copy.textContent = element.textContent;

	return copy;
}

/**
 * Loads the styles a fresh load of `url` (this editor) would print and this page doesn't have yet, into the page
 * and the canvas iframe, so a plugin activated since the page loaded styles its spots' previews (YayBoost's
 * assets/css/yayboost.css) without a reload. Resolves with the fetched page, for callers that read its inline data.
 */
export async function loadEditorStyles( url: string ): Promise< Document > {
	const response = await fetch( url, { credentials: 'same-origin' } );
	const page = new DOMParser().parseFromString( await response.text(), 'text/html' );
	const canvas = document.querySelector< HTMLIFrameElement >( 'iframe[name="editor-canvas"]' )?.contentDocument;

	for ( const element of Array.from( page.querySelectorAll( 'link[rel="stylesheet"][id], style[id]' ) ) ) {
		if ( document.getElementById( element.id ) ) {
			continue;
		}

		const copy = liveCopy( element );

		( page.head.contains( element ) ? document.head : document.body ).appendChild( copy );
		// The canvas iframe collected its styles when it mounted.
		canvas?.head.appendChild( liveCopy( element ) );

		if ( copy instanceof HTMLLinkElement ) {
			await settle( copy );
		}
	}

	return page;
}
