/** The object in a page's JSON element, as Helpers\JsonElementHelper::html() prints it; null when it is missing or unreadable. */
export function readJsonElement< T extends object >( id: string ): T | null {
	try {
		const parsed: unknown = JSON.parse( document.getElementById( id )?.textContent ?? '' );

		return parsed !== null && typeof parsed === 'object' ? ( parsed as T ) : null;
	} catch {
		return null;
	}
}
