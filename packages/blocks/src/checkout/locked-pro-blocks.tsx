import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { getBlockType, registerBlockType } from '@wordpress/blocks';
import { __, sprintf } from '@wordpress/i18n';
import { blockIcons } from '../shared/block-icons';
import { editorData } from '../shared/editor-data';
import { PRO_BLOCK_METAS } from '../shared/pro-blocks';
import { ProUpgradeNotice } from '../shared/pro-upgrade-notice';
import { saveNestedBlocks } from '../shared/register-dynamic-block';
import './locked-pro-blocks.css';

type BlockMetadata = Parameters< typeof registerBlockType >[ 0 ];

/** The inserter group for the stubs, after YayCheckout's own. Mirrors BlockRegistry::PRO_CATEGORY, which registers it. */
const PRO_CATEGORY = 'yaycheckout-pro';

/**
 * Each locked Pro block (editorData.lockedBlocks; in Free, all of them) as a stub, listed in the inserter under
 * YayCheckout Pro with its own title. It holds no Pro behaviour, only the block's name, labels and attributes,
 * so a layout made in Pro still parses. Inserting one shows an upgrade card; LayoutMarkupReader::publish_error()
 * refuses a live layout that has one. Every stub keeps its inner blocks, which the merchant can move out before
 * removing the stub, and takes no new ones; a leaf has none, so it still saves as a self-closing comment.
 */
function LockedProBlockEdit( { name }: { name: string } ) {
	const blockProps = useBlockProps( { className: 'yaycheckout-locked-pro-block' } );
	// Nothing new goes in; the inner blocks already there stay movable and removable.
	const { children, ...innerBlocksProps } = useInnerBlocksProps( blockProps, { allowedBlocks: [] } );

	return (
		<div { ...innerBlocksProps }>
			<ProUpgradeNotice
				className="yaycheckout-locked-pro-block__notice"
				text={ sprintf(
					/* translators: %s: block title. */
					__( '%s is part of YayCheckout Pro.', 'yaycheckout' ),
					// The registered title, which registerBlockType() translated.
					getBlockType( name )?.title ?? name
				) }
			/>
			{ children }
		</div>
	);
}

for ( const meta of PRO_BLOCK_METAS ) {
	if ( editorData.lockedBlocks.includes( meta.name ) ) {
		// No placement rules: a stub is an upgrade card, and Step's `parent` would still let a Step into the Steps stub.
		registerBlockType( { ...meta, parent: undefined, ancestor: undefined, allowedBlocks: undefined } as unknown as BlockMetadata, {
			category: PRO_CATEGORY,
			description: __( 'Part of YayCheckout Pro.', 'yaycheckout' ),
			icon: blockIcons[ meta.name ],
			example: undefined,
			edit: LockedProBlockEdit,
			save: saveNestedBlocks,
		} );
	}
}
