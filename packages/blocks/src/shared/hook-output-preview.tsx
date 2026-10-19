import { store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import pluginBlockMeta from '../plugin-block/block.json';
import wcHookMeta from '../wc-hook/block.json';
import { type BlockEditorSelectors } from './section-fields';
import './hook-output-preview.css';

interface HookOutputPreviewProps {
	/** The WooCommerce hook the calling block fires on the storefront. */
	hook: string;
	/** `wc-hook` for a WooCommerce Hook block, which fires the hook itself; `block` for a block a Hook block can take it from. */
	owner: 'block' | 'wc-hook';
}

/**
 * A stand-in for what another Yay plugin prints on `hook` while the layout has no spot for it: YayBoost's order
 * bump lands on a WooCommerce hook the calling block fires (PluginBlockCatalogue's `hooks`). Gone once the layout
 * holds the plugin's block or its placeholder, and from the original block while a Hook block takes the hook.
 */
export function HookOutputPreview( { hook, owner }: HookOutputPreviewProps ) {
	// Read at render: another block's script prints the catalogue, maybe after this bundle loaded.
	const candidates = ( window.yayCheckoutPluginBlocks?.entries ?? [] ).filter( ( entry ) => entry.hooks.includes( hook ) );
	const candidateTargets = candidates.map( ( entry ) => entry.target ).join( ' ' );

	// The targets to stand in for, joined: a string keeps useSelect's result stable between calls.
	const shownTargets = useSelect(
		( select ) => {
			if ( candidateTargets === '' ) {
				return '';
			}

			const editor = select( blockEditorStore ) as unknown as BlockEditorSelectors;
			const takenByHookBlock =
				owner === 'block' &&
				editor.getBlocksByName( wcHookMeta.name ).some( ( id ) => editor.getBlockAttributes( id )?.hook === hook );

			if ( takenByHookBlock ) {
				return '';
			}

			// Mirrors PluginBlockCatalogue::spot_for(): a placeholder for the target, or the target block itself. The
			// editor holds an unregistered block as core/missing, so a named block is a registered one.
			const placeholders = editor.getBlocksByName( pluginBlockMeta.name ).map( ( id ) => editor.getBlockAttributes( id )?.target );

			return candidateTargets
				.split( ' ' )
				.filter( ( target ) => ! placeholders.includes( target ) && editor.getBlocksByName( target ).length === 0 )
				.join( ' ' );
		},
		[ hook, owner, candidateTargets ]
	);
	const shown = candidates.filter( ( entry ) => shownTargets.split( ' ' ).includes( entry.target ) );

	return (
		<>
			{ shown.map( ( entry ) => (
				<div key={ entry.target } className="yaycheckout-hook-output-preview" role="note" contentEditable={ false }>
					<input type="checkbox" className="yaycheckout-hook-output-preview__checkbox" disabled aria-hidden="true" tabIndex={ -1 } />
					<span className="yaycheckout-hook-output-preview__body">
						<span className="yaycheckout-hook-output-preview__title">
							{ entry.title }
							<span className="yaycheckout-hook-output-preview__pill">{ entry.pluginName }</span>
						</span>
						<span className="yaycheckout-hook-output-preview__note">
							{ sprintf(
								/* translators: 1: block title, e.g. Order bump, 2: plugin name, e.g. YayBoost */
								__( '%1$s from %2$s shows here. To choose its spot, add the %1$s block.', 'yaycheckout' ),
								entry.title,
								entry.pluginName
							) }
						</span>
					</span>
				</div>
			) ) }
		</>
	);
}
