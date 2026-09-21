import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

/**
 * Root of a checkout layout. Holds sections as inner blocks.
 *
 * templateLock is always false so merchants can insert Contact, Billing, etc.
 * Saved "insert" from older layouts is ignored — that lock hid every child
 * from the inserter.
 */
export default function Edit() {
	const blockProps = useBlockProps( { className: 'yaycheckout-checkout' } );

	return (
		<div { ...blockProps }>
			<InnerBlocks templateLock={ false } />
		</div>
	);
}
