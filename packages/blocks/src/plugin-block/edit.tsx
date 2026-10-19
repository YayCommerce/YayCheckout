import { store as blockEditorStore, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { createBlock, hasBlockSupport } from '@wordpress/blocks';
import { Button, PanelBody } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { PluginBlockCard } from './card';
import { FbtPreview } from './fbt-preview';
import { pluginBlocksData } from './plugin-blocks-data';
import { pluginBlocksStore } from './store';

type Spacing = Record< string, unknown >;

interface PluginBlockAttributes {
	target: string;
	style?: { spacing?: Spacing };
}

interface EditProps {
	attributes: PluginBlockAttributes;
	clientId: string;
}

/** Stand-ins for what a spot's shortcode prints, by target: the shortcode's markup with sample data. */
const PREVIEWS: Record< string, () => JSX.Element > = {
	'yayboost/frequently-bought-together': FbtPreview,
};

/**
 * A spot in the layout for another Yay plugin's block (PluginBlockCatalogue): the card, and Add block once its
 * plugin has registered it. The storefront prints the entry's shortcode, when the plugin has one; while it does,
 * a target with a stand-in shows that in place of the card, and the sidebar links to the plugin's settings, as
 * the plugin's own blocks do.
 */
export default function Edit( { attributes, clientId }: EditProps ) {
	const { replaceBlock } = useDispatch( blockEditorStore );
	const state = useSelect( ( select ) => select( pluginBlocksStore ).getPluginState( attributes.target )?.state, [ attributes.target ] );
	const Preview = state === 'shortcode' ? PREVIEWS[ attributes.target ] : undefined;
	const settingsUrl = pluginBlocksData.entries.find( ( entry ) => entry.target === attributes.target )?.settingsUrl ?? '';
	const blockProps = useBlockProps( { className: 'yaycheckout-plugin-block' + ( Preview ? ' has-preview' : '' ) } );

	// Keeps the placeholder's margins when the real block can take them.
	const addRealBlock = () => {
		const spacing = attributes.style?.spacing;
		const keep = spacing && hasBlockSupport( attributes.target, 'spacing' ) ? { style: { spacing } } : {};
		replaceBlock( clientId, createBlock( attributes.target, keep ) );
	};

	if ( ! Preview ) {
		return (
			<div { ...blockProps }>
				<PluginBlockCard target={ attributes.target } addBlock={ addRealBlock } />
			</div>
		);
	}

	return (
		<>
			{ settingsUrl !== '' && (
				<InspectorControls>
					<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
						{ /* A new tab: the layout's unsaved changes stay in this one. */ }
						<Button variant="secondary" href={ settingsUrl } target="_blank" rel="noreferrer">
							{ __( 'Go to settings page', 'yaycheckout' ) }
						</Button>
					</PanelBody>
				</InspectorControls>
			) }
			<div { ...blockProps }>
				<Preview />
			</div>
		</>
	);
}
