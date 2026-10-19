import { store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { createBlock, hasBlockSupport } from '@wordpress/blocks';
import { useDispatch } from '@wordpress/data';
import { PluginBlockCard } from './card';

type Spacing = Record< string, unknown >;

interface PluginBlockAttributes {
	target: string;
	style?: { spacing?: Spacing };
}

interface EditProps {
	attributes: PluginBlockAttributes;
	clientId: string;
}

/**
 * A spot in the layout for another Yay plugin's block (PluginBlockCatalogue): the card, and Add block once its
 * plugin has registered it. The storefront prints the entry's shortcode, when the plugin has one.
 */
export default function Edit( { attributes, clientId }: EditProps ) {
	const { replaceBlock } = useDispatch( blockEditorStore );
	const blockProps = useBlockProps( { className: 'yaycheckout-plugin-block' } );

	// Keeps the placeholder's margins when the real block can take them.
	const addRealBlock = () => {
		const spacing = attributes.style?.spacing;
		const keep = spacing && hasBlockSupport( attributes.target, 'spacing' ) ? { style: { spacing } } : {};
		replaceBlock( clientId, createBlock( attributes.target, keep ) );
	};

	return (
		<div { ...blockProps }>
			<PluginBlockCard target={ attributes.target } addBlock={ addRealBlock } />
		</div>
	);
}
