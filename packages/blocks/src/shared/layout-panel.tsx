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
 * Adds a YayCheckout setting to blocks: registers `attribute` on each block `names` takes (a list, or a
 * test on the block name) in every editor, so markup saved in a layout stays valid anywhere, and shows
 * `Panel` in the sidebar only while one of them is selected in the layout editor, so other blocks and
 * editors pay nothing.
 */
export function addLayoutPanel(
	namespace: string,
	names: string[] | ( ( name: string ) => boolean ),
	attribute: string,
	schema: Record< string, unknown >,
	Panel: ComponentType< LayoutPanelProps >
): void {
	const takes = typeof names === 'function' ? names : ( name: string ) => names.includes( name );

	addFilter( 'blocks.registerBlockType', `${ namespace }/attribute`, ( settings: BlockSettings, name: string ) =>
		takes( name ) ? { ...settings, attributes: { ...settings.attributes, [ attribute ]: schema } } : settings
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
						{ takes( props.name ) && props.isSelected && <LayoutOnlyPanel { ...props } /> }
					</>
				);
			},
		'withLayoutPanel'
	);

	addFilter( 'editor.BlockEdit', `${ namespace }/panel`, withPanel );
}
