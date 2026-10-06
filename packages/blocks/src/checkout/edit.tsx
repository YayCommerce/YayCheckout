import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

export default function Edit() {
	const blockProps = useBlockProps( { className: 'yaycheckout-checkout' } );
	const innerBlocksProps = useInnerBlocksProps( blockProps, { templateLock: false } );

	return <div { ...innerBlocksProps } />;
}
