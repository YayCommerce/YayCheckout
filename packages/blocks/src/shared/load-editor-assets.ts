import { unstable__bootstrapServerSideBlockDefinitions as bootstrapServerSideBlockDefinitions } from '@wordpress/blocks';

/** What core's edit-form-blocks.php prints on wp-blocks with every block's server-side definition. */
const BOOTSTRAP_CALL = 'wp.blocks.unstable__bootstrapServerSideBlockDefinitions(';

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

/**
 * Hands the editor the server-side block definitions (title, apiVersion, attributes…) a fresh load prints, as core
 * does at page load. A block.json block's editor script often registers with its name alone and relies on them,
 * so a block registered after the page loaded needs them before its script runs. Core keeps the definitions it
 * already has.
 */
function bootstrapBlockDefinitions( page: Document ): void {
	for ( const script of Array.from( page.querySelectorAll( 'script:not([src])' ) ) ) {
		const text = script.textContent ?? '';
		const at = text.indexOf( BOOTSTRAP_CALL );

		if ( at === -1 ) {
			continue;
		}

		const definitions = jsonObjectAt( text, text.indexOf( '{', at ) );

		if ( definitions ) {
			bootstrapServerSideBlockDefinitions( definitions );
		}

		return;
	}
}

/** Waits for an appended script or stylesheet to load or fail; inline ones settle at once. */
function settle( element: HTMLScriptElement | HTMLLinkElement ): Promise< void > {
	const external = element instanceof HTMLScriptElement ? element.src !== '' : true;

	if ( ! external ) {
		return Promise.resolve();
	}

	return new Promise( ( resolve ) => {
		element.addEventListener( 'load', () => resolve(), { once: true } );
		element.addEventListener( 'error', () => resolve(), { once: true } );
	} );
}

/** A copy that runs once appended: a script parsed by DOMParser never does. */
function liveCopy( element: Element ): HTMLScriptElement | HTMLLinkElement | HTMLStyleElement {
	const copy = document.createElement( element.tagName.toLowerCase() ) as HTMLScriptElement | HTMLLinkElement | HTMLStyleElement;

	for ( const { name, value } of Array.from( element.attributes ) ) {
		copy.setAttribute( name, value );
	}

	copy.textContent = element.textContent;

	return copy;
}

/**
 * Loads the scripts and styles a fresh load of `url` (this editor) would print and this page doesn't have yet,
 * in document order, so a plugin activated since the page loaded registers its blocks without a reload. Core's
 * Block Directory (@wordpress/block-directory's loadAssets) does the same after installing a block. Scripts load
 * one at a time, since each may depend on the one before; inline data (`-js-before`, `-js-extra`) runs on append.
 * New styles also go into the canvas iframe. Resolves with the fetched page, for callers that read its inline data.
 */
export async function loadEditorAssets( url: string ): Promise< Document > {
	const response = await fetch( url, { credentials: 'same-origin' } );
	const page = new DOMParser().parseFromString( await response.text(), 'text/html' );

	bootstrapBlockDefinitions( page );

	const canvas = document.querySelector< HTMLIFrameElement >( 'iframe[name="editor-canvas"]' )?.contentDocument;

	for ( const element of Array.from( page.querySelectorAll( 'script[id], link[rel="stylesheet"][id], style[id]' ) ) ) {
		// An import map can't change once modules have loaded.
		if ( document.getElementById( element.id ) || element.getAttribute( 'type' ) === 'importmap' ) {
			continue;
		}

		const copy = liveCopy( element );

		( page.head.contains( element ) ? document.head : document.body ).appendChild( copy );

		// The canvas iframe collected its styles when it mounted, so a new block's styles go in there too.
		if ( ! ( copy instanceof HTMLScriptElement ) ) {
			canvas?.head.appendChild( liveCopy( element ) );
		}

		if ( ! ( copy instanceof HTMLStyleElement ) ) {
			await settle( copy );
		}
	}

	return page;
}
