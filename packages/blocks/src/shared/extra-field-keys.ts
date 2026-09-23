export const EXTRA_FIELD_BLOCK = 'yaycheckout/extra-field';

/** Same rule as LayoutMarkupReader::EXTRA_FIELD_KEY_PATTERN. */
export const EXTRA_FIELD_KEY_PATTERN = /^[A-Za-z0-9][A-Za-z0-9_]*$/;

interface BlockEditorSelectors {
	getClientIdsWithDescendants: () => string[];
	getBlockName: ( clientId: string ) => string | null;
	getBlockAttributes: ( clientId: string ) => Record< string, unknown > | null;
}

/**
 * Trimmed Key of every extra-field block in the document, in order. The Key is
 * the posted name persisted to order meta, so it must be set and unique.
 */
export function extraFieldKeys( blockEditor: BlockEditorSelectors ): string[] {
	return blockEditor
		.getClientIdsWithDescendants()
		.filter( ( clientId ) => blockEditor.getBlockName( clientId ) === EXTRA_FIELD_BLOCK )
		.map( ( clientId ) => String( blockEditor.getBlockAttributes( clientId )?.fieldKey ?? '' ).trim() );
}

export function duplicateKeys( keys: string[] ): string[] {
	return [ ...new Set( keys.filter( ( key, i ) => key !== '' && keys.indexOf( key ) !== i ) ) ];
}
