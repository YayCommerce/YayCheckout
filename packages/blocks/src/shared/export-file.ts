// Shared by the blocks bundle and the admin SPA (via the @blocks-shared alias), so it imports nothing.

/** PHP names the file in Content-Disposition; a missing or malformed header falls back to a generic name rather than failing the download. */
export function filenameFromHeaders( headers: Headers ): string {
	return /filename="([^"]+)"/.exec( headers.get( 'content-disposition' ) ?? '' )?.[ 1 ] ?? 'yaycheckout-layout.json';
}

export function saveFile( filename: string, json: string ): void {
	const url = URL.createObjectURL( new Blob( [ json ], { type: 'application/json' } ) );
	const anchor = document.createElement( 'a' );
	anchor.href = url;
	anchor.download = filename;
	anchor.click();
	// Deferred: Safari can drop the download if the URL is revoked in the same tick as the click.
	setTimeout( () => URL.revokeObjectURL( url ), 0 );
}

/** By extension: DropZone ignores `accept`, and some OSes report an empty MIME type for .json. */
export function isJsonFileName( name: string ): boolean {
	return name.toLowerCase().endsWith( '.json' );
}
