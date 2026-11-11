import { __, sprintf } from '@wordpress/i18n';

/**
 * Run test's browser checks (Pro): opens a test-run link, POST /layouts/{id}/test-run, in an off-screen
 * iframe and checks the checkout works there. The link is a preview, so its cart is sample products that
 * the next normal request strips, and it refuses orders. Pro\Renderer\LayoutTestMode prints the
 * window.yayCheckoutTestErrors catcher on it.
 */

/** The test-run route's answer, camelCased. */
export type TestRun = { url: string; expiresAt: string; refusalMessage: string };

/** A finding as Services\LayoutCheck shapes them; browser findings name no block. */
export type BrowserFinding = {
	code: string;
	severity: 'error' | 'warning';
	message: string;
	block: null;
	link: string;
};

export type TestResult = { findings: BrowserFinding[]; timedOut: boolean };

type Options = {
	phases: Array< 'render' >;
	onProgress?: ( label: string ) => void;
	signal?: AbortSignal;
};

type TestWindow = Window & {
	yayCheckoutTestErrors?: string[];
	jQuery?: ( target: unknown ) => {
		one( event: string, handler: () => void ): unknown;
		trigger( event: string ): unknown;
	};
};

const LOAD_MS = 20000;
const REFRESH_MS = 15000;
const STEP_MS = 300;
const WIDTH = 1280;

class TimedOut extends Error {}

export async function runLayoutTest( run: TestRun, { onProgress, signal }: Options ): Promise< TestResult > {
	const findings: BrowserFinding[] = [];
	const add = ( code: string, message: string ) =>
		findings.push( { code, severity: 'error', message, block: null, link: '' } );

	onProgress?.( __( 'Opening the checkout…', 'yaycheckout' ) );

	// #23: the iframe can't read the status, so the page is fetched once on its own.
	if ( ! ( await pageOpens( run.url, signal ) ) ) {
		add(
			'page_unavailable',
			__( 'The checkout page fails to open: it returns an error instead of the layout.', 'yaycheckout' )
		);
		return { findings, timedOut: false };
	}

	const frame = document.createElement( 'iframe' );
	frame.setAttribute( 'aria-hidden', 'true' );
	frame.tabIndex = -1;
	frame.style.cssText = `position:fixed;left:-10000px;top:0;width:${ WIDTH }px;height:900px;border:0;visibility:hidden;`;
	const abort = () => frame.remove();
	signal?.addEventListener( 'abort', abort );

	try {
		await withTimeout( loaded( frame, run.url ), LOAD_MS, signal );

		const win = frame.contentWindow as TestWindow | null;
		const doc = frame.contentDocument;

		if ( ! win || ! doc ) {
			add( 'page_unavailable', __( 'The checkout page could not be opened for testing.', 'yaycheckout' ) );
			return { findings, timedOut: false };
		}

		// #25
		const broken = formProblem( doc );
		if ( broken ) {
			add( 'form_broken', broken );
		}

		// #26: before any field is filled, so the refresh posts only what the page already holds.
		onProgress?.( __( 'Refreshing the totals…', 'yaycheckout' ) );
		const refresh = await refreshProblem( win, doc, signal );
		if ( refresh ) {
			add( 'refresh_failed', refresh );
		}

		// #27
		onProgress?.( __( 'Going through the steps…', 'yaycheckout' ) );
		const stuck = await stepsProblem( doc, signal );
		if ( stuck ) {
			add( 'steps_stuck', stuck );
		}

		// #24: last, so errors the refresh or the steps threw are caught too.
		const errors = win.yayCheckoutTestErrors ?? [];
		if ( errors.length > 0 ) {
			add(
				'js_errors',
				sprintf(
					/* translators: %s: the first JavaScript errors on the page, separated by " | ". */
					__( 'The checkout page throws JavaScript errors, which can stop fields, totals or payment from working: %s', 'yaycheckout' ),
					errors.slice( 0, 3 ).join( ' | ' )
				)
			);
		}

		return { findings, timedOut: false };
	} catch ( error ) {
		if ( error instanceof TimedOut ) {
			return { findings, timedOut: true };
		}
		throw error;
	} finally {
		signal?.removeEventListener( 'abort', abort );
		frame.remove();
	}
}

/**
 * Fills every required control `root` shows that is still empty with sample data, so Steps lets Continue
 * through. No input or change event fires: checkout.js would post the sample address to update_order_review,
 * and WooCommerce saves a logged-in customer's address to their account.
 */
export function fillRequired( root: Element ): void {
	const country = root.ownerDocument.querySelector< HTMLSelectElement >( '#billing_country, #shipping_country' )?.value ?? '';

	root.querySelectorAll< HTMLElement >( '.form-row.validate-required' ).forEach( ( row ) => {
		row.querySelectorAll< HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement >( 'input, select, textarea' ).forEach(
			( control ) => {
				if ( control.disabled || ! isShown( control ) ) {
					return;
				}

				if ( control instanceof HTMLSelectElement ) {
					if ( control.value === '' ) {
						const option = [ ...control.options ].find( ( item ) => item.value !== '' );
						control.value = option?.value ?? '';
					}
					return;
				}

				if ( control instanceof HTMLInputElement && ( control.type === 'checkbox' || control.type === 'radio' ) ) {
					const group = control.name
						? [ ...row.querySelectorAll< HTMLInputElement >( `input[name="${ CSS.escape( control.name ) }"]` ) ]
						: [ control ];
					if ( ! group.some( ( item ) => item.checked ) ) {
						control.checked = true;
					}
					return;
				}

				if ( control.value.trim() === '' ) {
					control.value = sampleValue( control, country );
				}
			}
		);
	} );

	// Shipping and payment choices are radio lists outside .form-row.
	const radios = new Map< string, HTMLInputElement[] >();
	root.querySelectorAll< HTMLInputElement >( 'input[type="radio"][name="payment_method"], input[type="radio"][name^="shipping_method"]' ).forEach(
		( radio ) => radios.set( radio.name, [ ...( radios.get( radio.name ) ?? [] ), radio ] )
	);
	radios.forEach( ( group ) => {
		if ( ! group.some( ( radio ) => radio.checked ) ) {
			group[ 0 ].checked = true;
		}
	} );
}

function sampleValue( control: HTMLInputElement | HTMLTextAreaElement, country: string ): string {
	const name = control.name;

	if ( control.type === 'email' || name.endsWith( '_email' ) ) {
		return 'test@example.com';
	}
	if ( control.type === 'tel' || name.endsWith( '_phone' ) ) {
		return '0123456789';
	}
	if ( name.endsWith( '_postcode' ) ) {
		return country === 'US' ? '10001' : '12345';
	}
	if ( control instanceof HTMLInputElement && control.type === 'number' ) {
		return control.min || '1';
	}
	if ( control.type === 'date' ) {
		return new Date().toISOString().slice( 0, 10 );
	}

	return 'Test';
}

/** As steps/view.ts isShown(): Select2 hides the real select and shows its container. */
function isShown( control: HTMLElement ): boolean {
	if ( control.classList.contains( 'select2-hidden-accessible' ) ) {
		const container = control.nextElementSibling;

		return container instanceof HTMLElement && container.classList.contains( 'select2-container' )
			? container.getClientRects().length > 0
			: control.closest( '.form-row' )?.getClientRects().length !== 0;
	}

	return control.getClientRects().length > 0;
}

async function pageOpens( url: string, signal?: AbortSignal ): Promise< boolean > {
	try {
		const response = await fetch( url, { credentials: 'same-origin', signal } );
		const body = await response.text();

		return response.ok && ! body.includes( 'wp-die-message' ) && ! body.includes( 'There has been a critical error' );
	} catch ( error ) {
		if ( signal?.aborted ) {
			throw error;
		}
		return false;
	}
}

function loaded( frame: HTMLIFrameElement, url: string ): Promise< void > {
	return new Promise( ( resolve ) => {
		frame.addEventListener( 'load', () => resolve(), { once: true } );
		frame.src = url;
		document.body.appendChild( frame );
	} );
}

function withTimeout< T >( promise: Promise< T >, ms: number, signal?: AbortSignal ): Promise< T > {
	return new Promise( ( resolve, reject ) => {
		const timer = window.setTimeout( () => reject( new TimedOut() ), ms );
		const aborted = () => {
			window.clearTimeout( timer );
			reject( new DOMException( 'Aborted', 'AbortError' ) );
		};

		if ( signal?.aborted ) {
			aborted();
			return;
		}
		signal?.addEventListener( 'abort', aborted, { once: true } );
		promise.then(
			( value ) => {
				window.clearTimeout( timer );
				resolve( value );
			},
			( error ) => {
				window.clearTimeout( timer );
				reject( error );
			}
		);
	} );
}

function wait( ms: number, signal?: AbortSignal ): Promise< void > {
	return withTimeout( new Promise( ( resolve ) => window.setTimeout( resolve, ms ) ), ms + 1000, signal );
}

/** #25: one checkout form, with its nonce, and a payment_method input when gateways are listed. */
function formProblem( doc: Document ): string {
	const forms = doc.querySelectorAll( 'form.checkout' );

	if ( forms.length !== 1 ) {
		return forms.length === 0
			? __( 'The page has no checkout form, so nothing can be ordered.', 'yaycheckout' )
			: __( 'The page has more than one checkout form, so WooCommerce can submit the wrong one.', 'yaycheckout' );
	}

	if ( ! forms[ 0 ].querySelector( 'input[name="woocommerce-process-checkout-nonce"]' ) ) {
		return __( 'The checkout form is missing its security field, so WooCommerce refuses every order.', 'yaycheckout' );
	}

	if ( forms[ 0 ].querySelector( '.wc_payment_method' ) && ! forms[ 0 ].querySelector( 'input[name="payment_method"]' ) ) {
		return __( 'Payment methods show but none can be chosen, so the order has no payment method.', 'yaycheckout' );
	}

	return '';
}

/** #26: WooCommerce's update_checkout must come back as updated_checkout, without an error notice. */
async function refreshProblem( win: TestWindow, doc: Document, signal?: AbortSignal ): Promise< string > {
	const $ = win.jQuery;

	if ( ! $ ) {
		return __( 'WooCommerce\'s checkout script is not loaded, so totals never refresh.', 'yaycheckout' );
	}

	const done = new Promise< void >( ( resolve ) => {
		$( doc.body ).one( 'updated_checkout', () => resolve() );
	} );
	$( doc.body ).trigger( 'update_checkout' );

	try {
		await withTimeout( done, REFRESH_MS, signal );
	} catch ( error ) {
		if ( error instanceof TimedOut ) {
			return __( 'The totals never finished refreshing: WooCommerce\'s update request did not come back.', 'yaycheckout' );
		}
		throw error;
	}

	const notice = doc.querySelector( '.woocommerce-NoticeGroup-updateOrderReview .woocommerce-error' );

	return notice
		? sprintf(
				/* translators: %s: the error WooCommerce showed. */
				__( 'Refreshing the totals shows an error: %s', 'yaycheckout' ),
				( notice.textContent ?? '' ).trim().replace( /\s+/g, ' ' )
		  )
		: '';
}

/** #27: from the first step, fill it and press its Next Step Button until the last step is open. */
async function stepsProblem( doc: Document, signal?: AbortSignal ): Promise< string > {
	const checkout = doc.querySelector( '.yaycheckout-checkout' );
	const steps = checkout ? [ ...checkout.querySelectorAll< HTMLElement >( '.yaycheckout-step' ) ] : [];

	if ( ! checkout || steps.length < 2 ) {
		return '';
	}

	const shared = [ ...checkout.querySelectorAll< HTMLElement >( '.yaycheckout-step-button' ) ].find(
		( button ) => ! button.closest( '.yaycheckout-step' )
	);

	for ( let index = 0; index < steps.length - 1; index++ ) {
		const step = steps[ index ];
		const number = index + 1;

		if ( step.dataset.state !== 'current' ) {
			return sprintf(
				/* translators: %d: step number. */
				__( 'Step %d does not open, so shoppers can\'t go through the steps.', 'yaycheckout' ),
				number
			);
		}

		fillRequired( step );

		const button = step.querySelector< HTMLElement >( '.yaycheckout-step-button' ) ?? shared;

		if ( ! button ) {
			return sprintf(
				/* translators: %d: step number. */
				__( 'Step %d has no Next Step Button, so shoppers can\'t leave it.', 'yaycheckout' ),
				number
			);
		}

		button.click();
		await wait( STEP_MS, signal );

		if ( steps[ index + 1 ].dataset.state !== 'current' ) {
			const invalid = step.querySelector< HTMLInputElement >( ':invalid' );
			const label = invalid?.closest( '.form-row' )?.querySelector( 'label' )?.textContent?.trim().replace( /\s*\*$/, '' );

			return label
				? sprintf(
						/* translators: 1: step number, 2: the field's label. */
						__( 'Step %1$d won\'t continue: "%2$s" is still refused after it was filled in.', 'yaycheckout' ),
						number,
						label
				  )
				: sprintf(
						/* translators: %d: step number. */
						__( 'Pressing Next on step %d does not open the next step.', 'yaycheckout' ),
						number
				  );
		}
	}

	return '';
}
