import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

interface CheckoutAttributes {
	templateLock: string | boolean;
}

interface EditProps {
	attributes: CheckoutAttributes;
}

/** templateLock is an attribute so the post type's template can set it without a rebuild. */
export default function Edit( { attributes }: EditProps ) {
	const blockProps = useBlockProps( { className: 'yaycheckout-checkout' } );

	return (
		<div { ...blockProps }>
			<InnerBlocks templateLock={ attributes.templateLock } />
		</div>
	);
}
