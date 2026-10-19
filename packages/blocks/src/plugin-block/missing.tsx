import { store as blockEditorStore, useBlockProps } from '@wordpress/block-editor';
import { parse as parseGrammar } from '@wordpress/block-serialization-default-parser';
import { createBlock, parse } from '@wordpress/blocks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useDispatch } from '@wordpress/data';
import { PluginBlockCard } from './card';
import { pluginBlocksData } from './plugin-blocks-data';

interface MissingProps {
	name: string;
	clientId: string;
	attributes: { originalName?: string; originalContent?: string };
}

const targets = new Set( pluginBlocksData.entries.map( ( entry ) => entry.target ) );

/** core/missing standing for a catalogue block: the card, so the merchant can get the plugin back. */
function MissingPluginBlock( { clientId, attributes }: MissingProps ) {
	const { replaceBlock } = useDispatch( blockEditorStore );
	const blockProps = useBlockProps( { className: 'yaycheckout-plugin-block' } );
	const target = attributes.originalName ?? '';

	// The block as saved, once its type is registered again; parse() yields core/missing until then.
	const addBlock = () => {
		const block = parse( attributes.originalContent ?? '' )[ 0 ];
		replaceBlock( clientId, block?.name === target ? block : createBlock( target ) );
	};

	// Its type stays unregistered here, so the margin comes from the block comment as saved.
	const useFree = () => {
		const attrs = parseGrammar( attributes.originalContent ?? '' )[ 0 ]?.attrs as { style?: { spacing?: { margin?: unknown } } } | null;
		const margin = attrs?.style?.spacing?.margin;
		replaceBlock( clientId, createBlock( 'yaycheckout/plugin-block', { target, ...( margin ? { style: { spacing: { margin } } } : {} ) } ) );
	};

	return (
		<div { ...blockProps }>
			<PluginBlockCard target={ target } addBlock={ addBlock } useFree={ useFree } />
		</div>
	);
}

/**
 * Shows the catalogue card in place of core's "doesn't include support" notice when a promoted block's plugin is
 * gone (deactivated, or Pro swapped for Free). Only renders: the markup changes when the merchant picks an action.
 */
export const withPluginBlockCard = createHigherOrderComponent(
	( BlockEdit ) => ( props: MissingProps ) =>
		props.name === 'core/missing' && targets.has( props.attributes.originalName ?? '' ) ? <MissingPluginBlock { ...props } /> : <BlockEdit { ...props } />,
	'withPluginBlockCard'
);
