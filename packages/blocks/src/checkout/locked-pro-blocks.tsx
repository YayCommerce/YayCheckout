import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
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
 * Free only: each Pro block as a locked stub, listed in the inserter under YayCheckout Pro with its own title. It holds no Pro
 * behaviour, only the block's name, labels and attributes, so a layout made in Pro still parses. Inserting
 * one shows an upgrade card; LayoutMarkupReader::publish_error() refuses to publish a layout that has one.
 * Every stub keeps its inner blocks, locked: a downgraded container keeps its fields, and a leaf has none,
 * so it still saves as a self-closing comment.
 */
function registerLockedProBlocks(): void {
	for ( const meta of PRO_BLOCK_METAS ) {
		registerBlockType( { ...meta, editorScript: undefined, viewScript: undefined, style: undefined, render: undefined } as unknown as BlockMetadata, {
			category: PRO_CATEGORY,
			description: __( 'Part of YayCheckout Pro.', 'yaycheckout' ),
			icon: blockIcons[ meta.name ],
			example: undefined,
			edit: function LockedProBlockEdit() {
				const blockProps = useBlockProps( { className: 'yaycheckout-locked-pro-block' } );

				return (
					<div { ...blockProps }>
						<ProUpgradeNotice
							className="yaycheckout-locked-pro-block__notice"
							text={ sprintf(
								/* translators: %s: block title. */
								__( '%s is part of YayCheckout Pro.', 'yaycheckout' ),
								meta.title
							) }
						/>
						<InnerBlocks templateLock="all" renderAppender={ false } />
					</div>
				);
			},
			save: saveNestedBlocks,
		} );
	}
}

if ( ! editorData.isPro ) {
	registerLockedProBlocks();
}
