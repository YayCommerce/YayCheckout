import { createLayout, removeLayout } from '../fixtures/layouts';
import { expect, test, wp } from '../fixtures/test';

const TITLE = 'E2E Activate layout';

test.describe( 'layouts list activate and deactivate', { tag: '@flow-layouts-activate' }, () => {
	let id = 0;

	test.beforeEach( () => {
		id = createLayout( TITLE );
	} );

	test.afterEach( () => {
		removeLayout( id );
	} );

	test( 'activating a draft makes it live, deactivating takes it off', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=yaycheckout#/layouts' );

		const row = page.getByRole( 'row' ).filter( { hasText: TITLE } );
		await expect( row.getByText( 'Inactive' ) ).toBeVisible();

		await test.step( 'activate', async () => {
			await row.getByRole( 'button', { name: 'Activate' } ).click();
			const dialog = page.getByRole( 'dialog', { name: `Make "${ TITLE }" your live checkout?` } );
			await dialog.getByRole( 'button', { name: 'Activate' } ).click();
			await expect( page.getByText( 'Layout activated.' ) ).toBeVisible();
			await expect( row.getByRole( 'button', { name: 'Deactivate' } ) ).toBeVisible();
			expect( JSON.parse( wp( 'option', 'get', 'yaycheckout_active_layout', '--format=json' ) ) ).toContain( id );
		} );

		await test.step( 'deactivate', async () => {
			await row.getByRole( 'button', { name: 'Deactivate' } ).click();
			const dialog = page.getByRole( 'dialog', { name: `Deactivate "${ TITLE }"?` } );
			await dialog.getByRole( 'button', { name: 'Deactivate' } ).click();
			await expect( page.getByText( 'Layout deactivated.' ) ).toBeVisible();
			await expect( row.getByText( 'Inactive' ) ).toBeVisible();
			expect( wp( 'post', 'get', String( id ), '--field=post_status' ) ).toBe( 'draft' );
		} );
	} );
} );
