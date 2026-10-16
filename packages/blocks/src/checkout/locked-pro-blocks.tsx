import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { ExternalLink } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { blockIcons } from '../shared/block-icons';
import { editorData } from '../shared/editor-data';
import { PRO_BLOCK_METAS } from '../shared/pro-blocks';
import { saveNestedBlocks } from '../shared/register-dynamic-block';
import './locked-pro-blocks.css';

type BlockMetadata = Parameters< typeof registerBlockType >[ 0 ];

/** Pro blocks that hold other blocks: their stubs keep a downgraded layout's inner blocks. */
const CONTAINERS = [ 'yaycheckout/steps', 'yaycheckout/step' ];

/**
 * Free only: each Pro block as a locked stub, listed in the inserter with a Pro tag. It holds no Pro
 * behaviour, only the block's name, labels and attributes, so a layout made in Pro still parses. Inserting
 * one shows an upgrade card; LayoutMarkupReader::publish_error() refuses to publish a layout that has one.
 */
function registerLockedProBlocks(): void {
	for ( const meta of PRO_BLOCK_METAS ) {
		const isContainer = CONTAINERS.includes( meta.name );

		registerBlockType( { ...meta, editorScript: undefined, viewScript: undefined, style: undefined, render: undefined } as unknown as BlockMetadata, {
			/* translators: %s: block title. */
			title: sprintf( __( '%s (Pro)', 'yaycheckout' ), meta.title ),
			description: __( 'Multi-step checkout is part of YayCheckout Pro.', 'yaycheckout' ),
			icon: blockIcons[ meta.name ],
			example: undefined,
			edit: function LockedProBlockEdit() {
				const blockProps = useBlockProps( { className: 'yaycheckout-locked-pro-block' } );

				return (
					<div { ...blockProps }>
						<p className="yaycheckout-locked-pro-block__notice">
							<span className="yaycheckout-locked-pro-block__tag">{ __( 'Pro', 'yaycheckout' ) }</span>
							{ sprintf(
								/* translators: %s: block title. */
								__( '%s is part of YayCheckout Pro. Upgrade to use multi-step checkout.', 'yaycheckout' ),
								meta.title
							) }{ ' ' }
							<ExternalLink href={ editorData.upgradeUrl }>{ __( 'Upgrade', 'yaycheckout' ) }</ExternalLink>
						</p>
						{ isContainer && <InnerBlocks templateLock="all" renderAppender={ false } /> }
					</div>
				);
			},
			save: isContainer ? saveNestedBlocks : () => null,
		} );
	}
}

if ( ! editorData.isPro ) {
	registerLockedProBlocks();
}
