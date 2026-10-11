/** Runs $callback once the document is parsed: now, or on DOMContentLoaded while it still loads. */
export function onDomReady( callback: () => void ): void {
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', callback );
	} else {
		callback();
	}
}
