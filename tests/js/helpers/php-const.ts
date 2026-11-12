import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const ROOT = fileURLToPath( new URL( '../../../', import.meta.url ) );

/** A PHP file's source, by its path from the plugin root. */
export function phpSource( path: string ): string {
	return readFileSync( ROOT + path, 'utf8' );
}

/** The literal of `const NAME = …;` in a PHP file: a quoted string, or a flat array of quoted strings. */
export function phpConst( path: string, name: string ): string | string[] {
	const match = phpSource( path ).match( new RegExp( `const\\s+${ name }\\s*=\\s*([\\s\\S]*?);` ) );

	if ( ! match ) {
		throw new Error( `No const ${ name } in ${ path }` );
	}

	const literal = match[ 1 ].trim();
	const strings = [ ...literal.matchAll( /'((?:[^'\\]|\\.)*)'/g ) ].map( ( m ) => m[ 1 ].replace( /\\'/g, "'" ) );

	return literal.startsWith( '[' ) ? strings : strings[ 0 ];
}
