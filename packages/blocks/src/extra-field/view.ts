import { initDisclosures } from '../shared/disclosure-view';

/** Wrapper of an Extra field with Remember value on; render.php sets it. */
const REMEMBER = '.yaycheckout-extra-field[data-yaycheckout-remember]';

interface CheckoutParams {
	wc_ajax_url: string;
	update_order_review_nonce: string;
}

/**
 * Posts the checkout form to CheckoutExtraFields::remember() whenever a remembered field changes,
 * so a reload prints the entry again. Read at change time: the login block refreshes the nonce.
 */
function initRememberedValues(): void {
	document.addEventListener( 'change', ( event ) => {
		const target = event.target instanceof Element ? event.target : null;
		const form = target?.closest< HTMLFormElement >( 'form.checkout' );
		const params = ( window as Window & { wc_checkout_params?: CheckoutParams } ).wc_checkout_params;

		if ( ! target?.closest( REMEMBER ) || ! form || ! params ) {
			return;
		}

		const body = new FormData( form );
		body.set( 'security', params.update_order_review_nonce );

		fetch( params.wc_ajax_url.replace( '%%endpoint%%', 'yaycheckout_remember_extra_fields' ), {
			method: 'POST',
			body,
			credentials: 'same-origin',
		} ).catch( () => undefined );
	} );
}

initDisclosures();
initRememberedValues();
