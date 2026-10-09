/**
 * The page's jQuery, for storefront scripts: WooCommerce's checkout scripts and select2 fire their
 * events, and listen for ours, through jQuery only.
 */

export type Control = HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;

export interface Jq {
	on( events: string, handler: ( event: Event ) => void ): Jq;
	val( value: string ): Jq;
	trigger( event: string ): Jq;
}

/** Null where the page has no jQuery. */
export function jquery( target: Element | Document ): Jq | null {
	const jq = ( window as Window & { jQuery?: ( target: Element | Document ) => Jq } ).jQuery;

	return jq ? jq( target ) : null;
}
