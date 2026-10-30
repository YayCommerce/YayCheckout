/**
 * Opens the checkout login modal and, after a successful login, refreshes
 * nonces and empty fields without replacing the checkout form.
 */

import { jquery } from '../shared/jquery';

const TRIGGER = '.yaycheckout-login__trigger';

interface LoginState {
	layout_id: number;
	values: Record< string, string >;
	nonces: {
		update_order_review: string;
		process_checkout: string;
	};
}

interface JsonBody {
	success?: boolean;
	data?: LoginState & { message?: string; state_nonce?: string };
}

let dialog: HTMLDialogElement | null = null;
let returnFocus: HTMLElement | null = null;

function rank( key: string ): number {
	if ( key.endsWith( '_country' ) ) {
		return 0;
	}

	if ( key.endsWith( '_state' ) ) {
		return 1;
	}

	return 2;
}

function showError( form: HTMLFormElement, message: string ): void {
	const error = form.querySelector< HTMLElement >( '.yaycheckout-login__error' );
	const button = form.querySelector< HTMLButtonElement >( '.yaycheckout-login__submit' );

	if ( error ) {
		error.hidden = false;
		error.textContent = message;
	}

	if ( button ) {
		button.disabled = false;
	}
}

function applyState( checkout: HTMLFormElement, state: LoginState ): void {
	const params = ( window as Window & { wc_checkout_params?: { update_order_review_nonce: string } } ).wc_checkout_params;

	if ( params ) {
		params.update_order_review_nonce = state.nonces.update_order_review;
	}

	const processNonce = checkout.querySelector< HTMLInputElement >( 'input[name="woocommerce-process-checkout-nonce"]' );

	if ( processNonce ) {
		processNonce.value = state.nonces.process_checkout;
	}

	Object.entries( state.values )
		.sort( ( left, right ) => rank( left[ 0 ] ) - rank( right[ 0 ] ) )
		.forEach( ( [ key, value ] ) => {
			const field = checkout.querySelector< HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement >(
				`[name="${ CSS.escape( key ) }"]`
			);

			if ( ! field || field.value !== '' ) {
				return;
			}

			field.value = value;
			jquery( field )?.val( value ).trigger( 'change' );
		} );

	dialog?.close();
	document.querySelectorAll( '.yaycheckout-login' ).forEach( ( node ) => node.remove() );
	dialog?.remove();
	dialog = null;
	jquery( document.body )?.trigger( 'update_checkout' );
}

async function submit( block: HTMLElement, form: HTMLFormElement ): Promise< void > {
	const button = form.querySelector< HTMLButtonElement >( '.yaycheckout-login__submit' );
	const error = form.querySelector< HTMLElement >( '.yaycheckout-login__error' );
	const loginUrl = block.dataset.loginUrl;
	const stateUrl = block.dataset.stateUrl;

	if ( button ) {
		button.disabled = true;
	}

	if ( error ) {
		error.hidden = true;
		error.textContent = '';
	}

	if ( ! loginUrl || ! stateUrl ) {
		showError( form, 'Could not log in.' );
		return;
	}

	try {
		const loginResponse = await fetch( loginUrl, {
			method: 'POST',
			body: new FormData( form ),
			credentials: 'same-origin',
		} );
		const loginBody = ( await loginResponse.json() ) as JsonBody;

		if ( ! loginBody.success ) {
			showError( form, loginBody.data?.message || 'Could not log in.' );
			return;
		}

		const stateRequest = new URL( stateUrl, window.location.href );
		stateRequest.searchParams.set( 'security', loginBody.data?.state_nonce ?? '' );

		const stateResponse = await fetch( stateRequest, { credentials: 'same-origin' } );
		const stateBody = ( await stateResponse.json() ) as JsonBody;
		const checkout = document.querySelector< HTMLFormElement >( 'form.checkout' );

		if ( ! stateBody.success || ! stateBody.data || ! checkout || checkout.dataset.yaycheckoutLayout !== String( stateBody.data.layout_id ) ) {
			window.location.reload();
			return;
		}

		applyState( checkout, stateBody.data );
	} catch {
		showError( form, 'Could not log in.' );
	}
}

/**
 * Moves the closed dialog out of its template into the body. This runs on page load, not on the first click,
 * because plugins that hook woocommerce_login_form_start (Sign in with Klarna) look for their placeholder once,
 * when their SDK loads, and can't find it inside a template.
 */
function mountDialog( block: HTMLElement ): HTMLDialogElement | null {
	if ( dialog ) {
		return dialog;
	}

	const template = block.querySelector< HTMLTemplateElement >( 'template.yaycheckout-login__template' );
	const next = template?.content.querySelector( 'dialog' );

	if ( ! ( next instanceof HTMLDialogElement ) ) {
		return null;
	}

	document.body.appendChild( next );
	dialog = next;

	const form = dialog.querySelector( 'form' );

	if ( form instanceof HTMLFormElement ) {
		form.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			void submit( block, form );
		} );
	}

	dialog.querySelector( '.yaycheckout-login__close' )?.addEventListener( 'click', () => {
		dialog?.close();
	} );
	dialog.addEventListener( 'close', () => {
		if ( returnFocus?.isConnected ) {
			returnFocus.focus();
		}
	} );

	return dialog;
}

function openDialog( block: HTMLElement, trigger: HTMLElement ): void {
	const opened = mountDialog( block );

	if ( ! opened ) {
		return;
	}

	returnFocus = trigger;
	opened.showModal();
}

function onClick( event: MouseEvent ): void {
	const target = event.target;

	if ( ! ( target instanceof Element ) ) {
		return;
	}

	const trigger = target.closest< HTMLElement >( TRIGGER );
	const block = trigger?.closest< HTMLElement >( '.yaycheckout-login' );

	if ( ! trigger || ! block || block.dataset.behavior !== 'modal' ) {
		return;
	}

	event.preventDefault();
	openDialog( block, trigger );
}

function mountOnLoad(): void {
	const block = document.querySelector< HTMLElement >( '.yaycheckout-login[data-behavior="modal"]' );

	if ( block ) {
		mountDialog( block );
	}
}

document.addEventListener( 'click', onClick );

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mountOnLoad );
} else {
	mountOnLoad();
}
