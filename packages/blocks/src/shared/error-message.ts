/** A message for an apiFetch rejection: the raw Response when `parse: false`, else `{ message }`. Never empty. */
export async function errorMessage( error: unknown, fallback: string ): Promise< string > {
	let message: string | undefined;
	if ( error instanceof Response ) {
		message = ( await error.json().catch( () => null ) )?.message;
	} else {
		message = ( error as { message?: string } )?.message;
	}
	return message || fallback;
}
