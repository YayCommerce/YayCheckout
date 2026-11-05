/**
 * Storefront script for yaycheckout/save-pdf-button.
 *
 * render.php prints a hidden order summary (Pro\Renderer\OrderPrintSummary) beside the button. On load it moves
 * under <body>, and the body takes a class, so the print CSS shows the summary alone, whether the dialog opens
 * from the button or from the browser's own Print. Before each print the summary takes a copy of the gateway's
 * thank-you output (bank details), which the page printed once in OrderPrintSummary::GATEWAY_CLASS, and drops
 * it once printed.
 *
 * The click opens the print dialog with the document titled after the button's data-filename, since browsers
 * name the PDF after the title, and puts the title back once the dialog has closed.
 */

import { bindOnce } from '../../shared/bind-once';
import { onDomReady } from '../../shared/dom-ready';

const BUTTON = 'button.yaycheckout-save-pdf';
/** Mirrors OrderPrintSummary::CLASS_NAME. */
const SUMMARY = 'yaycheckout-print-summary';
/** Mirrors OrderPrintSummary::GATEWAY_CLASS. */
const GATEWAY = '.yaycheckout-thankyou-gateway';
/** Mirrors OrderPrintSummary::PAYMENT_SLOT. */
const PAYMENT_SLOT = '[data-yaycheckout-print-payment]';
/** The body class style.css's print rules key on. */
const BODY_CLASS = 'yaycheckout-has-print-summary';
/** Mirrors BlockVisibility::HIDDEN_CLASS. */
const HIDDEN_BY_VISIBILITY = 'yaycheckout-hidden-by-visibility';

function fillPaymentSlot( summary: HTMLElement ): void {
	const slot = summary.querySelector< HTMLElement >( PAYMENT_SLOT );

	if ( ! slot ) {
		return;
	}

	const gateway = document.querySelector( GATEWAY );

	// A copy, taken at print time, so whatever a gateway's script drew since load (a QR code) is in it.
	slot.replaceChildren( ...( gateway ? Array.from( gateway.childNodes, ( node ) => node.cloneNode( true ) ) : [] ) );

	// cloneNode copies a <canvas> element but not its pixels, so each copy is drawn again from its original. Paired
	// before anything is removed below, while the copy's canvases still match the original's one for one.
	if ( gateway ) {
		const originals = gateway.querySelectorAll( 'canvas' );

		slot.querySelectorAll( 'canvas' ).forEach( ( copy, index ) => {
			const original = originals[ index ];

			if ( original ) {
				copy.width = original.width;
				copy.height = original.height;
				copy.getContext( '2d' )?.drawImage( original, 0, 0 );
			}
		} );
	}

	// Frames and scripts would load or run again and print blank, and forms and ids would be doubled, so they stay out.
	slot.querySelectorAll( 'iframe, script, noscript, input, button, select, textarea' ).forEach( ( node ) => node.remove() );
	slot.querySelectorAll( '[id]' ).forEach( ( node ) => node.removeAttribute( 'id' ) );
	// A form's content (a canvas, its text) stays; only the form element goes.
	slot.querySelectorAll( 'form' ).forEach( ( form ) => form.replaceWith( ...Array.from( form.childNodes ) ) );

	slot.hidden = ( slot.textContent ?? '' ).trim() === '' && ! slot.querySelector( 'img, svg, canvas' );
}

/**
 * A page margin of 0 leaves the browser no room for its headers and footers (date, title, URL, page count), so
 * Chrome prints none: the URL carries the order key. Added here, with the body class, so a page whose script
 * didn't run still prints with the browser's margins, and no page without the summary gets it.
 */
function addPageMargin(): HTMLStyleElement {
	const style = document.createElement( 'style' );

	style.media = 'print';
	style.textContent = '@page { margin: 0; }';
	document.head.appendChild( style );

	return style;
}

/**
 * The summary prints only while its button is shown. A Visibility rule on the button or on a block around it
 * (Visibility marks that block's first tag) leaves the browser's own Print printing the page, as without the block.
 */
function setActive( summary: HTMLElement, margin: HTMLStyleElement ): boolean {
	const button = summary.ownerDocument.querySelector( BUTTON );
	const active = !! button && ! button.closest( `.${ HIDDEN_BY_VISIBILITY }` ) && button.getClientRects().length > 0;

	document.body.classList.toggle( BODY_CLASS, active );
	margin.media = active ? 'print' : 'not all';

	return active;
}

/** The page's own title while the print dialog has it swapped for the file name; null otherwise. */
let swappedTitle: string | null = null;

function restoreTitle(): void {
	if ( swappedTitle !== null ) {
		document.title = swappedTitle;
		swappedTitle = null;
	}
}

function onClick( event: MouseEvent ): void {
	const button = event.target instanceof Element ? event.target.closest< HTMLButtonElement >( BUTTON ) : null;

	if ( ! button ) {
		return;
	}

	event.preventDefault();

	const filename = button.dataset.filename ?? '';

	// A second click before the dialog closes (print() doesn't block on iOS) keeps the first click's saved title.
	if ( filename !== '' && swappedTitle === null ) {
		swappedTitle = document.title;
		document.title = filename;
		window.addEventListener( 'afterprint', restoreTitle, { once: true } );
	}

	window.print();
}

function boot(): void {
	const summary = document.querySelector< HTMLElement >( `.${ SUMMARY }` );

	if ( summary ) {
		const margin = addPageMargin();

		document.body.appendChild( summary );
		setActive( summary, margin );
		window.addEventListener( 'beforeprint', () => {
			if ( setActive( summary, margin ) ) {
				fillPaymentSlot( summary );
			}
		} );
		window.addEventListener( 'afterprint', () => summary.querySelector( PAYMENT_SLOT )?.replaceChildren() );
	}

	document.addEventListener( 'click', onClick );
}

onDomReady( () => bindOnce( 'data-yaycheckout-save-pdf-bound', boot ) );
