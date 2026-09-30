import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

const STEP = 'yaycheckout/accordion-step';

/**
 * Canvas for checkout steps. Every step stays open here so its sections
 * can be edited; locking and Continue run on the storefront.
 */
export default function Edit() {
	const blockProps = useBlockProps( { className: 'yaycheckout-accordion' } );

	return (
		<div { ...blockProps }>
			<InnerBlocks allowedBlocks={ [ STEP ] } templateLock={ false } />
		</div>
	);
}
