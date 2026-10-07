export const EXTRA_FIELD_BLOCK = 'yaycheckout/extra-field';

/** Same rule as LayoutMarkupReader::EXTRA_FIELD_KEY_PATTERN. */
export const EXTRA_FIELD_KEY_PATTERN = /^[A-Za-z0-9][A-Za-z0-9_]*$/;

/** Class on the inspector's Key control, so the publish notice can focus it. */
export const EXTRA_FIELD_KEY_CONTROL_CLASS = 'yaycheckout-extra-field__key';

interface BlockEditorSelectors {
	getClientIdsWithDescendants: () => string[];
	getBlockName: ( clientId: string ) => string | null;
	getBlockAttributes: ( clientId: string ) => Record< string, unknown > | null;
}

export interface ExtraFieldKey {
	clientId: string;
	key: string;
}

/** Every extra-field block in the document, in order, with its trimmed Key. */
export function extraFields( blockEditor: BlockEditorSelectors ): ExtraFieldKey[] {
	return blockEditor
		.getClientIdsWithDescendants()
		.filter( ( clientId ) => blockEditor.getBlockName( clientId ) === EXTRA_FIELD_BLOCK )
		.map( ( clientId ) => ( {
			clientId,
			key: String( blockEditor.getBlockAttributes( clientId )?.fieldKey ?? '' ).trim(),
		} ) );
}

/** Trimmed Key of every extra-field block in the document, in order. */
export function extraFieldKeys( blockEditor: BlockEditorSelectors ): string[] {
	return extraFields( blockEditor ).map( ( field ) => field.key );
}

export function duplicateKeys( keys: string[] ): string[] {
	return [ ...new Set( keys.filter( ( key, i ) => key !== '' && keys.indexOf( key ) !== i ) ) ];
}
