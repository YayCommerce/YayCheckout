import { activateLayout, createLayout, removeLayout, simpleProductId } from '../fixtures/layouts';
import { expect, test, wp } from '../fixtures/test';

test.use( { storageState: { cookies: [], origins: [] } } );

test.describe( 'Pro multi-step checkout', { tag: '@flow-multi-step-checkout' }, () => {
	let id = 0;

	test.beforeAll( () => {
		id = createLayout( 'E2E Wizard', 'multi-step-wizard' );
		activateLayout( id );
	} );

	test.afterAll( () => {
		removeLayout( id );
	} );

	test( 'steps advance only when valid, go back, and end in an order', async ( { page } ) => {
		await page.goto( `/?add-to-cart=${ simpleProductId() }` );
		await page.goto( '/checkout/' );

		const step = ( n: number ) => page.locator( `.yaycheckout-step[data-step="${ n }"]` );
		await expect( step( 1 ) ).toHaveAttribute( 'data-state', 'current' );

		await test.step( 'an empty step 1 does not advance', async () => {
			await page.getByRole( 'button', { name: 'Continue to shipping' } ).click();
			await expect( step( 1 ) ).toHaveAttribute( 'data-state', 'current' );
			await expect( step( 2 ) ).not.toHaveAttribute( 'data-state', 'current' );
		} );

		await test.step( 'a filled step 1 advances to shipping', async () => {
			await page.locator( '#billing_email' ).fill( 'wizard@example.com' );
			await page.locator( '#billing_first_name' ).fill( 'Wanda' );
			await page.locator( '#billing_last_name' ).fill( 'Wizard' );
			await page.locator( '#billing_address_1' ).fill( '1 Test Street' );
			await page.locator( '#billing_city' ).fill( 'San Francisco' );
			await page.locator( '#billing_state' ).selectOption( 'CA' );
			await page.locator( '#billing_postcode' ).fill( '94110' );
			await page.locator( '#billing_phone' ).fill( '5555550100' );
			await page.getByRole( 'button', { name: 'Continue to shipping' } ).click();
			await expect( step( 1 ) ).toHaveAttribute( 'data-state', 'complete' );
			await expect( step( 2 ) ).toHaveAttribute( 'data-state', 'current' );
		} );

		await test.step( 'back returns to information', async () => {
			await page.getByRole( 'button', { name: 'Return to information' } ).click();
			await expect( step( 1 ) ).toHaveAttribute( 'data-state', 'current' );
			await page.getByRole( 'button', { name: 'Continue to shipping' } ).click();
			await expect( step( 2 ) ).toHaveAttribute( 'data-state', 'current' );
		} );

		await test.step( 'payment step places the order', async () => {
			await page.getByRole( 'button', { name: 'Continue to payment' } ).click();
			await expect( step( 3 ) ).toHaveAttribute( 'data-state', 'current' );
			await page.locator( '#payment_method_cod' ).check();
			await page.locator( '#place_order' ).click();
		} );

		await expect( page ).toHaveURL( /order-received\/(\d+)/ );
		const orderId = page.url().match( /order-received\/(\d+)/ )?.[ 1 ] ?? '';
		const order = JSON.parse( wp( 'wc', 'shop_order', 'get', orderId, '--user=admin', '--format=json' ) );
		expect( order.billing.email ).toBe( 'wizard@example.com' );
		expect( order.total ).toBe( '15.00' );
	} );
} );
