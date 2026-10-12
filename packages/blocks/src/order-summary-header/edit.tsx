import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { isCheckoutSection, useBlocksAllowedExcept } from '../shared/allowed-blocks';

export default function Edit() {
	const allowedBlocks = useBlocksAllowedExcept( isCheckoutSection );
	const innerBlocksProps = useInnerBlocksProps( useBlockProps( { className: 'yaycheckout-order-summary-header' } ), {
		allowedBlocks,
		templateLock: false,
	} );

	return <div { ...innerBlocksProps } />;
}
