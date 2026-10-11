/** The block-editor selectors the section readers use. */
export interface BlockEditorSelectors {
	getBlocksByName: ( name: string ) => string[];
	getClientIdsOfDescendants: ( clientIds: string[] ) => string[];
	getBlockName: ( clientId: string ) => string | null;
	getBlockAttributes: ( clientId: string ) => Record< string, unknown > | null;
}

export interface SectionField {
	key: string;
	attributes: Record< string, unknown >;
}

/** The Field blocks inside each section named sectionBlockName, in document order, that have a Key. */
export function sectionFields( editor: BlockEditorSelectors, sectionBlockName: string ): SectionField[] {
	return editor.getBlocksByName( sectionBlockName ).flatMap( ( sectionId ) =>
		editor
			.getClientIdsOfDescendants( [ sectionId ] )
			.filter( ( clientId ) => editor.getBlockName( clientId ) === 'yaycheckout/field' )
			.map( ( clientId ) => {
				const attributes = editor.getBlockAttributes( clientId ) ?? {};

				return { key: String( attributes.fieldKey ?? '' ).trim(), attributes };
			} )
			.filter( ( field ) => field.key !== '' )
	);
}
