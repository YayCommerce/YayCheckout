import { wp } from './test';

const REPOSITORY = '$r = new \\YayCheckout\\Repositories\\LayoutRepository();';

/**
 * Creates a draft layout from a template through LayoutRepository and returns its id.
 *
 * @param title    Layout title.
 * @param template Template slug from templates/layouts/ (Pro ones from templates/layouts/pro/).
 */
export function createLayout( title: string, template = 'classic-two-column' ): number {
	const id = Number( wp( 'eval', `${ REPOSITORY } echo $r->create( ${ JSON.stringify( title ) }, ${ JSON.stringify( template ) } )->id;` ) );
	if ( ! id ) {
		throw new Error( `Layout "${ title }" was not created from ${ template }.` );
	}
	return id;
}

/**
 * Publishes a layout and adds it to the active set.
 *
 * @param id Layout id.
 */
export function activateLayout( id: number ): void {
	wp( 'eval', `${ REPOSITORY } $r->activate( ${ id } );` );
}

/**
 * Deactivates, archives and deletes a layout, in the order LayoutRepository requires.
 *
 * @param id Layout id.
 */
export function removeLayout( id: number ): void {
	wp(
		'eval',
		`${ REPOSITORY } if ( $r->find( ${ id } ) ) { $r->deactivate( ${ id } ); $r->archive( ${ id } ); $r->delete( ${ id } ); }`
	);
}

/** Id of the seeded simple product (bin/setup-env.sh). */
export function simpleProductId(): number {
	return Number( wp( 'post', 'list', '--post_type=product', '--name=e2e-simple-product', '--field=ID' ) );
}
