import { activateLayout, createLayout, removeLayout, simpleProductId } from '../fixtures/layouts';
import { expect, test, wp } from '../fixtures/test';

const CODE = 'e2esave2';

test.use( { storageState: { cookies: [], origins: [] } } );

test.describe( 'coupon form block', { tag: '@flow-checkout-coupon' }, () => {
	let id = 0;
	let couponId = '';

	test.beforeAll( () => {
		id = createLayout( 'E2E Coupon' );
		activateLayout( id );
		couponId = wp( 'wc', 'shop_coupon', 'create', `--code=${ CODE }`, '--discount_type=fixed_cart', '--amount=2', '--user=admin', '--porcelain' );
	} );

	test.afterAll( () => {
		removeLayout( id );
		wp( 'wc', 'shop_coupon', 'delete', couponId, '--force=true', '--user=admin' );
	} );

	test( 'applies a coupon to the totals and removes it again', async ( { page } ) => {
		await page.goto( `/?add-to-cart=${ simpleProductId() }` );
		await page.goto( '/checkout/' );

		const total = page.locator( '.yaycheckout-order-summary-total' );
		await expect( total ).toContainText( '15.00' );

		await page.getByRole( 'button', { name: 'Add coupons' } ).click();
		await page.getByLabel( 'Enter code' ).fill( CODE );
		await page.getByRole( 'button', { name: 'Apply', exact: true } ).click();

		const remove = page.getByRole( 'button', { name: `Remove ${ CODE } coupon` } );
		await expect( remove ).toBeVisible();
		await expect( total ).toContainText( '13.00' );

		await remove.click();
		await expect( remove ).toBeHidden();
		await expect( total ).toContainText( '15.00' );
	} );
} );
