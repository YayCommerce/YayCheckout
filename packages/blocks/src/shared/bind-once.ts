/**
 * Runs $bind once per page, marked by $attribute on <html>.
 *
 * A view script's listeners live on document. A second copy of the script (a block bundled twice,
 * or two blocks sharing a helper) must not add them again.
 */
export function bindOnce( attribute: string, bind: () => void ): void {
	if ( document.documentElement.hasAttribute( attribute ) ) {
		return;
	}

	document.documentElement.setAttribute( attribute, '' );
	bind();
}
