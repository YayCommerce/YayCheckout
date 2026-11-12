import { createLayout, removeLayout } from '../fixtures/layouts';
import { expect, test, wp } from '../fixtures/test';

const LABEL = 'E2E Confirm and pay';

test.describe( 'layout editor saves a draft', { tag: '@flow-layout-editor-save' }, () => {
	let id = 0;

	test.beforeEach( () => {
		id = createLayout( 'E2E Editor save' );
	} );

	test.afterEach( () => {
		removeLayout( id );
	} );

	test( 'an edited Place order label survives a reload', async ( { page } ) => {
		await page.addLocatorHandler( page.getByRole( 'button', { name: 'Skip tour' } ), async ( skip ) => {
			await skip.click();
		}, { noWaitAfter: true } );
		await page.addLocatorHandler( page.getByRole( 'dialog', { name: 'Welcome to the editor' } ), async ( guide ) => {
			await guide.getByRole( 'button', { name: 'Close' } ).click();
		}, { noWaitAfter: true } );

		const editorUrl = `/wp-admin/post.php?post=${ id }&action=edit&yc-embed=1`;
		await page.goto( editorUrl );

		const canvas = page.frameLocator( 'iframe[title="Editor canvas"]' );
		const label = canvas
			.getByRole( 'document', { name: 'Block: Place Order' } )
			.getByRole( 'textbox', { name: 'Place order' } );
		const save = page.getByRole( 'button', { name: 'Save', exact: true } );

		await expect( save ).toHaveAttribute( 'aria-disabled', 'true' );

		await label.click();
		await label.press( 'ControlOrMeta+a' );
		await label.pressSequentially( LABEL );
		await expect( save ).not.toHaveAttribute( 'aria-disabled', 'true' );
		await save.click();
		await expect( save ).toHaveAttribute( 'aria-disabled', 'true' );

		expect( wp( 'post', 'get', String( id ), '--field=post_content' ) ).toContain( LABEL );
		expect( wp( 'post', 'get', String( id ), '--field=post_status' ) ).toBe( 'draft' );

		await page.goto( editorUrl );
		await expect( label ).toHaveText( LABEL );
	} );
} );
