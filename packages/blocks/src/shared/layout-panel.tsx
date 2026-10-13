import { createHigherOrderComponent } from '@wordpress/compose';
import type { ComponentType } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { useIsLayoutEditor } from './layout-editor';

export type LayoutPanelProps = {
	clientId: string;
	attributes: Record< string, unknown >;
	setAttributes: ( attributes: Record< string, unknown > ) => void;
};

type BlockSettings = { name?: string; attributes?: Record< string, unknown > };

/**
 * Adds a YayCheckout setting to core blocks: registers `attribute` on each of `names` in every
 * editor, so markup saved in a layout stays valid anywhere, and shows `Panel` in the sidebar only
 * while one of them is selected in the layout editor, so other blocks and editors pay nothing.
 */
export function addLayoutPanel(
	namespace: string,
	names: string[],
	attribute: string,
	schema: Record< string, unknown >,
	Panel: ComponentType< LayoutPanelProps >
): void {
	addFilter( 'blocks.registerBlockType', `${ namespace }/attribute`, ( settings: BlockSettings, name: string ) =>
		names.includes( name ) ? { ...settings, attributes: { ...settings.attributes, [ attribute ]: schema } } : settings
	);

	function LayoutOnlyPanel( props: LayoutPanelProps ) {
		return useIsLayoutEditor() ? <Panel { ...props } /> : null;
	}

	const withPanel = createHigherOrderComponent(
		( BlockEdit ) =>
			function LayoutPanelBlockEdit( props: LayoutPanelProps & { name: string; isSelected: boolean } ) {
				return (
					<>
						<BlockEdit { ...props } />
						{ names.includes( props.name ) && props.isSelected && <LayoutOnlyPanel { ...props } /> }
					</>
				);
			},
		'withLayoutPanel'
	);

	addFilter( 'editor.BlockEdit', `${ namespace }/panel`, withPanel );
}
