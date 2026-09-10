import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

interface CheckoutAttributes {
	templateLock: string | boolean;
}

interface EditProps {
	attributes: CheckoutAttributes;
}

/**
 * Root of a checkout layout. Holds the required sections as inner blocks.
 *
 * templateLock comes from the attribute rather than being hardcoded so the
 * post type's template can decide how much merchants may rearrange without
 * this block being rebuilt.
 */
export default function Edit( { attributes }: EditProps ) {
	const blockProps = useBlockProps( { className: 'yaycheckout-checkout' } );

	return (
		<div { ...blockProps }>
			<InnerBlocks templateLock={ attributes.templateLock } />
		</div>
	);
}
