import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

/**
 * templateLock is always false, whatever the saved attribute says: an "insert"
 * lock hides every child block from the inserter.
 */
export default function Edit() {
	const blockProps = useBlockProps( { className: 'yaycheckout-checkout' } );

	return (
		<div { ...blockProps }>
			<InnerBlocks templateLock={ false } />
		</div>
	);
}
