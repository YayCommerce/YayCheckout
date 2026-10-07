/**
 * Opens an apartment field that render.php printed behind its "+ Add apartment" link: on a click,
 * when the field gets a value (the login modal fills it through jQuery), or when the country's
 * locale makes it required. WooCommerce's locale script shows and hides the inner row, never our
 * container, so a locale that hides the row also hides the link.
 */

const REVEAL = '.yaycheckout-field__reveal';

interface Jq {
	on( events: string, handler: () => void ): Jq;
}

function jquery( target: Element | Document ): Jq | null {
	const jq = ( window as Window & { jQuery?: ( target: Element | Document ) => Jq } ).jQuery;

	return jq ? jq( target ) : null;
}

function open( button: HTMLButtonElement, container: HTMLElement, focus: boolean ): void {
	container.hidden = false;
	button.hidden = true;
	button.setAttribute( 'aria-expanded', 'true' );

	if ( focus ) {
		container.querySelector< HTMLElement >( 'input, select, textarea' )?.focus();
	}
}

function bind( button: HTMLButtonElement ): void {
	const container = document.getElementById( button.getAttribute( 'aria-controls' ) ?? '' );

	if ( ! container ) {
		return;
	}

	const input = container.querySelector< HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement >(
		'input, select, textarea'
	);
	const row = container.querySelector< HTMLElement >( '.form-row' );
	const openIfFilled = () => {
		if ( container.hidden && input && input.value !== '' ) {
			open( button, container, false );
		}
	};

	button.addEventListener( 'click', () => open( button, container, true ) );

	if ( input ) {
		input.addEventListener( 'input', openIfFilled );
		input.addEventListener( 'change', openIfFilled );
		jquery( input )?.on( 'change', openIfFilled );
	}

	jquery( document.body )?.on( 'country_to_state_changed', () => {
		if ( ! container.hidden || ! row ) {
			return;
		}

		if ( row.classList.contains( 'validate-required' ) ) {
			open( button, container, false );
			return;
		}

		button.hidden = row.style.display === 'none';
	} );

	openIfFilled();
}

function init(): void {
	document.querySelectorAll< HTMLButtonElement >( REVEAL ).forEach( bind );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
