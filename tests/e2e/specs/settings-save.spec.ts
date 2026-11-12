import { expect, test, wp } from '../fixtures/test';

test.describe( 'settings save and persist', { tag: '@flow-settings-save' }, () => {
	test.beforeEach( () => {
		wp( 'option', 'delete', 'yaycheckout_settings' );
	} );

	test.afterEach( () => {
		wp( 'option', 'delete', 'yaycheckout_settings' );
		wp( 'option', 'delete', 'yaycheckout_settings_updated_at' );
	} );

	test( 'Delete all data on uninstall stays on after a reload', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=yaycheckout#/settings/general' );

		const toggle = page.getByRole( 'switch', { name: 'Delete all data on uninstall' } );
		await expect( toggle ).toHaveAttribute( 'aria-checked', 'false' );

		await toggle.click();
		await expect( page.getByText( '1 change pending' ) ).toBeVisible();
		await page.getByRole( 'button', { name: 'Save changes' } ).click();
		await expect( page.getByText( 'Settings saved.' ) ).toBeVisible();

		await page.reload();
		await expect( page.getByRole( 'switch', { name: 'Delete all data on uninstall' } ) ).toHaveAttribute( 'aria-checked', 'true' );

		const saved = JSON.parse( wp( 'option', 'get', 'yaycheckout_settings', '--format=json' ) );
		expect( saved.general.delete_data_on_uninstall ).toBe( true );
	} );
} );
