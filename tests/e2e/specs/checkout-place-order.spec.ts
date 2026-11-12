import { activateLayout, createLayout, removeLayout, simpleProductId } from '../fixtures/layouts';
import { expect, test, wp } from '../fixtures/test';

test.use( { storageState: { cookies: [], origins: [] } } );

test.describe( 'guest places an order through the layout', { tag: '@flow-checkout-place-order' }, () => {
	let id = 0;

	test.beforeAll( () => {
		id = createLayout( 'E2E Checkout' );
		activateLayout( id );
	} );

	test.afterAll( () => {
		removeLayout( id );
	} );

	test( 'the checkout prints the active layout and a COD order goes through', async ( { page } ) => {
		await page.goto( `/?add-to-cart=${ simpleProductId() }` );
		await page.goto( '/checkout/' );

		const form = page.locator( 'form[name="checkout"]' );
		await expect( form ).toHaveAttribute( 'data-yaycheckout-layout', String( id ) );
		await expect( page.locator( 'body' ) ).toHaveClass( /yaycheckout-page/ );

		await test.step( 'fill the guest details', async () => {
			await page.locator( '#billing_email' ).fill( 'guest@example.com' );
			await page.locator( '#billing_first_name' ).fill( 'Jane' );
			await page.locator( '#billing_last_name' ).fill( 'Guest' );
			await page.locator( '#billing_address_1' ).fill( '1 Test Street' );
			await page.locator( '#billing_city' ).fill( 'San Francisco' );
			await page.locator( '#billing_state' ).selectOption( 'CA' );
			await page.locator( '#billing_postcode' ).fill( '94110' );
		} );

		await page.locator( '#payment_method_cod' ).check();
		await page.locator( '#place_order' ).click();

		await expect( page ).toHaveURL( /order-received\/(\d+)/ );
		const orderId = page.url().match( /order-received\/(\d+)/ )?.[ 1 ] ?? '';
		const order = JSON.parse( wp( 'wc', 'shop_order', 'get', orderId, '--user=admin', '--format=json' ) );
		expect( order.payment_method ).toBe( 'cod' );
		expect( order.billing.email ).toBe( 'guest@example.com' );
		expect( order.total ).toBe( '15.00' );
	} );
} );
